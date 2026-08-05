/* Global Ctrl+K Command Palette for Boiyets Fitness Gym */
document.addEventListener('DOMContentLoaded', () => {
    // Inject Command Palette Modal HTML into body if not present
    if (!document.getElementById('cmdPaletteModal')) {
        const modalHtml = `
        <div id="cmdPaletteModal" class="cmd-palette-backdrop" style="display:none;">
            <div class="cmd-palette-container">
                <div class="cmd-palette-header">
                    <i class="fa-solid fa-magnifying-glass cmd-search-icon"></i>
                    <input type="text" id="cmdSearchInput" class="cmd-search-input" placeholder="Type a command or search members, equipment, pages... (ESC to close)" autofocus>
                    <span class="cmd-badge-esc">ESC</span>
                </div>
                <div class="cmd-palette-results" id="cmdSearchResults">
                    <div class="cmd-category-title">Quick Actions</div>
                    <a href="admin_dashboard.php" class="cmd-item active"><i class="fa-solid fa-chart-line"></i> Go to Admin Dashboard</a>
                    <a href="all_members.php" class="cmd-item"><i class="fa-solid fa-users"></i> View All Members</a>
                    <a href="attendance_logs.php" class="cmd-item"><i class="fa-solid fa-clipboard-user"></i> Log Attendance / QR Scan</a>
                    <a href="equipment_monitoring.php" class="cmd-item"><i class="fa-solid fa-toolbox"></i> Equipment Monitoring</a>
                    <a href="revenue.php" class="cmd-item"><i class="fa-solid fa-cash-register"></i> Sales & Revenue Tracking</a>
                    <a href="chat.php" class="cmd-item"><i class="fa-solid fa-comments"></i> Open Messages & Live Chat</a>
                </div>
            </div>
        </div>
        <style>
            .cmd-palette-backdrop {
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0, 0, 0, 0.75);
                backdrop-filter: blur(5px);
                z-index: 99999;
                display: flex;
                align-items: flex-start;
                justify-content: center;
                padding-top: 10vh;
            }
            .cmd-palette-container {
                width: 100%;
                max-width: 620px;
                background: #131824;
                border: 1px solid #263043;
                border-radius: 14px;
                box-shadow: 0 20px 50px rgba(0, 0, 0, 0.8);
                overflow: hidden;
            }
            .cmd-palette-header {
                display: flex;
                align-items: center;
                padding: 1rem 1.25rem;
                border-bottom: 1px solid #263043;
                background: #0f1420;
            }
            .cmd-search-icon {
                color: #f59e0b;
                font-size: 1.2rem;
                margin-right: 0.85rem;
            }
            .cmd-search-input {
                flex: 1;
                background: transparent;
                border: none;
                color: #f8fafc;
                font-size: 1.1rem;
                font-family: inherit;
                outline: none;
            }
            .cmd-badge-esc {
                background: #1e2638;
                color: #94a3b8;
                font-size: 0.75rem;
                font-weight: 700;
                padding: 0.2rem 0.5rem;
                border-radius: 4px;
                border: 1px solid #263043;
            }
            .cmd-palette-results {
                max-height: 380px;
                overflow-y: auto;
                padding: 0.75rem;
            }
            .cmd-category-title {
                font-size: 0.75rem;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                color: #64748b;
                padding: 0.5rem 0.75rem;
            }
            .cmd-item {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.85rem 1rem;
                color: #cbd5e1;
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                transition: all 0.15s ease;
            }
            .cmd-item i {
                color: #f59e0b;
                width: 20px;
                text-align: center;
            }
            .cmd-item:hover, .cmd-item.active {
                background: #1a2131;
                color: #f59e0b;
            }
        </style>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }

    const modal = document.getElementById('cmdPaletteModal');
    const input = document.getElementById('cmdSearchInput');
    const results = document.getElementById('cmdSearchResults');

    // Ctrl+K or Cmd+K listener
    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            modal.style.display = modal.style.display === 'none' ? 'flex' : 'none';
            if (modal.style.display === 'flex') {
                input.focus();
                input.value = '';
            }
        }
        if (e.key === 'Escape' && modal.style.display !== 'none') {
            modal.style.display = 'none';
        }
    });

    // Close on backdrop click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });

    // Real-time Search Filter inside Command Palette
    input.addEventListener('input', () => {
        const query = input.value.toLowerCase();
        const items = results.querySelectorAll('.cmd-item');
        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(query) ? 'flex' : 'none';
        });
    });
});
