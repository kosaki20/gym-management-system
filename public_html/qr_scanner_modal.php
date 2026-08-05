<?php
require_once __DIR__ . '/../config/config.php';
// qr_scanner_modal.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>

<!-- QR Scanner Container - ORIGINAL DESIGN -->
<div id="qrScanner" class="qr-scanner-container">
    <div class="qr-scanner-header">
        <div class="qr-scanner-title">
            <i data-lucide="scan"></i>
            <span>QR Attendance Scanner</span>
        </div>
        <div id="qrScannerStatus" class="qr-scanner-status active">Active</div>
    </div>
    <input type="text" id="qrInput" class="qr-input" placeholder="Scan QR code or enter code manually..." autocomplete="off" autofocus>
    <div class="scanner-instructions">
        Press Enter or click Process after scanning
    </div>
    <div class="qr-scanner-buttons">
        <button id="processQR" class="qr-scanner-btn primary">
            <i data-lucide="check"></i> Process
        </button>
        <button id="toggleScanner" class="qr-scanner-btn secondary">
            <i data-lucide="power"></i> Disable
        </button>
    </div>
    <div id="qrResult" class="qr-scanner-result"></div>
</div>



<script>
// QR Scanner functionality - ORIGINAL IMPROVED VERSION
let qrScannerActive = true;
let lastProcessedQR = '';
let lastProcessedTime = 0;
let qrProcessing = false;
let qrCooldown = false;

function setupQRScanner() {
    const qrInput = document.getElementById('qrInput');
    const processQRBtn = document.getElementById('processQR');
    const toggleScannerBtn = document.getElementById('toggleScanner');
    const qrScanner = document.getElementById('qrScanner');
    const qrScannerStatus = document.getElementById('qrScannerStatus');
    
    // Process QR code when Enter is pressed
    qrInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter' && qrScannerActive && !qrProcessing && !qrCooldown) {
            processQRCode();
            e.preventDefault();
        }
    });
    
    // Process QR code when button is clicked
    processQRBtn.addEventListener('click', function() {
        if (qrScannerActive && !qrProcessing && !qrCooldown) {
            processQRCode();
        }
    });
    // Add more specific error handling
if (data.message && data.message.includes('expired')) {
    showQRResult('error', 'Membership Expired', data.message);
} else if (data.message && data.message.includes('not active')) {
    showQRResult('error', 'Membership Inactive', data.message);
} else {
    showQRResult('error', 'Error', data.message || 'Unknown error occurred');
}
    // Toggle scanner on/off
    toggleScannerBtn.addEventListener('click', function() {
        qrScannerActive = !qrScannerActive;
        
        if (qrScannerActive) {
            qrScanner.classList.remove('disabled');
            qrScannerStatus.textContent = 'Active';
            qrScannerStatus.classList.remove('disabled');
            qrScannerStatus.classList.add('active');
            toggleScannerBtn.innerHTML = '<i data-lucide="power"></i> Disable';
            qrInput.disabled = false;
            qrInput.placeholder = 'Scan QR code or enter code manually...';
            processQRBtn.disabled = false;
            qrInput.focus();
        } else {
            qrScanner.classList.add('disabled');
            qrScannerStatus.textContent = 'Disabled';
            qrScannerStatus.classList.remove('active');
            qrScannerStatus.classList.add('disabled');
            toggleScannerBtn.innerHTML = '<i data-lucide="power"></i> Enable';
            qrInput.disabled = true;
            qrInput.placeholder = 'Scanner disabled';
            processQRBtn.disabled = true;
        }
        
        lucide.createIcons();
    });
    
    // Smart focus management
    document.addEventListener('click', function(e) {
        if (qrScannerActive && 
            !e.target.closest('form') && 
            !e.target.closest('select') && 
            !e.target.closest('button') &&
            e.target !== qrInput) {
            setTimeout(() => {
                if (document.activeElement.tagName !== 'INPUT' && 
                    document.activeElement.tagName !== 'TEXTAREA' &&
                    document.activeElement.tagName !== 'SELECT') {
                    qrInput.focus();
                }
            }, 100);
        }
    });
    
    // Clear input after successful processing
    qrInput.addEventListener('input', function() {
        if (this.value === lastProcessedQR) {
            this.value = '';
        }
    });
    
    // Initial focus
    setTimeout(() => {
        if (qrScannerActive) {
            qrInput.focus();
        }
    }, 500);
}

