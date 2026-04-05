<?php
require_once 'includes/config.php';
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$auth_mode = $_GET['auth'] ?? 'login';
if (!in_array($auth_mode, ['login', 'register', 'forgot'], true)) {
  $auth_mode = 'login';
}

$auth_errors = [];
$auth_notice = '';
$auth_open = isset($_GET['auth']);

$reg_step = (int) ($_POST['reg_step'] ?? 1);
$reg_data = [
  'name'           => $_POST['name'] ?? '',
  'username'       => $_POST['username'] ?? '',
  'required_hours' => $_POST['required_hours'] ?? '',
  'password'       => $_POST['password'] ?? '',
];

$fp_step = (int) ($_POST['fp_step'] ?? 1);
$fp_username = strtolower(trim($_POST['fp_username'] ?? ''));
$fp_question = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $auth_action = $_POST['auth_action'] ?? 'login';
  $auth_open = true;

  if ($auth_action === 'login') {
    $auth_mode = 'login';
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
      $auth_errors[] = 'Please fill in all fields.';
    } else {
      $user = get_user($username);
      if (!$user) {
        $auth_errors[] = 'Username not found.';
      } elseif (!verify_password($password, $user['password'])) {
        $auth_errors[] = 'Incorrect password.';
      } else {
        $_SESSION['username'] = $username;
        header('Location: dashboard.php');
        exit;
      }
    }
  }

  if ($auth_action === 'register') {
    $auth_mode = 'register';

    if ($reg_step === 1) {
      $name = trim($_POST['name'] ?? '');
      $username = strtolower(trim($_POST['username'] ?? ''));
      $req_hrs = (float) ($_POST['required_hours'] ?? 500);
      $password = $_POST['password'] ?? '';

      if (!$name || !$username || !$password) {
        $auth_errors[] = 'Please fill in all fields.';
      } elseif (strlen($password) < 6) {
        $auth_errors[] = 'Password must be at least 6 characters.';
      } elseif ($req_hrs < 1) {
        $auth_errors[] = 'Enter a valid number of required hours.';
      } elseif (get_user($username)) {
        $auth_errors[] = 'Username already taken.';
      } else {
        $reg_step = 2;
      }
    } elseif ($reg_step === 2) {
      $name = trim($_POST['name'] ?? '');
      $username = strtolower(trim($_POST['username'] ?? ''));
      $req_hrs = (float) ($_POST['required_hours'] ?? 500);
      $password = $_POST['password'] ?? '';
      $sec_question = trim($_POST['security_question'] ?? '');
      $sec_answer = trim($_POST['security_answer'] ?? '');

      if (!$sec_question || !$sec_answer) {
        $auth_errors[] = 'Please select a security question and provide an answer.';
      } else {
        save_user([
          'name' => $name,
          'username' => $username,
          'password' => hash_password($password),
          'required_hours' => $req_hrs,
          'security_question' => $sec_question,
          'security_answer' => strtolower(trim($sec_answer)),
        ]);

        $_SESSION['username'] = $username;
        header('Location: dashboard.php');
        exit;
      }
    }
  }

  if ($auth_action === 'forgot') {
    $auth_mode = 'forgot';

    if ($fp_step === 1) {
      if (!$fp_username) {
        $auth_errors[] = 'Please enter your username.';
      } else {
        $user = get_user($fp_username);
        if (!$user) {
          $auth_errors[] = 'Username not found.';
        } elseif (!isset($user['security_question'])) {
          $auth_errors[] = 'This account has no security question set.';
        } else {
          $fp_question = $user['security_question'];
          $fp_step = 2;
        }
      }
    } elseif ($fp_step === 2) {
      $fp_answer = trim($_POST['fp_answer'] ?? '');
      if (!$fp_answer) {
        $auth_errors[] = 'Please enter your answer.';
        $fp_question = get_security_question($fp_username);
      } elseif (!verify_security_answer($fp_username, $fp_answer)) {
        $auth_errors[] = 'Incorrect answer. Please try again.';
        $fp_question = get_security_question($fp_username);
      } else {
        $fp_step = 3;
      }
    } elseif ($fp_step === 3) {
      $new_pw = $_POST['new_password'] ?? '';
      $confirm_pw = $_POST['confirm_password'] ?? '';
      $user = get_user($fp_username);

      if (strlen($new_pw) < 6) {
        $auth_errors[] = 'Password must be at least 6 characters.';
      } elseif ($new_pw !== $confirm_pw) {
        $auth_errors[] = 'Passwords do not match.';
      } elseif ($user && verify_password($new_pw, $user['password'])) {
        $auth_errors[] = 'New password cannot be the same as your current password.';
      } else {
        if ($user) {
          $user['password'] = hash_password($new_pw);
          save_user($user);
        }
        $auth_mode = 'login';
        $auth_notice = 'Password reset successfully. You can sign in now.';
        $fp_step = 1;
      }
    }
  }
}

