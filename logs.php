<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/config.php'; require_login();

$user = current_user(); $active_page = 'logs'; $log_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'log_hours') {
        $date = $_POST['date'] ?? ''; $desc = trim($_POST['description'] ?? ''); $from = $_POST['from'] ?? ''; $to = $_POST['to'] ?? '';
        if (!$date || !$from || !$to) { $log_errors[] = 'Please fill in date, from, and to.'; } else {
            [$fh, $fm] = array_map('intval', explode(':', $from)); [$th, $tm] = array_map('intval', explode(':', $to));
            $hours = (($th * 60 + $tm) - ($fh * 60 + $fm)) / 60;
            if ($hours <= 0) { $log_errors[] = '"To" time must be after "From" time.'; } else {
                add_log($user['id'], ['id' => generate_id(), 'date' => $date, 'description' => $desc, 'from' => $from, 'to' => $to, 'hours' => round($hours, 4), 'created_at' => date('Y-m-d H:i:s')]);
                set_flash('success', 'Hours logged successfully!'); header('Location: logs.php'); exit;
            }
        }
    }
    if ($action === 'edit_log') {
        $edit_id = $_POST['log_id'] ?? ''; $date = $_POST['date'] ?? ''; $desc = trim($_POST['description'] ?? ''); $from = $_POST['from'] ?? ''; $to = $_POST['to'] ?? '';
        if (!$date || !$from || !$to) { set_flash('error', 'Please fill in date, from, and to.'); header('Location: logs.php'); exit; }
        [$fh, $fm] = array_map('intval', explode(':', $from)); [$th, $tm] = array_map('intval', explode(':', $to));
        $hours = (($th * 60 + $tm) - ($fh * 60 + $fm)) / 60;
        if ($hours <= 0) { set_flash('error', '"To" time must be after "From" time.'); header('Location: logs.php'); exit; }
        update_log($edit_id, $user['id'], ['date' => $date, 'description' => $desc, 'from' => $from, 'to' => $to, 'hours' => round($hours, 4)]);
        set_flash('success', 'Log updated successfully!'); header('Location: logs.php'); exit;
    }
    if ($action === 'delete_log') {
        delete_log($_POST['log_id'] ?? '', $user['id']); set_flash('success', 'Log deleted.'); header('Location: logs.php'); exit;
    }
    if ($action === 'bulk_delete') {
        $ids = $_POST['log_ids'] ?? []; foreach ($ids as $id) { delete_log($id, $user['id']); }
        set_flash('success', count($ids) . " logs deleted."); header('Location: logs.php'); exit;
    }
    if ($action === 'bulk_log') {
        $start = $_POST['bulk_start'] ?? ''; $end = $_POST['bulk_end'] ?? ''; $hrs = (float) ($_POST['bulk_hrs'] ?? 8); $desc = trim($_POST['bulk_desc'] ?? ''); $exclude_days = array_map('intval', $_POST['exclude_days'] ?? []);
        $from_time = '08:00'; $total_min = (int) ($hrs * 60); $to_time = sprintf('%02d:%02d', intdiv(480 + $total_min, 60), (480 + $total_min) % 60);
        if ($start && $end && $start <= $end) {
            $bulk_logs = []; $cursor = strtotime($start); $endTs = strtotime($end);
            while ($cursor <= $endTs) {
                $dow = (int) date('N', $cursor);
                if (!in_array($dow, $exclude_days)) { $bulk_logs[] = ['date' => date('Y-m-d', $cursor), 'from' => $from_time, 'to' => $to_time, 'description' => $desc]; }
                $cursor = strtotime('+1 day', $cursor);
            }
            $count = bulk_add_logs($user['id'], $bulk_logs); set_flash('success', "{$count} log" . ($count !== 1 ? 's' : '') . " added successfully!");
        } else { set_flash('error', 'Please check your date range.'); }
        header('Location: logs.php'); exit;
    }
}

