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
        $allowance  = (float) ($_POST['allowance_per_day'] ?? 150);
        
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

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

/* ── LAYOUT LOCK & SCROLL ── */
.settings-wrap { 
    padding: 0.25rem 2rem 0 2rem; 
    width: 100%; 
    box-sizing: border-box; 
    max-width: 1400px; 
    margin: 0 auto; 
    height: 100vh; 
    display: flex; 
    flex-direction: column; 
    overflow: hidden; 
}

.settings-scroll-area {
    flex: 1;
    overflow-y: auto;
    padding-right: 12px;
    padding-bottom: 3rem;
}
.settings-scroll-area::-webkit-scrollbar { width: 6px; }
.settings-scroll-area::-webkit-scrollbar-track { background: transparent; }
.settings-scroll-area::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

/* ── UNIFIED PAGE HEADER ── */
.page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 1.5rem; width: 100%; flex-wrap: wrap; gap: 16px; flex-shrink: 0; }
.page-title-group { display: flex; flex-direction: column; gap: 4px; }
.page-eyebrow { font-family: 'Inter', sans-serif; font-size: var(--text-xs); font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 2px;}
.page-title { font-family: 'Inter', sans-serif !important; font-size: var(--text-2xl); font-weight: 800; color: var(--text); margin: 0; line-height: 1.1; letter-spacing: -0.03em; }
.page-subtitle { font-family: 'Inter', sans-serif; font-size: var(--text-sm); color: #64748b; margin-top: 4px; }

/* ── COMPACT CARD & GRID ── */
.settings-card { background: #ffffff; border: 1px solid var(--border); border-radius: 16px; padding: 1.75rem; box-shadow: 0 4px 20px rgba(0,0,0,0.03); margin-bottom: 1.5rem; }
.settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem; } 
.settings-col { display: flex; flex-direction: column; }

/* ── TYPOGRAPHY & FORMS ── */
.settings-section-title { font-family: 'Inter', sans-serif !important; font-size: var(--text-lg); font-weight: 800; color: var(--text); margin: 0 0 1rem 0; letter-spacing: -0.02em; }
.settings-divider { height: 1px; background: var(--border); margin: 1.5rem 0; border: none; }

.form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
.form-row-1 { margin-bottom: 1rem; }

.form-label-styled { font-family: 'Inter', sans-serif; font-size: 10px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #475569; margin-bottom: 6px; display: block; }
.form-input-styled { font-family: 'Inter', sans-serif; width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; color: #1e293b; box-sizing: border-box; outline: none; transition: all 0.2s ease; background: #ffffff; }
.form-input-styled:focus { border-color: #2d6a4f; box-shadow: 0 0 0 3px rgba(45,106,79,0.1); }
.form-input-styled:disabled, .form-input-styled[readonly] { background: #f8fafc; color: #94a3b8; border-color: #e2e8f0; cursor: not-allowed; }

select.form-input-styled { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 10px center; background-size: 14px; padding-right: 32px; cursor: pointer; }

/* ── PASSWORD VISIBILITY TOGGLE ── */
.password-wrapper { position: relative; display: flex; align-items: center; }
.password-wrapper .form-input-styled { padding-right: 40px; } 
.password-toggle-btn { position: absolute; right: 12px; background: none; border: none; padding: 0; color: #94a3b8; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: color 0.2s ease; outline: none; }
.password-toggle-btn:hover, .password-toggle-btn:focus { color: #2d6a4f; }

/* ── BUTTON ── */
.btn-save-profile { width: 100%; background: #2d6a4f; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; transition: background 0.15s; margin-top: auto; }
.btn-save-profile:hover { background: #1b4332; }

/* Error Banners */
.error-banner { background: #fff5f5; border: 1px solid #fca5a5; color: var(--red); padding: 10px 14px; border-radius: 8px; margin-bottom: 1rem; font-size: 12px; font-weight: 600; font-family: 'Inter', sans-serif; }

/* ── RESPONSIVE ── */
@media (max-width: 1024px) { .settings-grid { gap: 2rem; } }
@media (max-width: 768px) {
    .settings-wrap { padding: 1rem 1rem 0 1rem; height: auto; overflow: visible; }
    .settings-scroll-area { overflow-y: visible; padding-right: 0; }
    .settings-grid { grid-template-columns: 1fr; gap: 1.5rem; }
    .form-row-2 { grid-template-columns: 1fr; gap: 1rem; }
    .settings-divider { margin: 1.5rem 0; display: none; } 
    .btn-save-profile { margin-top: 1rem; }
}
</style>

<div class="settings-wrap">
    
    <div class="page-header">
        <div class="page-title-group">
            <div class="page-eyebrow">Account</div>
            <h1 class="page-title">Account & Profile Settings</h1>
            <div class="page-subtitle">Manage your profile, OJT requirements, and account security.</div>
        </div>
    </div>

    <div class="settings-scroll-area">
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
                            <label class="form-label-styled">Daily Allowance (₱)</label>
                            <input class="form-input-styled" type="number" name="allowance_per_day" step="0.01" value="<?= e($user['allowance_per_day'] ?? 150.00) ?>" />
                        </div>
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

                    <button type="submit" class="btn-save-profile">Save Profile</button>
                </div>
            </div>
        </form>

        <form method="POST" action="settings.php" class="settings-card" style="max-width: 600px;">
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

            <button type="submit" class="btn-save-profile" style="margin-top: 0.5rem;">Update Password</button>
        </form>
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