if ($auth_mode === 'forgot' && $fp_step === 2 && !$fp_question && $fp_username) {
  $fp_question = get_security_question($fp_username);
}

$register_render_step = ($auth_mode === 'register') ? $reg_step : 1;
$forgot_render_step = ($auth_mode === 'forgot') ? $fp_step : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e(APP_NAME) ?> — Track Your OJT Hours</title>
  <link rel="stylesheet" href="css/landing.css" />
</head>
<body>

<!-- ══════════════════════════════════════════
     NAV
══════════════════════════════════════════ -->
<nav class="nav">
  <div class="nav-brand">
    <div class="nav-brand-icon">
      <svg viewBox="0 0 24 24">
        <path d="M19 3h-1V1h-2v2H8V1H6v2H5C3.9 3 3 3.9 3 5v14c0 1.1.9 2 2 2h7.35c-.22-.62-.35-1.29-.35-2 0-3.31 2.69-6 6-6 .34 0 .67.03 1 .08V9H3V7h16v2.35c.72.22 1.39.57 2 1V5c0-1.1-.9-2-2-2z"/>
        <path d="M19 15c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm1 4.5h-1.5V21h-1v-3H19v1.5h1v1z"/>
      </svg>
    </div>
    <?= e(APP_NAME) ?>
  </div>
  <div class="nav-links">
    <a href="#features" class="nav-link">Features</a>
    <a href="#how" class="nav-link">How it works</a>
    <button type="button" class="nav-btn nav-btn-outline" data-open-auth="login">Log in</button>
    <button type="button" class="nav-btn nav-btn-solid" data-open-auth="register">Get started</button>
  </div>
</nav>

<!-- ══════════════════════════════════════════
     HERO
══════════════════════════════════════════ -->
<section class="hero">

  <div class="hero-badge">
    <span class="hero-badge-dot"></span>
    OJT Tracking Platform
  </div>

  <h1 class="hero-title">
    <span class="hero-title-line">Track Your OJT Hours</span>
    <span class="hero-title-line hero-title-accent">Smarter &amp; Easier</span>
  </h1>

  <p class="hero-sub">
    <?= e(APP_NAME) ?> helps you log, monitor, and manage your On-the-Job Training hours —
    so you always know where you stand and when you'll finish.
  </p>

  <div class="hero-ctas">
    <button type="button" class="btn-hero-primary" data-open-auth="register">
      <svg viewBox="0 0 24 24" fill="white"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
      Start tracking for free
    </button>
    <button type="button" class="btn-hero-secondary" data-open-auth="login">
      Log in to my account
    </button>
  </div>

  <div class="hero-stats">
    <div class="hero-stat">
      <div class="hero-stat-num">500+</div>
      <div class="hero-stat-label">Hours tracked</div>
    </div>
    <div class="hero-stat-divider"></div>
    <div class="hero-stat">
      <div class="hero-stat-num">100%</div>
      <div class="hero-stat-label">Free to use</div>
    </div>
    <div class="hero-stat-divider"></div>
    <div class="hero-stat">
      <div class="hero-stat-num">₱</div>
      <div class="hero-stat-label">Allowance tracker</div>
    </div>
    <div class="hero-stat-divider"></div>
    <div class="hero-stat">
      <div class="hero-stat-num">PH</div>
      <div class="hero-stat-label">Made in the Philippines</div>
    </div>
  </div>

