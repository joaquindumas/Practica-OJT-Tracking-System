<?php
require_once 'includes/config.php'; require_login();
$user = current_user(); $active_page = 'dashboard'; $log_errors = [];

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

$user = current_user(); $all_logs = $user['logs'] ?? []; $logged = total_logged($user); $required = $user['required_hours'] ?? 500; $remaining = hours_remaining($user); $pct = completion_percent($user); $est_date = estimated_completion($user); $est_basis = estimated_basis($user); $allowance = $user['allowance_per_day'] ?? 150;
usort($all_logs, fn($a, $b) => strtotime($b['date']) - strtotime($a['date'])); $recent_logs = array_slice($all_logs, 0, 4);
$total_days = count(array_unique(array_column($all_logs, 'date'))); $avg_hrs_day = $total_days > 0 ? round($logged / $total_days, 1) : 0; $projected_days = $avg_hrs_day > 0 ? (int) ceil($remaining / $avg_hrs_day) : 0;

include 'includes/header.php';
?>

<div class="content">
<div class="dash-wrap">
  <div class="dash-hero">
    <div class="dash-hero-content">
      <div class="dash-hero-eyebrow">Overview</div>
      <h1 class="dash-hero-title">Welcome back, <?= e(explode(' ', $user['name'] ?? $user['username'])[0]) ?> 👋</h1>
      <p class="dash-hero-sub">Track and manage your daily internship progress.</p>
    </div>
    <div class="dash-hero-actions">
      <button class="btn dash-btn-ghost" id="open-bulk-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
        Bulk Log
      </button>
      <button class="btn dash-btn-solid" id="open-modal-btn">
        <svg viewBox="0 0 24 24" fill="currentColor" class="icon-sm"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
        New Log Entry
      </button>
    </div>

  </div>

  <?php
    $total_allowance     = $total_days * $allowance;
    $earned_allowance    = round($logged / ($avg_hrs_day ?: 8)) * $allowance;
    $remaining_allowance = max(0, $projected_days * $allowance);
    $total_projected     = $total_allowance + $remaining_allowance;
    $allowance_pct       = $total_projected > 0 ? min(100, ($total_allowance / $total_projected) * 100) : 0;
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
      <div class="dash-stat-num">₱<?= number_format($total_projected, 2) ?> <span class="dash-stat-denom">Total</span></div>
      
      <div class="allowance-graph-container">
        <div class="allowance-chart" style="background: conic-gradient(#6ee7b7 <?= $allowance_pct ?>%, rgba(255,255,255,0.1) 0);">
          <div class="allowance-chart-inner">
            <span><?= round($allowance_pct) ?>%</span>
          </div>
        </div>
        
        <div class="allowance-legend">
          <div class="legend-item">
            <span class="legend-dot used"></span>
            <div class="legend-details">
              <span class="legend-label">Used</span>
              <span class="legend-value">₱<?= number_format($total_allowance, 2) ?></span>
            </div>
          </div>
          <div class="legend-item">
            <span class="legend-dot rem"></span>
            <div class="legend-details">
              <span class="legend-label">Remaining</span>
              <span class="legend-value">₱<?= number_format($remaining_allowance, 2) ?></span>
            </div>
          </div>
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
                            <div class="table-empty-sub">Click "New Log Entry" to get started</div>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($recent_logs as $log): ?>
                <tr class="dash-log-row">
                    <td class="font-600"><?= e(date('M j, Y', strtotime($log['date']))) ?></td>
                    <td><?= e($log['description'] ?: '—') ?></td>
                    <td class="tabular-nums"><?= e(date('g:i A', strtotime($log['from']))) ?></td>
                    <td class="tabular-nums"><?= e(date('g:i A', strtotime($log['to']))) ?></td>
                    <td><span class="highlight-hrs"><?= e(number_format($log['hours'], 1)) ?></span></td>
                    <td>
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

    <div class="dash-right">
      <div class="status-card">
        <div class="status-header">
            <div>
                <div class="status-title">Today's Status</div>
                <div class="status-date"><?= date('D, M j, Y') ?></div>
            </div>
          <?php $today_str = date('Y-m-d'); $today_log = null; $today_hrs = 0; foreach ($all_logs as $l) { if ($l['date'] === $today_str) { $today_log = $l; $today_hrs += $l['hours']; } } ?>
          <span class="status-badge <?= $today_log ? 'logged' : 'pending' ?>"><?= $today_log ? 'Logged' : 'Pending' ?></span>
        </div>
        
        <div class="status-body">
          <?php if ($today_log): ?>
            <div class="status-success-state">
              <div class="status-success-icon">
                <svg viewBox="0 0 24 24" fill="white" class="icon-lg"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
              </div>
              <div class="status-success-title">Log Recorded</div>
              <div class="status-success-sub"><strong><?= number_format($today_hrs, 1) ?> hours</strong> added today</div>
            </div>
          <?php else: ?>
            <form method="POST" action="dashboard.php" class="status-form">
                <input type="hidden" name="action" value="log_hours" />
                <input type="hidden" name="date" value="<?= date('Y-m-d') ?>" />
                <textarea name="description" placeholder="Briefly describe your tasks..."></textarea>
                <div class="today-time-row">
                    <div class="today-time-group"><label>From</label><input type="time" name="from" value="08:00"></div>
                    <div class="today-time-group"><label>To</label><input type="time" name="to" value="16:00"></div>
                </div>
              <button type="submit" class="btn-submit-status">Save Log</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal-overlay" id="log-modal">
  <div class="modal-card">
    <div class="modal-title-serif">New Log Entry</div>
    <div class="modal-subtitle">Record your OJT hours for a specific day.</div>
    <?php foreach ($log_errors as $err): ?><span class="form-error" style="color:#ef4444;font-size:0.75rem;display:block;margin-bottom:0.625rem;"><?= e($err) ?></span><?php endforeach; ?>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="action" value="log_hours" />
      <div class="form-group" style="margin-bottom:1rem;"><label class="form-label-styled">Date</label><input class="form-input-styled" type="date" id="log-date" name="date" value="<?= date('Y-m-d') ?>" required /></div>
      <div class="form-group" style="margin-bottom:1rem;"><label class="form-label-styled">Description (Optional)</label><input class="form-input-styled" type="text" id="log-desc" name="description" placeholder="What did you work on?" /></div>
      <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1rem;">
        <div class="form-group" style="margin:0;"><label class="form-label-styled">From</label><input class="form-input-styled" type="time" id="log-from" name="from" value="08:00" required /></div>
        <div class="form-group" style="margin:0;"><label class="form-label-styled">To</label><input class="form-input-styled" type="time" id="log-to" name="to" value="16:00" required /></div>
      </div>
      <div style="font-size:0.75rem;color:var(--text3);margin-bottom:1.5rem;font-weight:600;">Duration: <strong id="hrs-preview" style="color:#1b4332;">8.00 hrs</strong></div>
      <div class="modal-actions" style="display:flex;justify-content:flex-end;gap:0.625rem;"><button type="button" class="btn btn-secondary" id="modal-close-btn">Cancel</button><button type="submit" class="btn btn-primary">Save Log</button></div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="edit-modal">
  <div class="modal-card">
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
      <div class="modal-actions" style="display:flex;justify-content:flex-end;gap:0.625rem;"><button type="button" class="btn btn-secondary" id="edit-close-btn">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
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
      <div style="display:grid;grid-template-columns:1fr 1fr 5.625rem;gap:0.75rem;margin-bottom:1.25rem;">
        <div class="form-group" style="margin:0;"><label class="form-label-styled">From</label><input class="form-input-styled" type="date" name="bulk_start" id="bulk-start" required /></div>
        <div class="form-group" style="margin:0;"><label class="form-label-styled">To</label><input class="form-input-styled" type="date" name="bulk_end" id="bulk-end" required /></div>
        <div class="form-group" style="margin:0;"><label class="form-label-styled">Hrs/Day</label><input class="form-input-styled" type="number" name="bulk_hrs" id="bulk-hrs" value="8" min="0.5" max="24" step="0.5" required /></div>
      </div>
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
const logModal = document.getElementById('log-modal');
const editModal = document.getElementById('edit-modal');
const bulkModal = document.getElementById('bulk-modal');
const dayModal = document.getElementById('day-modal');