$user = current_user(); $all_logs = $user['logs'] ?? []; usort($all_logs, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
$log_map = []; foreach ($all_logs as $l) { $log_map[$l['date']][] = $l; }

$logs_per_page = 6;
$current_page = max(1, intval($_GET['page'] ?? 1));
$total_logs = count($all_logs);
$is_first_time_logs_user = ($total_logs === 0);
$total_pages = ceil($total_logs / $logs_per_page);
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
$paginated_logs = array_slice($all_logs, ($current_page - 1) * $logs_per_page, $logs_per_page);

$month_param = $_GET['month'] ?? date('Y-m'); if (!preg_match('/^\d{4}-\d{2}$/', $month_param)) $month_param = date('Y-m');
$force_tutorial = (($_GET['tutorial'] ?? '') === '1');
[$cal_year, $cal_month] = explode('-', $month_param); $cal_year = (int) $cal_year; $cal_month = (int) $cal_month;
$first_date_ts = mktime(0, 0, 0, $cal_month, 1, $cal_year); 
$prev_month = date('Y-m', mktime(0, 0, 0, $cal_month - 1, 1, $cal_year)); 
$next_month = date('Y-m', mktime(0, 0, 0, $cal_month + 1, 1, $cal_year));
$today = date('Y-m-d'); 
$first_dow_zero_indexed = (int) date('w', $first_date_ts); 
$days_in_month = (int) date('t', $first_date_ts);
$calendar_start_ts = strtotime('-' . $first_dow_zero_indexed . ' days', $first_date_ts);
$calendar_total_cells = 42;

include 'includes/header.php';
?>

<link rel="stylesheet" href="css/logs.css">

<div class="content">
<div class="dash-wrap">
    
    <div class="dash-hero logs-hero">
        <div class="dash-hero-content logs-hero-content">
            <div class="dash-hero-eyebrow">OJT HOURS</div>
            <h1 class="dash-hero-title logs-hero-title">Time Logs <span class="page-title-icon">🕓</span></h1>
            <p class="dash-hero-sub logs-hero-sub">Track and manage your daily on-the-job training hours.</p>
        </div>
        <div class="dash-hero-actions logs-hero-actions">
            <button type="button" class="btn btn-outline" id="start-log-tutorial-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                <span class="btn-label">Take a Tour </span>
            </button>
            <button type="button" class="btn btn-outline" id="view-toggle-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                <span class="btn-label">View Calendar</span>
            </button>
            <button class="btn btn-secondary" id="open-bulk-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                <span class="btn-label">Bulk Log</span>
            </button>
            <button class="btn btn-primary" id="open-modal-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                <span class="btn-label">New Log Entry</span>
            </button>
        </div>
    </div>

    <div id="calendar-container" style="display:none;">
        <div class="new-cal-card">
            <div class="new-cal-header">
                <h2><?= date('F Y', $first_date_ts) ?></h2>
                <div class="new-cal-nav">
                    <a href="?month=<?= date('Y-m') ?>" class="btn-today">Today</a>
                    <a href="?month=<?= $prev_month ?>" style="width:2em;height:2em;border:1px solid var(--border);border-radius:0.5rem;display:flex;align-items:center;justify-content:center;color:#64748b;text-decoration:none;font-size:clamp(0.875rem,1.2vw,1rem);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:1em;height:1em;"><polyline points="15 18 9 12 15 6"></polyline></svg>
                    </a>
                    <a href="?month=<?= $next_month ?>" style="width:2em;height:2em;border:1px solid var(--border);border-radius:0.5rem;display:flex;align-items:center;justify-content:center;color:#64748b;text-decoration:none;font-size:clamp(0.875rem,1.2vw,1rem);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:1em;height:1em;"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </a>
                </div>
            </div>
            <div class="new-cal-wrapper">
                <div class="new-cal-grid">
                    <div class="new-cal-dow">SUN</div><div class="new-cal-dow">MON</div><div class="new-cal-dow">TUE</div><div class="new-cal-dow">WED</div><div class="new-cal-dow">THU</div><div class="new-cal-dow">FRI</div><div class="new-cal-dow">SAT</div>
                    <?php for ($i = 0; $i < $calendar_total_cells; $i++):
                        $cell_ts = strtotime('+' . $i . ' days', $calendar_start_ts);
                        $date_str = date('Y-m-d', $cell_ts);
                        $cell_day = (int) date('j', $cell_ts);
                        $cell_month = (int) date('n', $cell_ts);
                        $is_current_month = ($cell_month === $cal_month);
                        $is_adjacent_month = !$is_current_month;
                        $day_logs = $log_map[$date_str] ?? [];
                        $is_logged = count($day_logs) > 0;
                        $total_day = array_sum(array_column($day_logs, 'hours'));
                        $is_today = ($date_str === $today);
                    ?>
                        <div class="new-cal-cell<?= $is_today ? ' new-cal-cell--today' : '' ?><?= $is_adjacent_month ? ' new-cal-cell--adjacent' : '' ?>" onclick="handleDayClick('<?= $date_str ?>', <?= $is_logged ? 'true' : 'false' ?>)">
                            <div class="new-cal-date-row" style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.5rem;gap:0.5rem;">
                                <div class="new-cal-date-stack">
                                    <span class="new-cal-date<?= $is_today ? ' new-cal-date--today' : '' ?><?= $is_adjacent_month ? ' new-cal-date--adjacent' : '' ?>" <?= ($date_str == date('Y-m-d')) ? 'style="color:#1b4332;font-weight:800;"' : '' ?>><?= $cell_day ?></span>
                                    <?php if ($is_today): ?><span class="new-cal-today-badge">TODAY</span><?php endif; ?>
                                </div>
                                <?php if ($is_logged):
                                    $day_log_ids = implode(',', array_column($day_logs, 'id'));
                                ?>
                                    <input type="checkbox" class="cal-checkbox custom-checkbox" value="<?= e($day_log_ids) ?>" onclick="event.stopPropagation(); handleCalCheckbox(this);" />
                                <?php endif; ?>
                            </div>
                            <?php if ($is_logged): ?>
                                <div class="pill-logged<?= $is_adjacent_month ? ' pill-logged--adjacent' : '' ?>"><?= number_format($total_day, 1) ?> hrs</div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="list-container">
        <div class="list-table-wrap">
            <div class="table-scroll">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;"><input type="checkbox" id="select-all" class="custom-checkbox" /></th>
                            <th style="width: 140px;">DATE</th>
                            <th>DESCRIPTION</th>
                            <th style="width: 120px;">FROM</th>
                            <th style="width: 120px;">TO</th>
                            <th class="col-hrs" style="width: 80px;">HRS</th>
                            <th style="width: 100px; text-align: center;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($paginated_logs)): ?><tr class="log-row-empty"><td colspan="7" style="text-align:center;padding:3rem;color:#888;">No logs found.</td></tr><?php else: ?>
                        <?php foreach ($paginated_logs as $log): ?>
                        <tr>
                            <td class="log-cell log-cell--select" style="text-align:center;"><input type="checkbox" class="row-checkbox custom-checkbox" value="<?= e($log['id']) ?>" /></td>
                            <td class="log-cell log-cell--date" style="color:var(--text);"><?= e(date('M j, Y', strtotime($log['date']))) ?></td>
                            <td class="log-cell log-cell--desc"><?= e(strlen($log['description']) > 30 ? substr($log['description'], 0, 30) . '...' : ($log['description'] ?: '—')) ?></td>
                            <td class="log-cell log-cell--from"><?= e(date('g:i A', strtotime($log['from']))) ?></td>
                            <td class="log-cell log-cell--to"><?= e(date('g:i A', strtotime($log['to']))) ?></td>
                            <td class="log-cell log-cell--hours col-hrs"><span class="highlight-hrs"><?= e(number_format($log['hours'], 1)) ?></span></td>
                            <td class="log-cell log-cell--actions" style="text-align:center;">
                                <button class="icon-btn edit-btn" data-id="<?= e($log['id']) ?>" data-date="<?= e($log['date']) ?>" data-desc="<?= e($log['description'] ?? '') ?>" data-from="<?= e($log['from']) ?>" data-to="<?= e($log['to']) ?>" title="Edit">
                                    <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_pages > 1): ?>
            <div class="pagination-footer">
                <?php if ($current_page > 1): ?>
                    <a href="?month=<?= $month_param ?>&page=<?= $current_page - 1 ?>" class="page-btn"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg></a>
                <?php else: ?>
                    <span class="page-btn disabled"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg></span>
                <?php endif; ?>
                <span class="page-btn active"><?= $current_page ?></span>
                <?php if ($current_page < $total_pages): ?>
                    <a href="?month=<?= $month_param ?>&page=<?= $current_page + 1 ?>" class="page-btn"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg></a>
                <?php else: ?>
                    <span class="page-btn disabled"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<div class="floating-action-bar" id="bulk-delete-bar">
    <span class="selected-count" id="bulk-delete-count">0 selected</span>
    <button type="button" class="btn-deselect" id="bulk-deselect">Deselect all</button>
    <form method="POST" action="logs.php" id="bulk-delete-form" style="margin:0;">
        <input type="hidden" name="action" value="bulk_delete" />
        <div id="bulk-delete-ids"></div>
        <button type="submit" class="btn-delete">
            <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            Delete
        </button>
    </form>