</section>

<div id="auth-modal" class="auth-modal <?= $auth_open ? 'open' : '' ?>" aria-hidden="<?= $auth_open ? 'false' : 'true' ?>" data-initial-mode="<?= e($auth_mode) ?>">
  <div class="auth-modal-backdrop" data-close-auth></div>
  <div class="auth-modal-card" role="dialog" aria-modal="true" aria-label="Authentication dialog">
    <button type="button" class="auth-modal-close" data-close-auth aria-label="Close">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>

    <div class="auth-modal-tabs">
      <button type="button" class="auth-modal-tab <?= $auth_mode === 'login' ? 'active' : '' ?>" data-auth-tab="login">Sign in</button>
      <button type="button" class="auth-modal-tab <?= $auth_mode === 'register' ? 'active' : '' ?>" data-auth-tab="register">Create account</button>
      <button type="button" class="auth-modal-tab <?= $auth_mode === 'forgot' ? 'active' : '' ?>" data-auth-tab="forgot">Forgot password</button>
    </div>

    <?php if (!empty($auth_errors)): ?>
      <div class="auth-modal-alert auth-modal-alert--error"><?= e($auth_errors[0]) ?></div>
    <?php endif; ?>
    <?php if ($auth_notice): ?>
      <div class="auth-modal-alert auth-modal-alert--success"><?= e($auth_notice) ?></div>
    <?php endif; ?>

    <div class="auth-panel <?= $auth_mode === 'login' ? 'active' : '' ?>" data-auth-panel="login" aria-hidden="<?= $auth_mode === 'login' ? 'false' : 'true' ?>">
      <h3 class="auth-modal-title">Sign in</h3>
      <p class="auth-modal-sub">Track your OJT hours and monitor your progress.</p>

      <form method="POST" action="landing.php#auth-modal" class="auth-modal-form">
        <input type="hidden" name="auth_action" value="login" />
        <div class="auth-modal-field">
          <input class="auth-modal-input" type="text" name="username" placeholder="Username" value="<?= e($_POST['username'] ?? '') ?>" required />
        </div>
        <div class="auth-modal-field">
          <input class="auth-modal-input" type="password" id="landing-login-pw" name="password" placeholder="Password" required />
          <button type="button" class="auth-modal-eye" data-toggle-pw="landing-login-pw">Show</button>
        </div>
        <button type="submit" class="auth-modal-btn auth-modal-btn--primary">Sign in</button>
      </form>
    </div>

    <div class="auth-panel <?= $auth_mode === 'register' ? 'active' : '' ?>" data-auth-panel="register" aria-hidden="<?= $auth_mode === 'register' ? 'false' : 'true' ?>">
      <h3 class="auth-modal-title">Create account</h3>
      <p class="auth-modal-sub">Set up your account and start tracking today.</p>

      <?php if ($register_render_step === 1): ?>
        <form method="POST" action="landing.php#auth-modal" class="auth-modal-form">
          <input type="hidden" name="auth_action" value="register" />
          <input type="hidden" name="reg_step" value="1" />
          <div class="auth-modal-field"><input class="auth-modal-input" type="text" name="name" placeholder="Full name" value="<?= e($reg_data['name']) ?>" required /></div>
          <div class="auth-modal-field"><input class="auth-modal-input" type="text" name="username" placeholder="Username" value="<?= e($reg_data['username']) ?>" required /></div>
          <div class="auth-modal-field"><input class="auth-modal-input" type="number" name="required_hours" placeholder="Required OJT hours (e.g. 500)" min="1" step="0.5" value="<?= e($reg_data['required_hours']) ?>" required /></div>
          <div class="auth-modal-field">
            <input class="auth-modal-input" type="password" id="landing-reg-pw" name="password" placeholder="Password (min. 6 characters)" required />
            <button type="button" class="auth-modal-eye" data-toggle-pw="landing-reg-pw">Show</button>
          </div>
          <button type="submit" class="auth-modal-btn auth-modal-btn--primary">Next</button>
        </form>
      <?php else: ?>
        <form method="POST" action="landing.php#auth-modal" class="auth-modal-form">
          <input type="hidden" name="auth_action" value="register" />
          <input type="hidden" name="reg_step" value="2" />
          <input type="hidden" name="name" value="<?= e($reg_data['name']) ?>" />
          <input type="hidden" name="username" value="<?= e($reg_data['username']) ?>" />
          <input type="hidden" name="required_hours" value="<?= e($reg_data['required_hours']) ?>" />
          <input type="hidden" name="password" value="<?= e($reg_data['password']) ?>" />

          <div class="auth-modal-field">
            <select class="auth-modal-input" name="security_question" required>
              <option value="">Select a security question</option>
              <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
              <option value="What was the name of your first pet?">What was the name of your first pet?</option>
              <option value="What city were you born in?">What city were you born in?</option>
              <option value="What is your childhood nickname?">What is your childhood nickname?</option>
              <option value="What is the name of your elementary school?">What is the name of your elementary school?</option>
            </select>
          </div>
          <div class="auth-modal-field"><input class="auth-modal-input" type="text" name="security_answer" placeholder="Your answer" required /></div>
          <button type="submit" class="auth-modal-btn auth-modal-btn--primary">Create account</button>
          <button type="button" class="auth-modal-btn auth-modal-btn--ghost" data-auth-tab="register" data-reset-step="register">Back</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="auth-panel <?= $auth_mode === 'forgot' ? 'active' : '' ?>" data-auth-panel="forgot" aria-hidden="<?= $auth_mode === 'forgot' ? 'false' : 'true' ?>">
      <h3 class="auth-modal-title">Forgot password</h3>
      <p class="auth-modal-sub">Recover your account in a few quick steps.</p>

      <?php if ($forgot_render_step === 1): ?>
        <form method="POST" action="landing.php#auth-modal" class="auth-modal-form">
          <input type="hidden" name="auth_action" value="forgot" />
          <input type="hidden" name="fp_step" value="1" />
          <div class="auth-modal-field"><input class="auth-modal-input" type="text" name="fp_username" placeholder="Username" value="<?= e($fp_username) ?>" required /></div>
          <button type="submit" class="auth-modal-btn auth-modal-btn--primary">Continue</button>
        </form>
      <?php elseif ($forgot_render_step === 2): ?>
        <form method="POST" action="landing.php#auth-modal" class="auth-modal-form">
          <input type="hidden" name="auth_action" value="forgot" />
          <input type="hidden" name="fp_step" value="2" />
          <input type="hidden" name="fp_username" value="<?= e($fp_username) ?>" />
          <div class="auth-modal-question"><?= e($fp_question) ?></div>
          <div class="auth-modal-field"><input class="auth-modal-input" type="text" name="fp_answer" placeholder="Your answer" required /></div>
          <button type="submit" class="auth-modal-btn auth-modal-btn--primary">Verify</button>
        </form>
      <?php else: ?>
        <form method="POST" action="landing.php#auth-modal" class="auth-modal-form">
          <input type="hidden" name="auth_action" value="forgot" />
          <input type="hidden" name="fp_step" value="3" />
          <input type="hidden" name="fp_username" value="<?= e($fp_username) ?>" />
          <div class="auth-modal-field">
            <input class="auth-modal-input" type="password" id="landing-new-pw" name="new_password" placeholder="New password" required />
            <button type="button" class="auth-modal-eye" data-toggle-pw="landing-new-pw">Show</button>
          </div>
          <div class="auth-modal-field">
            <input class="auth-modal-input" type="password" id="landing-confirm-pw" name="confirm_password" placeholder="Confirm new password" required />
            <button type="button" class="auth-modal-eye" data-toggle-pw="landing-confirm-pw">Show</button>
          </div>
          <button type="submit" class="auth-modal-btn auth-modal-btn--primary">Reset password</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════
     APP PREVIEW  (V2 Dashboard)
