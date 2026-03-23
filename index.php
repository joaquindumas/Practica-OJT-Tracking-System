<?php
require_once 'includes/config.php';
require_guest();

$mode     = $_GET['mode'] ?? 'login';
$reg_step = (int) ($_POST['reg_step'] ?? 1);
$errors   = [];

$reg_data = [
    'name'           => $_POST['name']           ?? '',
    'username'       => $_POST['username']        ?? '',
    'required_hours' => $_POST['required_hours']  ?? '',
    'password'       => $_POST['password']        ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = $_POST['mode'] ?? 'login';

    if ($mode === 'login') {
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

    } elseif ($mode === 'register') {
        if ($reg_step === 1) {
            $name     = trim($_POST['name']     ?? '');
            $username = strtolower(trim($_POST['username'] ?? ''));
            $req_hrs  = (float) ($_POST['required_hours'] ?? 500);
            $password = $_POST['password'] ?? '';

            if (!$name || !$username || !$password) {
                $errors[] = 'Please fill in all fields.';
                $reg_step = 1;
            } elseif (strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
                $reg_step = 1;
            } elseif ($req_hrs < 1) {
                $errors[] = 'Enter a valid number of required hours.';
                $reg_step = 1;
            } elseif (get_user($username)) {
                $errors[] = 'Username already taken.';
                $reg_step = 1;
            } else {
                $reg_step = 2;
            }

        } elseif ($reg_step === 2) {
            $name         = trim($_POST['name']             ?? '');
            $username     = strtolower(trim($_POST['username'] ?? ''));
            $req_hrs      = (float) ($_POST['required_hours'] ?? 500);
            $password     = $_POST['password']              ?? '';
            $sec_question = trim($_POST['security_question'] ?? '');
            $sec_answer   = trim($_POST['security_answer']   ?? '');

            if (!$sec_question || !$sec_answer) {
                $errors[] = 'Please select a security question and provide an answer.';
                $reg_step = 2;
            } else {
                save_user([
                    'name'              => $name,
                    'username'          => $username,
                    'password'          => hash_password($password),
                    'required_hours'    => $req_hrs,
                    'security_question' => $sec_question,
                    'security_answer'   => strtolower(trim($sec_answer)),
                ]);
                $_SESSION['username'] = $username;
                set_flash('success', 'Account created! Welcome to ' . APP_NAME);
                header('Location: dashboard.php');
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e(APP_NAME) ?> — <?= $mode === 'register' ? 'Create Account' : 'Log In' ?></title>
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

    <div class="auth-icon-wrap">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M19 3h-1V1h-2v2H8V1H6v2H5C3.9 3 3 3.9 3 5v14c0 1.1.9 2 2 2h7.35c-.22-.62-.35-1.29-.35-2 0-3.31 2.69-6 6-6 .34 0 .67.03 1 .08V9H3V7h16v2.35c.72.22 1.39.57 2 1V5c0-1.1-.9-2-2-2z"/>
        <path d="M19 15c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm1 4.5h-1.5V21h-1v-3H19v1.5h1v1z"/>
      </svg>
    </div>

    <?php if ($mode === 'login'): ?>
      <div class="auth-welcome">Sign into <?= e(APP_NAME) ?></div>
      <div class="auth-sub">Track your OJT hours and monitor your progress.</div>

      <form method="POST" action="index.php">
        <input type="hidden" name="mode" value="login" />
        <div class="auth-field">
          <div class="auth-field-icon">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
          </div>
          <input class="auth-input" type="text" name="username"
                 placeholder="Username" required
                 value="<?= e($_POST['username'] ?? '') ?>" />
        </div>

        <div class="auth-field">
          <div class="auth-field-icon">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1s3.1 1.39 3.1 3.1v2z"/></svg>
          </div>
          <input class="auth-input auth-input--pw" type="password" id="login-pw" name="password"
                 placeholder="Password" required />
          <button type="button" class="eye-toggle" onclick="togglePw('login-pw', this)">
            <svg class="eye-show" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
            <svg class="eye-hide" viewBox="0 0 24 24" fill="currentColor" style="display:none;"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46A11.804 11.804 0 0 0 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>
          </button>
        </div>

        <div class="auth-forgot">
          <a href="forgot_password.php">Forgot password?</a>
        </div>

        <button type="submit" class="auth-btn">Sign in ›</button>
      </form>

      <div class="auth-toggle">
        No account yet? <a href="index.php?mode=register">Create one</a>
      </div>

    <?php elseif ($mode === 'register' && $reg_step === 1): ?>
      <div class="step-indicator">
        <div class="step-dot active"></div>
        <div class="step-dot"></div>
      </div>

      <div class="auth-welcome">Create account</div>
      <div class="auth-sub">Step 1 of 2 — Your basic info.</div>

      <form method="POST" action="index.php">
        <input type="hidden" name="mode"     value="register" />
        <input type="hidden" name="reg_step" value="1" />

        <div class="auth-field">
          <div class="auth-field-icon">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
          </div>
          <input class="auth-input" type="text" name="name"
                 placeholder="Full name" required
                 value="<?= e($reg_data['name']) ?>" />
        </div>

        <div class="auth-field">
          <div class="auth-field-icon">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
          </div>
          <input class="auth-input" type="text" name="username"
                 placeholder="Username" required
                 value="<?= e($reg_data['username']) ?>" />
        </div>

        <div class="auth-field">
          <div class="auth-field-icon">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
          </div>
          <input class="auth-input" type="number" name="required_hours"
                 placeholder="Required OJT hours (e.g. 500)" min="1" step="0.5"
                 value="<?= e($reg_data['required_hours']) ?>" required />
        </div>

        <div class="auth-field">
          <div class="auth-field-icon">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1s3.1 1.39 3.1 3.1v2z"/></svg>
          </div>
          <input class="auth-input auth-input--pw" type="password" id="reg-pw" name="password"
                 placeholder="Password (min. 6 characters)" required />
          <button type="button" class="eye-toggle" onclick="togglePw('reg-pw', this)">
            <svg class="eye-show" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
            <svg class="eye-hide" viewBox="0 0 24 24" fill="currentColor" style="display:none;"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46A11.804 11.804 0 0 0 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>
          </button>
        </div>

        <button type="submit" class="auth-btn">Next ›</button>
      </form>

      <div class="auth-toggle">
        Already have an account? <a href="index.php?mode=login">Sign in</a>
      </div>

    <?php elseif ($mode === 'register' && $reg_step === 2): ?>
      <div class="step-indicator">
        <div class="step-dot done"></div>
        <div class="step-dot active"></div>
      </div>

      <div class="auth-welcome">Security Setup</div>
      <div class="auth-sub">Step 2 of 2 — This helps you recover your password.</div>

      <form method="POST" action="index.php">
        <input type="hidden" name="mode"           value="register" />
        <input type="hidden" name="reg_step"       value="2" />
        <input type="hidden" name="name"           value="<?= e($reg_data['name']) ?>" />
        <input type="hidden" name="username"       value="<?= e($reg_data['username']) ?>" />
        <input type="hidden" name="required_hours" value="<?= e($reg_data['required_hours']) ?>" />
        <input type="hidden" name="password"       value="<?= e($reg_data['password']) ?>" />

        <div class="auth-group">
          <label class="auth-label">Security Question</label>
          <select class="auth-input" name="security_question" required>
            <option value="">— Select a question —</option>
            <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
            <option value="What was the name of your first pet?">What was the name of your first pet?</option>
            <option value="What city were you born in?">What city were you born in?</option>
            <option value="What is your childhood nickname?">What is your childhood nickname?</option>
            <option value="What is the name of your elementary school?">What is the name of your elementary school?</option>
          </select>
        </div>

        <div class="auth-field">
          <div class="auth-field-icon">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/></svg>
          </div>
          <input class="auth-input" type="text" name="security_answer"
                 placeholder="Your answer" required />
        </div>

        <button type="submit" class="auth-btn">Create account ›</button>
        <a href="index.php?mode=register">
          <button type="button" class="btn-back">← Back</button>
        </a>
      </form>

    <?php endif; ?>
  </div>
</div>

<script>
  function togglePw(fieldId, btn) {
    const input = document.getElementById(fieldId);
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    btn.querySelector('.eye-show').style.display = isPassword ? 'none' : '';
    btn.querySelector('.eye-hide').style.display = isPassword ? '' : 'none';
  }
  const popup = document.getElementById('error-popup');
  if (popup) {
    setTimeout(() => popup.classList.add('hide'), 3000);
    setTimeout(() => popup.remove(), 3400);
  }
</script>
</body>
</html>