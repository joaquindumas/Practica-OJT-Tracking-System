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

// ── PAGINATION LOGIC (6 Logs per page) ──
$logs_per_page = 6;
$current_page = max(1, intval($_GET['page'] ?? 1));
$total_logs = count($all_logs);
$total_pages = ceil($total_logs / $logs_per_page);
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;
$paginated_logs = array_slice($all_logs, ($current_page - 1) * $logs_per_page, $logs_per_page);

$month_param = $_GET['month'] ?? date('Y-m'); if (!preg_match('/^\d{4}-\d{2}$/', $month_param)) $month_param = date('Y-m');
[$cal_year, $cal_month] = explode('-', $month_param); $cal_year = (int) $cal_year; $cal_month = (int) $cal_month;
$first_date_ts = mktime(0, 0, 0, $cal_month, 1, $cal_year); 
$prev_month = date('Y-m', mktime(0, 0, 0, $cal_month - 1, 1, $cal_year)); 
$next_month = date('Y-m', mktime(0, 0, 0, $cal_month + 1, 1, $cal_year));
$today = date('Y-m-d'); 
$first_dow_zero_indexed = (int) date('w', $first_date_ts); 
$days_in_month = (int) date('t', $first_date_ts);

include 'includes/header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Inter:wght@400;500;600;700;800&display=swap');
.dash-wrap * { font-family: 'Inter', sans-serif !important; }
.dash-wrap { padding: 1.25rem 2rem; width: 100%; box-sizing: border-box; }

