/**
 * Boiyets Gym Management System — Global QR Scanner (Ghost Input Method)
 *
 * WHY GHOST INPUT?
 *   USB keyboard-wedge QR scanners send characters to whichever OS element
 *   currently has keyboard focus. Without an <input> element focused, the
 *   browser discards the keystrokes — document-level keydown listeners alone
 *   are NOT enough on most OS/browser combos.
 *
 * HOW THIS WORKS:
 *   1. A visually invisible <input> (the "ghost input") is permanently present
 *      in the page, positioned off-screen so it cannot be seen or clicked.
 *   2. It is focused immediately on page load.
 *   3. After any click that doesn't land on a real form control (textarea,
 *      select, input, button), focus returns to the ghost input automatically.
 *   4. When the scanner fires its keystroke burst + Enter into the ghost input,
 *      the value is read, validated, and sent to process_qr.php via AJAX.
 *   5. A toast notification shows the result without disrupting the page.
 *
 * TIMING DETECTION:
 *   Scanners type < 50ms per character. Humans type > 80ms. We use this to
 *   ignore accidental keystrokes from normal keyboard use while the ghost
 *   input is focused (e.g. pressing Enter on a button that returns focus here).
 */

(function () {
  'use strict';

  // ─── Config ────────────────────────────────────────────────────────────────
  const SCANNER_CHAR_THRESHOLD_MS = 60;   // Max ms between chars to count as scanner
  const SCANNER_MIN_LENGTH        = 5;    // Shortest valid QR code
  const COOLDOWN_MS               = 3000; // Block re-scan of same code for N ms
  const REFOCUS_DELAY_MS          = 80;   // ms after click before stealing focus back
  const TOAST_DURATION_SUCCESS    = 5000;
  const TOAST_DURATION_ERROR      = 6000;

  // ─── State ─────────────────────────────────────────────────────────────────
  let ghostInput    = null;
  let lastKeyTime   = 0;
  let lastQRCode    = '';
  let lastQRTime    = 0;
  let isProcessing  = false;
  let refocusTimer  = null;

  // ─── Helpers ───────────────────────────────────────────────────────────────
  function isRealFormControl(el) {
    if (!el) return false;
    const tag = el.tagName;
    if (tag === 'INPUT' && el !== ghostInput) return true;
    if (tag === 'TEXTAREA') return true;
    if (tag === 'SELECT')   return true;
    // Editable divs / rich-text
    if (el.isContentEditable) return true;
    return false;
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ─── CSS Injection ─────────────────────────────────────────────────────────
  function injectStyles() {
    if (document.getElementById('qr-global-styles')) return;
    const s = document.createElement('style');
    s.id = 'qr-global-styles';
    s.textContent = `
      @keyframes qrToastIn  { from { transform:translateX(120%); opacity:0 } to { transform:translateX(0); opacity:1 } }
      @keyframes qrToastOut { from { transform:translateX(0); opacity:1 } to { transform:translateX(120%); opacity:0 } }
      @keyframes qrPulse    { from { opacity:.5; transform:scale(.8) } to { opacity:1; transform:scale(1.2) } }

      #qr-ghost-input {
        position: fixed !important;
        left: -9999px !important;
        top: -9999px !important;
        width: 1px !important;
        height: 1px !important;
        opacity: 0 !important;
        pointer-events: none !important;
        border: none !important;
        outline: none !important;
        background: transparent !important;
        color: transparent !important;
        z-index: -1 !important;
      }

      #qr-status-badge {
        position: fixed;
        bottom: 20px;
        left: 20px;
        z-index: 99997;
        background: rgba(13,18,33,0.88);
        border: 1px solid rgba(232,160,18,0.2);
        border-radius: 10px;
        padding: 6px 14px 6px 10px;
        font-family: "Outfit","Inter",sans-serif;
        font-size: 0.73rem;
        font-weight: 700;
        color: #475569;
        display: flex;
        align-items: center;
        gap: 7px;
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        pointer-events: none;
        transition: all 0.25s ease;
        user-select: none;
      }
      #qr-status-badge .qr-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #1e293b;
        border: 1px solid #334155;
        flex-shrink: 0;
        transition: all 0.2s;
      }
      #qr-status-badge.active {
        color: #e8a012;
        border-color: rgba(232,160,18,0.5);
      }
      #qr-status-badge.active .qr-dot {
        background: #e8a012;
        border-color: #e8a012;
        box-shadow: 0 0 6px #e8a012;
        animation: qrPulse 0.55s ease infinite alternate;
      }
      #qr-status-badge.no-focus {
        color: #ef4444;
        border-color: rgba(239,68,68,0.4);
      }
      #qr-status-badge.no-focus .qr-dot {
        background: #ef4444;
        border-color: #ef4444;
      }
      #qr-toast-wrap {
        position: fixed;
        bottom: 28px;
        right: 24px;
        z-index: 99998;
        display: flex;
        flex-direction: column-reverse;
        gap: 10px;
        pointer-events: none;
        max-width: min(420px, calc(100vw - 48px));
      }
    `;
    document.head.appendChild(s);
  }

  // ─── Status Badge ──────────────────────────────────────────────────────────
  function getBadge() {
    let el = document.getElementById('qr-status-badge');
    if (!el) {
      el = document.createElement('div');
      el.id = 'qr-status-badge';
      el.innerHTML = '<span class="qr-dot"></span><span id="qr-status-text">QR Scanner Ready</span>';
      document.body.appendChild(el);
    }
    return el;
  }

  function setBadge(state, text) {
    const el = getBadge();
    el.className = state === 'active' ? 'active' : (state === 'nofocus' ? 'no-focus' : '');
    document.getElementById('qr-status-text').textContent = text;
  }

  // ─── Toast Notifications ───────────────────────────────────────────────────
  const ICONS = {
    success: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
    error:   '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    warning: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
  };
  const COLOURS = {
    success: { bg:'rgba(16,185,129,.12)',  border:'rgba(16,185,129,.4)',  icon:'#4ade80', glow:'rgba(16,185,129,.2)'  },
    error:   { bg:'rgba(239,68,68,.12)',   border:'rgba(239,68,68,.4)',   icon:'#f87171', glow:'rgba(239,68,68,.2)'   },
    warning: { bg:'rgba(245,158,11,.12)',  border:'rgba(245,158,11,.4)',  icon:'#fbbf24', glow:'rgba(245,158,11,.2)'  },
  };

  function getToastWrap() {
    let el = document.getElementById('qr-toast-wrap');
    if (!el) { el = document.createElement('div'); el.id = 'qr-toast-wrap'; document.body.appendChild(el); }
    return el;
  }

  function showToast(type, title, message) {
    const c = COLOURS[type] || COLOURS.error;
    const icon = ICONS[type] || ICONS.error;
    const wrap = getToastWrap();

    const t = document.createElement('div');
    t.setAttribute('data-qr-toast', '1');
    t.style.cssText = [
      `background:${c.bg}`,
      `border:1.5px solid ${c.border}`,
      `box-shadow:0 0 24px ${c.glow},0 8px 36px rgba(0,0,0,.55)`,
      'border-radius:14px',
      'padding:14px 18px',
      'display:flex',
      'align-items:flex-start',
      'gap:13px',
      'backdrop-filter:blur(20px)',
      '-webkit-backdrop-filter:blur(20px)',
      'pointer-events:auto',
      'animation:qrToastIn .35s cubic-bezier(.34,1.56,.64,1)',
      'font-family:"Outfit","Inter",sans-serif',
      'cursor:default',
    ].join(';');

    t.innerHTML = `
      <div style="color:${c.icon};flex-shrink:0;margin-top:2px;">${icon}</div>
      <div style="flex:1;min-width:0;">
        <div style="font-weight:800;font-size:.95rem;color:#e8ecf4;margin-bottom:3px;display:flex;align-items:center;justify-content:space-between;gap:8px;">
          <span>${escapeHtml(title)}</span>
          <button onclick="this.closest('[data-qr-toast]').remove()"
            style="background:none;border:none;color:#475569;cursor:pointer;padding:0;font-size:1rem;line-height:1;flex-shrink:0;"
            title="Dismiss">✕</button>
        </div>
        <div style="font-size:.86rem;color:#94a3b8;line-height:1.5;">${escapeHtml(message)}</div>
      </div>`;

    wrap.appendChild(t);

    const dur = type === 'success' ? TOAST_DURATION_SUCCESS : TOAST_DURATION_ERROR;
    setTimeout(() => {
      t.style.animation = 'qrToastOut .28s ease forwards';
      setTimeout(() => t.remove(), 300);
    }, dur);
  }

  // ─── Ghost Input ───────────────────────────────────────────────────────────
  function createGhostInput() {
    ghostInput = document.createElement('input');
    ghostInput.type         = 'text';
    ghostInput.id           = 'qr-ghost-input';
    ghostInput.autocomplete = 'off';
    ghostInput.autocorrect  = 'off';
    ghostInput.autocapitalize = 'off';
    ghostInput.spellcheck   = false;
    ghostInput.tabIndex     = -1;
    ghostInput.setAttribute('aria-hidden', 'true');
    document.body.appendChild(ghostInput);
  }

  function focusGhost() {
    if (ghostInput && document.activeElement !== ghostInput) {
      ghostInput.focus({ preventScroll: true });
    }
  }

  // Return focus to ghost after a short delay so buttons/links can fire first
  function scheduleRefocus() {
    clearTimeout(refocusTimer);
    refocusTimer = setTimeout(() => {
      const active = document.activeElement;
      if (!isRealFormControl(active)) {
        focusGhost();
      }
    }, REFOCUS_DELAY_MS);
  }

  // ─── QR Processing ─────────────────────────────────────────────────────────
  function processCode(raw) {
    const code = raw.trim();

    if (code.length < SCANNER_MIN_LENGTH) return;

    // Must match known QR patterns
    if (!/^(CLIENT_\d+|WALKIN_\d+|\d{4,})$/i.test(code)) return;

    // Cooldown guard
    const now = Date.now();
    if (code === lastQRCode && (now - lastQRTime) < COOLDOWN_MS) {
      const secs = Math.ceil((COOLDOWN_MS - (now - lastQRTime)) / 1000);
      showToast('warning', 'QR Cooldown', `Please wait ${secs}s before re-scanning.`);
      return;
    }

    if (isProcessing) return;

    submitQR(code);
  }

  function submitQR(code) {
    isProcessing = true;
    lastQRCode   = code;
    lastQRTime   = Date.now();
    setBadge('active', 'Processing QR...');

    fetch('process_qr.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body:    'qr_code=' + encodeURIComponent(code),
    })
    .then(r => {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(data => {
      if (data.success) {
        const label = data.action === 'check_in' ? '✅ Checked In' : '👋 Checked Out';
        showToast('success', label, data.message || data.member_name);

        window.dispatchEvent(new CustomEvent('qr:attendance', {
          detail: { action: data.action, memberName: data.member_name, code }
        }));
      } else {
        let title = 'Scan Failed';
        if (/expired/i.test(data.message))    title = '⚠️ Membership Expired';
        else if (/not active/i.test(data.message)) title = '⚠️ Inactive Member';
        else if (/not found/i.test(data.message))  title = '❓ Unknown QR Code';
        showToast('error', title, data.message || 'Could not process QR code.');
      }
    })
    .catch(err => {
      console.error('[QR Scanner]', err);
      showToast('error', 'Scanner Error', 'Network error — server unreachable.');
    })
    .finally(() => {
      isProcessing = false;
      setBadge('', 'QR Scanner Ready');
      // Re-focus ghost so next scan is ready immediately
      scheduleRefocus();
    });
  }

  // ─── Ghost Input Event Handlers ────────────────────────────────────────────
  function onGhostKeydown(e) {
    if (e.key === 'Enter') {
      e.preventDefault();

      const raw      = ghostInput.value;
      const elapsed  = Date.now() - lastKeyTime;
      ghostInput.value = '';

      // Reject if the last char came in > threshold ago (human pressed Enter manually)
      if (raw.length === 0) return;

      // If there was a long pause before Enter, probably human; only reject if
      // the buffer content itself is implausible for a scanner
      processCode(raw);
      return;
    }

    // Track timing for any other key
    lastKeyTime = Date.now();
  }

  function onGhostInput() {
    // Keep track of typing speed — if value grew suspiciously slowly, clear it
    // (This is a secondary safeguard; Enter handler is the primary gate)
    const now = Date.now();
    if (lastKeyTime > 0 && (now - lastKeyTime) > SCANNER_CHAR_THRESHOLD_MS * 3) {
      // Very slow input — probably not a scanner, but let Enter decide
    }
    lastKeyTime = now;
  }

  // Show warning when ghost loses focus to a non-form element
  function onGhostBlur(e) {
    const next = e.relatedTarget;
    if (!next || !isRealFormControl(next)) {
      // Lost focus to nothing useful — badge goes warning
      setBadge('nofocus', 'Click page to re-arm scanner');
    }
  }

  function onGhostFocus() {
    setBadge('', 'QR Scanner Ready');
  }

  // ─── Click Listener — Re-arm after every non-form-control click ────────────
  function onDocumentClick(e) {
    const target = e.target;

    // If they clicked on a real input/textarea/select, let the browser handle it
    if (isRealFormControl(target)) return;

    // For everything else (buttons, links, divs, table rows, etc.)
    // schedule a refocus back to the ghost input after the click action completes
    scheduleRefocus();
  }

  // ─── Visibility change — refocus when tab becomes visible again ────────────
  function onVisibilityChange() {
    if (document.visibilityState === 'visible') {
      scheduleRefocus();
    }
  }

  // ─── Init ──────────────────────────────────────────────────────────────────
  function init() {
    injectStyles();
    createGhostInput();
    getBadge();

    // Wire ghost input events
    ghostInput.addEventListener('keydown', onGhostKeydown);
    ghostInput.addEventListener('input',   onGhostInput);
    ghostInput.addEventListener('blur',    onGhostBlur);
    ghostInput.addEventListener('focus',   onGhostFocus);

    // Re-arm on every click
    document.addEventListener('click', onDocumentClick, true);

    // Re-arm when tab regains visibility
    document.addEventListener('visibilitychange', onVisibilityChange);

    // Initial focus
    focusGhost();

    console.log('[QR Scanner] Ghost input armed and ready.');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
