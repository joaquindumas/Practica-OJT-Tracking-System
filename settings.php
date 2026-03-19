<?php
require_once 'includes/config.php';
require_login();

$user = current_user();
$active_page = 'settings';
$profile_errors = [];
$password_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $req_hrs = (float) ($_POST['required_hours'] ?? 0);
    if (!$name) {
      $profile_errors[] = 'Name cannot be empty.';
    } elseif ($req_hrs < 1) {
      $profile_errors[] = 'Enter a valid number of required hours.';
    } else {
      $user['name'] = $name;
      $user['required_hours'] = $req_hrs;
      if (!empty($_POST['security_question']))
        $user['security_question'] = $_POST['security_question'];
      if (!empty($_POST['security_answer']))
        $user['security_answer'] = strtolower(trim($_POST['security_answer']));
      save_user($user);
      set_flash('success', 'Profile saved!');
      header('Location: settings.php');
      if (isset($_POST['allowance_per_day'])) {
    $user['allowance_per_day'] = (float) $_POST['allowance_per_day'];
}
      exit;
    }
  }

  if ($action === 'change_password') {
    $current = $_POST['current_password'] ?? '';
    $new_pw = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (!verify_password($current, $user['password'])) {
      $password_errors[] = 'Current password is incorrect.';
    } elseif (strlen($new_pw) < 6) {
      $password_errors[] = 'New password must be at least 6 characters.';
    } elseif ($new_pw !== $confirm) {
      $password_errors[] = 'Passwords do not match.';
    } else {
      $user['password'] = hash_password($new_pw);
      save_user($user);
      set_flash('success', 'Password updated successfully!');
      header('Location: settings.php');
      exit;
    }
  }
}

include 'includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Settings</div>
    <div class="page-subtitle">Manage your account and preferences</div>
  </div>
</div>

<div class="settings-section">
  <div class="settings-section-title">
    <svg viewBox="0 0 24 24" fill="currentColor">
      <path
        d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" />
    </svg>
    Profile
  </div>
  <?php foreach ($profile_errors as $err): ?>
    <span class="form-error"><?= e($err) ?></span>
  <?php endforeach; ?>
  <form method="POST" action="settings.php">
    <input type="hidden" name="action" value="update_profile" />
    <div class="form-group">
      <label class="form-label">Full Name</label>
      <input class="form-input" type="text" name="name" value="<?= e($user['name'] ?? '') ?>" required />
    </div>
    <div class="form-group">
      <label class="form-label">Username</label>
      <input class="form-input" type="text" value="<?= e($user['username']) ?>" readonly />
    </div>
    <div class="form-group">
      <label class="form-label">Required OJT Hours</label>
      <input class="form-input" type="number" name="required_hours" min="1" step="0.5"
        value="<?= e($user['required_hours'] ?? 500) ?>" required />
    </div>
    <div class="form-group">
      <label class="form-label">Daily Allowance (₱)</label>
      <input class="form-input" type="number" name="allowance_per_day" min="0" step="0.01"
        value="<?= e(number_format($user['allowance_per_day'] ?? 150, 2, '.', '')) ?>" placeholder="e.g. 150.00" />
      <span style="font-size:11px;color:var(--text3);margin-top:4px;display:block;">
        Used to calculate your total and projected allowance earnings.
      </span>
    </div>
    <div class="form-group">
      <label class="form-label">Security Question</label>
      <select class="form-input" name="security_question">
        <option value="">— Select a question —</option>
        <option value="What is your mother's maiden name?" <?= ($user['security_question'] ?? '') === "What is your mother's maiden name?" ? 'selected' : '' ?>>What is your mother's maiden name?</option>
        <option value="What was the name of your first pet?" <?= ($user['security_question'] ?? '') === "What was the name of your first pet?" ? 'selected' : '' ?>>What was the name of your first pet?</option>
        <option value="What city were you born in?" <?= ($user['security_question'] ?? '') === "What city were you born in?" ? 'selected' : '' ?>>What city were you born in?</option>
        <option value="What is your childhood nickname?" <?= ($user['security_question'] ?? '') === "What is your childhood nickname?" ? 'selected' : '' ?>>What is your childhood nickname?</option>
        <option value="What is the name of your elementary school?" <?= ($user['security_question'] ?? '') === "What is the name of your elementary school?" ? 'selected' : '' ?>>What is the name of your elementary school?</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Security Answer</label>
      <input class="form-input" type="text" name="security_answer" placeholder="Your answer"
        value="<?= e($user['security_answer'] ?? '') ?>" />
    </div>
    <button type="submit" class="btn btn-primary">Save Profile</button>
  </form>
</div>

<div class="settings-section">
  <div class="settings-section-title">
    <svg viewBox="0 0 24 24" fill="currentColor">
      <path
        d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1s3.1 1.39 3.1 3.1v2z" />
    </svg>
    Change Password
  </div>
  <?php foreach ($password_errors as $err): ?>
    <span class="form-error"><?= e($err) ?></span>
  <?php endforeach; ?>
  <form method="POST" action="settings.php">
    <input type="hidden" name="action" value="change_password" />
    <div class="form-group">
      <label class="form-label">Current Password</label>
      <input class="form-input" type="password" name="current_password" placeholder="Current password" required />
    </div>
    <div class="form-group">
      <label class="form-label">New Password</label>
      <input class="form-input" type="password" name="new_password" placeholder="Min. 6 characters" required />
    </div>
    <div class="form-group">
      <label class="form-label">Confirm New Password</label>
      <input class="form-input" type="password" name="confirm_password" placeholder="Repeat new password" required />
    </div>
    <button type="submit" class="btn btn-primary">Update Password</button>
  </form>
</div>

<?php include 'includes/footer.php'; ?>