/* ── BULLETPROOF HEADER ALIGNMENT ── */
.logs-header-wrap { 
    display: flex; 
    flex-direction: row;
    align-items: center; 
    justify-content: space-between; 
    margin-bottom: 1.5rem; 
    width: 100%;
}
.logs-header-left {
    display: flex;
    flex-direction: column;
    justify-content: center;
}
.logs-title { font-size: 2.15rem; font-weight: 800; color: var(--text); letter-spacing: -0.04em; line-height: 1; margin: 0; }
.dash-sync { display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--text3); font-weight: 600; margin-top: 8px; }
.sync-dot { width: 6px; height: 6px; background: #10b981; border-radius: 50%; position: relative; }
.sync-dot::after { content: ''; position: absolute; width: 100%; height: 100%; background: #10b981; border-radius: 50%; animation: pulse 2s infinite; }
@keyframes pulse { 0% { transform: scale(1); opacity: 0.8; } 70% { transform: scale(2.5); opacity: 0; } 100% { opacity: 0; } }

/* ── BUTTONS FIXED TO RIGHT ── */
.logs-actions { display: flex; gap: 10px; align-items: center; flex-shrink: 0; }
.btn { padding: 10px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; transition: all 0.15s ease; display: inline-flex; align-items: center; justify-content: center; outline: none; }
.btn-outline { background: white; color: var(--text, #1a1a1a); border: 1px solid var(--border, #e0e0e0); }
.btn-outline:hover { background: #fafafa; border-color: #d1d5db; }
.btn-secondary { background: #f0faf2; color: #1b4332; border: 1px solid #c8e6d0; }
.btn-secondary:hover { background: #e2f4e8; border-color: #b7e4c7; }
.btn-primary { background: #2d6a4f; color: white; border: 1px solid #2d6a4f; }
.btn-primary:hover { background: #1b4332; border-color: #1b4332; }

/* ── EXACT CALENDAR DIMENSIONS ── */
.new-cal-card { background: white; border-radius: 18px; padding: 1.25rem 1.5rem; border: 1px solid var(--border); box-shadow: 0 2px 12px rgba(0,0,0,0.02); margin-bottom: 1.5rem; }
.new-cal-header { display: flex; align-items: center; gap: 16px; margin-bottom: 1rem; }
.new-cal-header h2 { font-size: 1.35rem; font-weight: 800; letter-spacing: -0.02em; margin: 0; color: var(--text); }
.new-cal-nav { display: flex; gap: 6px; }
.new-cal-nav a { width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid #eee; color: #555; text-decoration: none; transition: 0.15s; }
.new-cal-nav a:hover { background: #fafafa; border-color: #ccc; }

/* ── SCROLLABLE WRAPPER ── */
.new-cal-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: thin; scrollbar-color: #c8e6d0 transparent; padding-bottom: 4px; }
.new-cal-wrapper::-webkit-scrollbar { height: 6px; }
.new-cal-wrapper::-webkit-scrollbar-track { background: transparent; }
.new-cal-wrapper::-webkit-scrollbar-thumb { background: #c8e6d0; border-radius: 4px; }

.new-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); border: 1px solid #f0f0f0; border-radius: 8px; overflow: hidden; min-width: 650px; }
.new-cal-dow { padding: 8px; font-size: 10px; font-weight: 700; text-transform: uppercase; color: #888; text-align: center; background: #fdfdfd; border-bottom: 1px solid #f0f0f0; }

/* ── SHORTER CELLS FOR 6-ROW MONTHS ── */
.new-cal-cell { min-height: 65px; padding: 8px; border-bottom: 1px solid #f0f0f0; border-right: 1px solid #f0f0f0; cursor: pointer; transition: background 0.1s; display: flex; flex-direction: column; }
.new-cal-cell:nth-child(7n) { border-right: none; }
.new-cal-cell:hover { background: #fafafa; }
.new-cal-cell.empty { background: transparent; cursor: default; }

.new-cal-date { font-size: 12px; font-weight: 500; color: #555; margin-bottom: 4px; display: block; }
.pill-logged { background: #f0faf2; border-left: 3px solid #2d6a4f; color: #1b4332; padding: 4px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; width: fit-content; margin-top: auto;}

/* ── LIST VIEW & PAGINATION ── */
.list-table-wrap { background: white; border-radius: 16px; padding: 1.5rem; border: 1px solid var(--border); box-shadow: 0 2px 12px rgba(0,0,0,0.02); overflow-x: auto; }
.log-table { width: 100%; border-collapse: collapse; min-width: 600px; }
.log-table thead tr { border-bottom: 1px solid #eee; text-align: left; font-size: 10px; color: #888; text-transform: uppercase; letter-spacing: 0.05em; }
.log-table th, .log-table td { padding: 12px 10px; }
.log-table tbody tr { border-bottom: 1px solid #f9f9f9; font-size: 13px; transition: 0.1s; }
.log-table tbody tr:hover { background: #fcfcfc; }
.bulk-delete-bar { display: none; align-items: center; justify-content: space-between; padding: 12px 16px; background: #fff5f5; border: 1px solid #fecaca; border-radius: 10px; margin-bottom: 16px; flex-wrap: wrap; gap: 8px;}

/* Custom Pagination Buttons */
.page-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; border: 1px solid #e2e8f0; background: white; color: #475569; text-decoration: none; transition: all 0.2s ease; }
.page-btn:hover:not(.disabled) { border-color: #cbd5e1; color: #0f172a; background: #f8fafc; }
.page-btn.disabled { opacity: 0.4; cursor: default; }

/* ── MODAL TYPOGRAPHY & DESIGN ── */
.modal-card { font-family: 'Inter', sans-serif; border-radius: 16px; padding: 1.5rem; }
.modal-title-serif { font-family: 'Fraunces', serif !important; font-size: 1.5rem; font-weight: 700; color: #1b4332; margin-bottom: 4px; }
.form-label-styled { font-family: 'Inter', sans-serif; font-size: 10px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase; color: #475569; margin-bottom: 6px; display: block; }
.form-input-styled { font-family: 'Inter', sans-serif; width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; color: #1e293b; box-sizing: border-box; outline: none; transition: border-color 0.2s ease; }
.form-input-styled:focus { border-color: #2d6a4f; }
.day-toggle { padding: 6px 14px; border: 1px solid #e2e8f0; border-radius: 999px; font-size: 11px; font-weight: 700; cursor: pointer; background: white; color: #64748b; transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; }
.day-toggle:hover { border-color: #cbd5e1; }
.day-toggle--excluded { border-color: #ef4444; color: #ef4444; background: white; }
.day-toggle--excluded::before { content: '✕'; margin-right: 4px; font-weight: 800; }

@media (max-width: 1440px) { .logs-title { font-size: 1.85rem; } .new-cal-cell { min-height: 61px; padding: 6px; } }
@media (max-width: 768px) { 
    /* Only allow stacking on true mobile sizes */
    .logs-header-wrap { flex-direction: column; align-items: flex-start; gap: 1rem; } 
    .logs-actions { width: 100%; display: flex; flex-wrap: wrap; } 
    .logs-actions button { flex: 1; justify-content: center; min-width: 120px;} 
}
</style>

<div class="dash-wrap">
    <div class="logs-header-wrap">
        <div class="logs-header-left">
            <h1 class="logs-title">Time Logs 🕓</h1>
            <div class="dash-sync">
                <span class="sync-dot"></span>
                Last synced at <?= date('g:i A') ?>
            </div>
        </div>
        <div class="logs-actions">
            <button type="button" class="btn btn-outline" id="view-toggle-btn">View List</button>
            <button class="btn btn-secondary" id="open-bulk-btn"><svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;margin-right:6px;"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg> Bulk Log</button>
            <button class="btn btn-primary" id="open-modal-btn"><svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;margin-right:6px;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg> New Log Entry</button>
        </div>
    </div>

    <div id="calendar-container">
        <div class="new-cal-card">
            <div class="new-cal-header">
                <h2><?= date('F Y', $first_date_ts) ?></h2>
                <div class="new-cal-nav">
                    <a href="?month=<?= $prev_month ?>"><svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg></a>
                    <a href="?month=<?= $next_month ?>"><svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></a>
                </div>
            </div>
            
            <div class="new-cal-wrapper">
                <div class="new-cal-grid">
                    <div class="new-cal-dow">SUN</div><div class="new-cal-dow">MON</div><div class="new-cal-dow">TUE</div><div class="new-cal-dow">WED</div><div class="new-cal-dow">THU</div><div class="new-cal-dow">FRI</div><div class="new-cal-dow">SAT</div>
                    <?php for ($e = 0; $e < $first_dow_zero_indexed; $e++): ?><div class="new-cal-cell empty"></div><?php endfor; ?>
                    <?php for ($d = 1; $d <= $days_in_month; $d++):
                        $date_str = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $d);
                        $day_logs = $log_map[$date_str] ?? [];
                        $is_logged = count($day_logs) > 0;
                        $total_day = array_sum(array_column($day_logs, 'hours'));
                    ?>
                        <div class="new-cal-cell" onclick="handleDayClick('<?= $date_str ?>', <?= $is_logged ? 'true' : 'false' ?>)">
                            <span class="new-cal-date" <?= ($date_str == date('Y-m-d')) ? 'style="color:#2d6a4f;font-weight:800;"' : '' ?>><?= $d ?></span>
                            <?php if ($is_logged): ?><div class="pill-logged"><?= number_format($total_day, 1) ?> hrs</div><?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="list-container" style="display:none;">
        <div class="bulk-delete-bar" id="bulk-delete-bar">
            <span id="bulk-delete-count" style="color:#dc2626; font-weight:700; font-size:12px;">0 selected</span>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <button type="button" class="btn btn-outline" id="bulk-deselect" style="padding:6px 14px;font-size:12px;">Deselect all</button>
                <form method="POST" action="logs.php" id="bulk-delete-form" style="margin:0;"><input type="hidden" name="action" value="bulk_delete" /><div id="bulk-delete-ids"></div><button type="submit" class="btn btn-primary" style="background:#dc2626; border:none; padding:6px 14px; font-size:12px;">Delete selected</button></form>
            </div>
        </div>
        <div class="list-table-wrap">
            <table class="log-table">
                <thead><tr><th><input type="checkbox" id="select-all" style="cursor:pointer;" /></th><th>Date</th><th>Description</th><th>From</th><th>To</th><th>Hrs</th><th></th></tr></thead>
                <tbody>
                    <?php if (empty($paginated_logs)): ?><tr><td colspan="7" style="text-align:center; padding:3rem; color:#888;">No logs found.</td></tr><?php else: ?>
                    <?php foreach ($paginated_logs as $log): ?>
                    <tr>
                        <td><input type="checkbox" class="row-checkbox" value="<?= e($log['id']) ?>" style="cursor:pointer; accent-color:#2d6a4f;" /></td>
                        <td style="font-weight:600; color:var(--text);"><?= e(date('M j, Y', strtotime($log['date']))) ?></td>
                        <td style="color:var(--text3);"><?= e($log['description'] ?: '—') ?></td>
                        <td style="color:var(--text3); white-space:nowrap;"><?= e(date('g:i A', strtotime($log['from']))) ?></td>
                        <td style="color:var(--text3); white-space:nowrap;"><?= e(date('g:i A', strtotime($log['to']))) ?></td>
                        <td style="font-weight:700; color:#2d6a4f; white-space:nowrap;"><?= e(number_format($log['hours'], 1)) ?></td>
                        <td style="text-align:right; display:flex; gap:8px; justify-content:flex-end;">
                            <button class="edit-btn" data-id="<?= e($log['id']) ?>" data-date="<?= e($log['date']) ?>" data-desc="<?= e($log['description'] ?? '') ?>" data-from="<?= e($log['from']) ?>" data-to="<?= e($log['to']) ?>" style="background:none;border:none;cursor:pointer;color:var(--text3);transition:0.15s;" onmouseover="this.style.color='#2d6a4f'" onmouseout="this.style.color='var(--text3)'" title="Edit"><svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></button>
                            <form method="POST" style="display:inline;margin:0;" onsubmit="return confirm('Delete this log?')"><input type="hidden" name="action" value="delete_log" /><input type="hidden" name="log_id" value="<?= e($log['id']) ?>" /><button type="submit" style="background:none;border:none;cursor:pointer;color:var(--text3);transition:0.15s;" onmouseover="this.style.color='#dc2626'" onmouseout="this.style.color='var(--text3)'" title="Delete"><svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button></form>
                        </td>
                    </tr>
                    <?php endforeach; ?><?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem; padding-top:1rem; border-top:1px solid #f0f0f0;">
                <span style="font-size:12px; font-weight:500; color:#64748b;">Showing page <?= $current_page ?> of <?= $total_pages ?></span>
                <div style="display:flex; gap:8px;">
                    <?php if ($current_page > 1): ?>
                        <a href="?month=<?= $month_param ?>&page=<?= $current_page - 1 ?>" class="page-btn"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg></a>
                    <?php else: ?>
                        <span class="page-btn disabled"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg></span>
                    <?php endif; ?>
                    
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?month=<?= $month_param ?>&page=<?= $current_page + 1 ?>" class="page-btn"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg></a>
                    <?php else: ?>
                        <span class="page-btn disabled"><svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal-overlay" id="log-modal">
  <div class="modal-card">
    <div class="modal-title-serif">New Log Entry</div>
    <div style="font-size:13px; color:#64748b; margin-bottom:1.5rem;">Record your OJT hours for a specific day.</div>
    
    <?php foreach ($log_errors as $err): ?><span class="form-error" style="color:#ef4444; font-size:12px; display:block; margin-bottom:10px;"><?= e($err) ?></span><?php endforeach; ?>
    
    <form method="POST" action="logs.php">
      <input type="hidden" name="action" value="log_hours" />
      <div class="form-group" style="margin-bottom:1rem;"><label class="form-label-styled">Date</label><input class="form-input-styled" type="date" id="log-date" name="date" value="<?= date('Y-m-d') ?>" required /></div>
      <div class="form-group" style="margin-bottom:1rem;"><label class="form-label-styled">Description (Optional)</label><input class="form-input-styled" type="text" id="log-desc" name="description" placeholder="What did you work on?" /></div>
      <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:1rem;"><div class="form-group" style="margin:0;"><label class="form-label-styled">From</label><input class="form-input-styled" type="time" id="log-from" name="from" value="08:00" required /></div><div class="form-group" style="margin:0;"><label class="form-label-styled">To</label><input class="form-input-styled" type="time" id="log-to" name="to" value="16:00" required /></div></div>
      
      <div style="font-size:12px;color:var(--text3);margin-bottom:1.5rem;">Duration: <strong id="hrs-preview" style="color:#2d6a4f;">8.00 hrs</strong></div>
      <div class="modal-actions" style="display:flex; justify-content:flex-end; gap:10px;"><button type="button" class="btn btn-outline" id="modal-close-btn">Cancel</button><button type="submit" class="btn btn-primary">Save Log</button></div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="edit-modal">
  <div class="modal-card">
    <div class="modal-title-serif">Edit Log</div>
    <div style="font-size:13px; color:#64748b; margin-bottom:1.5rem;">Update the details for this log entry.</div>
    
    <form method="POST" action="logs.php">
      <input type="hidden" name="action" value="edit_log" /><input type="hidden" name="log_id" id="edit-log-id" />
      <div class="form-group" style="margin-bottom:1rem;"><label class="form-label-styled">Date</label><input class="form-input-styled" type="date" id="edit-date" name="date" required /></div>
      <div class="form-group" style="margin-bottom:1rem;"><label class="form-label-styled">Description</label><input class="form-input-styled" type="text" id="edit-desc" name="description" /></div>
      <div class="form-row" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:1rem;"><div class="form-group" style="margin:0;"><label class="form-label-styled">From</label><input class="form-input-styled" type="time" id="edit-from" name="from" required /></div><div class="form-group" style="margin:0;"><label class="form-label-styled">To</label><input class="form-input-styled" type="time" id="edit-to" name="to" required /></div></div>
      
      <div style="font-size:12px;color:var(--text3);margin-bottom:1.5rem;">Duration: <strong id="edit-hrs-preview" style="color:#2d6a4f;"></strong></div>
      <div class="modal-actions" style="display:flex; justify-content:flex-end; gap:10px;"><button type="button" class="btn btn-outline" id="edit-close-btn">Cancel</button><button type="submit" class="btn btn-primary">Save Changes</button></div>
    </form>
  </div>
</div>

<div class="modal-overlay" id="day-modal">
  <div class="modal-card modal-wide">
    <div class="modal-title-serif" id="day-modal-title" style="margin-bottom:16px;">Logs for —</div><div id="day-modal-body"></div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;flex-wrap:wrap;"><button type="button" class="btn btn-outline" id="day-modal-close">Close</button><button type="button" class="btn btn-primary" id="day-modal-add">+ Add Log</button></div>
  </div>
</div>

<div class="modal-overlay" id="bulk-modal">
  <div class="modal-card" style="max-width:520px;">
    <div class="modal-title-serif">Bulk Entry</div>
    <div style="font-size:13px; color:#64748b; margin-bottom:1.5rem;">Fill past days automatically. Already-logged days are skipped.</div>
    
    <form method="POST" action="logs.php"><input type="hidden" name="action" value="bulk_log" />
      <div style="display:grid;grid-template-columns:1fr 1fr 90px;gap:12px;margin-bottom:1.25rem;">
        <div class="form-group" style="margin:0;"><label class="form-label-styled">From</label><input class="form-input-styled" type="date" name="bulk_start" id="bulk-start" required /></div>
        <div class="form-group" style="margin:0;"><label class="form-label-styled">To</label><input class="form-input-styled" type="date" name="bulk_end" id="bulk-end" required /></div>
        <div class="form-group" style="margin:0;"><label class="form-label-styled">Hrs/Day</label><input class="form-input-styled" type="number" name="bulk_hrs" id="bulk-hrs" value="8" min="0.5" max="24" step="0.5" required /></div>
      </div>
      <input type="hidden" name="bulk_from" value="08:00" /><input type="hidden" name="bulk_to" id="bulk-to-hidden" value="16:00" />
      
      <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label-styled" style="margin-bottom:10px;">Exclude Days <span style="color:#888; font-weight:500; font-size:10px; text-transform:none;">(SELECTED = SKIP)</span></label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php foreach (['MON','TUE','WED','THU','FRI','SAT','SUN'] as $i => $day): ?>
                <label class="day-toggle <?= in_array($day, ['SAT','SUN']) ? 'day-toggle--excluded' : '' ?>">
                    <input type="checkbox" name="exclude_days[]" value="<?= $i + 1 ?>" <?= in_array($day, ['SAT','SUN']) ? 'checked' : '' ?> style="display:none;" />
                    <span><?= $day ?></span>
                </label>
            <?php endforeach; ?>
        </div>
      </div>

      <div class="form-group" style="margin-bottom:1.25rem;"><label class="form-label-styled">Description (optional)</label><input class="form-input-styled" type="text" name="bulk_desc" placeholder="e.g. OJT at company" /></div>
      <div id="bulk-range-preview" style="font-size:12px;margin-bottom:1.5rem;min-height:16px;color:#2d6a4f;font-weight:600;"></div>
      <div class="modal-actions" style="display:flex; justify-content:flex-end; gap:10px;"><button type="button" class="btn btn-outline" id="bulk-close-btn">Cancel</button><button type="submit" class="btn btn-primary">Fill Days in Range</button></div>
    </form>
  </div>
</div>

<script>
// ── DATA AND UTILS ──
function formatTime(t) { if (!t) return ''; const [h, m] = t.split(':').map(Number); const ampm = h >= 12 ? 'PM' : 'AM'; return `${h % 12 || 12}:${m.toString().padStart(2, '0')} ${ampm}`; }
function calcHrs(from, to) { if (!from || !to) return 0; const [fh, fm] = from.split(':').map(Number); const [th, tm] = to.split(':').map(Number); return ((th * 60 + tm) - (fh * 60 + fm)) / 60; }
const logData = <?php $js_map = []; foreach ($log_map as $date => $dlogs) { if (substr($date, 0, 7) !== $month_param) continue; $js_map[$date] = array_map(fn($l) => ['id' => $l['id'], 'from' => $l['from'], 'to' => $l['to'], 'hours' => $l['hours'], 'desc' => $l['description'] ?? '', 'date' => $l['date']], $dlogs); } echo json_encode($js_map); ?>;

// ── VIEW TOGGLE (Persists on Pagination reload!) ──
const viewListBtn = document.getElementById('view-toggle-btn'); 
const viewCal = document.getElementById('calendar-container'); 
const viewList = document.getElementById('list-container');

function toggleView() { 
    const current = localStorage.getItem('logs_view') || 'calendar'; 
    if (current === 'calendar') { 
        viewCal.style.display = 'none'; viewList.style.display = 'block'; viewListBtn.textContent = 'View Calendar'; localStorage.setItem('logs_view', 'list'); 
    } else { 
        viewCal.style.display = 'block'; viewList.style.display = 'none'; viewListBtn.textContent = 'View List'; localStorage.setItem('logs_view', 'calendar'); 
    } 
}
if (localStorage.getItem('logs_view') === 'list') { viewCal.style.display = 'none'; viewList.style.display = 'block'; viewListBtn.textContent = 'View Calendar'; } 
viewListBtn.addEventListener('click', toggleView);

// ── CALENDAR CLICKS ──
function handleDayClick(date, isLogged) { 
    if (isLogged) {
        openDayModal(date); 
    } else {
        document.getElementById('log-date').value = date;
        document.getElementById('log-modal').classList.add('open');
    }
}

// ── MODAL HANDLING ──
const logModal = document.getElementById('log-modal');
const editModal = document.getElementById('edit-modal');
const bulkModal = document.getElementById('bulk-modal');
const dayModal = document.getElementById('day-modal');

document.getElementById('open-modal-btn')?.addEventListener('click', () => logModal.classList.add('open'));
document.getElementById('modal-close-btn')?.addEventListener('click', () => logModal.classList.remove('open'));
document.getElementById('open-bulk-btn')?.addEventListener('click', () => bulkModal.classList.add('open'));
document.getElementById('bulk-close-btn')?.addEventListener('click', () => bulkModal.classList.remove('open'));
document.getElementById('edit-close-btn')?.addEventListener('click', () => editModal.classList.remove('open'));

window.addEventListener('click', e => { 
    if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('open'); 
});

// ── EDIT LOGIC ──
document.querySelectorAll('.edit-btn').forEach(btn => { 
    btn.addEventListener('click', () => { 
        document.getElementById('edit-log-id').value = btn.dataset.id; 
        document.getElementById('edit-date').value = btn.dataset.date; 
        document.getElementById('edit-desc').value = btn.dataset.desc; 
        document.getElementById('edit-from').value = btn.dataset.from; 
        document.getElementById('edit-to').value = btn.dataset.to; 
        updateEditPreview(); 
        editModal.classList.add('open'); 
    }); 
});

// ── HOURS PREVIEW LOGIC ──
const logFrom = document.getElementById('log-from'), logTo = document.getElementById('log-to'), hrsPreview = document.getElementById('hrs-preview');
const editFrom = document.getElementById('edit-from'), editTo = document.getElementById('edit-to'), editPreview = document.getElementById('edit-hrs-preview');

function updateHrsPreview() { 
    if(!logFrom || !logTo) return; 
    const hrs = calcHrs(logFrom.value, logTo.value); 
    hrsPreview.textContent = hrs > 0 ? hrs.toFixed(2) + ' hrs' : '— invalid'; 
    hrsPreview.style.color = hrs > 0 ? '#2d6a4f' : '#dc2626'; 
}
function updateEditPreview() { 
    if(!editFrom || !editTo) return; 
    const hrs = calcHrs(editFrom.value, editTo.value); 
    editPreview.textContent = hrs > 0 ? hrs.toFixed(2) + ' hrs' : '— invalid'; 
    editPreview.style.color = hrs > 0 ? '#2d6a4f' : '#dc2626'; 
}

if(logFrom) logFrom.addEventListener('change', updateHrsPreview); 
if(logTo) logTo.addEventListener('change', updateHrsPreview);
if(editFrom) editFrom.addEventListener('change', updateEditPreview); 
if(editTo) editTo.addEventListener('change', updateEditPreview);

// ── DAY MODAL (Multiple logs on same day) ──
let currentDayDate = '';
function openDayModal(date) {
  currentDayDate = date; const logs = logData[date] || []; 
  document.getElementById('day-modal-title').textContent = 'Logs for ' + date; 
  let html = '<div style="display:flex;flex-direction:column;gap:10px;">';
  logs.forEach(l => { html += `<div style="display:flex;align-items:center;justify-content:space-between;padding:12px;background:#f9f9f9;border-radius:8px;border:1px solid #eee;flex-wrap:wrap;gap:8px;"><div style="min-width:0;"><div style="font-size:13px;font-weight:700;color:var(--text);">${formatTime(l.from)} — ${formatTime(l.to)} <span style="color:#2d6a4f;margin-left:8px;">${parseFloat(l.hours).toFixed(2)} hrs</span></div>${l.desc ? `<div style="font-size:12px;color:var(--text3);margin-top:4px;word-break:break-word;">${l.desc}</div>` : ''}</div><div style="display:flex;gap:8px;align-items:center;flex-shrink:0;"><button type="button" style="background:none;border:none;cursor:pointer;color:var(--text3);transition:color 0.15s;" onmouseover="this.style.color='#2d6a4f'" onmouseout="this.style.color='var(--text3)'" onclick="openEditFromDay('${l.id}','${l.date}','${l.from}','${l.to}',\`${l.desc.replace(/`/g,"'")}\`)" title="Edit"><svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg></button><form method="POST" action="logs.php" style="display:inline;margin:0;" onsubmit="return confirm('Delete this log?')"><input type="hidden" name="action" value="delete_log" /><input type="hidden" name="log_id" value="${l.id}" /><button type="submit" style="background:none;border:none;cursor:pointer;color:var(--text3);transition:color 0.15s;" onmouseover="this.style.color='#dc2626'" onmouseout="this.style.color='var(--text3)'" title="Delete"><svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg></button></form></div></div>`; });
  html += '</div>'; document.getElementById('day-modal-body').innerHTML = html; dayModal.classList.add('open');
}
function openEditFromDay(id, date, from, to, desc) { dayModal.classList.remove('open'); document.getElementById('edit-log-id').value = id; document.getElementById('edit-date').value = date; document.getElementById('edit-from').value = from; document.getElementById('edit-to').value = to; document.getElementById('edit-desc').value = desc; updateEditPreview(); editModal.classList.add('open'); }
document.getElementById('day-modal-close')?.addEventListener('click', () => dayModal.classList.remove('open'));
document.getElementById('day-modal-add')?.addEventListener('click', () => { dayModal.classList.remove('open'); document.getElementById('log-date').value = currentDayDate; logModal.classList.add('open'); });

// ── BULK LOGIC ──
document.querySelectorAll('.day-toggle input[type="checkbox"]').forEach(cb => { 
    cb.addEventListener('change', (e) => { 
        const label = e.target.closest('.day-toggle');
        label.classList.toggle('day-toggle--excluded', e.target.checked);
        updateRangePreview(); 
    }); 
});

const bulkHrs = document.getElementById('bulk-hrs'), bulkToHidden = document.getElementById('bulk-to-hidden');
function updateBulkToTime() { if (!bulkHrs || !bulkToHidden) return; const hrs = parseFloat(bulkHrs.value) || 8; const toMin = 480 + Math.round(hrs * 60); bulkToHidden.value = `${Math.floor(toMin/60).toString().padStart(2,'0')}:${(toMin%60).toString().padStart(2,'0')}`; updateRangePreview(); }
if (bulkHrs) bulkHrs.addEventListener('change', updateBulkToTime);
const bulkStart = document.getElementById('bulk-start'), bulkEnd = document.getElementById('bulk-end'), rangePreview = document.getElementById('bulk-range-preview');
function updateRangePreview() { if (!bulkStart || !bulkEnd || !rangePreview) return; if (!bulkStart.value || !bulkEnd.value) { rangePreview.textContent = ''; return; } const start = new Date(bulkStart.value); const end = new Date(bulkEnd.value); if (start > end) { rangePreview.textContent = 'Start must be before end.'; rangePreview.style.color = '#dc2626'; return; } const excluded = Array.from(document.querySelectorAll('.day-toggle input:checked')).map(cb => parseInt(cb.value)); let count = 0, cursor = new Date(start); while (cursor <= end) { const iso = cursor.getDay() === 0 ? 7 : cursor.getDay(); if (!excluded.includes(iso)) count++; cursor.setDate(cursor.getDate() + 1); } const hrs = parseFloat(bulkHrs?.value) || 8; rangePreview.style.color = '#2d6a4f'; rangePreview.textContent = `${count} day${count !== 1 ? 's' : ''} will be filled — ${(hrs * count).toFixed(1)} hrs total`; }
if (bulkStart) bulkStart.addEventListener('change', updateRangePreview); if (bulkEnd) bulkEnd.addEventListener('change', updateRangePreview);

// ── BULK DELETE (LIST VIEW) ──
const selectAll = document.getElementById('select-all'), bulkDeleteBar = document.getElementById('bulk-delete-bar'), bulkDeleteCount = document.getElementById('bulk-delete-count'), bulkDeleteIds = document.getElementById('bulk-delete-ids'), bulkDeselect = document.getElementById('bulk-deselect');
function getChecked() { return Array.from(document.querySelectorAll('.row-checkbox:checked')); }
function updateBulkBar() { const checked = getChecked(); if (!bulkDeleteBar) return; if (checked.length > 0) { bulkDeleteBar.style.display = 'flex'; if (bulkDeleteCount) bulkDeleteCount.textContent = checked.length + ' selected'; if (bulkDeleteIds) { bulkDeleteIds.innerHTML = ''; checked.forEach(cb => { const inp = document.createElement('input'); inp.type = 'hidden'; inp.name = 'log_ids[]'; inp.value = cb.value; bulkDeleteIds.appendChild(inp); }); } } else { bulkDeleteBar.style.display = 'none'; if (selectAll) selectAll.checked = false; } }
if (selectAll) { selectAll.addEventListener('change', () => { document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = selectAll.checked); updateBulkBar(); }); }
document.querySelectorAll('.row-checkbox').forEach(cb => { cb.addEventListener('change', () => { const all = document.querySelectorAll('.row-checkbox'); if (selectAll) selectAll.checked = getChecked().length === all.length; updateBulkBar(); }); });
if (bulkDeselect) { bulkDeselect.addEventListener('click', () => { document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false); if (selectAll) selectAll.checked = false; updateBulkBar(); }); }

<?php if (!empty($log_errors)): ?>document.getElementById('log-modal').classList.add('open');<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>