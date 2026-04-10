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
        $name       = trim($_POST['name'] ?? '');
        $req_hrs    = (float) ($_POST['required_hours'] ?? 0);
        $allowance  = (float) ($_POST['allowance_per_day'] ?? 0);
        $currency   = $_POST['currency'] ?? 'PHP';
        
        if (!$name) {
            $profile_errors[] = 'Name cannot be empty.';
        } elseif ($req_hrs < 1) {
            $profile_errors[] = 'Enter a valid number of required hours.';
        } else {
            $user['name']              = $name;
            $user['required_hours']    = $req_hrs;
            $user['allowance_per_day'] = $allowance;
            $user['currency']          = $currency;
            
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

$page_css = 'css/dashboard.css';
include 'includes/header.php';
?>

<style>
.settings-card {
    background: #ffffff;
    border: 1px solid rgba(226,232,240,0.75);
    border-radius: 1.25rem;
    padding: 1.75rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.04);
    margin-bottom: 1.5rem;
}
.settings-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2.5rem;
}
.settings-col {
    display: flex;
    flex-direction: column;
}
.settings-lower-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    align-items: start;
}
.settings-section-title {
    font-size: 1.125rem;
    font-weight: 800;
    color: #112317;
    margin: 0 0 1rem 0;
    letter-spacing: -0.02em;
}
.page-subtitle {
    font-size: 0.95rem;
    color: #64748b;
    margin-top: 0.5rem;
    line-height: 1.6;
}
.settings-divider {
    height: 1px;
    background: rgba(226,232,240,1);
    margin: 1.5rem 0;
    border: none;
}
.form-row-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}
.form-row-1 {
    margin-bottom: 1rem;
}
.form-label-styled {
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: #475569;
    margin-bottom: 0.5rem;
    display: block;
}
.form-input-styled {
    width: 100%;
    padding: 12px 14px;
    border: 1.5px solid rgba(226,232,240,1);
    border-radius: 0.75rem;
    font-size: 0.95rem;
    color: #1e293b;
    background: #ffffff;
    outline: none;
    transition: all 0.15s ease;
}
.form-input-styled:focus {
    border-color: #2d6a4f;
    box-shadow: 0 0 0 3px rgba(45,106,79,0.08);
}
select.form-input-styled {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 14px;
    padding-right: 40px;
    cursor: pointer;
}
.password-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}
.password-wrapper .form-input-styled {
    padding-right: 42px;
}
.password-toggle-btn {
    position: absolute;
    right: 12px;
    background: none;
    border: none;
    padding: 0;
    color: #94a3b8;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s ease;
    outline: none;
}
.password-toggle-btn:hover {
    color: #2d6a4f;
}
.error-banner {
    background: #fff5f5;
    border: 1px solid #fca5a5;
    color: #b91c1c;
    padding: 10px 14px;
    border-radius: 0.75rem;
    margin-bottom: 1rem;
    font-size: 0.85rem;
    font-weight: 600;
}
@media (max-width: 1024px) {
    .settings-grid {
        gap: 2rem;
    }
}
@media (max-width: 768px) {
    .settings-grid,
    .settings-lower-grid {
        grid-template-columns: 1fr;
        gap: 1.25rem;
    }
    .settings-card {
        padding: 1.25rem;
    }
    .form-row-2 {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="content">
  <div class="dash-wrap">
    <div class="dash-hero">
      <div class="dash-hero-content">
        <div class="dash-hero-eyebrow">Account</div>
        <h1 class="dash-hero-title">Account & Profile Settings</h1>
        <div class="dash-hero-sub">Manage your profile, OJT requirements, and account security.</div>
      </div>
    </div>

    <form method="POST" action="settings.php" class="settings-card">
            <input type="hidden" name="action" value="update_profile" />
            
            <?php foreach ($profile_errors as $err): ?>
                <div class="error-banner"><?= e($err) ?></div>
            <?php endforeach; ?>

            <div class="settings-grid">
                <div class="settings-col">
                    <h2 class="settings-section-title">Personal Information</h2>
                    
                    <div class="form-row-2">
                        <div>
                            <label class="form-label-styled">Full Name</label>
                            <input class="form-input-styled" type="text" name="name" value="<?= e($user['name'] ?? '') ?>" required />
                        </div>
                        <div>
                            <label class="form-label-styled">Username</label>
                            <input class="form-input-styled" type="text" value="<?= e($user['username'] ?? '') ?>" disabled />
                        </div>
                    </div>

                    <hr class="settings-divider">

                    <h2 class="settings-section-title">OJT Requirements</h2>
                    
                    <div class="form-row-2">
                        <div>
                            <label class="form-label-styled">Required OJT Hours</label>
                            <input class="form-input-styled" type="number" name="required_hours" min="1" step="0.5" value="<?= e($user['required_hours'] ?? 240) ?>" required />
                        </div>
                        <div>
                            <label class="form-label-styled">Daily Allowance (<?= get_currency_symbol($user['currency'] ?? 'PHP') ?>)</label>
                            <input class="form-input-styled" type="number" name="allowance_per_day" step="0.01" value="<?= e($user['allowance_per_day'] ?? 0.00) ?>" />
                        </div>
                    </div>

                    <div class="form-row-1">
                        <label class="form-label-styled">Currency</label>
                        <select class="form-input-styled" name="currency">
                            <option value="PHP" <?= ($user['currency'] ?? 'PHP') === 'PHP' ? 'selected' : '' ?>>PHP (₱)</option>
                            <option value="USD" <?= ($user['currency'] ?? 'PHP') === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                            <option value="EUR" <?= ($user['currency'] ?? 'PHP') === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                            <option value="GBP" <?= ($user['currency'] ?? 'PHP') === 'GBP' ? 'selected' : '' ?>>GBP (£)</option>
                        </select>
                    </div>
                </div>

                <div class="settings-col">
                    <h2 class="settings-section-title">Security</h2>
                    
                    <div class="form-row-1">
                        <label class="form-label-styled">Security Question</label>
                        <select class="form-input-styled" name="security_question">
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

                    <div class="form-row-1" style="margin-bottom: 2rem;">
                        <label class="form-label-styled">Security Answer</label>
                        <div class="password-wrapper">
                            <input class="form-input-styled" type="password" id="sec-answer" name="security_answer" value="<?= e($user['security_answer'] ?? '') ?>" placeholder="Leave blank to keep current" />
                            <button type="button" class="password-toggle-btn" onclick="togglePassword('sec-answer', this)" title="Show/Hide">
                                <svg class="eye-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Profile</button>
                </div>
            </div>
        </form>

        <div class="settings-lower-grid">
            <form method="POST" action="settings.php" class="settings-card">
                <input type="hidden" name="action" value="change_password" />
                
                <h2 class="settings-section-title">Change Password</h2>
                <div class="page-subtitle" style="margin-bottom: 1.5rem;">Update your account password to keep it secure.</div>

                <?php foreach ($password_errors as $err): ?>
                    <div class="error-banner"><?= e($err) ?></div>
                <?php endforeach; ?>

                <div class="form-row-1">
                    <label class="form-label-styled">Current Password</label>
                    <div class="password-wrapper">
                        <input class="form-input-styled" type="password" id="cur-pass" name="current_password" placeholder="Enter current password" required />
                        <button type="button" class="password-toggle-btn" onclick="togglePassword('cur-pass', this)" title="Show/Hide">
                            <svg class="eye-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                </div>
                
                <div class="form-row-2">
                    <div>
                        <label class="form-label-styled">New Password</label>
                        <div class="password-wrapper">
                            <input class="form-input-styled" type="password" id="new-pass" name="new_password" placeholder="Min. 6 characters" required />
                            <button type="button" class="password-toggle-btn" onclick="togglePassword('new-pass', this)" title="Show/Hide">
                                <svg class="eye-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="form-label-styled">Confirm New Password</label>
                        <div class="password-wrapper">
                            <input class="form-input-styled" type="password" id="conf-pass" name="confirm_password" placeholder="Repeat new password" required />
                            <button type="button" class="password-toggle-btn" onclick="togglePassword('conf-pass', this)" title="Show/Hide">
                                <svg class="eye-icon" viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem;">Update Password</button>
            </form>

        </div>
    </div>
</div>

<script>
// Toggle Password Visibility Logic
function togglePassword(inputId, btn) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        // Eye-off icon
        btn.innerHTML = `<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>`;
    } else {
        input.type = 'password';
        // Eye-on icon
        btn.innerHTML = `<svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>`;
    }
}
</script>

<?php include 'includes/footer.php'; ?>