══════════════════════════════════════════ -->
<section class="preview-section">
  <div class="preview-wrap">

    <!-- Browser chrome -->
    <div class="preview-bar">
      <div class="preview-dot" style="background:#ff5f57;"></div>
      <div class="preview-dot" style="background:#febc2e;"></div>
      <div class="preview-dot" style="background:#28c840;"></div>
      <div class="preview-url"><?= e(APP_NAME) ?> — Dashboard</div>
    </div>

    <div class="preview-body">

      <!-- ── Dark green sidebar ── -->
      <div class="preview-sidebar">

        <!-- Brand -->
        <div class="psb-brand">
          <div class="psb-brand-dot"></div>
          <div class="psb-brand-label"></div>
        </div>

        <!-- Nav items -->
        <div class="psb-section-label">MAIN</div>
        <div class="psb-item active">
          <div class="psb-item-icon" style="background:rgba(255,255,255,0.7);border-radius:3px;"></div>
          <div class="psb-item-label" style="width:56px;background:rgba(255,255,255,0.7);"></div>
        </div>
        <div class="psb-item">
          <div class="psb-item-icon" style="background:rgba(255,255,255,0.25);border-radius:3px;"></div>
          <div class="psb-item-label" style="width:46px;background:rgba(255,255,255,0.25);"></div>
        </div>

        <div class="psb-section-label" style="margin-top:10px;">ACCOUNT</div>
        <div class="psb-item">
          <div class="psb-item-icon" style="background:rgba(255,255,255,0.25);border-radius:3px;"></div>
          <div class="psb-item-label" style="width:42px;background:rgba(255,255,255,0.25);"></div>
        </div>

        <div class="psb-footer">
          <div class="psb-divider" style="background:rgba(255,255,255,0.12);"></div>
          <div class="psb-item">
            <div class="psb-item-icon" style="background:rgba(255,255,255,0.2);border-radius:3px;"></div>
            <div class="psb-item-label" style="width:38px;background:rgba(255,255,255,0.2);"></div>
          </div>
        </div>
      </div>

      <!-- ── Main area ── -->
      <div class="preview-main">

        <!-- Welcome header -->
        <div class="pm-welcome-card">
          <div class="pm-welcome-left">
            <div class="pm-welcome-eyebrow"></div>
            <div class="pm-welcome-title"></div>
            <div class="pm-welcome-sub"></div>
          </div>
          <div class="pm-header-actions">
            <div class="pm-btn-white"></div>
            <div class="pm-btn-green"></div>
          </div>
        </div>

        <!-- Three stat cards -->
        <div class="pm-stats">

          <!-- Card 1: Total Progress (dark green) -->
          <div class="pm-stat-card pm-stat-dark">
            <div class="pm-stat-eyebrow" style="background:rgba(255,255,255,0.3);width:70px;"></div>
            <div class="pm-stat-num" style="color:#fff;font-size:22px;">136.0 <span style="font-size:11px;font-weight:500;opacity:0.6;">/240hrs</span></div>
            <!-- Progress bar -->
            <div style="height:5px;background:rgba(255,255,255,0.2);border-radius:999px;margin:8px 0 5px;overflow:hidden;">
              <div style="width:56.7%;height:100%;background:#52b788;border-radius:999px;"></div>
            </div>
            <div style="font-size:8px;color:rgba(255,255,255,0.6);margin-bottom:8px;">56.7% complete</div>
            <!-- Est date chip -->
            <div style="display:inline-flex;align-items:center;gap:4px;background:rgba(255,255,255,0.12);border-radius:999px;padding:3px 8px;">
              <div style="width:5px;height:5px;border-radius:50%;background:rgba(255,255,255,0.5);"></div>
              <span style="font-size:8px;color:rgba(255,255,255,0.75);font-weight:600;">Est. April 22, 2026</span>
            </div>
          </div>

          <!-- Card 2: Remaining Hours (light) -->
          <div class="pm-stat-card pm-stat-light">
            <div class="pm-stat-eyebrow" style="background:rgba(27,67,50,0.12);width:80px;"></div>
            <div class="pm-stat-num" style="color:var(--green);font-size:22px;">104.0 <span style="font-size:11px;font-weight:500;color:var(--accent);">hrs</span></div>
            <div style="font-size:8px;color:var(--accent);margin:3px 0 10px;">hours left to complete your OJT</div>
            <!-- Avg chip -->
            <div style="display:inline-flex;align-items:center;gap:4px;background:var(--green-xlight);border:1px solid var(--green-light);border-radius:999px;padding:3px 9px;">
              <div style="width:5px;height:5px;border-radius:50%;background:var(--accent);"></div>
              <span style="font-size:8px;color:var(--green);font-weight:600;">Avg 8 hrs/day · 13 days left</span>
            </div>
          </div>

          <!-- Card 3: Allowance Summary (dark green) -->
          <div class="pm-stat-card pm-stat-dark">
            <div class="pm-stat-eyebrow" style="background:rgba(255,255,255,0.3);width:80px;"></div>
            <div class="pm-stat-num" style="color:#fff;font-size:18px;">₱4,500.00 <span style="font-size:10px;font-weight:500;opacity:0.6;">Total</span></div>
            <div class="pm-allowance-split">
              <div class="pm-allowance-item">
                <span class="pm-allowance-label">Used</span>
                <span class="pm-allowance-value">₱2,550.00</span>
              </div>
              <div class="pm-allowance-divider"></div>
              <div class="pm-allowance-item pm-allowance-item--right">
                <span class="pm-allowance-label">Remaining</span>
                <span class="pm-allowance-value">₱1,950.00</span>
              </div>
            </div>
          </div>

        </div><!-- /pm-stats -->

        <!-- Bottom row: logs table + quick log panel -->
        <div class="pm-bottom-row">

          <!-- Recent Logs table card -->
          <div class="pm-logs-card">
            <div class="pm-table-header">
              <div class="pm-table-title">RECENT LOGS</div>
              <div style="display:flex;align-items:center;gap:3px;">
                <div style="width:36px;height:7px;border-radius:3px;background:rgba(0,0,0,0.07);"></div>
                <div style="width:6px;height:7px;border-radius:2px;background:rgba(0,0,0,0.07);"></div>
              </div>
            </div>
            <div class="pm-table">
              <!-- Col headers -->
              <div class="pm-table-head">
                <div style="width:58px;height:6px;border-radius:3px;background:rgba(0,0,0,0.08);"></div>
                <div style="width:70px;height:6px;border-radius:3px;background:rgba(0,0,0,0.08);"></div>
                <div style="flex:1;"></div>
                <div style="width:36px;height:6px;border-radius:3px;background:rgba(0,0,0,0.08);"></div>
                <div style="width:36px;height:6px;border-radius:3px;background:rgba(0,0,0,0.08);"></div>
                <div style="width:28px;height:6px;border-radius:3px;background:rgba(0,0,0,0.08);"></div>
                <div style="width:28px;"></div>
              </div>
              <?php
                $rows2 = [
                  ['Mar 31, 2026', '—',           '8:00 AM', '4:00 PM', '8.0'],
                  ['Mar 30, 2026', '—',           '8:00 AM', '4:00 PM', '8.0'],
                  ['Mar 25, 2026', '—',           '8:00 AM', '4:00 PM', '8.0'],
                  ['Mar 24, 2026', '—',           '8:00 AM', '4:00 PM', '8.0'],
                ];
                foreach ($rows2 as $row):
              ?>
              <div class="pm-table-row">
                <div style="width:58px;font-size:8px;font-weight:600;color:var(--text2);"><?= $row[0] ?></div>
                <div style="width:70px;font-size:8px;color:var(--text3);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= $row[1] ?></div>
                <div style="flex:1;"></div>
                <div style="width:36px;font-size:8px;color:var(--text3);"><?= $row[2] ?></div>
                <div style="width:36px;font-size:8px;color:var(--text3);"><?= $row[3] ?></div>
                <div class="pm-pill"><span style="color:var(--green);font-size:8px;font-weight:700;"><?= $row[4] ?></span></div>
                <div style="display:flex;gap:3px;">
                  <div style="width:14px;height:14px;border-radius:3px;background:var(--bg);"></div>
                  <div style="width:14px;height:14px;border-radius:3px;background:var(--bg);"></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Quick log panel -->
          <div class="pm-quicklog-card">
            <div class="pm-ql-header">
              <div>
                <div style="font-size:9.5px;font-weight:700;color:var(--text);margin-bottom:2px;">Today's Status</div>
                <div style="font-size:7.5px;color:var(--text3);">SAT, APR 4, 2026</div>
              </div>
              <div class="pm-ql-badge">PENDING</div>
            </div>
            <div class="pm-ql-textarea"></div>
            <div class="pm-ql-times">
              <div class="pm-ql-time-group">
                <div style="font-size:7px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:3px;">FROM</div>
                <div class="pm-ql-time-input">08:00 am</div>
              </div>
              <div class="pm-ql-time-group">
                <div style="font-size:7px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:0.06em;margin-bottom:3px;">TO</div>
                <div class="pm-ql-time-input">04:00 pm</div>
              </div>
            </div>
            <div class="pm-ql-save">Save Log</div>
          </div>

        </div><!-- /pm-bottom-row -->

      </div><!-- /preview-main -->
    </div><!-- /preview-body -->
  </div><!-- /preview-wrap -->
