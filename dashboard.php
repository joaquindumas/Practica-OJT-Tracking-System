<?php
require_once 'includes/config.php';
require_login();

$user = current_user();
$active_page = 'dashboard';
$page_css = 'css/dashboard.css';
$log_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'log_hours') {
        $date = $_POST['date'] ?? ''; $desc = trim($_POST['description'] ?? ''); $from = $_POST['from'] ?? ''; $to = $_POST['to'] ?? '';
        if (!$date || !$from || !$to) { $log_errors[] = 'Please fill in date, from, and to.'; } else {
            [$fh, $fm] = array_map('intval', explode(':', $from)); [$th, $tm] = array_map('intval', explode(':', $to)); $hours = (($th * 60 + $tm) - ($fh * 60 + $fm)) / 60;
            if ($hours <= 0) { $log_errors[] = '"To" time must be after "From" time.'; } else {
                add_log($user['id'], ['id' => generate_id(), 'date' => $date, 'description' => $desc, 'from' => $from, 'to' => $to, 'hours' => round($hours, 4), 'created_at' => date('Y-m-d H:i:s')]);
                set_flash('success', 'Hours logged successfully!'); header('Location: dashboard.php'); exit;
            }
        }
    }
    if ($action === 'edit_log') {
        $edit_id = $_POST['log_id'] ?? ''; $date = $_POST['date'] ?? ''; $desc = trim($_POST['description'] ?? ''); $from = $_POST['from'] ?? ''; $to = $_POST['to'] ?? '';
        if ($date && $from && $to) {
            [$fh, $fm] = array_map('intval', explode(':', $from)); [$th, $tm] = array_map('intval', explode(':', $to)); $hours = (($th * 60 + $tm) - ($fh * 60 + $fm)) / 60;
            if ($hours > 0) { update_log($edit_id, $user['id'], ['date' => $date, 'description' => $desc, 'from' => $from, 'to' => $to, 'hours' => round($hours, 4)]); set_flash('success', 'Log updated!'); }
        }
        header('Location: dashboard.php'); exit;
    }
    if ($action === 'delete_log') { delete_log($_POST['log_id'] ?? '', $user['id']); set_flash('success', 'Log deleted.'); header('Location: dashboard.php'); exit; }
    if ($action === 'bulk_log') {
        $start = $_POST['bulk_start'] ?? ''; $end = $_POST['bulk_end'] ?? ''; $hrs = (float) ($_POST['bulk_hrs'] ?? 8); $desc = trim($_POST['bulk_desc'] ?? ''); $exclude_days = array_map('intval', $_POST['exclude_days'] ?? []);
        $from_time = '08:00'; $total_min = (int) ($hrs * 60); $to_time = sprintf('%02d:%02d', intdiv(480 + $total_min, 60), (480 + $total_min) % 60);
        if ($start && $end && $start <= $end) {
            $bulk_logs = []; $cursor = strtotime($start); $endTs = strtotime($end);
            while ($cursor <= $endTs) { $dow = (int) date('N', $cursor); if (!in_array($dow, $exclude_days)) { $bulk_logs[] = ['date' => date('Y-m-d', $cursor), 'from' => $from_time, 'to' => $to_time, 'description' => $desc]; } $cursor = strtotime('+1 day', $cursor); }
            $count = bulk_add_logs($user['id'], $bulk_logs); set_flash('success', "{$count} log" . ($count !== 1 ? 's' : '') . " added!");
        } else { set_flash('error', 'Please check your date range.'); }
        header('Location: dashboard.php'); exit;
    }
}

$user = current_user(); $all_logs = $user['logs'] ?? []; $logged = total_logged($user); $required = $user['required_hours'] ?? DEFAULT_REQUIRED_HOURS; $remaining = hours_remaining($user); $pct = completion_percent($user); $est_date = estimated_completion($user); $est_basis = estimated_basis($user); $allowance = $user['allowance_per_day'] ?? 0;
usort($all_logs, fn($a, $b) => strtotime($b['date']) - strtotime($a['date'])); $recent_logs = array_slice($all_logs, 0, 4);
$total_days = count(array_unique(array_column($all_logs, 'date'))); $avg_hrs_day = $total_days > 0 ? round($logged / $total_days, 1) : 0; $projected_days = $avg_hrs_day > 0 ? (int) ceil($remaining / $avg_hrs_day) : 0;


