<?php
require_once 'includes/config.php';
require_guest();

$mode = $_GET['mode'] ?? 'login';
if (!in_array($mode, ['login', 'register', 'forgot'], true)) {
  $mode = 'login';
}

$errors = [];
$notice = '';
$reg_step = (int) ($_POST['reg_step'] ?? 1);
$reg_data = [
  'name'             => $_POST['name'] ?? '',
  'username'         => $_POST['username'] ?? '',
  'required_hours'   => $_POST['required_hours'] ?? '',
  'allowance_per_day'=> $_POST['allowance_per_day'] ?? '',
  'currency'         => $_POST['currency'] ?? 'PHP',
  'password'         => $_POST['password'] ?? '',
];

$fp_step = (int) ($_POST['fp_step'] ?? 1);
$fp_username = strtolower(trim($_POST['fp_username'] ?? ''));
$fp_question = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? 'login';

  if ($action === 'login') {
    $mode = 'login';
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
      $errors[] = 'Please fill in all fields.';
    } else {
      $user = get_user($username);
      if (!$user) {
        $errors[] = 'Username not found.';
      } elseif (!verify_password($password, $user['password'])) {
        $errors[] = 'Incorrect password.';
      } else {
        $_SESSION['username'] = $username;
        header('Location: dashboard.php');
        exit;
      }
    }
  } elseif ($action === 'register') {
    $mode = 'register';

    if ($reg_step === 1) {
      $name = trim($_POST['name'] ?? '');
      $username = strtolower(trim($_POST['username'] ?? ''));
      $req_hrs = (float) ($_POST['required_hours'] ?? DEFAULT_REQUIRED_HOURS);
      $allowance = trim($_POST['allowance_per_day'] ?? '');
      $allowance = $allowance === '' ? 0 : (int) round((float) $allowance);
      $password = $_POST['password'] ?? '';

      if (!$name || !$username || !$password) {
        $errors[] = 'Please fill in all fields.';
      } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
      } elseif ($req_hrs < 1) {
        $errors[] = 'Enter a valid number of required hours.';
      } elseif ($allowance < 0) {
        $errors[] = 'Allowance cannot be negative.';
      } elseif (get_user($username)) {
        $errors[] = 'Username already taken.';
      } else {
        $reg_step = 2;
        $reg_data['name'] = $name;
        $reg_data['username'] = $username;
        $reg_data['required_hours'] = $req_hrs;
        $reg_data['allowance_per_day'] = $allowance;
        $reg_data['password'] = $password;
      }
    } elseif ($reg_step === 2) {
      $sec_question = trim($_POST['security_question'] ?? '');
      $sec_answer = trim($_POST['security_answer'] ?? '');

      if (!$sec_question || !$sec_answer) {
        $errors[] = 'Please select a security question and provide an answer.';
        $reg_step = 2;
      } else {
        save_user([
          'name' => $reg_data['name'],
          'username' => $reg_data['username'],
          'password' => hash_password($reg_data['password']),
          'required_hours' => $reg_data['required_hours'],
          'allowance_per_day' => $reg_data['allowance_per_day'],
          'currency' => $reg_data['currency'],
          'security_question' => $sec_question,
          'security_answer' => hash_password(strtolower(trim($sec_answer))),
        ]);
        $_SESSION['username'] = $reg_data['username'];
        header('Location: dashboard.php');
        exit;
      }
    }
  } elseif ($action === 'forgot') {
    $mode = 'forgot';

    if ($fp_step === 1) {
      $username = strtolower(trim($_POST['username'] ?? ''));
      if (!$username) {
        $errors[] = 'Please enter your username.';
        $fp_step = 1;
      } else {
        $user = get_user($username);
        if (!$user) {
          $errors[] = 'Username not found.';
          $fp_step = 1;
        } elseif (!isset($user['security_question'])) {
          $errors[] = 'This account has no security question set. Please update it in Settings.';
          $fp_step = 1;
        } else {
          $fp_question = $user['security_question'];
          $fp_step = 2;
          $fp_username = $username;
        }
      }
    } elseif ($fp_step === 2) {
      $username = strtolower(trim($_POST['username'] ?? ''));
      $answer = trim($_POST['security_answer'] ?? '');
      if (!$answer) {
        $errors[] = 'Please enter your answer.';
        $fp_question = get_security_question($username);
        $fp_step = 2;
      } elseif (!verify_security_answer($username, $answer)) {
        $errors[] = 'Incorrect answer. Please try again.';
        $fp_question = get_security_question($username);
        $fp_step = 2;
      } else {
        $fp_step = 3;
        $fp_username = $username;
      }
    } elseif ($fp_step === 3) {
      $username = strtolower(trim($_POST['username'] ?? ''));
      $new_pw = $_POST['new_password'] ?? '';
      $confirm = $_POST['confirm_password'] ?? '';

      if (strlen($new_pw) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
        $fp_step = 3;
      } elseif ($new_pw !== $confirm) {
        $errors[] = 'Passwords do not match.';
        $fp_step = 3;
      } elseif (verify_password($new_pw, get_user($username)['password'])) {
        $errors[] = 'New password cannot be the same as your current password.';
        $fp_step = 3;
      } else {
        $user = get_user($username);
        $user['password'] = hash_password($new_pw);
        save_user($user);
        $notice = 'Password reset successfully. You can login now.';
        $mode = 'login';
        $fp_step = 1;
      }
    }
  }
}

