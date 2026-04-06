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
        $notice = 'Password reset successfully. You can sign in now.';
        $mode = 'login';
        $fp_step = 1;
      }
    }
  }
}

$register_render_step = ($mode === 'register') ? $reg_step : 1;
$forgot_render_step = ($mode === 'forgot') ? $fp_step : 1;
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
<body>

<?php if (!empty($errors)): ?>
  <div class="auth-error-popup" id="error-popup"><?= e($errors[0]) ?></div>
<?php endif; ?>

<div class="auth-wrap">
  <div class="auth-card">

    <div class="auth-brand">
      <div class="auth-brand-icon">
        <svg viewBox="0 0 24 24">
          <path d="M19 3h-1V1h-2v2H8V1H6v2H5C3.9 3 3 3.9 3 5v14c0 1.1.9 2 2 2h7.35c-.22-.62-.35-1.29-.35-2 0-3.31 2.69-6 6-6 .34 0 .67.03 1 .08V9H3V7h16v2.35c.72.22 1.39.57 2 1V5c0-1.1-.9-2-2-2z"/>
          <path d="M19 15c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm1 4.5h-1.5V21h-1v-3H19v1.5h1v1z"/>
        </svg>
      </div>
      <div class="auth-brand-name"><?= e(APP_NAME) ?></div>
    </div>

    <div class="auth-tabs">
      <button type="button" class="auth-tab <?= $mode === 'login' ? 'active' : '' ?>" data-mode="login">Sign in</button>
      <button type="button" class="auth-tab <?= $mode === 'register' ? 'active' : '' ?>" data-mode="register">Create account</button>
      <button type="button" class="auth-tab <?= $mode === 'forgot' ? 'active' : '' ?>" data-mode="forgot">Forgot password</button>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="auth-alert auth-alert--error"><?= e($errors[0]) ?></div>
    <?php endif; ?>
    <?php if ($notice): ?>
      <div class="auth-alert auth-alert--success"><?= e($notice) ?></div>
    <?php endif; ?>

    <div class="auth-panel <?= $mode === 'login' ? 'active' : '' ?>" data-panel="login">
      <h3 class="auth-title">Sign in</h3>
      <p class="auth-sub">Track your OJT hours and monitor your progress.</p>

      <form method="POST" action="auth.php" class="auth-form">
        <input type="hidden" name="action" value="login" />
        <div class="auth-field">
          <input class="auth-input" type="text" name="username" placeholder="Username" value="<?= e($_POST['username'] ?? '') ?>" required />
        </div>
        <div class="auth-field">
          <input class="auth-input auth-input--pw" type="password" id="login-pw" name="password" placeholder="Password" required />
          <button type="button" class="eye-toggle" data-toggle-pw="login-pw">Show</button>
        </div>
        <button type="submit" class="auth-btn">Sign in</button>
      </form>
    </div>

    <div class="auth-panel <?= $mode === 'register' ? 'active' : '' ?>" data-panel="register">
      <h3 class="auth-title">Create account</h3>
      <p class="auth-sub">Set up your account and start tracking today.</p>

      <?php if ($register_render_step === 1): ?>
        <form method="POST" action="auth.php" class="auth-form">
          <input type="hidden" name="action" value="register" />
          <input type="hidden" name="reg_step" value="1" />
          <div class="auth-field">
            <input class="auth-input" type="text" name="name" placeholder="Full Name" value="<?= e($reg_data['name']) ?>" required />
          </div>
          <div class="auth-field">
            <input class="auth-input" type="text" name="username" placeholder="Username" value="<?= e($reg_data['username']) ?>" required />
          </div>
          <div class="auth-field">
            <input class="auth-input" type="number" name="required_hours" placeholder="Required OJT Hours (e.g. 500)" value="<?= e($reg_data['required_hours']) ?>" min="1" required />
          </div>
          <div class="auth-group">
            <select name="currency" class="auth-input">
              <option value="PHP" <?= $reg_data['currency'] === 'PHP' ? 'selected' : '' ?>>₱ PHP</option>
              <option value="USD" <?= $reg_data['currency'] === 'USD' ? 'selected' : '' ?>>$ USD</option>
              <option value="EUR" <?= $reg_data['currency'] === 'EUR' ? 'selected' : '' ?>>€ EUR</option>
              <option value="GBP" <?= $reg_data['currency'] === 'GBP' ? 'selected' : '' ?>>£ GBP</option>
            </select>
            <input class="auth-input" type="number" name="allowance_per_day" placeholder="Daily Allowance (Optional)" value="<?= e($reg_data['allowance_per_day']) ?>" min="0" />
          </div>
          <div class="auth-field">
            <input class="auth-input auth-input--pw" type="password" id="reg-pw" name="password" placeholder="Password" required />
            <button type="button" class="eye-toggle" data-toggle-pw="reg-pw">Show</button>
          </div>
          <button type="submit" class="auth-btn">Continue</button>
        </form>
      <?php elseif ($register_render_step === 2): ?>
        <div class="step-indicator">
          <div class="step-dot done"></div>
          <div class="step-dot active"></div>
        </div>
        <h3 class="auth-title">Security Question</h3>
        <p class="auth-sub">Set up a security question for password recovery.</p>

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
            <select class="auth-input" name="security_question" required>
              <option value="">Select a question</option>
              <option value="What is your mother's maiden name?" <?= $_POST['security_question'] === "What is your mother's maiden name?" ? 'selected' : '' ?>>What is your mother's maiden name?</option>
              <option value="What was the name of your first pet?" <?= $_POST['security_question'] === "What was the name of your first pet?" ? 'selected' : '' ?>>What was the name of your first pet?</option>
              <option value="What is the name of your favorite teacher?" <?= $_POST['security_question'] === "What is the name of your favorite teacher?" ? 'selected' : '' ?>>What is the name of your favorite teacher?</option>
              <option value="What city were you born in?" <?= $_POST['security_question'] === "What city were you born in?" ? 'selected' : '' ?>>What city were you born in?</option>
              <option value="What is your favorite book?" <?= $_POST['security_question'] === "What is your favorite book?" ? 'selected' : '' ?>>What is your favorite book?</option>
            </select>
          </div>
          <div class="auth-field">
            <input class="auth-input" type="text" name="security_answer" placeholder="Your answer" value="<?= e($_POST['security_answer'] ?? '') ?>" required />
          </div>
          <button type="submit" class="auth-btn">Create Account</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="auth-panel <?= $mode === 'forgot' ? 'active' : '' ?>" data-panel="forgot">
      <h3 class="auth-title">Reset Password</h3>
      <p class="auth-sub">Enter your username and answer your security question.</p>

      <?php if ($forgot_render_step === 1): ?>
        <form method="POST" action="auth.php" class="auth-form">
          <input type="hidden" name="action" value="forgot" />
          <input type="hidden" name="fp_step" value="1" />
          <div class="auth-field">
            <input class="auth-input" type="text" name="username" placeholder="Username" value="<?= e($fp_username) ?>" required />
          </div>
          <button type="submit" class="auth-btn">Continue</button>
        </form>
      <?php elseif ($forgot_render_step === 2): ?>
        <div class="step-indicator">
          <div class="step-dot done"></div>
          <div class="step-dot active"></div>
        </div>
        <h3 class="auth-title">Security Question</h3>
        <p class="auth-sub">Answer your security question to reset your password.</p>

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
        <div class="step-indicator">
          <div class="step-dot done"></div>
          <div class="step-dot done"></div>
          <div class="step-dot active"></div>
        </div>
        <h3 class="auth-title">New Password</h3>
        <p class="auth-sub">Enter a new password for your account.</p>

        <form method="POST" action="auth.php" class="auth-form">
          <input type="hidden" name="action" value="forgot" />
          <input type="hidden" name="fp_step" value="3" />
          <input type="hidden" name="username" value="<?= e($fp_username) ?>" />
          <div class="auth-field">
            <input class="auth-input auth-input--pw" type="password" id="new-pw" name="new_password" placeholder="New password" required />
            <button type="button" class="eye-toggle" data-toggle-pw="new-pw">Show</button>
          </div>
          <div class="auth-field">
            <input class="auth-input auth-input--pw" type="password" id="confirm-pw" name="confirm_password" placeholder="Confirm new password" required />
            <button type="button" class="eye-toggle" data-toggle-pw="confirm-pw">Show</button>
          </div>
          <button type="submit" class="auth-btn">Reset Password</button>
        </form>
      <?php endif; ?>
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
    if (e.target.matches('[data-toggle-pw]')) {
      const id = e.target.dataset.togglePw;
      const input = document.getElementById(id);
      if (input.type === 'password') {
        input.type = 'text';
        e.target.textContent = 'Hide';
      } else {
        input.type = 'password';
        e.target.textContent = 'Show';
      }
    }
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