function processQRCode() {
    if (qrProcessing || qrCooldown) return;
    
    const qrInput = document.getElementById('qrInput');
    const qrResult = document.getElementById('qrResult');
    const processQRBtn = document.getElementById('processQR');
    const qrCode = qrInput.value.trim();
    
    if (!qrCode) {
        showQRResult('error', 'Error', 'Please enter a QR code');
        return;
    }
    
    // Prevent processing the same QR code twice in quick succession
    const currentTime = Date.now();
    if (qrCode === lastProcessedQR && (currentTime - lastProcessedTime) < 3000) {
        const timeLeft = Math.ceil((3000 - (currentTime - lastProcessedTime)) / 1000);
        showQRResult('error', 'Cooldown', `Please wait ${timeLeft} seconds before scanning this QR code again`);
        qrInput.value = '';
        qrInput.focus();
        return;
    }
    
    qrProcessing = true;
    qrCooldown = true;
    processQRBtn.disabled = true;
    processQRBtn.innerHTML = '<i data-lucide="loader" class="animate-spin"></i> Processing';
    lucide.createIcons();
    
    // Show processing message
    showQRResult('info', 'Processing', `Scanning QR code...`);
    
    // Make AJAX call to process the QR code
    fetch('process_qr.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'qr_code=' + encodeURIComponent(qrCode)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showQRResult('success', 'Success', data.message);
            lastProcessedQR = qrCode;
            lastProcessedTime = Date.now();
            
            // Update attendance data
            updateAttendanceData(data.action, data.member_name);
            
            // Trigger custom event for other components
            window.dispatchEvent(new CustomEvent('qrScanSuccess', { 
            detail: { 
                message: data.message, 
                qrCode: qrCode,
                action: data.action,
                memberName: data.member_name
            } 
        }));
            
        } else {
        // IMPROVED ERROR HANDLING
        if (data.message && data.message.includes('expired')) {
            showQRResult('error', 'Membership Expired', data.message);
        } else if (data.message && data.message.includes('not active')) {
            showQRResult('error', 'Membership Inactive', data.message);
        } else {
            showQRResult('error', 'Error', data.message || 'Unknown error occurred');
        }
        lastProcessedQR = qrCode;
        lastProcessedTime = Date.now();
    }
    })
    .catch(error => {
        console.error('Error:', error);
        showQRResult('error', 'Network Error', 'Failed to process QR code. Please try again.');
        lastProcessedQR = qrCode;
        lastProcessedTime = Date.now();
    })
    .finally(() => {
        qrProcessing = false;
        processQRBtn.disabled = false;
        processQRBtn.innerHTML = '<i data-lucide="check"></i> Process';
        lucide.createIcons();
        
        // Clear input and refocus after processing
        setTimeout(() => {
            qrInput.value = '';
            if (qrScannerActive) {
                qrInput.focus();
            }
        }, 500);
        
        // Enable scanning again after 3 seconds
        setTimeout(() => {
            qrCooldown = false;
        }, 3000);
    });
}

function playAudioFeedback(type) {
    try {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) return;
        const ctx = new AudioCtx();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);

        if (type === 'success') {
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc.frequency.setValueAtTime(880, ctx.currentTime + 0.1); // A5
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.3);
        } else {
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(220, ctx.currentTime); // A3
            osc.frequency.setValueAtTime(146.83, ctx.currentTime + 0.15); // D3
            gain.gain.setValueAtTime(0.2, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.4);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.4);
        }
    } catch(e) { console.log('Audio feedback unavailable:', e); }
}

function showQRResult(type, title, message) {
    const qrResult = document.getElementById('qrResult');
    const qrScanner = document.getElementById('qrScanner');
    
    playAudioFeedback(type === 'success' ? 'success' : 'error');
    
    if (qrScanner) {
        qrScanner.style.boxShadow = type === 'success' 
            ? '0 0 25px rgba(34, 197, 94, 0.6), 0 0 50px rgba(34, 197, 94, 0.3)' 
            : '0 0 25px rgba(239, 68, 68, 0.6), 0 0 50px rgba(239, 68, 68, 0.3)';
        setTimeout(() => { qrScanner.style.boxShadow = ''; }, 1200);
    }

    qrResult.className = 'qr-scanner-result ' + type;
    qrResult.innerHTML = `
        <div class="qr-result-title">${title}</div>
        <div class="qr-result-message">${message}</div>
    `;
    qrResult.style.display = 'block';
    
    // Auto-hide result after appropriate time
    let hideTime = type === 'success' ? 4000 : 5000;
    if (title === 'Cooldown') hideTime = 3000;
    if (title === 'Processing') hideTime = 2000;
    
    setTimeout(() => {
        qrResult.style.display = 'none';
    }, hideTime);
}

function updateAttendanceData(action, memberName) {
    // Update the attendance count based on action
    const currentCount = parseInt(document.getElementById('attendanceCount')?.textContent || '0');
    
    if (action === 'check_in') {
        document.getElementById('attendanceCount').textContent = currentCount + 1;
        if (window.showToast) {
            window.showToast(`${memberName} checked in successfully!`, 'success');
        }
    } else if (action === 'check_out') {
        // You might want to decrease count or show different message
        if (window.showToast) {
            window.showToast(`${memberName} checked out successfully!`, 'success');
        }
    }
    
    // Refresh attendance data if the function exists
    if (window.refreshAttendanceData) {
        window.refreshAttendanceData();
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    setupQRScanner();
});
</script>
