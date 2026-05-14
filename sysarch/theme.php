<?php
/**
 * theme.php — Shared Dark/Light Mode Theme
 * Include this file in the <head> of every user-facing page.
 * 
 * Usage:
 *   <?php require 'theme.php'; ?>
 * 
 * Also add the toggle button in your <nav> using:
 *   <?php echo theme_toggle_button(); ?>
 * 
 * And at the bottom of your page, before </body>:
 *   <?php echo theme_script(); ?>
 */

function theme_styles(): string {
    return <<<'CSS'
    <style id="uc-ccs-theme">
    /* ══════════════════════════════════════════
       UC CCS — Shared Theme CSS Variables
       Include this via theme.php on every page
    ══════════════════════════════════════════ */

    /* ── LIGHT MODE (default) ── */
    :root {
        /* Backgrounds */
        --bg-body:         #f0eef8;
        --bg-panel:        #ffffff;
        --bg-nav:          #2d1b4e;
        --bg-header:       #3d2b5e;
        --bg-hover:        #f5f3fc;
        --bg-input:        #ffffff;
        --bg-tag:          #ede9f7;
        --bg-card:         #faf9fe;
        --bg-table-head:   #3d2b5e;
        --bg-table-row:    #ffffff;
        --bg-table-alt:    #f8f6fd;
        --bg-table-hover:  #f0eef8;

        /* Text */
        --text-primary:    #1e1b2e;
        --text-secondary:  #4a4560;
        --text-muted:      #8b84a0;
        --text-nav:        #ffffff;
        --text-label:      #6b6480;
        --text-val:        #1e1b2e;
        --text-post:       #2d2444;
        --text-meta:       #6b6480;
        --text-rules:      #2d2444;
        --text-rules-li:   #3d3560;
        --text-table-head: #ffffff;
        --text-table-body: #2d2444;

        /* Borders & Shadows */
        --border:          #e8e6f0;
        --border-panel:    rgba(74, 30, 138, 0.08);
        --shadow-panel:    0 4px 24px rgba(74, 30, 138, 0.08);
        --shadow-nav:      0 2px 12px rgba(45, 27, 78, 0.15);
        --shadow-card:     0 2px 12px rgba(74, 30, 138, 0.06);

        /* Accents */
        --accent:          #6c3fc7;
        --accent-hover:    #5a31aa;
        --accent-light:    #ede9f7;
        --accent-gold:     #c9a227;
        --accent-gold-hover:#b08a1a;

        /* Form inputs */
        --input-border:    #d4cfe8;
        --input-focus:     #6c3fc7;
        --input-text:      #1e1b2e;
        --input-placeholder: #a89fbe;
        --input-bg:        #ffffff;

        /* Status badges */
        --status-active-bg:    #dcfce7;
        --status-active-text:  #166534;
        --status-active-dot:   #22c55e;
        --status-inactive-bg:  #f3f4f6;
        --status-inactive-text:#374151;
        --status-inactive-dot: #9ca3af;
        --status-pending-bg:   #fef9c3;
        --status-pending-text: #854d0e;
        --status-pending-dot:  #eab308;

        /* Notifications */
        --notif-bg:        #ffffff;
        --notif-border:    #e8e6f0;
        --notif-hover:     #f5f3fc;
        --notif-header-bg: #f0eef8;
        --notif-header-text:#1e1b2e;

        /* Footer */
        --footer-bg:       #2d1b4e;
        --footer-text:     rgba(255,255,255,0.6);

        /* Scrollbar */
        --scrollbar-track: #f0eef8;
        --scrollbar-thumb: #c4b8e0;

        /* Breadcrumb / page title */
        --page-title-color: #3d2b5e;
        --breadcrumb-color: #8b84a0;

        /* Alert / flash messages */
        --alert-success-bg:   #dcfce7;
        --alert-success-text: #166534;
        --alert-success-border:#bbf7d0;
        --alert-error-bg:     #fee2e2;
        --alert-error-text:   #991b1b;
        --alert-error-border: #fecaca;
        --alert-info-bg:      #ede9f7;
        --alert-info-text:    #3d2b5e;
        --alert-info-border:  #c4b8e0;
    }

    /* ── DARK MODE ── */
    [data-theme="dark"] {
        /* Backgrounds */
        --bg-body:         #110e1c;
        --bg-panel:        #1c1730;
        --bg-nav:          #0d0a18;
        --bg-header:       #261e3d;
        --bg-hover:        #261e3d;
        --bg-input:        #261e3d;
        --bg-tag:          #261e3d;
        --bg-card:         #1c1730;
        --bg-table-head:   #261e3d;
        --bg-table-row:    #1c1730;
        --bg-table-alt:    #1a1528;
        --bg-table-hover:  #261e3d;

        /* Text */
        --text-primary:    #ece8ff;
        --text-secondary:  #c5bde0;
        --text-muted:      #7a7394;
        --text-nav:        #ece8ff;
        --text-label:      #a89fbe;
        --text-val:        #ddd6f8;
        --text-post:       #cfc8e8;
        --text-meta:       #7a7394;
        --text-rules:      #cfc8e8;
        --text-rules-li:   #bab3d5;
        --text-table-head: #ece8ff;
        --text-table-body: #cfc8e8;

        /* Borders & Shadows */
        --border:          #2b2245;
        --border-panel:    rgba(130, 90, 230, 0.18);
        --shadow-panel:    0 4px 28px rgba(0, 0, 0, 0.5);
        --shadow-nav:      0 2px 16px rgba(0, 0, 0, 0.6);
        --shadow-card:     0 2px 14px rgba(0, 0, 0, 0.4);

        /* Accents */
        --accent:          #9b72f5;
        --accent-hover:    #b08fff;
        --accent-light:    #261e3d;
        --accent-gold:     #e0a832;
        --accent-gold-hover:#f0c050;

        /* Form inputs */
        --input-border:    #3b3060;
        --input-focus:     #9b72f5;
        --input-text:      #ece8ff;
        --input-placeholder: #6b6488;
        --input-bg:        #1e1830;

        /* Status badges */
        --status-active-bg:    #052e16;
        --status-active-text:  #4ade80;
        --status-active-dot:   #4ade80;
        --status-inactive-bg:  #1c2433;
        --status-inactive-text:#9ca3af;
        --status-inactive-dot: #4b5563;
        --status-pending-bg:   #2d2010;
        --status-pending-text: #fbbf24;
        --status-pending-dot:  #f59e0b;

        /* Notifications */
        --notif-bg:        #1c1730;
        --notif-border:    #2b2245;
        --notif-hover:     #261e3d;
        --notif-header-bg: #261e3d;
        --notif-header-text:#ece8ff;

        /* Footer */
        --footer-bg:       #0d0a18;
        --footer-text:     rgba(200,190,240,0.5);

        /* Scrollbar */
        --scrollbar-track: #1a1528;
        --scrollbar-thumb: #4a3f6e;

        /* Breadcrumb / page title */
        --page-title-color: #c5bde0;
        --breadcrumb-color: #7a7394;

        /* Alert / flash messages */
        --alert-success-bg:   #052e16;
        --alert-success-text: #4ade80;
        --alert-success-border:#14532d;
        --alert-error-bg:     #2d0a0a;
        --alert-error-text:   #f87171;
        --alert-error-border: #7f1d1d;
        --alert-info-bg:      #1c1730;
        --alert-info-text:    #c5bde0;
        --alert-info-border:  #3b3060;
    }

    /* ── SMOOTH TRANSITIONS ── */
    *, *::before, *::after {
        transition: background-color 0.25s ease, color 0.25s ease,
                    border-color 0.25s ease, box-shadow 0.25s ease;
    }

    /* ── GLOBAL BASE ── */
    body {
        background: var(--bg-body) !important;
        color: var(--text-primary) !important;
    }

    /* ── NAV ── */
    nav {
        background: var(--bg-nav) !important;
        box-shadow: var(--shadow-nav) !important;
    }
    .nav-title { color: var(--text-nav) !important; }
    .nav-links a {
        color: rgba(255,255,255,0.75) !important;
    }
    .nav-links a:hover,
    .nav-links a.active {
        color: #fff !important;
        background: rgba(255,255,255,0.12) !important;
    }
    .btn-nav {
        background: var(--accent-gold) !important;
        color: #fff !important;
    }
    .btn-nav:hover {
        background: var(--accent-gold-hover) !important;
    }

    /* ── PANELS ── */
    .info-panel,
    .announcement-panel,
    .rules-panel,
    .content-panel,
    .card-panel,
    .form-panel {
        background: var(--bg-panel) !important;
        border: 1px solid var(--border-panel) !important;
        box-shadow: var(--shadow-panel) !important;
    }
    .panel-header {
        background: var(--bg-header) !important;
        color: #fff !important;
    }

    /* ── PROFILE / INFO CARD ── */
    .profile-card, .info-list {
        background: transparent !important;
    }
    .info-item .label { color: var(--text-label) !important; }
    .info-item .val   { color: var(--text-val) !important; }
    .session-count    { color: var(--accent) !important; font-weight: 700 !important; }

    /* ── STATUS BADGES ── */
    .status-indicator.active {
        background: var(--status-active-bg) !important;
        color: var(--status-active-text) !important;
    }
    .status-indicator.active .status-dot  { background: var(--status-active-dot) !important; }
    .status-indicator.inactive {
        background: var(--status-inactive-bg) !important;
        color: var(--status-inactive-text) !important;
    }
    .status-indicator.inactive .status-dot { background: var(--status-inactive-dot) !important; }
    .status-indicator.pending {
        background: var(--status-pending-bg) !important;
        color: var(--status-pending-text) !important;
    }
    .status-indicator.pending .status-dot  { background: var(--status-pending-dot) !important; }

    /* ── ANNOUNCEMENTS ── */
    .scroll-box { scrollbar-color: var(--scrollbar-thumb) var(--scrollbar-track); }
    .post { border-bottom: 1px solid var(--border) !important; }
    .post-meta { color: var(--text-meta) !important; }
    .post p    { color: var(--text-post) !important; }

    /* ── RULES ── */
    .rules-content h3, .rules-content h4,
    .rules-content p,  .rules-content strong { color: var(--text-rules) !important; }
    .rules-content li  { color: var(--text-rules-li) !important; }

    /* ── TABLES ── */
    table {
        background: var(--bg-table-row) !important;
        color: var(--text-table-body) !important;
        border-color: var(--border) !important;
    }
    thead th, .table-header th {
        background: var(--bg-table-head) !important;
        color: var(--text-table-head) !important;
        border-color: var(--border) !important;
    }
    tbody tr:nth-child(even) {
        background: var(--bg-table-alt) !important;
    }
    tbody tr:hover {
        background: var(--bg-table-hover) !important;
    }
    tbody td {
        color: var(--text-table-body) !important;
        border-color: var(--border) !important;
    }

    /* ── FORM INPUTS ── */
    input, select, textarea {
        background: var(--input-bg) !important;
        color: var(--input-text) !important;
        border-color: var(--input-border) !important;
    }
    input::placeholder, textarea::placeholder {
        color: var(--input-placeholder) !important;
    }
    input:focus, select:focus, textarea:focus {
        border-color: var(--input-focus) !important;
        box-shadow: 0 0 0 3px rgba(108, 63, 199, 0.15) !important;
        outline: none !important;
    }
    label { color: var(--text-label) !important; }

    /* ── CARDS ── */
    .card, .stat-card, .info-card {
        background: var(--bg-card) !important;
        border: 1px solid var(--border-panel) !important;
        box-shadow: var(--shadow-card) !important;
        color: var(--text-primary) !important;
    }

    /* ── ALERTS ── */
    .alert-success {
        background: var(--alert-success-bg) !important;
        color: var(--alert-success-text) !important;
        border-color: var(--alert-success-border) !important;
    }
    .alert-error, .alert-danger {
        background: var(--alert-error-bg) !important;
        color: var(--alert-error-text) !important;
        border-color: var(--alert-error-border) !important;
    }
    .alert-info {
        background: var(--alert-info-bg) !important;
        color: var(--alert-info-text) !important;
        border-color: var(--alert-info-border) !important;
    }

    /* ── BUTTONS ── */
    .btn-primary, .btn-accent {
        background: var(--accent) !important;
        color: #fff !important;
        border-color: var(--accent) !important;
    }
    .btn-primary:hover, .btn-accent:hover {
        background: var(--accent-hover) !important;
        border-color: var(--accent-hover) !important;
    }

    /* ── NOTIFICATIONS ── */
    .notification-dropdown {
        background: var(--notif-bg) !important;
        border: 1px solid var(--notif-border) !important;
        box-shadow: var(--shadow-panel) !important;
    }
    .notification-header {
        background: var(--notif-header-bg) !important;
        color: var(--notif-header-text) !important;
        border-bottom: 1px solid var(--notif-border) !important;
    }
    .notification-entry {
        border-bottom: 1px solid var(--notif-border) !important;
    }
    .notification-entry:hover  { background: var(--notif-hover) !important; }
    .notification-message { color: var(--text-primary) !important; }
    .notification-time    { color: var(--text-muted) !important; }
    .notification-empty   { color: var(--text-muted) !important; }
    .notification-btn svg { stroke: rgba(255,255,255,0.85) !important; }

    /* ── FOOTER ── */
    footer {
        background: var(--footer-bg) !important;
        color: var(--footer-text) !important;
    }

    /* ── PAGE TITLES / BREADCRUMBS ── */
    .page-title   { color: var(--page-title-color) !important; }
    .breadcrumb   { color: var(--breadcrumb-color) !important; }

    /* ── SCROLLBAR ── */
    ::-webkit-scrollbar { width: 7px; height: 7px; }
    ::-webkit-scrollbar-track { background: var(--scrollbar-track); }
    ::-webkit-scrollbar-thumb {
        background: var(--scrollbar-thumb);
        border-radius: 4px;
    }

    /* ── THEME TOGGLE BUTTON ── */
    .theme-toggle-btn {
        display: flex;
        align-items: center;
        gap: 6px;
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.22);
        border-radius: 20px;
        padding: 5px 13px;
        cursor: pointer;
        font-size: 0.82rem;
        font-weight: 600;
        color: #fff;
        letter-spacing: 0.3px;
        transition: background 0.2s, transform 0.15s;
        white-space: nowrap;
    }
    .theme-toggle-btn:hover {
        background: rgba(255,255,255,0.22);
        transform: translateY(-1px);
    }
    .theme-toggle-btn .toggle-icon {
        font-size: 1rem;
        line-height: 1;
    }
    </style>