</div>

<div class="modal-overlay" id="log-modal">
    <div class="modal-card modal-card--add-log">
    <div class="modal-title-serif">New Log Entry</div>
    <div class="modal-subtitle">Record your OJT hours for a specific day.</div>
    <?php foreach ($log_errors as $err): ?><span class="form-error" style="color:#ef4444;font-size:0.75rem;display:block;margin-bottom:0.625rem;"><?= e($err) ?></span><?php endforeach; ?>
    <form method="POST" action="logs.php">
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
    <div class="modal-card modal-card--edit-log">
    <div class="modal-title-serif">Edit Log</div>
    <div class="modal-subtitle">Update the details for this log entry.</div>
    <form method="POST" action="logs.php">
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
        <div class="modal-card modal-card--bulk-log">
    <div class="modal-title-serif">Bulk Entry</div>
    <div class="modal-subtitle">Fill past days automatically. Already-logged days are skipped.</div>
    <form method="POST" action="logs.php"><input type="hidden" name="action" value="bulk_log" />
            <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:0.75rem;">
        <div class="form-group" style="margin:0;"><label class="form-label-styled">From</label><input class="form-input-styled" type="date" name="bulk_start" id="bulk-start" required /></div>
        <div class="form-group" style="margin:0;"><label class="form-label-styled">To</label><input class="form-input-styled" type="date" name="bulk_end" id="bulk-end" required /></div>
      </div>
      <div class="form-group" style="margin-bottom:1.25rem;"><label class="form-label-styled">Hrs/Day</label><input class="form-input-styled" type="number" name="bulk_hrs" id="bulk-hrs" value="8" min="0.5" max="24" step="0.5" required /></div>
      <input type="hidden" name="bulk_from" value="08:00" /><input type="hidden" name="bulk_to" id="bulk-to-hidden" value="16:00" />
      <div class="form-group" style="margin-bottom:1.25rem;">
                <label class="form-label-styled" style="margin-bottom:0.625rem;">Exclude Days <span class="bulk-exclude-hint">(SELECTED = SKIP)</span></label>
                <div class="bulk-day-toggle-row" style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <?php foreach (['MON','TUE','WED','THU','FRI','SAT','SUN'] as $i => $day): ?>
                <label class="day-toggle <?= in_array($day, ['SAT','SUN']) ? 'day-toggle--excluded' : '' ?>">
                    <input type="checkbox" name="exclude_days[]" value="<?= $i + 1 ?>" <?= in_array($day, ['SAT','SUN']) ? 'checked' : '' ?> style="display:none;" />
                    <span><?= $day ?></span>
                </label>
            <?php endforeach; ?>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:1.25rem;"><label class="form-label-styled">Description (optional)</label><input class="form-input-styled" type="text" name="bulk_desc" placeholder="e.g. OJT at company" /></div>
            <div id="bulk-range-preview" class="bulk-range-preview"></div>
            <div class="modal-actions"><button type="button" class="btn btn-secondary" id="bulk-close-btn">Cancel</button><button type="submit" class="btn btn-primary">Fill Range</button></div>
    </form>
  </div>
</div>

<div id="logs-tutorial-overlay" class="tutorial-overlay" aria-hidden="true">
    <div class="tutorial-backdrop"></div>
    <div class="tutorial-spotlight" id="tutorial-spotlight" aria-hidden="true"></div>
    <div class="tutorial-card" id="logs-tutorial-card" role="dialog" aria-modal="true" aria-labelledby="tutorial-step-title">
        <div class="tutorial-step-count" id="tutorial-step-count">Step 1 of 1</div>
        <h3 class="tutorial-step-title" id="tutorial-step-title">Tutorial</h3>
        <p class="tutorial-step-body" id="tutorial-step-body"></p>
        <div class="tutorial-actions">
            <button type="button" class="btn btn-secondary" id="tutorial-prev-btn">Back</button>
            <button type="button" class="btn btn-outline" id="tutorial-skip-btn">Skip</button>
            <button type="button" class="btn btn-primary" id="tutorial-next-btn">Next</button>
        </div>
    </div>
</div>

<script>
// ── DATA AND UTILS ──
function formatTime(t) { if (!t) return ''; const [h, m] = t.split(':').map(Number); const ampm = h >= 12 ? 'PM' : 'AM'; return `${h % 12 || 12}:${m.toString().padStart(2, '0')} ${ampm}`; }
function calcHrs(from, to) { if (!from || !to) return 0; const [fh, fm] = from.split(':').map(Number); const [th, tm] = to.split(':').map(Number); return ((th * 60 + tm) - (fh * 60 + fm)) / 60; }
const logData = <?php $js_map = []; foreach ($log_map as $date => $dlogs) { if (substr($date, 0, 7) !== $month_param) continue; $js_map[$date] = array_map(fn($l) => ['id' => $l['id'], 'from' => $l['from'], 'to' => $l['to'], 'hours' => $l['hours'], 'desc' => $l['description'] ?? '', 'date' => $l['date']], $dlogs); } echo json_encode($js_map); ?>;

