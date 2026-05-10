<?php
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e(APP_NAME) ?> — <?= e(ucfirst($active_page ?? 'Dashboard')) ?></title>

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,400&family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet" />

  <!-- Base styles -->
  <link rel="stylesheet" href="css/main.css?v=<?= time() ?>" />

  <!-- Header / sidebar / nav styles -->
  <link rel="stylesheet" href="css/header.css?v=<?= time() ?>" />

  <!-- Optional per-page stylesheet (set $page_css before including header) -->
  <?php if (isset($page_css)): ?>
    <link rel="stylesheet" href="<?= e($page_css) ?>?v=<?= time() ?>" />
  <?php endif; ?>
</head>

<body>

<?php if ($flash): ?>
  <div class="toast toast--<?= e($flash['type']) ?> toast--show" id="toast">
    <?php if ($flash['type'] === 'success'): ?>
      <svg viewBox="0 0 24 24" fill="white" style="width:14px;height:14px;flex-shrink:0;">
        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
      </svg>
    <?php else: ?>
      <svg viewBox="0 0 24 24" fill="white" style="width:14px;height:14px;flex-shrink:0;">
        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
      </svg>
    <?php endif; ?>
    <?= e($flash['message']) ?>
  </div>
  <script>
    setTimeout(() => {
      const t = document.getElementById('toast');
      if (t) t.classList.remove('toast--show');
    }, 3000);
    
  </script>

  
<?php endif; ?>