</section>

<!-- ══════════════════════════════════════════
     FEATURES
══════════════════════════════════════════ -->
<section class="features-section" id="features">
  <div class="section-eyebrow">Features</div>
  <h2 class="section-title">Everything you need for OJT</h2>
  <p class="section-sub">All the tools to track, manage, and complete your internship hours in one place.</p>

  <div class="features-grid">

    <div class="feature-card">
      <div class="feature-icon">
        <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
      </div>
      <div class="feature-title">Easy Hour Logging</div>
      <div class="feature-desc">Log your OJT hours in seconds. Set your time in, time out, and description. Hours are calculated automatically.</div>
    </div>

    <div class="feature-card">
      <div class="feature-icon">
        <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5C3.9 3 3 3.9 3 5v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/></svg>
      </div>
      <div class="feature-title">Calendar View</div>
      <div class="feature-desc">Visualize your logged days on a calendar. See which days you've worked at a glance and spot any gaps.</div>
    </div>

    <div class="feature-card">
      <div class="feature-icon">
        <svg viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
      </div>
      <div class="feature-title">Bulk Log Entry</div>
      <div class="feature-desc">Log multiple days at once using a date range. Perfect for catching up on missed entries with custom day exclusions.</div>
    </div>

    <div class="feature-card">
      <div class="feature-icon">
        <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
      </div>
      <div class="feature-title">Allowance Tracker</div>
      <div class="feature-desc">Set your daily allowance and automatically see how much you've earned and your projected total earnings.</div>
    </div>

    <div class="feature-card">
      <div class="feature-icon">
        <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 4l5 2.18V11c0 3.5-2.33 6.79-5 7.93-2.67-1.14-5-4.43-5-7.93V7.18L12 5z"/></svg>
      </div>
      <div class="feature-title">Secure &amp; Private</div>
      <div class="feature-desc">Your data is protected with secure password hashing and security question recovery. Your hours are yours alone.</div>
    </div>

  </div>