// ── VIEW TOGGLE ──
const viewListBtn = document.getElementById('view-toggle-btn'); 
const viewCal = document.getElementById('calendar-container'); 
const viewList = document.getElementById('list-container');
const iconList = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>`;
const iconCal = `<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>`;

function clearAllSelections() {
    document.querySelectorAll('.row-checkbox, .cal-checkbox, #select-all').forEach(cb => cb.checked = false);
    document.querySelectorAll('.new-cal-cell').forEach(cell => cell.classList.remove('cal-selected'));
    updateBulkBar();
}

function setToggleBtn(mode) {
    const label = viewListBtn.querySelector('.btn-label');
    const svg = viewListBtn.querySelector('svg');
    if (mode === 'list') {
        // currently showing list, button should offer "View Calendar"
        svg.outerHTML; // keep existing
        viewListBtn.innerHTML = iconCal + '<span class="btn-label">View Calendar</span>';
    } else {
        viewListBtn.innerHTML = iconList + '<span class="btn-label">View List</span>';
    }
}

function toggleView() { 
    const current = localStorage.getItem('logs_view') || 'list'; 
    if (current === 'list') { 
        viewCal.style.display = 'block'; viewList.style.display = 'none';
        setToggleBtn('calendar');
        localStorage.setItem('logs_view', 'calendar'); 
    } else { 
        viewCal.style.display = 'none'; viewList.style.display = 'block';
        setToggleBtn('list');
        localStorage.setItem('logs_view', 'list'); 
    } 
    clearAllSelections();
}

if (localStorage.getItem('logs_view') === 'calendar') { 
    viewCal.style.display = 'block'; viewList.style.display = 'none';
    setToggleBtn('calendar');
} else {
    viewCal.style.display = 'none'; viewList.style.display = 'block';
    setToggleBtn('list');
}
viewListBtn.addEventListener('click', toggleView);

// ── CALENDAR CLICKS ──
function handleDayClick(date, isLogged) { 
    if (isLogged) { openDayModal(date); } 
    else { document.getElementById('log-date').value = date; openLogModalSheet(); }
}

function handleCalCheckbox(cb) {
    const cell = cb.closest('.new-cal-cell');
    if (cb.checked) { cell.classList.add('cal-selected'); } 
    else { cell.classList.remove('cal-selected'); }
    updateBulkBar();
}

// ── MODAL HANDLING ──
const logModal = document.getElementById('log-modal');
const logModalCard = logModal?.querySelector('.modal-card--add-log') || null;
const logForm = logModal?.querySelector('form') || null;
const editModal = document.getElementById('edit-modal');
const editModalCard = editModal?.querySelector('.modal-card--edit-log') || null;
const editForm = editModal?.querySelector('form') || null;
const bulkModal = document.getElementById('bulk-modal');
const bulkModalCard = bulkModal?.querySelector('.modal-card--bulk-log') || null;
const bulkForm = bulkModal?.querySelector('form') || null;
const dayModal = document.getElementById('day-modal');

let logsPageScrollY = 0;
function lockLogsPageScroll() {
    if (window.innerWidth > 768) return;
    logsPageScrollY = window.scrollY || window.pageYOffset || 0;
    document.documentElement.classList.add('quick-log-open');
    document.body.classList.add('quick-log-open');
    document.body.style.position = 'fixed';
    document.body.style.top = `-${logsPageScrollY}px`;
    document.body.style.left = '0';
    document.body.style.right = '0';
    document.body.style.width = '100%';
}

function unlockLogsPageScroll() {
    if (window.innerWidth > 768) return;
    document.documentElement.classList.remove('quick-log-open');
    document.body.classList.remove('quick-log-open');
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.left = '';
    document.body.style.right = '';
    document.body.style.width = '';
    window.scrollTo(0, logsPageScrollY);
}

function openLogModalSheet() {
    logModal?.classList.add('open');
    lockLogsPageScroll();
}

function closeLogModalSheet() {
    logModal?.classList.remove('open');
    unlockLogsPageScroll();
}

function openEditModalSheet() {
    editModal?.classList.add('open');
    lockLogsPageScroll();
}

function closeEditModalSheet() {
    editModal?.classList.remove('open');
    unlockLogsPageScroll();
}

function openBulkModalSheet() {
    bulkModal?.classList.add('open');
    lockLogsPageScroll();
}

function closeBulkModalSheet() {
    bulkModal?.classList.remove('open');
    unlockLogsPageScroll();
}

document.getElementById('open-modal-btn')?.addEventListener('click', () => openLogModalSheet());
document.getElementById('modal-close-btn')?.addEventListener('click', () => closeLogModalSheet());
document.getElementById('open-bulk-btn')?.addEventListener('click', () => openBulkModalSheet());
document.getElementById('bulk-close-btn')?.addEventListener('click', () => closeBulkModalSheet());
document.getElementById('edit-close-btn')?.addEventListener('click', () => closeEditModalSheet());
window.addEventListener('click', e => {
    if (e.target.classList.contains('modal-overlay')) {
        if (logModal && e.target === logModal) {
            closeLogModalSheet();
            return;
        }
        if (editModal && e.target === editModal) {
            closeEditModalSheet();
            return;
        }
        if (bulkModal && e.target === bulkModal) {
            closeBulkModalSheet();
            return;
        }
        e.target.classList.remove('open');
    }
});
logForm?.addEventListener('submit', () => unlockLogsPageScroll());
editForm?.addEventListener('submit', () => unlockLogsPageScroll());
bulkForm?.addEventListener('submit', () => unlockLogsPageScroll());

if (logModalCard && logModal) {
    let touchStartY = 0;
    let touchStartX = 0;
    let dragY = 0;
    let canSwipeToClose = false;

    logModalCard.addEventListener('touchstart', (e) => {
        if (window.innerWidth > 768 || !e.touches[0]) return;
        touchStartY = e.touches[0].clientY;
        touchStartX = e.touches[0].clientX;
        dragY = 0;
        canSwipeToClose = logModalCard.scrollTop <= 0;
    }, { passive: true });

    logModalCard.addEventListener('touchmove', (e) => {
        if (!canSwipeToClose || window.innerWidth > 768 || !e.touches[0]) return;
        const deltaY = e.touches[0].clientY - touchStartY;
        const deltaX = Math.abs(e.touches[0].clientX - touchStartX);
        if (deltaY > 0 && deltaY > deltaX) {
            e.preventDefault();
            dragY = Math.min(deltaY, 110);
            logModalCard.style.transform = `translateY(${dragY * 0.35}px)`;
        }
    }, { passive: false });

    logModalCard.addEventListener('touchend', () => {
        if (window.innerWidth > 768) return;
        if (canSwipeToClose && dragY > 85) {
            closeLogModalSheet();
        }
        logModalCard.style.transform = '';
        touchStartY = 0;
        touchStartX = 0;
        dragY = 0;
        canSwipeToClose = false;
    });

    logModal.addEventListener('touchmove', (e) => {
        if (window.innerWidth > 768) return;
        if (!logModalCard.contains(e.target)) e.preventDefault();
    }, { passive: false });
}

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
            closeEditModalSheet();
        }
        editModalCard.style.transform = '';
        touchStartY = 0;
        touchStartX = 0;
        dragY = 0;
        canSwipeToClose = false;
    });

    editModal.addEventListener('touchmove', (e) => {
        if (window.innerWidth > 768) return;
        if (!editModalCard.contains(e.target)) e.preventDefault();
    }, { passive: false });
}

if (bulkModalCard && bulkModal) {
    let touchStartY = 0;
    let touchStartX = 0;
    let dragY = 0;
    let canSwipeToClose = false;

    bulkModalCard.addEventListener('touchstart', (e) => {
        if (window.innerWidth > 768 || !e.touches[0]) return;
        touchStartY = e.touches[0].clientY;
        touchStartX = e.touches[0].clientX;
        dragY = 0;
        canSwipeToClose = bulkModalCard.scrollTop <= 0;
    }, { passive: true });

    bulkModalCard.addEventListener('touchmove', (e) => {
        if (!canSwipeToClose || window.innerWidth > 768 || !e.touches[0]) return;
        const deltaY = e.touches[0].clientY - touchStartY;
        const deltaX = Math.abs(e.touches[0].clientX - touchStartX);
        if (deltaY > 0 && deltaY > deltaX) {
            e.preventDefault();
            dragY = Math.min(deltaY, 110);
            bulkModalCard.style.transform = `translateY(${dragY * 0.35}px)`;
        }
    }, { passive: false });

    bulkModalCard.addEventListener('touchend', () => {
        if (window.innerWidth > 768) return;
        if (canSwipeToClose && dragY > 85) {
            closeBulkModalSheet();
        }
        bulkModalCard.style.transform = '';
        touchStartY = 0;
        touchStartX = 0;
        dragY = 0;
        canSwipeToClose = false;
    });

    bulkModal.addEventListener('touchmove', (e) => {
        if (window.innerWidth > 768) return;
        if (!bulkModalCard.contains(e.target)) e.preventDefault();
    }, { passive: false });
}

// ── EDIT LOGIC ──
document.querySelectorAll('.edit-btn').forEach(btn => { 
    btn.addEventListener('click', () => { 
        document.getElementById('edit-log-id').value = btn.dataset.id; 
        document.getElementById('edit-date').value = btn.dataset.date; 
        document.getElementById('edit-desc').value = btn.dataset.desc; 
        document.getElementById('edit-from').value = btn.dataset.from; 
        document.getElementById('edit-to').value = btn.dataset.to; 
        updateEditPreview(); openEditModalSheet(); 
    }); 
});

// ── HOURS PREVIEW ──
const logFrom = document.getElementById('log-from'), logTo = document.getElementById('log-to'), hrsPreview = document.getElementById('hrs-preview');
const editFrom = document.getElementById('edit-from'), editTo = document.getElementById('edit-to'), editPreview = document.getElementById('edit-hrs-preview');
function updateHrsPreview() { if(!logFrom||!logTo) return; const hrs = calcHrs(logFrom.value,logTo.value); hrsPreview.textContent = hrs>0?hrs.toFixed(2)+' hrs':'— invalid'; hrsPreview.style.color=hrs>0?'#1b4332':'#dc2626'; }
function updateEditPreview() { if(!editFrom||!editTo) return; const hrs = calcHrs(editFrom.value,editTo.value); editPreview.textContent = hrs>0?hrs.toFixed(2)+' hrs':'— invalid'; editPreview.style.color=hrs>0?'#1b4332':'#dc2626'; }
if(logFrom) logFrom.addEventListener('change', updateHrsPreview); if(logTo) logTo.addEventListener('change', updateHrsPreview);
if(editFrom) editFrom.addEventListener('change', updateEditPreview); if(editTo) editTo.addEventListener('change', updateEditPreview);

// ── DAY MODAL ──
let currentDayDate = '';
function openDayModal(date) {
    currentDayDate = date; const logs = logData[date] || []; 
    document.getElementById('day-modal-title').textContent = 'Logs for ' + date; 
    let html = '<div style="display:flex;flex-direction:column;gap:0.625rem;">';
    logs.forEach(l => { html += `<div style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem;background:#f8fafc;border-radius:0.5rem;border:1px solid #e2e8f0;flex-wrap:wrap;gap:0.5rem;"><div style="min-width:0;"><div style="font-size:0.8125rem;font-weight:700;color:var(--text);">${formatTime(l.from)} — ${formatTime(l.to)} <span style="color:#1b4332;margin-left:0.5rem;">${parseFloat(l.hours).toFixed(2)} hrs</span></div>${l.desc?`<div style="font-size:0.75rem;color:#64748b;margin-top:4px;word-break:break-word;">${l.desc}</div>`:''}</div><div style="display:flex;gap:0.5rem;align-items:center;flex-shrink:0;"><button type="button" class="icon-btn" onclick="openEditFromDay('${l.id}','${l.date}','${l.from}','${l.to}',\`${l.desc.replace(/`/g,"'")}\`)" title="Edit"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg></button><form method="POST" action="logs.php" style="display:inline;margin:0;" onsubmit="return confirm('Delete this log?')"><input type="hidden" name="action" value="delete_log" /><input type="hidden" name="log_id" value="${l.id}" /><button type="submit" class="icon-btn delete" title="Delete"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg></button></form></div></div>`; });
    html += '</div>'; document.getElementById('day-modal-body').innerHTML = html; dayModal.classList.add('open');
}
function openEditFromDay(id,date,from,to,desc) { dayModal.classList.remove('open'); document.getElementById('edit-log-id').value=id; document.getElementById('edit-date').value=date; document.getElementById('edit-from').value=from; document.getElementById('edit-to').value=to; document.getElementById('edit-desc').value=desc; updateEditPreview(); openEditModalSheet(); }
document.getElementById('day-modal-close')?.addEventListener('click', () => dayModal.classList.remove('open'));
document.getElementById('day-modal-add')?.addEventListener('click', () => { dayModal.classList.remove('open'); document.getElementById('log-date').value=currentDayDate; openLogModalSheet(); });