// Greeting + day counter
$hour = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');

$ojt_start = null;
if (!empty($all_logs)) {
    $dates = array_column($all_logs, 'date');
    sort($dates);
    $ojt_start = $dates[0];
}
$day_count = $total_days;

$today_str = date('Y-m-d');
$today_log = null;
$today_hrs = 0;
foreach ($all_logs as $l) {
  if (($l['date'] ?? '') === $today_str) {
    $today_log = $l;
    $today_hrs += (float) ($l['hours'] ?? 0);
  }
}

include 'includes/header.php';
?>

<div class="content">
<div class="dash-wrap">
  <div class="dash-hero">
    <div class="dash-hero-content">
      <div class="dash-hero-eyebrow">OJT Dashboard</div>
      <h1 class="dash-hero-title"><?= $greeting ?>, <?= e(explode(' ', $user['name'] ?? $user['username'])[0]) ?> 👋</h1>
      <div class="dash-hero-status" id="hero-today-status">
        <div class="dash-hero-status-top">
          <span class="dash-hero-status-title">Today's Status</span>
          <span class="status-badge <?= $today_log ? 'logged' : 'pending' ?>"><?= $today_log ? 'Logged' : 'Pending' ?></span>
        </div>
        <span class="dash-hero-status-meta">Day <?= $total_days ?> of OJT &nbsp;·&nbsp; <?= date('D, M j, Y') ?></span>
        <button type="button" class="dash-hero-status-link" data-open-quick-log>Quick Log</button>
      </div>
    </div>
  </div>

  <?php
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    $week_logged_dates = [];
    foreach ($all_logs as $week_log) {
      $week_date = $week_log['date'] ?? '';
      if ($week_date >= $week_start && $week_date <= $week_end) {
        $week_logged_dates[$week_date] = true;
      }
    }
    $week_logged_days = count($week_logged_dates);
    $weekly_collected_allowance = $week_logged_days * $allowance;
    $total_collected_allowance = $total_days * $allowance;
    $remaining_money_by_days_left = max(0, $projected_days * $allowance);
    $week_range_label = date('M j', strtotime($week_start)) . ' - ' . date('M j', strtotime($week_end));
  ?>
  <div class="dash-stat-row-3">

    <div class="dash-stat-card dash-stat-card--progress">
      <div class="dash-stat-eyebrow">Total Progress</div>
      <div class="dash-stat-num"><?= number_format($logged, 1) ?> <span class="dash-stat-denom">/ <?= number_format($required, 0) ?>hrs</span></div>
      <div class="dash-progress-bar"><div class="dash-progress-fill" style="width:<?= min(100, $pct) ?>%;"></div></div>
      <div class="dash-stat-sub"><?= number_format($pct, 1) ?>% complete</div>
      <?php if ($est_date && $est_date !== 'Completed'): ?>
        <div class="dash-est-pill"><svg viewBox="0 0 24 24" fill="currentColor" class="icon-xs"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg> Est. <?= e($est_date) ?></div>
      <?php elseif ($est_date === 'Completed'): ?>
        <div class="dash-est-pill">🎉 Completed!</div>
      <?php endif; ?>
    </div>

    <div class="dash-stat-card dash-stat-card--remaining">
      <div class="dash-stat-eyebrow">Remaining Hours</div>
      <div class="dash-stat-num"><?= number_format($remaining, 1) ?> <span class="dash-stat-denom">hrs</span></div>
      <div class="dash-stat-sub">hours left to complete your OJT</div>
      <?php if ($avg_hrs_day > 0): ?>
        <div class="dash-remaining-pill">
          <svg viewBox="0 0 24 24" fill="currentColor" class="icon-xs"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
          Avg <?= $avg_hrs_day ?> hrs/day · <?= $projected_days ?> days left
        </div>
      <?php else: ?>
        <div class="dash-remaining-pill">Log hours to see your pace</div>
      <?php endif; ?>
    </div>

    <div class="dash-stat-card dash-stat-card--allowance">
      <div class="dash-stat-eyebrow">Allowance Summary</div>
      <div class="dash-stat-num"><?= get_currency_symbol($user['currency']) ?><?= number_format($weekly_collected_allowance, 0) ?> <span class="dash-stat-denom">Collected This Week</span></div>
      <div class="dash-stat-sub"><?= e($week_range_label) ?> · <?= $week_logged_days ?> day<?= $week_logged_days !== 1 ? 's' : '' ?> logged</div>
      
  <div class="allowance-split">
  <div class="allowance-split-item">
    <span class="allowance-split-label">Total Collected</span>
    <span class="allowance-split-value"><?= get_currency_symbol($user['currency']) ?><?= number_format($total_collected_allowance, 0) ?></span>
  </div>
  <div class="allowance-split-divider"></div>
  <div class="allowance-split-item">
    <span class="allowance-split-label">Projected Left</span>
    <span class="allowance-split-value"><?= get_currency_symbol($user['currency']) ?><?= number_format($remaining_money_by_days_left, 0) ?></span>
  </div>