CSS;
}

function theme_toggle_button(): string {
    return <<<'HTML'
    <li>
        <button class="theme-toggle-btn" id="themeToggle" title="Toggle Dark/Light Mode">
            <span class="toggle-icon" id="toggleIcon">🌙</span>
            <span id="toggleLabel">Dark</span>
        </button>
    </li>
HTML;
}

function theme_script(): string {
    return <<<'JS'
    <script>
    /* ── UC CCS THEME TOGGLE — shared across all pages ── */
    (function () {
        const html  = document.documentElement;
        const btn   = document.getElementById('themeToggle');
        const icon  = document.getElementById('toggleIcon');
        const label = document.getElementById('toggleLabel');
        const KEY   = 'uc_ccs_theme';

        function applyTheme(theme) {
            html.setAttribute('data-theme', theme);
            if (theme === 'dark') {
                if (icon)  icon.textContent  = '☀️';
                if (label) label.textContent = 'Light';
                if (btn)   btn.title = 'Switch to Light Mode';
            } else {
                if (icon)  icon.textContent  = '🌙';
                if (label) label.textContent = 'Dark';
                if (btn)   btn.title = 'Switch to Dark Mode';
            }
        }

        // Apply saved or default theme immediately (prevents flash)
        const saved = localStorage.getItem(KEY) || 'light';
        applyTheme(saved);

        if (btn) {
            btn.addEventListener('click', function () {
                const next = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
                localStorage.setItem(KEY, next);
                applyTheme(next);
            });
        }

        // Sync across tabs/pages in real time
        window.addEventListener('storage', function (e) {
            if (e.key === KEY && e.newValue) applyTheme(e.newValue);
        });
    })();
    </script>
JS;
}

// Auto-apply theme on page load (prevents white flash before JS runs)
$_theme_init = <<<'SCRIPT'
<script>
(function(){
    var t = localStorage.getItem('uc_ccs_theme') || 'light';
    document.documentElement.setAttribute('data-theme', t);
})();
</script>
SCRIPT;
echo $_theme_init;