// ── BULK LOG MODAL ──
document.querySelectorAll('.day-toggle input[type="checkbox"]').forEach(cb => { cb.addEventListener('change', (e) => { e.target.closest('.day-toggle').classList.toggle('day-toggle--excluded', e.target.checked); updateRangePreview(); }); });
const bulkHrs = document.getElementById('bulk-hrs'), bulkToHidden = document.getElementById('bulk-to-hidden');
function updateBulkToTime() { if(!bulkHrs||!bulkToHidden) return; const hrs=parseFloat(bulkHrs.value)||8; const toMin=480+Math.round(hrs*60); bulkToHidden.value=`${Math.floor(toMin/60).toString().padStart(2,'0')}:${(toMin%60).toString().padStart(2,'0')}`; updateRangePreview(); }
if(bulkHrs) bulkHrs.addEventListener('change', updateBulkToTime);
const bulkStart=document.getElementById('bulk-start'), bulkEnd=document.getElementById('bulk-end'), rangePreview=document.getElementById('bulk-range-preview');
function updateRangePreview() { if(!bulkStart||!bulkEnd||!rangePreview) return; if(!bulkStart.value||!bulkEnd.value){rangePreview.textContent='';return;} const start=new Date(bulkStart.value); const end=new Date(bulkEnd.value); if(start>end){rangePreview.textContent='Start must be before end.';rangePreview.style.color='#dc2626';return;} const excluded=Array.from(document.querySelectorAll('.day-toggle input:checked')).map(cb=>parseInt(cb.value)); let count=0,cursor=new Date(start); while(cursor<=end){const iso=cursor.getDay()===0?7:cursor.getDay();if(!excluded.includes(iso))count++;cursor.setDate(cursor.getDate()+1);} const hrs=parseFloat(bulkHrs?.value)||8; rangePreview.style.color='#1b4332'; rangePreview.textContent=`${count} day${count!==1?'s':''} will be filled — ${(hrs*count).toFixed(1)} hrs total`; }
if(bulkStart) bulkStart.addEventListener('change', updateRangePreview); if(bulkEnd) bulkEnd.addEventListener('change', updateRangePreview);

