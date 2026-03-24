<?php
require_once 'includes/config.php';
require_login();

$user            = current_user();
$active_page     = 'settings';
$profile_errors  = [];
$password_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name      = trim($_POST['name'] ?? '');
        $req_hrs   = (float) ($_POST['required_hours'] ?? 0);
        $allowance = (float) ($_POST['allowance_per_day'] ?? 150);
        if (!$name) {
            $profile_errors[] = 'Name cannot be empty.';
        } elseif ($req_hrs < 1) {
            $profile_errors[] = 'Enter a valid number of required hours.';
        } else {
            $user['name']              = $name;
            $user['required_hours']    = $req_hrs;
            $user['allowance_per_day'] = $allowance;
            if (!empty($_POST['security_question'])) $user['security_question'] = $_POST['security_question'];
            if (!empty($_POST['security_answer']))   $user['security_answer']   = strtolower(trim($_POST['security_answer']));
            save_user($user);
            set_flash('success', 'Profile saved!');
            header('Location: settings.php'); exit;
        }
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new_pw  = $_POST['new_password']     ?? '';
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
            set_flash('success', 'Password updated!');
            header('Location: settings.php'); exit;
        }
    }
}

include 'includes/header.php';
?>

<div class="page-header">
  <div class="page-header-top">
    <div>
      <div class="page-eyebrow">Account</div>
      <h1 class="page-title">Settings</h1>
      <p class="page-subtitle">Manage your profile, OJT requirements, and account security.</p>
    </div>
  </div>
</div>

<div class="content" style="max-width:700px;">

  <!-- Profile -->
  <div class="settings-section">
    <div class="settings-section-header">
      <div class="settings-section-icon">
        <svg viewBox="0 0 24 24"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
      </div>
      <div>
        <div class="settings-section-title">Profile & OJT Settings</div>
        <div class="settings-section-desc">Your name, required hours, and allowance rate</div>
      </div>
    </div>
    <div class="settings-section-body">
      <?php foreach ($profile_errors as $err): ?>
        <div style="background:#fff5f5;border:1px solid #fca5a5;color:var(--red);padding:10px 14px;border-radius:var(--radius-sm);margin-bottom:1rem;font-size:13px;font-weight:600;"><?= e($err) ?></div>
      <?php endforeach; ?>
      <form method="POST" action="settings.php">
        <input type="hidden" name="action" value="update_profile" />
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input class="form-input" type="text" name="name" value="<?= e($user['name'] ?? '') ?>" required />
          </div>
          <div class="form-group">
            <label class="form-label">Username</label>
            <input class="form-input" type="text" value="<?= e($user['username']) ?>" readonly />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Required OJT Hours</label>
            <input class="form-input" type="number" name="required_hours" min="1" step="0.5" value="<?= e($user['required_hours'] ?? 500) ?>" required />
            <span class="form-hint">Total hours required to complete your OJT</span>
          </div>
          <div class="form-group">
            <label class="form-label">Daily Allowance (₱)</label>
            <input class="form-input" type="number" name="allowance_per_day" min="0" step="0.01" value="<?= e(number_format($user['allowance_per_day'] ?? 150, 2, '.', '')) ?>" />
            <span class="form-hint">Used to calculate your total earnings</span>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Security Question</label>
          <select class="form-input" name="security_question">
            <option value="">— Select a question —</option>
            <?php
              $questions = [
                "What is your mother's maiden name?",
                "What was the name of your first pet?",
                "What city were you born in?",
                "What is your childhood nickname?",
                "What is the name of your elementary school?",
              ];
              foreach ($questions as $q):
            ?>
              <option value="<?= e($q) ?>" <?= ($user['security_question'] ?? '') === $q ? 'selected' : '' ?>><?= e($q) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Security Answer</label>
          <input class="form-input" type="text" name="security_answer" placeholder="Your answer" value="<?= e($user['security_answer'] ?? '') ?>" />
        </div>
        <button type="submit" class="btn btn-primary">Save Profile</button>
      </form>
    </div>
  </div>

  <!-- Password -->
  <div class="settings-section">
    <div class="settings-section-header">
      <div class="settings-section-icon">
        <svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1s3.1 1.39 3.1 3.1v2z"/></svg>
      </div>
      <div>
        <div class="settings-section-title">Change Password</div>
        <div class="settings-section-desc">Update your account password</div>
      </div>
    </div>
    <div class="settings-section-body">
      <?php foreach ($password_errors as $err): ?>
        <div style="background:#fff5f5;border:1px solid #fca5a5;color:var(--red);padding:10px 14px;border-radius:var(--radius-sm);margin-bottom:1rem;font-size:13px;font-weight:600;"><?= e($err) ?></div>
      <?php endforeach; ?>
      <form method="POST" action="settings.php">
        <input type="hidden" name="action" value="change_password" />
        <div class="form-group">
          <label class="form-label">Current Password</label>
          <input class="form-input" type="password" name="current_password" placeholder="Enter current password" required />
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">New Password</label>
            <input class="form-input" type="password" name="new_password" placeholder="Min. 6 characters" required />
          </div>
          <div class="form-group">
            <label class="form-label">Confirm New Password</label>
            <input class="form-input" type="password" name="confirm_password" placeholder="Repeat new password" required />
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Update Password</button>
      </form>
    </div>
  </div>

</div>

<?php include 'includes/footer.php'; ?>