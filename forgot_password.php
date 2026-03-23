<?php
require_once 'includes/config.php';
require_guest();

$step = 1;
$errors = [];
$username = '';
$question = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $step = (int) ($_POST['step'] ?? 1);

  if ($step === 1) {
    $username = strtolower(trim($_POST['username'] ?? ''));
    if (!$username) {
      $errors[] = 'Please enter your username.';
      $step = 1;
    } else {
      $user = get_user($username);
      if (!$user) {
        $errors[] = 'Username not found.';
        $step = 1;
      } elseif (!isset($user['security_question'])) {
        $errors[] = 'This account has no security question set. Please update it in Settings.';
        $step = 1;
      } else {
        $question = $user['security_question'];
        $step = 2;
      }
    }

  } elseif ($step === 2) {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $answer = trim($_POST['security_answer'] ?? '');
    if (!$answer) {
      $errors[] = 'Please enter your answer.';
      $question = get_security_question($username);
      $step = 2;
    } elseif (!verify_security_answer($username, $answer)) {
      $errors[] = 'Incorrect answer. Please try again.';
      $question = get_security_question($username);
      $step = 2;
    } else {
      $step = 3;
    }

  } elseif ($step === 3) {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $new_pw = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $user = get_user($username);

    if (strlen($new_pw) < 6) {
      $errors[] = 'Password must be at least 6 characters.';
      $step = 3;
    } elseif ($new_pw !== $confirm) {
      $errors[] = 'Passwords do not match.';
      $step = 3;
    } elseif (verify_password($new_pw, $user['password'])) {
      $errors[] = 'New password cannot be the same as your current password.';
      $step = 3;
    } else {
      $user['password'] = hash_password($new_pw);
      save_user($user);
      set_flash('success', 'Password reset successfully! Please log in.');
      header('Location: index.php');
      exit;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e(APP_NAME) ?> — Reset Password</title>
  <link
    href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="css/main.css" />
  <link rel="stylesheet" href="css/auth.css" />
  <style>
    .auth-wrap {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(160deg, #c9e8f5 0%, #dff0f8 40%, #eaf6fb 70%, #d6eef7 100%);
      position: relative;
      overflow: hidden;
    }

    .auth-wrap::before {
      content: '';
      position: fixed;
      width: 700px;
      height: 700px;
      border-radius: 50%;
      border: 1px solid rgba(255, 255, 255, 0.4);
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      pointer-events: none;
    }

    .auth-wrap::after {
      content: '';
      position: fixed;
      width: 950px;
      height: 950px;
      border-radius: 50%;
      border: 1px solid rgba(255, 255, 255, 0.25);
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      pointer-events: none;
    }

    .auth-card {
      background: rgba(255, 255, 255, 0.75);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-radius: 24px;
      padding: 2.5rem 2rem;
      width: 400px;
      border: 1px solid rgba(255, 255, 255, 0.9);
      box-shadow: 0 8px 32px rgba(100, 160, 200, 0.15);
      position: relative;
      z-index: 1;
      text-align: center;
    }

    .auth-icon-wrap {
      width: 56px;
      height: 56px;
      border-radius: 16px;
      background: rgba(255, 255, 255, 0.9);
      border: 1px solid rgba(0, 0, 0, 0.08);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .auth-icon-wrap svg {
      width: 26px;
      height: 26px;
    }

    .auth-welcome {
      font-size: 22px;
      font-weight: 700;
      color: #1a1a1a;
      margin-bottom: 0.35rem;
      text-align: center;
    }

    .auth-sub {
      font-size: 13px;
      color: #7a7a7a;
      margin-bottom: 1.75rem;
      text-align: center;
      line-height: 1.5;
    }

    .auth-field {
      position: relative;
      margin-bottom: 0.75rem;
      text-align: left;
    }

    .auth-field-icon {
      position: absolute;
      left: 13px;
      top: 50%;
      transform: translateY(-50%);
      color: #aaa;
      display: flex;
      align-items: center;
      pointer-events: none;
    }

    .auth-field-icon svg {
      width: 15px;
      height: 15px;
    }

    .auth-input {
      width: 100%;
      padding: 11px 14px 11px 38px;
      border: 1px solid rgba(0, 0, 0, 0.09);
      border-radius: 10px;
      font-family: inherit;
      font-size: 14px;
      color: #1a1a1a;
      background: rgba(245, 247, 250, 0.8);
      outline: none;
      transition: border 0.15s, background 0.15s;
    }

    .auth-input:focus {
      border-color: #3a7d5a;
      background: white;
    }

    .auth-input::placeholder {
      color: #bbb;
    }

    .auth-input--pw {
      padding-right: 42px;
    }

    .auth-btn {
      width: 100%;
      padding: 13px;
      background: #1a1a1a;
      color: white;
      border: none;
      border-radius: 10px;
      font-family: inherit;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      margin-top: 0.25rem;
      transition: background 0.15s, transform 0.1s;
      letter-spacing: 0.01em;
    }

    .auth-btn:hover {
      background: #2d2d2d;
    }

    .auth-btn:active {
      transform: scale(0.99);
    }

    .auth-toggle {
      text-align: center;
      margin-top: 1.25rem;
      font-size: 13px;
      color: #888;
    }

    .auth-toggle a {
      color: #3a7d5a;
      font-weight: 600;
      text-decoration: none;
    }

    .auth-label {
      font-size: 11px;
      font-weight: 600;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: #888;
      display: block;
      margin-bottom: 5px;
      text-align: left;
    }

    .auth-group {
      margin-bottom: 0.85rem;
      text-align: left;
    }

    .auth-error-popup {
      position: fixed;
      top: 1.5rem;
      left: 50%;
      transform: translateX(-50%);
      background: #c0392b;
      color: white;
      padding: 12px 24px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 600;
      z-index: 999;
      opacity: 1;
      transition: opacity 0.4s ease;
      white-space: nowrap;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .auth-error-popup.hide {
      opacity: 0;
    }

    .eye-toggle {
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: #aaa;
      padding: 4px;
      display: flex;
      align-items: center;
    }

    .eye-toggle:hover {
      color: #555;
    }

    .eye-toggle svg {
      width: 18px;
      height: 18px;
    }

    .step-indicator {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-bottom: 1.5rem;
    }

    .step-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #ddd;
      transition: background 0.2s;
    }

    .step-dot.active {
      background: #1a1a1a;
    }

    .step-dot.done {
      background: #3a7d5a;
    }
  </style>
</head>

<body>

  <?php if (!empty($errors)): ?>
    <div class="auth-error-popup" id="error-popup"><?= e($errors[0]) ?></div>
  <?php endif; ?>

  <div class="auth-wrap">
    <div class="auth-card">

      <div class="auth-icon-wrap">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path
            d="M19 3h-1V1h-2v2H8V1H6v2H5C3.9 3 3 3.9 3 5v14c0 1.1.9 2 2 2h7.35c-.22-.62-.35-1.29-.35-2 0-3.31 2.69-6 6-6 .34 0 .67.03 1 .08V9H3V7h16v2.35c.72.22 1.39.57 2 1V5c0-1.1-.9-2-2-2z"
            fill="#1a1a1a" />
          <path d="M19 15c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm1 4.5h-1.5V21h-1v-3H19v1.5h1v1z"
            fill="#1a1a1a" />
        </svg>
      </div>

      <!-- Step indicator -->
      <div class="step-indicator">
        <div class="step-dot <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>"></div>
        <div class="step-dot <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>"></div>
        <div class="step-dot <?= $step >= 3 ? 'active' : '' ?>"></div>
      </div>

      <?php if ($step === 1): ?>
        <div class="auth-welcome">Forgot Password</div>
        <div class="auth-sub">Enter your username to get started.</div>
        <form method="POST" action="forgot_password.php">
          <input type="hidden" name="step" value="1" />
          <div class="auth-field">
            <div class="auth-field-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path
                  d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" />
              </svg>
            </div>
            <input class="auth-input" type="text" name="username" placeholder="your_username" required />
          </div>
          <button type="submit" class="auth-btn">Continue ›</button>
        </form>

      <?php elseif ($step === 2): ?>
        <div class="auth-welcome">Security Question</div>
        <div class="auth-sub"><?= e($question) ?></div>
        <form method="POST" action="forgot_password.php">
          <input type="hidden" name="step" value="2" />
          <input type="hidden" name="username" value="<?= e($username) ?>" />
          <div class="auth-field">
            <div class="auth-field-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path
                  d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z" />
              </svg>
            </div>
            <input class="auth-input" type="text" name="security_answer" placeholder="Your answer" required />
          </div>
          <button type="submit" class="auth-btn">Verify ›</button>
        </form>

      <?php elseif ($step === 3): ?>
        <div class="auth-welcome">New Password</div>
        <div class="auth-sub">Choose a strong new password.</div>
        <form method="POST" action="forgot_password.php">
          <input type="hidden" name="step" value="3" />
          <input type="hidden" name="username" value="<?= e($username) ?>" />
          <div class="auth-field">
            <div class="auth-field-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path
                  d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1s3.1 1.39 3.1 3.1v2z" />
              </svg>
            </div>
            <input class="auth-input auth-input--pw" type="password" id="new-pw" name="new_password"
              placeholder="Min. 6 characters" required />
            <button type="button" class="eye-toggle" onclick="togglePw('new-pw', this)">
              <svg class="eye-show" viewBox="0 0 24 24" fill="currentColor">
                <path
                  d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z" />
              </svg>
              <svg class="eye-hide" viewBox="0 0 24 24" fill="currentColor" style="display:none;">
                <path
                  d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46A11.804 11.804 0 0 0 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z" />
              </svg>
            </button>
          </div>
          <div class="auth-field">
            <div class="auth-field-icon">
              <svg viewBox="0 0 24 24" fill="currentColor">
                <path
                  d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1s3.1 1.39 3.1 3.1v2z" />
              </svg>
            </div>
            <input class="auth-input auth-input--pw" type="password" id="confirm-pw" name="confirm_password"
              placeholder="Repeat new password" required />
            <button type="button" class="eye-toggle" onclick="togglePw('confirm-pw', this)">
              <svg class="eye-show" viewBox="0 0 24 24" fill="currentColor">
                <path
                  d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z" />
              </svg>
              <svg class="eye-hide" viewBox="0 0 24 24" fill="currentColor" style="display:none;">
                <path
                  d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46A11.804 11.804 0 0 0 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z" />
              </svg>
            </button>
          </div>
          <button type="submit" class="auth-btn">Reset Password ›</button>
        </form>
      <?php endif; ?>

      <div class="auth-toggle">
        <a href="index.php">← Back to login</a>
      </div>

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