// ── PREVIOUS LOGS TUTORIAL ──
const tutorialOverlay = document.getElementById('logs-tutorial-overlay');
const tutorialCard = document.getElementById('logs-tutorial-card');
const tutorialStepCount = document.getElementById('tutorial-step-count');
const tutorialStepTitle = document.getElementById('tutorial-step-title');
const tutorialStepBody = document.getElementById('tutorial-step-body');
const tutorialPrevBtn = document.getElementById('tutorial-prev-btn');
const tutorialSkipBtn = document.getElementById('tutorial-skip-btn');
const tutorialNextBtn = document.getElementById('tutorial-next-btn');
const tutorialStartBtn = document.getElementById('start-log-tutorial-btn');
const tutorialSpotlight = document.getElementById('tutorial-spotlight');
const TUTORIAL_STORAGE_KEY = 'logs_previous_tutorial_done_v1_<?= (int) ($user['id'] ?? 0) ?>';
const TUTORIAL_AUTORUN_SEEN_KEY = 'logs_previous_tutorial_autorun_seen_v1_<?= (int) ($user['id'] ?? 0) ?>';
const forceTutorial = <?= $force_tutorial ? 'true' : 'false' ?>;
const isFirstTimeLogsUser = <?= $is_first_time_logs_user ? 'true' : 'false' ?>;

let tutorialStepIndex = 0;
let tutorialFocusedEl = null;

function hasCompletedTutorial() {
    try {
        return localStorage.getItem(TUTORIAL_STORAGE_KEY) === '1';
    } catch (e) {
        return false;
    }
}

function markTutorialCompleted() {
    try {
        localStorage.setItem(TUTORIAL_STORAGE_KEY, '1');
    } catch (e) {
        // Ignore storage failures; tutorial will simply reappear on later visits.
    }
}

function hasSeenTutorialAutoRun() {
    try {
        return localStorage.getItem(TUTORIAL_AUTORUN_SEEN_KEY) === '1';
    } catch (e) {
        return false;
    }
}

function markTutorialAutoRunSeen() {
    try {
        localStorage.setItem(TUTORIAL_AUTORUN_SEEN_KEY, '1');
    } catch (e) {
        // Ignore storage failures.
    }
}

function closeAllLogModals() {
    [logModal, editModal, bulkModal, dayModal].forEach(m => m?.classList.remove('open'));
}

function hideTutorialSpotlight() {
    if (!tutorialSpotlight) return;
    tutorialSpotlight.style.opacity = '0';
    tutorialSpotlight.style.width = '0px';
    tutorialSpotlight.style.height = '0px';
}