$register_render_step = ($mode === 'register') ? $reg_step : 1;
$forgot_render_step = ($mode === 'forgot') ? $fp_step : 1;

$hero_by_mode = [
  'login' => [
    'eyebrow' => 'Welcome back',
    'title' => 'Log in to continue building.',
    'subtitle' => 'Access your OJT hours, progress, and allowance from one account.',
  ],
  'register' => [
    'eyebrow' => 'New to ' . APP_NAME,
    'title' => 'Create your account.',
    'subtitle' => 'Set up your profile and start tracking your OJT progress.',
  ],
  'forgot' => [
    'eyebrow' => 'Account recovery',
    'title' => 'Reset your password.',
    'subtitle' => 'Verify your account and set a new password.',
  ],
];

$hero = $hero_by_mode[$mode];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e(APP_NAME) ?> — Authentication</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="css/main.css" />
  <link rel="stylesheet" href="css/auth.css" />
</head>
<body class="auth-page">

<?php if (!empty($errors)): ?>
  <div class="auth-error-popup" id="error-popup"><?= e($errors[0]) ?></div>
<?php endif; ?>

<div class="auth-wrap">
  <div class="auth-shell">
    <div class="auth-hero">
      <div class="auth-brand">
        <div class="auth-brand-icon">
          <svg viewBox="0 0 24 24">
            <path d="M19 3h-1V1h-2v2H8V1H6v2H5C3.9 3 3 3.9 3 5v14c0 1.1.9 2 2 2h7.35c-.22-.62-.35-1.29-.35-2 0-3.31 2.69-6 6-6 .34 0 .67.03 1 .08V9H3V7h16v2.35c.72.22 1.39.57 2 1V5c0-1.1-.9-2-2-2z"/>
            <path d="M19 15c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm1 4.5h-1.5V21h-1v-3H19v1.5h1v1z"/>
          </svg>
        </div>
        <div class="auth-brand-name"><?= e(APP_NAME) ?></div>
      </div>

      <div class="auth-hero-badge"><?= e($hero['eyebrow']) ?></div>
      <h1 class="auth-hero-title"><?= e($hero['title']) ?></h1>
      <p class="auth-hero-subtitle"><?= e($hero['subtitle']) ?></p>
    </div>

    <div class="auth-card">
      <?php if (!empty($errors)): ?>
        <div class="auth-alert auth-alert--error"><?= e($errors[0]) ?></div>
      <?php endif; ?>
      <?php if ($notice): ?>
        <div class="auth-alert auth-alert--success"><?= e($notice) ?></div>
      <?php endif; ?>

      <div class="auth-panel <?= $mode === 'login' ? 'active' : '' ?>" data-panel="login">
        <form method="POST" action="auth.php" class="auth-form">
          <input type="hidden" name="action" value="login" />
          <div class="auth-field">
            <label class="auth-label" for="login-username">Username</label>
            <input class="auth-input" id="login-username" type="text" name="username" placeholder="Enter your username" value="<?= e($_POST['username'] ?? '') ?>" required />
          </div>
          <div class="auth-field">
            <div class="auth-field-head auth-field-head--split">
              <label class="auth-label" for="login-pw">Password</label>
              <a class="auth-link" href="auth.php?mode=forgot">Forgot password?</a>
            </div>
            <input class="auth-input auth-input--pw" type="password" id="login-pw" name="password" placeholder="Enter your password" required />
            <button type="button" class="eye-toggle" data-toggle-pw="login-pw" aria-label="Show password" aria-pressed="false">
              <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 5c5.25 0 9.27 3.33 10.74 7-1.47 3.67-5.49 7-10.74 7S2.73 15.67 1.26 12C2.73 8.33 6.75 5 12 5zm0 2c-3.94 0-7.09 2.33-8.51 5 1.42 2.67 4.57 5 8.51 5s7.09-2.33 8.51-5c-1.42-2.67-4.57-5-8.51-5zm0 2.5A2.5 2.5 0 1 1 9.5 12 2.5 2.5 0 0 1 12 9.5z"/>
              </svg>
              <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                <path d="m3.28 2.22 18.5 18.5-1.06 1.06-3.07-3.07A12.15 12.15 0 0 1 12 20c-5.25 0-9.27-3.33-10.74-7A12.65 12.65 0 0 1 6.1 7.56L2.22 3.28l1.06-1.06zM7.6 9.06A9.9 9.9 0 0 0 3.49 13c1.42 2.67 4.57 5 8.51 5 1.49 0 2.9-.34 4.14-.9l-2.07-2.07A3.5 3.5 0 0 1 9 9.93zM12 6c5.25 0 9.27 3.33 10.74 7a12.48 12.48 0 0 1-3.64 4.8l-1.45-1.45A9.84 9.84 0 0 0 20.51 13c-1.42-2.67-4.57-5-8.51-5-1 0-1.95.15-2.84.43L7.58 6.85C8.93 6.3 10.42 6 12 6z"/>
              </svg>
            </button>
          </div>
          <button type="submit" class="auth-btn">Login</button>
        </form>
      </div>

      <div class="auth-panel <?= $mode === 'register' ? 'active' : '' ?>" data-panel="register">
        <?php if ($register_render_step === 1): ?>
          <form method="POST" action="auth.php" class="auth-form">
            <input type="hidden" name="action" value="register" />
            <input type="hidden" name="reg_step" value="1" />
            <div class="auth-grid">
              <div class="auth-field">
                <label class="auth-label" for="register-name">Full name</label>
                <input class="auth-input" id="register-name" type="text" name="name" placeholder="John Doe" value="<?= e($reg_data['name']) ?>" required />
              </div>
              <div class="auth-field">
                <label class="auth-label" for="register-username">Username</label>
                <input class="auth-input" id="register-username" type="text" name="username" placeholder="johndoe" value="<?= e($reg_data['username']) ?>" required />
              </div>
            </div>
            <div class="auth-field">
              <label class="auth-label" for="required-hours">Required OJT hours</label>
              <input class="auth-input" id="required-hours" type="number" name="required_hours" placeholder="500" value="<?= e($reg_data['required_hours']) ?>" min="1" required />
            </div>
            <div class="auth-grid auth-grid--compact">
              <div class="auth-field">
                <label class="auth-label" for="register-currency">Currency</label>
                <select name="currency" id="register-currency" class="auth-input">
                  <option value="PHP" <?= $reg_data['currency'] === 'PHP' ? 'selected' : '' ?>>₱ PHP</option>
                  <option value="USD" <?= $reg_data['currency'] === 'USD' ? 'selected' : '' ?>>$ USD</option>
                  <option value="EUR" <?= $reg_data['currency'] === 'EUR' ? 'selected' : '' ?>>€ EUR</option>
                  <option value="GBP" <?= $reg_data['currency'] === 'GBP' ? 'selected' : '' ?>>£ GBP</option>
                </select>
              </div>
              <div class="auth-field">
                <label class="auth-label" for="register-allowance">Daily allowance</label>
                <input class="auth-input" id="register-allowance" type="number" name="allowance_per_day" placeholder="Optional" value="<?= e($reg_data['allowance_per_day']) ?>" min="0" />
              </div>
            </div>
            <div class="auth-field">
              <label class="auth-label" for="reg-pw">Password</label>
              <input class="auth-input auth-input--pw" type="password" id="reg-pw" name="password" placeholder="Create a password" required />
              <button type="button" class="eye-toggle" data-toggle-pw="reg-pw" aria-label="Show password" aria-pressed="false">
                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M12 5c5.25 0 9.27 3.33 10.74 7-1.47 3.67-5.49 7-10.74 7S2.73 15.67 1.26 12C2.73 8.33 6.75 5 12 5zm0 2c-3.94 0-7.09 2.33-8.51 5 1.42 2.67 4.57 5 8.51 5s7.09-2.33 8.51-5c-1.42-2.67-4.57-5-8.51-5zm0 2.5A2.5 2.5 0 1 1 9.5 12 2.5 2.5 0 0 1 12 9.5z"/>
                </svg>
                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="m3.28 2.22 18.5 18.5-1.06 1.06-3.07-3.07A12.15 12.15 0 0 1 12 20c-5.25 0-9.27-3.33-10.74-7A12.65 12.65 0 0 1 6.1 7.56L2.22 3.28l1.06-1.06zM7.6 9.06A9.9 9.9 0 0 0 3.49 13c1.42 2.67 4.57 5 8.51 5 1.49 0 2.9-.34 4.14-.9l-2.07-2.07A3.5 3.5 0 0 1 9 9.93zM12 6c5.25 0 9.27 3.33 10.74 7a12.48 12.48 0 0 1-3.64 4.8l-1.45-1.45A9.84 9.84 0 0 0 20.51 13c-1.42-2.67-4.57-5-8.51-5-1 0-1.95.15-2.84.43L7.58 6.85C8.93 6.3 10.42 6 12 6z"/>
                </svg>
              </button>
            </div>
            <button type="submit" class="auth-btn">Continue</button>
          </form>
        <?php elseif ($register_render_step === 2): ?>
          <form method="POST" action="auth.php" class="auth-form">
            <input type="hidden" name="action" value="register" />
            <input type="hidden" name="reg_step" value="2" />
            <input type="hidden" name="name" value="<?= e($reg_data['name']) ?>" />
            <input type="hidden" name="username" value="<?= e($reg_data['username']) ?>" />
            <input type="hidden" name="required_hours" value="<?= e($reg_data['required_hours']) ?>" />
            <input type="hidden" name="allowance_per_day" value="<?= e($reg_data['allowance_per_day']) ?>" />
            <input type="hidden" name="currency" value="<?= e($reg_data['currency']) ?>" />
            <input type="hidden" name="password" value="<?= e($reg_data['password']) ?>" />
            <div class="auth-field">
              <label class="auth-label" for="register-question">Security question</label>
              <select class="auth-input" id="register-question" name="security_question" required>
                <option value="">Select a question</option>
                <option value="What is your mother's maiden name?" <?= $_POST['security_question'] === "What is your mother's maiden name?" ? 'selected' : '' ?>>What is your mother's maiden name?</option>
                <option value="What was the name of your first pet?" <?= $_POST['security_question'] === "What was the name of your first pet?" ? 'selected' : '' ?>>What was the name of your first pet?</option>
                <option value="What is the name of your favorite teacher?" <?= $_POST['security_question'] === "What is the name of your favorite teacher?" ? 'selected' : '' ?>>What is the name of your favorite teacher?</option>
                <option value="What city were you born in?" <?= $_POST['security_question'] === "What city were you born in?" ? 'selected' : '' ?>>What city were you born in?</option>
                <option value="What is your favorite book?" <?= $_POST['security_question'] === "What is your favorite book?" ? 'selected' : '' ?>>What is your favorite book?</option>
              </select>
            </div>
            <div class="auth-field">
              <label class="auth-label" for="register-answer">Security answer</label>
              <input class="auth-input" id="register-answer" type="text" name="security_answer" placeholder="Your answer" value="<?= e($_POST['security_answer'] ?? '') ?>" required />
            </div>
            <button type="submit" class="auth-btn">Create Account</button>
          </form>
        <?php endif; ?>
      </div>

      <div class="auth-panel <?= $mode === 'forgot' ? 'active' : '' ?>" data-panel="forgot">
        <?php if ($forgot_render_step === 1): ?>
          <form method="POST" action="auth.php" class="auth-form">
            <input type="hidden" name="action" value="forgot" />
            <input type="hidden" name="fp_step" value="1" />
            <div class="auth-field">
              <label class="auth-label" for="forgot-username">Username</label>
              <input class="auth-input" id="forgot-username" type="text" name="username" placeholder="Enter your username" value="<?= e($fp_username) ?>" required />
            </div>
            <button type="submit" class="auth-btn">Continue</button>
          </form>
        <?php elseif ($forgot_render_step === 2): ?>
          <form method="POST" action="auth.php" class="auth-form">
            <input type="hidden" name="action" value="forgot" />
            <input type="hidden" name="fp_step" value="2" />
            <input type="hidden" name="username" value="<?= e($fp_username) ?>" />
            <div class="auth-field">
              <label class="auth-label"><?= e($fp_question) ?></label>
              <input class="auth-input" type="text" name="security_answer" placeholder="Your answer" required />
            </div>
            <button type="submit" class="auth-btn">Continue</button>
          </form>
        <?php elseif ($forgot_render_step === 3): ?>
          <form method="POST" action="auth.php" class="auth-form">
            <input type="hidden" name="action" value="forgot" />
            <input type="hidden" name="fp_step" value="3" />
            <input type="hidden" name="username" value="<?= e($fp_username) ?>" />
            <div class="auth-field">
              <label class="auth-label" for="new-pw">New password</label>
              <input class="auth-input auth-input--pw" type="password" id="new-pw" name="new_password" placeholder="New password" required />
              <button type="button" class="eye-toggle" data-toggle-pw="new-pw" aria-label="Show password" aria-pressed="false">
                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M12 5c5.25 0 9.27 3.33 10.74 7-1.47 3.67-5.49 7-10.74 7S2.73 15.67 1.26 12C2.73 8.33 6.75 5 12 5zm0 2c-3.94 0-7.09 2.33-8.51 5 1.42 2.67 4.57 5 8.51 5s7.09-2.33 8.51-5c-1.42-2.67-4.57-5-8.51-5zm0 2.5A2.5 2.5 0 1 1 9.5 12 2.5 2.5 0 0 1 12 9.5z"/>
                </svg>
                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="m3.28 2.22 18.5 18.5-1.06 1.06-3.07-3.07A12.15 12.15 0 0 1 12 20c-5.25 0-9.27-3.33-10.74-7A12.65 12.65 0 0 1 6.1 7.56L2.22 3.28l1.06-1.06zM7.6 9.06A9.9 9.9 0 0 0 3.49 13c1.42 2.67 4.57 5 8.51 5 1.49 0 2.9-.34 4.14-.9l-2.07-2.07A3.5 3.5 0 0 1 9 9.93zM12 6c5.25 0 9.27 3.33 10.74 7a12.48 12.48 0 0 1-3.64 4.8l-1.45-1.45A9.84 9.84 0 0 0 20.51 13c-1.42-2.67-4.57-5-8.51-5-1 0-1.95.15-2.84.43L7.58 6.85C8.93 6.3 10.42 6 12 6z"/>
                </svg>
              </button>
            </div>
            <div class="auth-field">
              <label class="auth-label" for="confirm-pw">Confirm new password</label>
              <input class="auth-input auth-input--pw" type="password" id="confirm-pw" name="confirm_password" placeholder="Confirm new password" required />
              <button type="button" class="eye-toggle" data-toggle-pw="confirm-pw" aria-label="Show password" aria-pressed="false">
                <svg class="icon-eye" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="M12 5c5.25 0 9.27 3.33 10.74 7-1.47 3.67-5.49 7-10.74 7S2.73 15.67 1.26 12C2.73 8.33 6.75 5 12 5zm0 2c-3.94 0-7.09 2.33-8.51 5 1.42 2.67 4.57 5 8.51 5s7.09-2.33 8.51-5c-1.42-2.67-4.57-5-8.51-5zm0 2.5A2.5 2.5 0 1 1 9.5 12 2.5 2.5 0 0 1 12 9.5z"/>
                </svg>
                <svg class="icon-eye-off" viewBox="0 0 24 24" aria-hidden="true">
                  <path d="m3.28 2.22 18.5 18.5-1.06 1.06-3.07-3.07A12.15 12.15 0 0 1 12 20c-5.25 0-9.27-3.33-10.74-7A12.65 12.65 0 0 1 6.1 7.56L2.22 3.28l1.06-1.06zM7.6 9.06A9.9 9.9 0 0 0 3.49 13c1.42 2.67 4.57 5 8.51 5 1.49 0 2.9-.34 4.14-.9l-2.07-2.07A3.5 3.5 0 0 1 9 9.93zM12 6c5.25 0 9.27 3.33 10.74 7a12.48 12.48 0 0 1-3.64 4.8l-1.45-1.45A9.84 9.84 0 0 0 20.51 13c-1.42-2.67-4.57-5-8.51-5-1 0-1.95.15-2.84.43L7.58 6.85C8.93 6.3 10.42 6 12 6z"/>
                </svg>
              </button>
            </div>
            <button type="submit" class="auth-btn">Reset Password</button>
          </form>
        <?php endif; ?>
      </div>

      <div class="auth-footer">
        <?php if ($mode === 'login'): ?>
          Don't have an account? <a href="auth.php?mode=register">Create account</a>
        <?php elseif ($mode === 'register'): ?>
          Already have an account? <a href="auth.php?mode=login">Login</a>
        <?php else: ?>
          Remembered it? <a href="auth.php?mode=login">Login</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const tabs = document.querySelectorAll('.auth-tab');
  const panels = document.querySelectorAll('.auth-panel');

  tabs.forEach(tab => {
    tab.addEventListener('click', function() {
      const mode = this.dataset.mode;
      window.location.href = `auth.php?mode=${mode}`;
    });
  });

  // Eye toggle
  document.addEventListener('click', function(e) {
    const btn = e.target.closest('[data-toggle-pw]');
    if (!btn) return;

    const id = btn.dataset.togglePw;
    const input = document.getElementById(id);
    if (!input) return;

    const isVisible = input.type === 'text';
    input.type = isVisible ? 'password' : 'text';
    btn.classList.toggle('is-visible', !isVisible);
    btn.setAttribute('aria-pressed', String(!isVisible));
    btn.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
  });

  // Hide error popup after 5 seconds
  const errorPopup = document.getElementById('error-popup');
  if (errorPopup) {
    setTimeout(() => {
      errorPopup.classList.add('hide');
    }, 5000);
  }
});
</script>
</body>
</html>