</section>

<!-- ══════════════════════════════════════════
     HOW IT WORKS
══════════════════════════════════════════ -->
<section class="how-section" id="how">
  <div class="how-inner">
    <div class="section-eyebrow">How it works</div>
    <h2 class="section-title">Up and running in minutes</h2>
    <p class="section-sub">Three simple steps to start tracking your OJT hours today.</p>

    <div class="how-steps">
      <div class="how-step">
        <div class="how-step-num">1</div>
        <div class="how-step-title">Create your account</div>
        <div class="how-step-desc">Sign up in under a minute. Set your name, username, required OJT hours, and you're ready to go.</div>
      </div>
      <div class="how-step">
        <div class="how-step-num">2</div>
        <div class="how-step-title">Log your hours</div>
        <div class="how-step-desc">After each OJT day, log your time in and time out. Use bulk log to catch up on multiple days at once.</div>
      </div>
      <div class="how-step">
        <div class="how-step-num">3</div>
        <div class="how-step-title">Track your progress</div>
        <div class="how-step-desc">Watch your progress bar grow. See your estimated completion date and how much allowance you've earned.</div>
      </div>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════
     CTA
══════════════════════════════════════════ -->
<section class="cta-section">
  <div class="cta-box">
    <h2 class="cta-title">Start tracking your OJT today</h2>
    <p class="cta-sub">Free to use. No setup needed. Just create an account and start logging your hours right away.</p>
    <div class="cta-btns">
      <button type="button" class="btn-cta-white" data-open-auth="register">Create free account</button>
      <button type="button" class="btn-cta-outline" data-open-auth="login">Already have an account</button>
    </div>
  </div>