// Dashboard Trigger Buttons (Ensure your dashboard buttons have these IDs)
document.getElementById('open-modal-btn')?.addEventListener('click', () => logModal.classList.add('open'));
document.getElementById('open-bulk-btn')?.addEventListener('click', () => bulkModal.classList.add('open'));

// Close Buttons inside Modals
document.getElementById('modal-close-btn')?.addEventListener('click', () => logModal.classList.remove('open'));
document.getElementById('edit-close-btn')?.addEventListener('click', () => editModal.classList.remove('open'));
document.getElementById('bulk-close-btn')?.addEventListener('click', () => bulkModal.classList.remove('open'));
document.getElementById('day-modal-close')?.addEventListener('click', () => dayModal?.classList.remove('open'));

// Day Modal 'New Log' action
document.getElementById('day-modal-add')?.addEventListener('click', () => {
    dayModal?.classList.remove('open');
    logModal.classList.add('open');
});

// Close when clicking on the dark overlay background
window.addEventListener('click', e => { 
    if (e.target.classList.contains('modal-overlay')) {
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
        editModal.classList.add('open'); 
    }); 
});

// ── 4. LIVE DURATION PREVIEWS ──
const logFrom = document.getElementById('log-from'), logTo = document.getElementById('log-to'), hrsPreview = document.getElementById('hrs-preview');
const editFrom = document.getElementById('edit-from'), editTo = document.getElementById('edit-to'), editPreview = document.getElementById('edit-hrs-preview');

function updateHrsPreview() { 
    if(!logFrom || !logTo || !hrsPreview) return; 
    const hrs = calcHrs(logFrom.value, logTo.value); 
    hrsPreview.textContent = hrs > 0 ? hrs.toFixed(2) + ' hrs' : '— invalid'; 
    hrsPreview.style.color = hrs > 0 ? '#1b4332' : '#dc2626'; 
}

function updateEditPreview() { 
    if(!editFrom || !editTo || !editPreview) return; 
    const hrs = calcHrs(editFrom.value, editTo.value); 
    editPreview.textContent = hrs > 0 ? hrs.toFixed(2) + ' hrs' : '— invalid'; 
    editPreview.style.color = hrs > 0 ? '#1b4332' : '#dc2626'; 
}

if(logFrom) logFrom.addEventListener('change', updateHrsPreview); 
if(logTo) logTo.addEventListener('change', updateHrsPreview);
if(editFrom) editFrom.addEventListener('change', updateEditPreview); 
if(editTo) editTo.addEventListener('change', updateEditPreview);

// Initialize standard new log preview
updateHrsPreview();

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
</div>