</div>
      </div>
    </div>

  <div class="dash-body">
    <div class="dash-left">
      <div class="table-header-group">
        <h2 class="table-title">Recent Logs</h2>
        <a href="logs.php" class="table-link">View all <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon-xxs"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
      </div>
      <div class="table-wrap">
        <table class="dash-log-table">
            <colgroup><col class="col-w-18" /><col class="col-w-42" /><col class="col-w-15" /><col class="col-w-15" /><col class="col-w-10" /><col style="width: 70px;" /></colgroup>
            <thead><tr><th>Date</th><th>Description</th><th>From</th><th>To</th><th>Hrs</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($recent_logs)): ?>
                <tr>
                    <td colspan="6">
                        <div class="table-empty-state">
                            <div class="table-empty-title">No logs yet</div>
                          <div class="table-empty-sub">Visit Time Logs to add your first entry</div>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($recent_logs as $log): ?>
                <tr class="dash-log-row">
                  <td class="font-600" data-label="Date"><?= e(date('M j, Y', strtotime($log['date']))) ?></td>
                  <td data-label="Description"><span class="log-desc-text"><?= e($log['description'] ?: '—') ?></span></td>
                  <td class="tabular-nums" data-label="From"><?= e(date('g:i A', strtotime($log['from']))) ?></td>
                  <td class="tabular-nums" data-label="To"><?= e(date('g:i A', strtotime($log['to']))) ?></td>
                  <td data-label="Hrs"><span class="highlight-hrs"><?= e(number_format($log['hours'], 1)) ?></span></td>
                  <td data-label="Actions">
                        <div style="display: flex; gap: 4px; justify-content: flex-end;">
                            <button class="action-btn edit-btn" data-id="<?= e($log['id']) ?>" data-date="<?= e($log['date']) ?>" data-desc="<?= e($log['description'] ?? '') ?>" data-from="<?= e($log['from']) ?>" data-to="<?= e($log['to']) ?>" title="Edit">
                                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                            </button>
                            <form method="POST" action="dashboard.php" class="action-form" onsubmit="return confirm('Delete this log?')">
                                <input type="hidden" name="action" value="delete_log" />
                                <input type="hidden" name="log_id" value="<?= e($log['id']) ?>" />
                                <button type="submit" class="action-btn delete" title="Delete">
                                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<div class="modal-overlay" id="quick-log-modal">
  <div class="modal-card modal-card--quick-log" style="max-width:min(520px, 95vw);">
    <div class="modal-title-serif">New Log Entry</div>
    <div class="modal-subtitle">Add your OJT hours for today without leaving the dashboard.</div>
    <?php foreach ($log_errors as $err): ?>
      <span class="form-error" style="color:#ef4444;font-size:0.75rem;display:block;margin-bottom:0.625rem;"><?= e($err) ?></span>
    <?php endforeach; ?>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="action" value="log_hours" />
      <input type="hidden" id="quick-log-date" name="date" value="<?= date('Y-m-d') ?>" />
      <div style="font-size:0.75rem;color:var(--text3);margin-bottom:1rem;font-weight:600;">Logging for <strong id="quick-log-date-label" style="color:#1b4332;"><?= e(date('D, M j, Y')) ?></strong></div>
      <div class="form-group" style="margin-bottom:1rem;"><label class="form-label-styled">Description</label><input class="form-input-styled" type="text" id="quick-log-desc" name="description" placeholder="What did you work on?" /></div>
      <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1rem;">
        <div class="form-group" style="margin:0;"><label class="form-label-styled">From</label><input class="form-input-styled" type="time" id="quick-log-from" name="from" value="08:00" required /></div>
        <div class="form-group" style="margin:0;"><label class="form-label-styled">To</label><input class="form-input-styled" type="time" id="quick-log-to" name="to" value="16:00" required /></div>
      </div>
      <div style="font-size:0.75rem;color:var(--text3);margin-bottom:1.5rem;font-weight:600;">Duration: <strong id="quick-hrs-preview" style="color:#1b4332;">8.00 hrs</strong></div>
      <div class="modal-actions" style="display:flex;justify-content:flex-end;gap:0.625rem;"><button type="button" class="btn btn-secondary" id="quick-log-close-btn">Cancel</button><button type="submit" class="btn btn-primary">Save Log</button></div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="edit-modal">
  <div class="modal-card modal-card--edit-log">
    <div class="modal-title-serif">Edit Log</div>
    <div class="modal-subtitle">Update the details for this log entry.</div>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="action" value="edit_log" /><input type="hidden" name="log_id" id="edit-log-id" />
      <div class="form-group" style="margin-bottom:1rem;"><label class="form-label-styled">Date</label><input class="form-input-styled" type="date" id="edit-date" name="date" required /></div>
      <div class="form-group" style="margin-bottom:1rem;"><label class="form-label-styled">Description</label><input class="form-input-styled" type="text" id="edit-desc" name="description" /></div>
      <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1rem;">
        <div class="form-group" style="margin:0;"><label class="form-label-styled">From</label><input class="form-input-styled" type="time" id="edit-from" name="from" required /></div>
        <div class="form-group" style="margin:0;"><label class="form-label-styled">To</label><input class="form-input-styled" type="time" id="edit-to" name="to" required /></div>
      </div>
      <div style="font-size:0.75rem;color:var(--text3);margin-bottom:1.5rem;font-weight:600;">Duration: <strong id="edit-hrs-preview" style="color:#1b4332;"></strong></div>
      <div class="modal-actions"><button type="button" class="btn btn-secondary" id="edit-close-btn">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="day-modal">
  <div class="modal-card modal-wide">
    <div class="modal-title-serif" id="day-modal-title" style="margin-bottom:1rem;">Logs for —</div>
    <div id="day-modal-body"></div>
    <div style="display:flex;gap:0.625rem;justify-content:flex-end;margin-top:1.25rem;flex-wrap:wrap;">
        <button type="button" class="btn btn-secondary" id="day-modal-close">Close</button>
        <button type="button" class="btn btn-primary" id="day-modal-add">New Log</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="bulk-modal">
  <div class="modal-card" style="max-width:min(520px, 95vw);">
    <div class="modal-title-serif">Bulk Entry</div>
    <div class="modal-subtitle">Fill past days automatically. Already-logged days are skipped.</div>
    <form method="POST" action="dashboard.php"><input type="hidden" name="action" value="bulk_log" />
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
        <div class="form-group" style="margin:0;"><label class="form-label-styled">From</label><input class="form-input-styled" type="date" name="bulk_start" id="bulk-start" required /></div>
        <div class="form-group" style="margin:0;"><label class="form-label-styled">To</label><input class="form-input-styled" type="date" name="bulk_end" id="bulk-end" required /></div>
      </div>
      <div class="form-group" style="margin-bottom:1.25rem;"><label class="form-label-styled">Hrs/Day</label><input class="form-input-styled" type="number" name="bulk_hrs" id="bulk-hrs" value="8" min="0.5" max="24" step="0.5" required /></div>
      <input type="hidden" name="bulk_from" value="08:00" /><input type="hidden" name="bulk_to" id="bulk-to-hidden" value="16:00" />
      <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label-styled" style="margin-bottom:0.625rem;">Exclude Days <span style="color:#888;font-weight:500;text-transform:none;">(SELECTED = SKIP)</span></label>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <?php foreach (['MON','TUE','WED','THU','FRI','SAT','SUN'] as $i => $day): ?>
                <label class="day-toggle <?= in_array($day, ['SAT','SUN']) ? 'day-toggle--excluded' : '' ?>">
                    <input type="checkbox" name="exclude_days[]" value="<?= $i + 1 ?>" <?= in_array($day, ['SAT','SUN']) ? 'checked' : '' ?> style="display:none;" />
                    <span><?= $day ?></span>
                </label>
            <?php endforeach; ?>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:1.25rem;"><label class="form-label-styled">Description (optional)</label><input class="form-input-styled" type="text" name="bulk_desc" placeholder="e.g. OJT at company" /></div>
      <div id="bulk-range-preview" style="font-size:0.75rem;margin-bottom:1.5rem;min-height:1rem;color:#1b4332;font-weight:700;"></div>
      <div class="modal-actions" style="display:flex;justify-content:flex-end;gap:0.625rem;"><button type="button" class="btn btn-secondary" id="bulk-close-btn">Cancel</button><button type="submit" class="btn btn-primary">Fill Days in Range</button></div>
    </form>
  </div>