</section>

<!-- ══════════════════════════════════════════
     FOOTER
══════════════════════════════════════════ -->
<footer>
  <div class="footer-grid">
    <div class="footer-item footer-item--left">Copyright <?= date('Y') ?>, <?= e(APP_NAME) ?></div>
    <div class="footer-item footer-item--center"><strong>Joaquin Miguel Dumas</strong>, Computer Engineer - Web / UI / Product</div>
    <div class="footer-item footer-item--right">Made with &hearts; in the Philippines</div>
  </div>
</footer>

<script>
  (function () {
    const modal = document.getElementById('auth-modal');
    if (!modal) return;

    const openButtons = document.querySelectorAll('[data-open-auth]');
    const closeButtons = document.querySelectorAll('[data-close-auth]');
    const tabs = document.querySelectorAll('[data-auth-tab]');
    const panels = document.querySelectorAll('[data-auth-panel]');
    const initialMode = modal.getAttribute('data-initial-mode') || 'login';

    function setAuthTab(mode) {
      tabs.forEach(tab => {
        tab.classList.toggle('active', tab.getAttribute('data-auth-tab') === mode);
      });
      panels.forEach(panel => {
        const active = panel.getAttribute('data-auth-panel') === mode;
        panel.classList.toggle('active', active);
        panel.setAttribute('aria-hidden', active ? 'false' : 'true');
      });
    }

    function openAuth(mode) {
      setAuthTab(mode || initialMode);
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
    }

    function closeAuth() {
      modal.classList.remove('open');
      modal.setAttribute('aria-hidden', 'true');
      if (window.history && window.history.replaceState) {
        window.history.replaceState({}, document.title, 'landing.php');
      }
    }

    openButtons.forEach(btn => {
      btn.addEventListener('click', function () {
        openAuth(this.getAttribute('data-open-auth'));
      });
    });

    tabs.forEach(tab => {
      tab.addEventListener('click', function () {
        setAuthTab(this.getAttribute('data-auth-tab'));
      });
    });

    document.querySelectorAll('[data-reset-step="register"]').forEach(btn => {
      btn.addEventListener('click', function () {
        window.location.href = 'landing.php?auth=register#auth-modal';
      });
    });

    closeButtons.forEach(btn => {
      btn.addEventListener('click', closeAuth);
    });

    modal.addEventListener('click', function (e) {
      if (e.target.classList.contains('auth-modal-backdrop')) closeAuth();
    });

    document.querySelectorAll('[data-toggle-pw]').forEach(btn => {
      btn.addEventListener('click', function () {
        const input = document.getElementById(this.getAttribute('data-toggle-pw'));
        if (!input) return;
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        this.textContent = isPassword ? 'Hide' : 'Show';
      });
    });

    setAuthTab(initialMode);
    if (modal.classList.contains('open') || window.location.hash === '#auth-modal') {
      modal.classList.add('open');
      modal.setAttribute('aria-hidden', 'false');
    }
  })();
</script>

</body>
</html>