function showTutorialSpotlight(targetEl) {
    if (!tutorialSpotlight || !targetEl) {
        hideTutorialSpotlight();
        return;
    }

    const isMobile = window.matchMedia('(max-width: 768px)').matches;
    if (isMobile) {
        hideTutorialSpotlight();
        return;
    }

    const rect = targetEl.getBoundingClientRect();
    const pad = 10;
    const left = Math.max(8, rect.left - pad);
    const top = Math.max(8, rect.top - pad);
    const width = Math.min(window.innerWidth - left - 8, rect.width + (pad * 2));
    const height = Math.min(window.innerHeight - top - 8, rect.height + (pad * 2));

    const computedRadius = parseFloat(window.getComputedStyle(targetEl).borderRadius || '10') || 10;

    tutorialSpotlight.style.left = `${Math.round(left)}px`;
    tutorialSpotlight.style.top = `${Math.round(top)}px`;
    tutorialSpotlight.style.width = `${Math.round(width)}px`;
    tutorialSpotlight.style.height = `${Math.round(height)}px`;
    tutorialSpotlight.style.borderRadius = `${Math.max(10, computedRadius + 8)}px`;
    tutorialSpotlight.style.opacity = '1';
}

function setLogsView(mode) {
    if (!viewCal || !viewList || !viewListBtn) return;
    if (mode === 'calendar') {
        viewCal.style.display = 'block';
        viewList.style.display = 'none';
        setToggleBtn('calendar');
        localStorage.setItem('logs_view', 'calendar');
    } else {
        viewCal.style.display = 'none';
        viewList.style.display = 'block';
        setToggleBtn('list');
        localStorage.setItem('logs_view', 'list');
    }
    clearAllSelections();
}

const tutorialSteps = [
    {
        title: 'Welcome',
        body: 'This short guide walks you through everything you can do on the Time Logs page.',
        selector: '#start-log-tutorial-btn'
    },
    {
        title: 'Open New Log Entry',
        body: 'Use New Log Entry when filling a specific previous date.',
        selector: '#open-modal-btn',
        prepare: () => closeAllLogModals()
    },
    {
        title: 'Use Bulk Log for Many Days',
        body: 'Use Bulk Log when you need to fill multiple previous days quickly.',
        selector: '#open-bulk-btn',
        prepare: () => closeAllLogModals()
    },
    {
        title: 'Multi-Select Delete in Table',
        body: 'Use the checkboxes in the table to select multiple logs, then use the Delete action bar to remove them at once.',
        selector: '#select-all',
        prepare: () => {
            closeAllLogModals();
            setLogsView('list');
            const firstRowCheckbox = document.querySelector('.row-checkbox');
            if (firstRowCheckbox) {
                firstRowCheckbox.checked = true;
                updateBulkBar();
            }
        }
    },
    {
        title: 'View Calendar Button',
        body: 'Use this button to switch from list view to calendar view.',
        selector: '#view-toggle-btn',
        prepare: () => {
            closeAllLogModals();
            setLogsView('list');
        }
    },
    {
        title: 'View List Button',
        body: 'When you are in calendar view, this same button lets you switch back to list view.',
        selector: '#view-toggle-btn',
        prepare: () => {
            closeAllLogModals();
            setLogsView('calendar');
        }
    },
    {
        title: 'You are Ready',
        body: 'You can reopen this guide anytime using the Take a Tour button.',
        selector: '#start-log-tutorial-btn',
        prepare: () => closeAllLogModals()
    }
];

function clearTutorialFocus() {
    if (tutorialFocusedEl) {
        tutorialFocusedEl.classList.remove('tutorial-focus');
        tutorialFocusedEl.classList.remove('tutorial-focus--strong');
    }
    tutorialFocusedEl = null;
    hideTutorialSpotlight();
}

function positionTutorialCard(targetEl) {
    if (!tutorialCard) return;
    const isMobile = window.matchMedia('(max-width: 768px)').matches;

    tutorialCard.style.right = '';
    tutorialCard.style.bottom = '';

    if (isMobile) {
        tutorialCard.style.left = '12px';
        tutorialCard.style.right = '12px';
        tutorialCard.style.bottom = '12px';
        tutorialCard.style.top = 'auto';
        tutorialCard.style.width = 'auto';
        tutorialCard.style.transform = 'none';
        return;
    }

    const viewportPad = 12;
    const gap = 14;
    const cardWidth = Math.min(360, window.innerWidth - (viewportPad * 2));
    tutorialCard.style.width = cardWidth + 'px';
    tutorialCard.style.transform = 'none';

    if (!targetEl) {
        tutorialCard.style.left = Math.max(viewportPad, window.innerWidth - cardWidth - viewportPad) + 'px';
        tutorialCard.style.top = viewportPad + 'px';
        return;
    }

    const rect = targetEl.getBoundingClientRect();
    const cardHeight = tutorialCard.offsetHeight || 220;
    const clamp = (v, min, max) => Math.max(min, Math.min(max, v));

    const candidates = [
        {
            x: clamp(rect.left, viewportPad, window.innerWidth - cardWidth - viewportPad),
            y: rect.bottom + gap
        },
        {
            x: clamp(rect.left, viewportPad, window.innerWidth - cardWidth - viewportPad),
            y: rect.top - cardHeight - gap
        },
        {
            x: rect.right + gap,
            y: clamp(rect.top + (rect.height / 2) - (cardHeight / 2), viewportPad, window.innerHeight - cardHeight - viewportPad)
        },
        {
            x: rect.left - cardWidth - gap,
            y: clamp(rect.top + (rect.height / 2) - (cardHeight / 2), viewportPad, window.innerHeight - cardHeight - viewportPad)
        }
    ];

    const fitsViewport = pos => (
        pos.x >= viewportPad &&
        pos.y >= viewportPad &&
        (pos.x + cardWidth) <= (window.innerWidth - viewportPad) &&
        (pos.y + cardHeight) <= (window.innerHeight - viewportPad)
    );

    const overlapsTarget = pos => {
        const cardRect = {
            left: pos.x,
            right: pos.x + cardWidth,
            top: pos.y,
            bottom: pos.y + cardHeight
        };
        return !(cardRect.right < rect.left || cardRect.left > rect.right || cardRect.bottom < rect.top || cardRect.top > rect.bottom);
    };

    let chosen = candidates.find(pos => fitsViewport(pos) && !overlapsTarget(pos));

    if (!chosen) {
        chosen = {
            x: Math.max(viewportPad, window.innerWidth - cardWidth - viewportPad),
            y: viewportPad
        };
    }

    tutorialCard.style.left = Math.round(chosen.x) + 'px';
    tutorialCard.style.top = Math.round(chosen.y) + 'px';
}