</div>

 
<script>
// ── 1. UTILITY: CALCULATE HOURS ──
function calcHrs(from, to) { 
    if (!from || !to) return 0; 
    const [fh, fm] = from.split(':').map(Number); 
    const [th, tm] = to.split(':').map(Number); 
    return ((th * 60 + tm) - (fh * 60 + fm)) / 60; 
}

// ── 2. MODAL OPEN / CLOSE LOGIC ──
// Note: #log-modal removed - "Today's Status" card handles quick logging
const editModal = document.getElementById('edit-modal');
const bulkModal = document.getElementById('bulk-modal');
const dayModal = document.getElementById('day-modal');
const quickLogModal = document.getElementById('quick-log-modal');
const editModalCard = editModal?.querySelector('.modal-card--edit-log') || null;
const editForm = editModal?.querySelector('form') || null;
const quickLogCard = quickLogModal?.querySelector('.modal-card--quick-log') || null;
const quickLogForm = quickLogModal?.querySelector('form') || null;
const quickLogFrom = document.getElementById('quick-log-from');
const quickLogTo = document.getElementById('quick-log-to');
const quickHrsPreview = document.getElementById('quick-hrs-preview');

let pageScrollY = 0;
function lockPageScroll() {
  if (window.innerWidth > 768) return;
  pageScrollY = window.scrollY || window.pageYOffset || 0;
  document.documentElement.classList.add('quick-log-open');
  document.body.classList.add('quick-log-open');
  document.body.style.position = 'fixed';
  document.body.style.top = `-${pageScrollY}px`;
  document.body.style.left = '0';
  document.body.style.right = '0';
  document.body.style.width = '100%';
}