<div class="app">

  <header class="mobile-topbar" aria-label="Mobile header">
    <button type="button" class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Open navigation" aria-controls="app-sidebar" aria-expanded="false">
      <span></span>
      <span></span>
      <span></span>
    </button>
    <a href="dashboard.php" class="mobile-topbar-brand">
      <span class="mobile-topbar-icon">
        <svg viewBox="0 0 24 24">
          <path d="M19 3h-1V1h-2v2H8V1H6v2H5C3.9 3 3 3.9 3 5v14c0 1.1.9 2 2 2h7.35c-.22-.62-.35-1.29-.35-2 0-3.31 2.69-6 6-6 .34 0 .67.03 1 .08V9H3V7h16v2.35c.72.22 1.39.57 2 1V5c0-1.1-.9-2-2-2z"/>
          <path d="M19 15c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm1 4.5h-1.5V21h-1v-3H19v1.5h1v1z"/>
        </svg>
      </span>
      <span class="mobile-topbar-text">
        <span class="mobile-topbar-name"><?= e(APP_NAME) ?></span>
      </span>
    </a>
    <?php if (($active_page ?? '') === 'dashboard'): ?>
      <button type="button" class="mobile-topbar-action" data-open-quick-log>Quick Log</button>
    <?php endif; ?>
  </header>
  <div class="mobile-sidebar-backdrop" id="mobile-sidebar-backdrop" aria-hidden="true"></div>
  <script>
    (function () {
      const btn = document.getElementById('mobile-menu-toggle');
      const backdrop = document.getElementById('mobile-sidebar-backdrop');
      if (!btn || !backdrop || btn.dataset.bound === '1') return;

      const setOpen = (open) => {
        document.body.classList.toggle('sidebar-open', open);
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
      };

      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        setOpen(!document.body.classList.contains('sidebar-open'));
      });

      backdrop.addEventListener('click', function () {
        setOpen(false);
      });

      document.addEventListener('click', function (e) {
        if (e.target && e.target.closest && e.target.closest('#mobile-sidebar-close')) {
          setOpen(false);
        }
      });

      window.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setOpen(false);
      });

      btn.dataset.bound = '1';
    })();
  </script>

  <!-- ═══════════════════════════════════
       SIDEBAR
  ════════════════════════════════════ -->
  <aside class="sidebar" id="app-sidebar">

    <!-- Brand -->
    <a href="dashboard.php" class="sidebar-brand">
      <div class="sidebar-brand-icon">
        <svg viewBox="0 0 24 24">
          <path d="M19 3h-1V1h-2v2H8V1H6v2H5C3.9 3 3 3.9 3 5v14c0 1.1.9 2 2 2h7.35c-.22-.62-.35-1.29-.35-2 0-3.31 2.69-6 6-6 .34 0 .67.03 1 .08V9H3V7h16v2.35c.72.22 1.39.57 2 1V5c0-1.1-.9-2-2-2z"/>
          <path d="M19 15c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm1 4.5h-1.5V21h-1v-3H19v1.5h1v1z"/>
        </svg>
      </div>
      <div class="sidebar-brand-text">
        <div class="sidebar-brand-name"><?= e(APP_NAME) ?></div>
        <div class="sidebar-brand-version"><?= e(defined('APP_VERSION') ? (string) constant('APP_VERSION') : 'v0.5.0') ?></div>
      </div>
    </a>

    <button type="button" class="mobile-sidebar-close" id="mobile-sidebar-close" aria-label="Close navigation">X</button>

    <!-- Nav links -->
    <nav class="sidebar-nav">

      <div class="nav-section">Main</div>

      <a href="dashboard.php" class="nav-item <?= ($active_page === 'dashboard') ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
        </svg>
        Dashboard
      </a>

      <a href="logs.php" class="nav-item <?= ($active_page === 'logs') ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/>
        </svg>
        Time Logs
      </a>

          <a href="notes.php" class="nav-item <?= ($active_page === 'notes') ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
        </svg>
        Notes
      </a>
 

      <div class="nav-section">Account</div>

      <a href="settings.php" class="nav-item <?= ($active_page === 'settings') ? 'active' : '' ?>">
        <svg viewBox="0 0 24 24" fill="currentColor">
          <path d="M19.14 12.94c.04-.3.06-.61.06-.94s-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/>
        </svg>
        Settings
      </a>

    </nav>

    <!-- User info + logout -->
    <div class="sidebar-bottom">
      <?php
        $sidebar_display_name = $user['name'] ?? ($user['username'] ?? APP_NAME);
        $sidebar_display_name = explode(' ', trim((string) $sidebar_display_name))[0] ?: $sidebar_display_name;
        $sidebar_initial = strtoupper(substr(trim((string) $sidebar_display_name), 0, 1));
        $sidebar_app_version = defined('APP_VERSION') ? (string) constant('APP_VERSION') : 'v0.5.0';
      ?>
      <div class="sidebar-user sidebar-user--card">
        <div class="sidebar-user-main">
          <div class="sidebar-avatar"><?= e($sidebar_initial ?: 'U') ?></div>
          <div>
            <div class="sidebar-user-name"><?= e($sidebar_display_name) ?></div>
            <div class="sidebar-user-role"><?= e($sidebar_app_version) ?></div>
          </div>
        </div>

        <form method="POST" action="logout.php">
          <button type="submit" class="sidebar-user-inline-logout" aria-label="Log out" title="Log out">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
              <polyline points="16 17 21 12 16 7" />
              <line x1="21" y1="12" x2="9" y2="12" />
            </svg>
          </button>
        </form>
      </div>
    </div>

  </aside>
  <!-- /sidebar -->

  <!-- ═══════════════════════════════════
       MAIN CONTENT (pages render here)
  ════════════════════════════════════ -->
  <main class="main">

  <!-- Mobile bottom tab bar -->
  <nav class="bottom-tab-bar" aria-label="Mobile navigation">
    <a href="dashboard.php" class="bottom-tab <?= ($active_page === 'dashboard') ? 'bottom-tab--active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
      <span>Home</span>
    </a>
    <a href="logs.php" class="bottom-tab <?= ($active_page === 'logs') ? 'bottom-tab--active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
      <span>Logs</span>
    </a>
    <a href="notes.php" class="bottom-tab <?= ($active_page === 'notes') ? 'bottom-tab--active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/></svg>
      <span>Notes</span>
    </a>
    <a href="settings.php" class="bottom-tab <?= ($active_page === 'settings') ? 'bottom-tab--active' : '' ?>">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.14 12.94c.04-.3.06-.61.06-.94s-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
      <span>Settings</span>
    </a>
  </nav>