function renderTutorialStep() {
    const step = tutorialSteps[tutorialStepIndex];
    if (!step || !tutorialOverlay) return;
    if (typeof step.prepare === 'function') step.prepare();

    tutorialStepCount.textContent = `Step ${tutorialStepIndex + 1} of ${tutorialSteps.length}`;
    tutorialStepTitle.textContent = step.title;
    tutorialStepBody.textContent = step.body;
    tutorialPrevBtn.disabled = tutorialStepIndex === 0;
    tutorialNextBtn.textContent = tutorialStepIndex === tutorialSteps.length - 1 ? 'Finish' : 'Next';

    clearTutorialFocus();
    const targetEl = document.querySelector(step.selector);
    if (targetEl) {
        tutorialFocusedEl = targetEl;
        tutorialFocusedEl.classList.add('tutorial-focus');
        tutorialFocusedEl.classList.add('tutorial-focus--strong');
        tutorialFocusedEl.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
        showTutorialSpotlight(targetEl);

        // Reposition after smooth scrolling settles.
        setTimeout(() => {
            if (!tutorialOverlay?.classList.contains('open') || tutorialFocusedEl !== targetEl) return;
            showTutorialSpotlight(targetEl);
            positionTutorialCard(targetEl);
        }, 220);
    } else {
        hideTutorialSpotlight();
    }
    positionTutorialCard(targetEl);
}

function closeTutorial(markDone = false) {
    clearTutorialFocus();
    tutorialOverlay?.classList.remove('open');
    tutorialOverlay?.setAttribute('aria-hidden', 'true');
    if (markDone) markTutorialCompleted();
    closeAllLogModals();
}

function openTutorial() {
    if (!tutorialOverlay) return;
    tutorialStepIndex = 0;
    tutorialOverlay.classList.add('open');
    tutorialOverlay.setAttribute('aria-hidden', 'false');
    renderTutorialStep();
}

tutorialStartBtn?.addEventListener('click', openTutorial);
tutorialPrevBtn?.addEventListener('click', () => {
    if (tutorialStepIndex > 0) {
        tutorialStepIndex -= 1;
        renderTutorialStep();
    }
});
tutorialNextBtn?.addEventListener('click', () => {
    if (tutorialStepIndex < tutorialSteps.length - 1) {
        tutorialStepIndex += 1;
        renderTutorialStep();
    } else {
        closeTutorial(true);
    }
});
tutorialSkipBtn?.addEventListener('click', () => closeTutorial(true));
window.addEventListener('resize', () => {
    if (!tutorialOverlay?.classList.contains('open') || !tutorialFocusedEl) return;
    showTutorialSpotlight(tutorialFocusedEl);
    positionTutorialCard(tutorialFocusedEl);
});
window.addEventListener('keydown', e => {
    if (!tutorialOverlay?.classList.contains('open')) return;
    if (e.key === 'Escape') closeTutorial(false);
});

setTimeout(() => {
    if (forceTutorial) {
        openTutorial();
    } else if (isFirstTimeLogsUser && !hasSeenTutorialAutoRun()) {
        markTutorialAutoRunSeen();
        openTutorial();
    }

    if (forceTutorial && window.history?.replaceState) {
        const cleanUrl = new URL(window.location.href);
        cleanUrl.searchParams.delete('tutorial');
        window.history.replaceState({}, '', cleanUrl.toString());
    }
}, 450);

// ── FLOATING ACTION BAR ──
const selectAll = document.getElementById('select-all');
const bulkDeleteBar = document.getElementById('bulk-delete-bar');
const bulkDeleteCount = document.getElementById('bulk-delete-count');
const bulkDeleteIds = document.getElementById('bulk-delete-ids');
const bulkDeselect = document.getElementById('bulk-deselect');

function getChecked() { 
    return Array.from(document.querySelectorAll('.row-checkbox:checked, .cal-checkbox:checked')); 
}

function updateBulkBar() { 
    const checked = getChecked(); 
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        const tr = cb.closest('tr');
        if (cb.checked) tr.classList.add('row-selected');
        else tr.classList.remove('row-selected');
    });
    if (!bulkDeleteBar) return; 
    if (checked.length > 0) { 
        bulkDeleteBar.classList.add('show'); 
        if (bulkDeleteCount) bulkDeleteCount.textContent = checked.length + ' selected'; 
        if (bulkDeleteIds) { 
            bulkDeleteIds.innerHTML = ''; 
            checked.forEach(cb => { 
                cb.value.split(',').forEach(id => {
                    const inp = document.createElement('input'); 
                    inp.type = 'hidden'; inp.name = 'log_ids[]'; inp.value = id.trim(); 
                    bulkDeleteIds.appendChild(inp);
                });
            }); 
        } 
    } else { 
        bulkDeleteBar.classList.remove('show'); 
        if (selectAll) selectAll.checked = false; 
    } 
}

if (selectAll) { 
    selectAll.addEventListener('change', () => { 
        document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = selectAll.checked); 
        updateBulkBar(); 
    }); 
}
document.querySelectorAll('.row-checkbox').forEach(cb => { 
    cb.addEventListener('change', () => { 
        const all = document.querySelectorAll('.row-checkbox'); 
        if (selectAll) selectAll.checked = getChecked().filter(c => c.classList.contains('row-checkbox')).length === all.length; 
        updateBulkBar(); 
    }); 
});

if (bulkDeselect) { 
    bulkDeselect.addEventListener('click', () => { 
        document.querySelectorAll('.row-checkbox, .cal-checkbox').forEach(cb => cb.checked = false);
        document.querySelectorAll('.new-cal-cell').forEach(cell => cell.classList.remove('cal-selected'));
        if (selectAll) selectAll.checked = false; 
        updateBulkBar(); 
    }); 
}

<?php if (!empty($log_errors)): ?>openLogModalSheet();<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>