function unlockPageScroll() {
  if (window.innerWidth > 768) return;
  document.documentElement.classList.remove('quick-log-open');
  document.body.classList.remove('quick-log-open');
  document.body.style.position = '';
  document.body.style.top = '';
  document.body.style.left = '';
  document.body.style.right = '';
  document.body.style.width = '';
  window.scrollTo(0, pageScrollY);
}

function openQuickLogModal() {
  const quickLogDate = document.getElementById('quick-log-date');
  const quickLogDateLabel = document.getElementById('quick-log-date-label');
  if (quickLogDate) quickLogDate.value = new Date().toISOString().slice(0, 10);
  if (quickLogDateLabel) {
    quickLogDateLabel.textContent = new Intl.DateTimeFormat('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    }).format(new Date());
  }
  updateQuickPreview();
  quickLogModal?.classList.add('open');
  lockPageScroll();
}

function closeQuickLogModal() {
  quickLogModal?.classList.remove('open');
  unlockPageScroll();
}

function openEditLogModal() {
  updateEditPreview();
  editModal?.classList.add('open');
  lockPageScroll();
}

function closeEditLogModal() {
  editModal?.classList.remove('open');
  unlockPageScroll();
}

// Dashboard Trigger Buttons
document.getElementById('open-bulk-btn')?.addEventListener('click', () => bulkModal.classList.add('open'));
document.querySelectorAll('[data-open-quick-log]').forEach(btn => {
  btn.addEventListener('click', () => {
    openQuickLogModal();
  });
});

// Close Buttons inside Modals
document.getElementById('edit-close-btn')?.addEventListener('click', () => closeEditLogModal());
document.getElementById('bulk-close-btn')?.addEventListener('click', () => bulkModal.classList.remove('open'));
document.getElementById('day-modal-close')?.addEventListener('click', () => dayModal?.classList.remove('open'));
document.getElementById('quick-log-close-btn')?.addEventListener('click', () => closeQuickLogModal());
quickLogForm?.addEventListener('submit', () => unlockPageScroll());
editForm?.addEventListener('submit', () => unlockPageScroll());

// Mobile: swipe down on quick-log sheet to close.
if (quickLogCard && quickLogModal) {
  let touchStartY = 0;
  let touchStartX = 0;
  let dragY = 0;
  let canSwipeToClose = false;

  quickLogCard.addEventListener('touchstart', (e) => {
    if (window.innerWidth > 768 || !e.touches[0]) return;
    touchStartY = e.touches[0].clientY;
    touchStartX = e.touches[0].clientX;
    dragY = 0;
    canSwipeToClose = quickLogCard.scrollTop <= 0;
  }, { passive: true });

  quickLogCard.addEventListener('touchmove', (e) => {
    if (!canSwipeToClose || window.innerWidth > 768 || !e.touches[0]) return;
    const deltaY = e.touches[0].clientY - touchStartY;
    const deltaX = Math.abs(e.touches[0].clientX - touchStartX);
    if (deltaY > 0 && deltaY > deltaX) {
      e.preventDefault();
      dragY = Math.min(deltaY, 110);
      quickLogCard.style.transform = `translateY(${dragY * 0.35}px)`;
    }
  }, { passive: false });

  quickLogCard.addEventListener('touchend', () => {
    if (window.innerWidth > 768) return;
    if (canSwipeToClose && dragY > 85) {
      closeQuickLogModal();
    }
    quickLogCard.style.transform = '';
    touchStartY = 0;
    touchStartX = 0;
    dragY = 0;
    canSwipeToClose = false;
  });

  quickLogModal.addEventListener('touchmove', (e) => {
    if (window.innerWidth > 768) return;
    if (!quickLogCard.contains(e.target)) {
      e.preventDefault();
    }
  }, { passive: false });
}

// Mobile: swipe down on edit-log sheet to close.
if (editModalCard && editModal) {
  let touchStartY = 0;
  let touchStartX = 0;
  let dragY = 0;
  let canSwipeToClose = false;

  editModalCard.addEventListener('touchstart', (e) => {
    if (window.innerWidth > 768 || !e.touches[0]) return;
    touchStartY = e.touches[0].clientY;
    touchStartX = e.touches[0].clientX;
    dragY = 0;
    canSwipeToClose = editModalCard.scrollTop <= 0;
  }, { passive: true });

  editModalCard.addEventListener('touchmove', (e) => {
    if (!canSwipeToClose || window.innerWidth > 768 || !e.touches[0]) return;
    const deltaY = e.touches[0].clientY - touchStartY;
    const deltaX = Math.abs(e.touches[0].clientX - touchStartX);
    if (deltaY > 0 && deltaY > deltaX) {
      e.preventDefault();
      dragY = Math.min(deltaY, 110);
      editModalCard.style.transform = `translateY(${dragY * 0.35}px)`;
    }
  }, { passive: false });

  editModalCard.addEventListener('touchend', () => {
    if (window.innerWidth > 768) return;
    if (canSwipeToClose && dragY > 85) {
      closeEditLogModal();
    }
    editModalCard.style.transform = '';
    touchStartY = 0;
    touchStartX = 0;
    dragY = 0;
    canSwipeToClose = false;
  });

  editModal.addEventListener('touchmove', (e) => {
    if (window.innerWidth > 768) return;
    if (!editModalCard.contains(e.target)) {
      e.preventDefault();
    }
  }, { passive: false });
}


// Close when clicking on the dark overlay background
window.addEventListener('click', e => { 
    if (e.target.classList.contains('modal-overlay')) {
    if (quickLogModal && e.target === quickLogModal) {
      closeQuickLogModal();
      return;
    }
    if (editModal && e.target === editModal) {
      closeEditLogModal();
      return;
    }
        e.target.classList.remove('open'); 
    }
});

// ── 3. EDIT LOGIC (POPULATE FORM) ──
document.querySelectorAll('.edit-btn').forEach(btn => { 
    btn.addEventListener('click', () => { 
        document.getElementById('edit-log-id').value = btn.dataset.id || ''; 
        document.getElementById('edit-date').value = btn.dataset.date || ''; 
        document.getElementById('edit-desc').value = btn.dataset.desc || ''; 
        document.getElementById('edit-from').value = btn.dataset.from || ''; 
        document.getElementById('edit-to').value = btn.dataset.to || ''; 
        updateEditPreview(); 
    openEditLogModal(); 
    }); 
});

// ── 4. LIVE DURATION PREVIEWS ──
// Note: log-modal preview removed - modal no longer exists
const editFrom = document.getElementById('edit-from'), editTo = document.getElementById('edit-to'), editPreview = document.getElementById('edit-hrs-preview');

function updateEditPreview() { 
    if(!editFrom || !editTo || !editPreview) return; 
    const hrs = calcHrs(editFrom.value, editTo.value); 
    editPreview.textContent = hrs > 0 ? hrs.toFixed(2) + ' hrs' : '— invalid'; 
    editPreview.style.color = hrs > 0 ? '#1b4332' : '#dc2626'; 
}

function updateQuickPreview() {
  if (!quickLogFrom || !quickLogTo || !quickHrsPreview) return;
  const hrs = calcHrs(quickLogFrom.value, quickLogTo.value);
  quickHrsPreview.textContent = hrs > 0 ? hrs.toFixed(2) + ' hrs' : '— invalid';
  quickHrsPreview.style.color = hrs > 0 ? '#1b4332' : '#dc2626';
}

if(editFrom) editFrom.addEventListener('change', updateEditPreview); 
if(editTo) editTo.addEventListener('change', updateEditPreview);
if(quickLogFrom) quickLogFrom.addEventListener('change', updateQuickPreview);
if(quickLogTo) quickLogTo.addEventListener('change', updateQuickPreview);
updateQuickPreview();


// ── 5. BULK ENTRY LOGIC ──
// Toggle Excluded Days Style
document.querySelectorAll('.day-toggle input[type="checkbox"]').forEach(cb => { 
    cb.addEventListener('change', (e) => { 
        e.target.closest('.day-toggle').classList.toggle('day-toggle--excluded', e.target.checked); 
        updateRangePreview(); 
    }); 
});

// Update hidden 'To' time based on the 'Hrs/Day' input field (assumes 08:00 AM start time)
const bulkHrs = document.getElementById('bulk-hrs'), bulkToHidden = document.getElementById('bulk-to-hidden');
function updateBulkToTime() { 
    if(!bulkHrs || !bulkToHidden) return; 
    const hrs = parseFloat(bulkHrs.value) || 8; 
    const toMin = 480 + Math.round(hrs * 60); // 480 mins = 08:00 AM
    bulkToHidden.value = `${Math.floor(toMin/60).toString().padStart(2,'0')}:${(toMin%60).toString().padStart(2,'0')}`; 
    updateRangePreview(); 
}
if(bulkHrs) bulkHrs.addEventListener('change', updateBulkToTime);

// Calculate total expected days and hours
const bulkStart = document.getElementById('bulk-start'), bulkEnd = document.getElementById('bulk-end'), rangePreview = document.getElementById('bulk-range-preview');
function updateRangePreview() { 
    if(!bulkStart || !bulkEnd || !rangePreview) return; 
    if(!bulkStart.value || !bulkEnd.value) { rangePreview.textContent=''; return; } 
    
    const start = new Date(bulkStart.value); 
    const end = new Date(bulkEnd.value); 
    
    if(start > end){
        rangePreview.textContent = 'Start date must be before end date.';
        rangePreview.style.color = '#dc2626';
        return;
    } 
    
    // Grab all currently checked (excluded) day numbers
    const excluded = Array.from(document.querySelectorAll('.day-toggle input:checked')).map(cb=>parseInt(cb.value)); 
    
    let count = 0, cursor = new Date(start); 
    while(cursor <= end){
        const iso = cursor.getDay() === 0 ? 7 : cursor.getDay(); // Standardize Sunday to 7
        if(!excluded.includes(iso)) count++;
        cursor.setDate(cursor.getDate() + 1);
    } 
    
    const hrs = parseFloat(bulkHrs?.value) || 8; 
    rangePreview.style.color = '#1b4332'; 
    rangePreview.textContent = `${count} day${count !== 1 ? 's' : ''} will be filled — ${(hrs * count).toFixed(1)} hrs total`; 
}

// Trigger bulk preview updates when date changes
if(bulkStart) bulkStart.addEventListener('change', updateRangePreview); 
if(bulkEnd) bulkEnd.addEventListener('change', updateRangePreview);
</script>

<!-- Driver.js Tutorial Library -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.css">
<script src="https://cdn.jsdelivr.net/npm/driver.js@1.3.1/dist/driver.iife.js" id="driver-script"></script>

<script>
// Dashboard Tutorial for First-Time Users
(function() {
    const tutorialCompleted = <?= $user['tutorial_completed'] ?? 0 ?>;
    
    console.log('Tutorial Debug - tutorialCompleted value:', tutorialCompleted);
    console.log('Tutorial Debug - typeof tutorialCompleted:', typeof tutorialCompleted);
    
    function initTutorial() {
        console.log('Tutorial Debug - initTutorial called');
        console.log('Tutorial Debug - window.driver exists:', typeof window.driver !== 'undefined');
        
        // Check if Driver.js loaded successfully
        if (typeof window.driver === 'undefined') {
            console.warn('Driver.js failed to load from CDN. Tutorial skipped.');
            return;
        }
        
        // Only show tutorial for new users
        if (tutorialCompleted === 0) {
            console.log('Tutorial Debug - Starting tutorial...');
            const driverObj = window.driver({
            showProgress: true,
            showButtons: ['next', 'previous'],
            steps: [
                {
                    popover: {
                        title: 'Welcome to Practica!',
                        description: 'Let\'s take a quick tour of your OJT tracking dashboard. You can skip this anytime by pressing ESC or clicking "Close".',
                    }
                },
                {
                  element: '#hero-today-status',
                    popover: {
                        title: 'Today\'s Status',
                    description: 'See whether today is logged and jump directly to Time Logs when you need to add or review entries.',
                        side: 'left',
                        align: 'start'
                    }
                },
                {
                    element: '.dash-log-table',
                    popover: {
                        title: 'Recent Logs',
                        description: 'View your recent log entries. You can edit or delete them by clicking the action icons.',
                        side: 'top',
                        align: 'start'
                    }
                },
                {
                    element: '.table-link',
                    popover: {
                        title: 'Time Logs',
                        description: 'Click here to access Time Logs for bulk entry or historical logging.',
                        side: 'bottom',
                        align: 'start'
                    }
                },
                {
                    element: '.dash-stat-card--progress',
                    popover: {
                        title: 'Progress Tracking',
                        description: 'Monitor your completion progress toward your required hours.',
                        side: 'right',
                        align: 'start'
                    }
                },
                {
                    popover: {
                        title: 'You\'re All Set!',
                        description: 'You can restart this tutorial anytime from Settings. Happy tracking!',
                    }
                }
            ],
            onDestroyStarted: function() {
                // Mark tutorial as completed (whether finished or skipped)
                fetch('dashboard.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=complete_tutorial'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Tutorial marked as completed');
                    }
                })
                .catch(error => console.warn('Failed to save tutorial state:', error));
                
                // Allow the destroy to proceed
                driverObj.destroy();
            }
        });
        
        // Start the tutorial
        driverObj.drive();
    }
    }
    
    // Wait for Driver.js script to load, then initialize
    const driverScript = document.getElementById('driver-script');
    if (driverScript) {
        driverScript.addEventListener('load', function() {
            console.log('Tutorial Debug - Driver.js script loaded');
            // Small delay to ensure driver object is available
            setTimeout(initTutorial, 100);
        });
        
        // If script already loaded (cached), run immediately
        if (driverScript.readyState === 'complete') {
            console.log('Tutorial Debug - Driver.js already loaded from cache');
            setTimeout(initTutorial, 100);
        }
    } else {
        console.warn('Tutorial Debug - Driver script element not found');
    }
})();
</script>

</div>

<?php include 'includes/footer.php'; ?>