<?php
require_once 'includes/config.php';
require_login();

$user        = current_user();
$active_page = 'dashboard';
$log_errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'log_hours') {
        $date = $_POST['date'] ?? '';
        $desc = trim($_POST['description'] ?? '');
        $from = $_POST['from'] ?? '';
        $to   = $_POST['to']   ?? '';
        if (!$date || !$from || !$to) {
            $log_errors[] = 'Please fill in date, from, and to.';
        } else {
            [$fh, $fm] = array_map('intval', explode(':', $from));
            [$th, $tm] = array_map('intval', explode(':', $to));
            $hours = (($th * 60 + $tm) - ($fh * 60 + $fm)) / 60;
            if ($hours <= 0) {
                $log_errors[] = '"To" time must be after "From" time.';
            } else {
                add_log($user['id'], [
                    'id'          => generate_id(),
                    'date'        => $date,
                    'description' => $desc,
                    'from'        => $from,
                    'to'          => $to,
                    'hours'       => round($hours, 4),
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
                set_flash('success', 'Hours logged successfully!');
                header('Location: dashboard.php');
                exit;
            }
        }
    }

    if ($action === 'edit_log') {
        $edit_id = $_POST['log_id'] ?? '';
        $date    = $_POST['date']   ?? '';
        $desc    = trim($_POST['description'] ?? '');
        $from    = $_POST['from']   ?? '';
        $to      = $_POST['to']     ?? '';
        if ($date && $from && $to) {
            [$fh, $fm] = array_map('intval', explode(':', $from));
            [$th, $tm] = array_map('intval', explode(':', $to));
            $hours = (($th * 60 + $tm) - ($fh * 60 + $fm)) / 60;
            if ($hours > 0) {
                update_log($edit_id, $user['id'], [
                    'date' => $date, 'description' => $desc,
                    'from' => $from, 'to' => $to, 'hours' => round($hours, 4),
                ]);
                set_flash('success', 'Log updated!');
            }
        }
        header('Location: dashboard.php'); exit;
    }

    if ($action === 'delete_log') {
        delete_log($_POST['log_id'] ?? '', $user['id']);
        set_flash('success', 'Log deleted.');
        header('Location: dashboard.php'); exit;
    }

    if ($action === 'bulk_delete') {
        $ids = $_POST['log_ids'] ?? [];
        $count = 0;
        foreach ($ids as $id) { delete_log($id, $user['id']); $count++; }
        set_flash('success', "{$count} log" . ($count !== 1 ? 's' : '') . " deleted.");
        header('Location: dashboard.php'); exit;
    }

    if ($action === 'bulk_log') {
        $start        = $_POST['bulk_start']  ?? '';
        $end          = $_POST['bulk_end']    ?? '';
        $hrs          = (float) ($_POST['bulk_hrs'] ?? 8);
        $desc         = trim($_POST['bulk_desc'] ?? '');
        $exclude_days = array_map('intval', $_POST['exclude_days'] ?? []);
        $from_time    = '08:00';
        $total_min    = (int) ($hrs * 60);
        $to_time      = sprintf('%02d:%02d', intdiv(480 + $total_min, 60), (480 + $total_min) % 60);
        if ($start && $end && $start <= $end) {
            $bulk_logs = [];
            $cursor = strtotime($start);
            $endTs  = strtotime($end);
            while ($cursor <= $endTs) {
                $dow = (int) date('N', $cursor);
                if (!in_array($dow, $exclude_days)) {
                    $bulk_logs[] = ['date' => date('Y-m-d', $cursor), 'from' => $from_time, 'to' => $to_time, 'description' => $desc];
                }
                $cursor = strtotime('+1 day', $cursor);
            }
            $count = bulk_add_logs($user['id'], $bulk_logs);
            set_flash('success', "{$count} log" . ($count !== 1 ? 's' : '') . " added!");
        } else {
            set_flash('error', 'Please check your date range.');
        }
        header('Location: dashboard.php'); exit;
    }
}

$user        = current_user();
$all_logs    = $user['logs'] ?? [];
$logged      = total_logged($user);
$required    = $user['required_hours'] ?? 500;
$remaining   = hours_remaining($user);
$pct         = completion_percent($user);
$est_date    = estimated_completion($user);
$est_basis   = estimated_basis($user);
$allowance   = $user['allowance_per_day'] ?? 150;

usort($all_logs, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
$recent_logs = array_slice($all_logs, 0, 5);

// Weekly summaries
$weekly_summaries = [];
for ($i = 3; $i >= 0; $i--) {
    $ws    = strtotime("monday -{$i} week");
    $we    = strtotime("sunday -{$i} week");
    $label = 'Week ' . (4 - $i) . ' (' . date('M j', $ws) . '–' . date('j', $we) . ')';
    $hrs   = 0; $days = 0;
    foreach ($all_logs as $l) {
        $ts = strtotime($l['date']);
        if ($ts >= $ws && $ts <= $we) { $hrs += $l['hours']; $days++; }
    }
    $weekly_summaries[] = ['label' => $label, 'hrs' => round($hrs, 1), 'days' => $days, 'num' => 'W' . (4 - $i)];
}

// Monthly
$month_key  = date('Y-m');
$month_name = date('F Y');
$month_hrs  = 0; $month_days = 0;
foreach ($all_logs as $l) {
    if (substr($l['date'], 0, 7) === $month_key) { $month_hrs += $l['hours']; $month_days++; }
}

// Allowance
$total_days          = count(array_unique(array_column($all_logs, 'date')));
$total_allowance     = $total_days * $allowance;
$avg_hrs_day         = $total_days > 0 ? round($logged / $total_days, 1) : 0;
$projected_days      = $avg_hrs_day > 0 ? (int) ceil($remaining / $avg_hrs_day) : 0;
$projected_allowance = $projected_days * $allowance;

if ($avg_hrs_day >= 8) {
    $insight = "Great consistency! Averaging {$avg_hrs_day} hrs/day — you're on track to finish on schedule.";
} elseif ($avg_hrs_day >= 6) {
    $insight = "Averaging {$avg_hrs_day} hrs/day. A few extra hours per day will keep you on track.";
} elseif ($avg_hrs_day > 0) {
    $insight = "Averaging {$avg_hrs_day} hrs/day. Log more consistently to meet your OJT target.";
} else {
    $insight = "Start logging your hours to see personalized insights about your progress.";
}

include 'includes/header.php';
?>

<style>
/* ── Google Font override for dashboard ── */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

.dash-wrap * {
  font-family: 'Inter', sans-serif !important;
}

/* ── Sidebar hover animations ── */
.nav-item {
  transition: background 0.18s ease, color 0.18s ease, transform 0.15s ease, padding-left 0.15s ease !important;
}
.nav-item:hover {
  transform: translateX(3px) !important;
  padding-left: 16px !important;
}
.nav-item.active {
  transform: none !important;
}
.nav-item--logout {
  transition: background 0.18s ease, color 0.18s ease, transform 0.15s ease !important;
}
.nav-item--logout:hover {
  transform: translateX(3px) !important;
}

/* ── Dashboard layout ── */
.dash-wrap { padding: 0; }

.dash-header {
  padding: 1.5rem 1.75rem 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--border);
  background: var(--surface);
}
.dash-header-left {}
.dash-eyebrow {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--green-mid);
  margin-bottom: 3px;
}
.dash-title {
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--text);
  letter-spacing: -0.02em;
}
.dash-sub {
  font-size: 12px;
  color: var(--text3);
  margin-top: 2px;
}
.dash-actions { display: flex; gap: 8px; }

/* ── Top stat row ── */
.dash-stat-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
  padding: 1.5rem 1.75rem;
  background: var(--bg);
  border-bottom: 1px solid var(--border);
}

.dash-stat-card {
  border-radius: 14px;
  padding: 1.75rem 1.75rem;
  cursor: default;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.dash-stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(27,67,50,0.12);
}
.dash-stat-card--progress {
  background: var(--green-dark);
}
.dash-stat-card--remaining {
  background: #f0faf2;
  border: 1px solid #c8e6d0;
}
.dash-stat-card--remaining .dash-stat-eyebrow { color: var(--green-mid); }
.dash-stat-card--remaining .dash-stat-num { color: var(--green-dark); }
.dash-stat-card--remaining .dash-stat-sub { color: var(--green-mid); }
.dash-remaining-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: rgba(45,106,79,0.1);
  border-radius: 999px;
  padding: 5px 12px;
  font-size: 12px;
  font-weight: 500;
  color: var(--green-dark);
  margin-top: 10px;
}
.dash-stat-eyebrow {
  font-size: 10px;
  font-weight: 600;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  margin-bottom: 8px;
}
.dash-stat-card--progress .dash-stat-eyebrow { color: rgba(149,213,178,0.65); }

.dash-stat-num {
  font-size: 3rem;
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1;
}
.dash-stat-card--progress .dash-stat-num { color: white; }

.dash-stat-sub {
  font-size: 12px;
  margin-top: 6px;
}
.dash-stat-card--progress .dash-stat-sub { color: rgba(149,213,178,0.6); }

.dash-progress-bar {
  height: 6px;
  border-radius: 999px;
  background: rgba(255,255,255,0.15);
  overflow: hidden;
  margin: 14px 0 8px;
}
.dash-progress-fill {
  height: 100%;
  background: var(--green-muted);
  border-radius: 999px;
  transition: width 0.8s ease;
}
.dash-est-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: rgba(255,255,255,0.1);
  border-radius: 999px;
  padding: 5px 12px;
  font-size: 12px;
  font-weight: 500;
  color: rgba(149,213,178,0.9);
  margin-top: 10px;
}

/* ── Body grid ── */
.dash-body {
  display: grid;
  grid-template-columns: 1fr 300px;
  min-height: calc(100vh - 180px);
  align-items: start;
}
.dash-left {
  padding: 1.5rem 1.75rem;
  border-right: 1px solid var(--border);
  min-height: calc(100vh - 180px);
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}
.dash-logs-section {
  display: flex;
  flex-direction: column;
}
.dash-logs-section .table-wrap {
  background: var(--surface);
}
.dash-right {
  padding: 1.5rem;
  background: var(--bg);
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  overflow-y: auto;
}

/* ── Fix time inputs: keep clickable, uppercase AM/PM only ── */
input[type="time"]::-webkit-datetime-edit-ampm-field {
  text-transform: uppercase;
}
input[type="time"]::-webkit-calendar-picker-indicator {
  opacity: 0.4;
  cursor: pointer;
}

/* ── Section title ── */
.dash-section-title {
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--text2);
  margin-bottom: 0.75rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.dash-section-title a {
  font-size: 11px;
  color: var(--green);
  font-weight: 600;
  text-transform: none;
  letter-spacing: 0;
  display: flex;
  align-items: center;
  gap: 3px;
  transition: color 0.15s;
}
.dash-section-title a:hover { color: var(--green-dark); }

/* ── Log table ── */
.dash-log-table { width: 100%; border-collapse: collapse; }
.dash-log-table th {
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--text3);
  padding: 0 12px 8px;
  text-align: left;
  border-bottom: 1px solid var(--border);
}
.dash-log-table td {
  padding: 9px 12px;
  font-size: 12.5px;
  border-bottom: 1px solid var(--border);
  color: var(--text);
  vertical-align: middle;
  transition: background 0.12s;
}
.dash-log-table tr:last-child td { border-bottom: none; }
.dash-log-table tbody tr:last-child td { border-bottom: none !important; }
.dash-log-row { transition: background 0.12s ease; border-bottom: 1px solid var(--border); }
.dash-log-row:last-child { border-bottom: none; }
.dash-log-row:hover { background: var(--green-xlight); }
.dash-log-row:hover td { background: transparent; }
.dash-log-row button:hover {
  background: var(--green-light) !important;
  color: var(--green-dark) !important;
}
.dash-log-row .delete-form button:hover {
  background:#fee2e2 !important;
  color: var(--red) !important;
}

.dash-hrs-val {
  font-size: 14px;
  font-weight: 700;
  color: var(--green);
  font-variant-numeric: tabular-nums;
  transition: color 0.12s;
}

/* ── Progress card (monthly) ── */
.dash-monthly-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1rem 1.25rem;
  transition: box-shadow 0.2s ease;
}
.dash-monthly-card:hover {
  box-shadow: var(--shadow);
}
.dash-monthly-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}
.dash-monthly-label { font-size: 12px; font-weight: 600; color: var(--text2); }
.dash-monthly-pct   { font-size: 12px; font-weight: 700; color: var(--green); font-variant-numeric: tabular-nums; }
.dash-monthly-track {
  height: 5px;
  background: var(--bg2);
  border-radius: 999px;
  overflow: hidden;
}
.dash-monthly-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--green-mid), var(--green));
  border-radius: 999px;
}

/* ── Allowance cards ── */
.dash-allowance-grid {
  display: grid;
  grid-template-columns: repeat(3,1fr);
  gap: 10px;
  padding: 0 1.75rem 1.5rem;
  background: var(--bg);
  border-top: 1px solid var(--border);
}
.dash-allowance-item {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 1rem 1.25rem;
  transition: transform 0.18s ease, box-shadow 0.18s ease;
}
.dash-allowance-item:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow);
}
.dash-allowance-eyebrow {
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--text3);
  margin-bottom: 5px;
}
.dash-allowance-val {
  font-size: 1.25rem;
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1;
}
.dash-allowance-meta { font-size: 10px; color: var(--text3); margin-top: 4px; }

/* ── Right panel cards ── */
.dash-right-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
  transition: box-shadow 0.2s ease;
}
.dash-right-card:hover { box-shadow: var(--shadow); }
.dash-right-card-head {
  padding: 0.875rem 1rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.dash-right-card-title { font-size: 12px; font-weight: 700; color: var(--text); }
.dash-right-card-sub   { font-size: 11px; color: var(--text3); margin-top: 1px; }
.dash-right-card-body  { padding: 0.5rem 1rem; }

/* ── Weekly row ── */
.dash-week-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 4px;
  border-bottom: 1px solid var(--border);
  transition: background 0.12s ease;
  border-radius: 6px;
  margin: 0 -4px;
}
.dash-week-row:last-child { border-bottom: none; }
.dash-week-row:hover { background: var(--green-xlight); }
.dash-week-badge {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: var(--green-xlight);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 9px;
  font-weight: 800;
  color: var(--green-dark);
  flex-shrink: 0;
  letter-spacing: 0.02em;
  transition: background 0.12s, color 0.12s;
}
.dash-week-row:hover .dash-week-badge { background: var(--green); color: white; }
.dash-week-info { flex: 1; min-width: 0; }
.dash-week-title { font-size: 11.5px; font-weight: 600; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dash-week-sub   { font-size: 10px; color: var(--text3); margin-top: 1px; }
.dash-week-hrs   { font-size: 13px; font-weight: 700; color: var(--green); font-variant-numeric: tabular-nums; flex-shrink: 0; }

/* ── Insight card ── */
.dash-insight {
  background: var(--green-xlight);
  border: 1px solid var(--green-light);
  border-radius: 12px;
  padding: 1rem;
  display: flex;
  gap: 10px;
  transition: box-shadow 0.2s ease;
}
.dash-insight:hover { box-shadow: 0 4px 16px rgba(45,106,79,0.12); }
.dash-insight-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: white;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: var(--shadow-sm);
}
.dash-insight-icon svg { width: 14px; height: 14px; fill: var(--green); }
.dash-insight-title { font-size: 11px; font-weight: 700; color: var(--green-dark); margin-bottom: 3px; }
.dash-insight-text  { font-size: 11px; color: var(--text2); line-height: 1.55; }
</style>

<div class="dash-wrap">

  <!-- Header -->
  <div class="dash-header">
    <div class="dash-header-left">
      <div class="dash-title">Welcome back, <?= e(explode(' ', $user['name'] ?? $user['username'])[0]) ?></div>
      <div class="dash-sub">You've completed <?= number_format($pct, 1) ?>% of your internship requirements.</div>
    </div>
    <div class="dash-actions">
      <button class="btn btn-secondary btn-sm" id="open-bulk-btn">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
        Bulk Log
      </button>
      <button class="btn btn-primary btn-sm" id="open-modal-btn">
        <svg viewBox="0 0 24 24" fill="white"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
        New Log Entry
      </button>
    </div>
  </div>

  <!-- Top stats -->
  <div class="dash-stat-row">

    <!-- Total Progress -->
    <div class="dash-stat-card dash-stat-card--progress">
      <div class="dash-stat-eyebrow">Total Progress</div>
      <div class="dash-stat-num">
        <?= number_format($logged, 1) ?>
        <span style="font-size:1.1rem;font-weight:400;opacity:0.45;"> / <?= number_format($required, 0) ?> hrs</span>
      </div>
      <div class="dash-progress-bar">
        <div class="dash-progress-fill" style="width:<?= min(100, $pct) ?>%;"></div>
      </div>
      <div class="dash-stat-sub"><?= number_format($pct, 1) ?>% complete</div>
      <?php if ($est_date && $est_date !== 'Completed'): ?>
        <div class="dash-est-pill">
          <svg viewBox="0 0 24 24" fill="currentColor" style="width:11px;height:11px;"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
          Est. <?= e($est_date) ?>
        </div>
      <?php elseif ($est_date === 'Completed'): ?>
        <div class="dash-est-pill">🎉 Completed!</div>
      <?php endif; ?>
    </div>

    <!-- Remaining Hours -->
    <div class="dash-stat-card dash-stat-card--remaining">
      <div class="dash-stat-eyebrow">Remaining Hours</div>
      <div class="dash-stat-num">
        <?= number_format($remaining, 1) ?>
        <span style="font-size:1.1rem;font-weight:400;opacity:0.45;"> hrs</span>
      </div>
      <div class="dash-stat-sub" style="margin-top:6px;">hours left to complete your OJT</div>
      <?php if ($avg_hrs_day > 0): ?>
        <div class="dash-remaining-pill">
          <svg viewBox="0 0 24 24" fill="currentColor" style="width:11px;height:11px;"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
          Avg <?= $avg_hrs_day ?> hrs/day · <?= $projected_days ?> days left
        </div>
      <?php else: ?>
        <div class="dash-remaining-pill">Log hours to see your pace</div>
      <?php endif; ?>
    </div>
  </div>

<div class="dash-body">

  <!-- Left column -->
  <div class="dash-left">

    <!-- Recent logs -->
    <div class="dash-logs-section">
      <!-- Section header: toggles between title row and bulk-action bar, same height -->
      <div id="logs-header" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;min-height:28px;">
        <!-- Normal title (visible by default) -->
        <div id="logs-title-row" style="display:flex;align-items:center;justify-content:space-between;width:100%;">
          <span style="font-size:12px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:var(--text2);">Recent Logs</span>
          <a href="logs.php" style="font-size:11px;color:var(--green);font-weight:600;display:flex;align-items:center;gap:3px;">
            View all
            <svg viewBox="0 0 24 24" fill="currentColor" style="width:12px;height:12px;"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>
          </a>
        </div>
        <!-- Bulk-action bar (hidden by default, shown when rows selected) -->
        <div id="bulk-delete-bar" style="display:none;align-items:center;justify-content:space-between;width:100%;background:#fff5f5;border:1px solid #fecaca;border-radius:8px;padding:6px 12px;">
          <span id="bulk-delete-count" style="font-size:12px;font-weight:700;color:#dc2626;">0 selected</span>
          <div style="display:flex;gap:8px;align-items:center;">
            <button type="button" class="btn btn-secondary btn-sm" id="bulk-deselect" style="font-size:11px;padding:4px 10px;">Deselect all</button>
            <form method="POST" action="dashboard.php" id="bulk-delete-form" style="display:inline;">
              <input type="hidden" name="action" value="bulk_delete" />
              <div id="bulk-delete-ids"></div>
              <button type="submit" class="btn btn-danger btn-sm" style="font-size:11px;padding:4px 10px;">Delete selected</button>
            </form>
          </div>
        </div>
      </div>

      <div class="table-wrap" style="border: 1px solid var(--border); border-radius: 10px; overflow: hidden; background: var(--surface);">
        <table style="width:100%;border-collapse:collapse;table-layout:fixed;">
          <colgroup>
            <col style="width:36px;" />
            <col style="width:120px;" />
            <col style="width:auto;" />
            <col style="width:95px;" />
            <col style="width:95px;" />
            <col style="width:55px;" />
            <col style="width:60px;" />
          </colgroup>
          <thead>
            <tr style="background:var(--bg2);border-bottom:1px solid var(--border);">
              <th style="padding:7px 12px;text-align:left;">
                <input type="checkbox" class="log-checkbox" id="select-all" style="width:14px;height:14px;accent-color:var(--green);cursor:pointer;" />
              </th>
              <th style="padding:7px 12px;font-size:10px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--text3);text-align:left;">Date</th>
              <th style="padding:7px 12px;font-size:10px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--text3);text-align:left;">Description</th>
              <th style="padding:7px 12px;font-size:10px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--text3);text-align:left;">From</th>
              <th style="padding:7px 12px;font-size:10px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--text3);text-align:left;">To</th>
              <th style="padding:7px 12px;font-size:10px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--text3);text-align:left;">Hrs</th>
              <th style="padding:7px 12px;"></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent_logs)): ?>
              <tr>
                <td colspan="7" style="padding:3rem;text-align:center;">
                  <div style="display:flex;flex-direction:column;align-items:center;gap:8px;">
                    <div style="width:48px;height:48px;background:var(--bg2);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                      <svg viewBox="0 0 24 24" fill="var(--text3)" style="width:20px;height:20px;"><path d="M19 3h-1V1h-2v2H8V1H6v2H5C3.9 3 3 3.9 3 5v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/></svg>
                    </div>
                    <div style="font-size:14px;font-weight:600;color:var(--text2);">No logs yet</div>
                    <div style="font-size:12px;color:var(--text3);">Click "New Log Entry" to get started</div>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($recent_logs as $log): ?>
              <tr class="dash-log-row">
                <td style="padding:9px 12px;">
                  <input type="checkbox" class="log-checkbox row-checkbox" value="<?= e($log['id']) ?>"
                         style="width:14px;height:14px;accent-color:var(--green);cursor:pointer;" />
                </td>
                <td style="padding:9px 12px;font-size:12.5px;font-weight:600;color:var(--text);white-space:nowrap;">
                  <?= e(date('M j, Y', strtotime($log['date']))) ?>
                </td>
                <td style="padding:9px 12px;font-size:12.5px;color:var(--text2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                  <?= e($log['description'] ?: '—') ?>
                </td>
                <td style="padding:9px 12px;font-size:12.5px;color:var(--text2);font-variant-numeric:tabular-nums;white-space:nowrap;">
                  <?= e(date('g:i A', strtotime($log['from']))) ?>
                </td>
                <td style="padding:9px 12px;font-size:12.5px;color:var(--text2);font-variant-numeric:tabular-nums;white-space:nowrap;">
                  <?= e(date('g:i A', strtotime($log['to']))) ?>
                </td>
                <td style="padding:9px 12px;">
                  <span style="font-size:15px;font-weight:700;color:var(--green);font-variant-numeric:tabular-nums;">
                    <?= e(number_format($log['hours'], 1)) ?>
                  </span>
                </td>
                <td style="padding:9px 12px;">
                  <div style="display:flex;gap:4px;align-items:center;justify-content:flex-end;">
                    <button class="edit-btn"
                      data-id="<?= e($log['id']) ?>"
                      data-date="<?= e($log['date']) ?>"
                      data-desc="<?= e($log['description'] ?? '') ?>"
                      data-from="<?= e($log['from']) ?>"
                      data-to="<?= e($log['to']) ?>"
                      title="Edit"
                      style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:6px;background:none;border:none;cursor:pointer;color:var(--text3);transition:all 0.12s;">
                      <svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                    </button>
                    <form method="POST" action="dashboard.php" class="delete-form" style="display:inline;">
                      <input type="hidden" name="action" value="delete_log" />
                      <input type="hidden" name="log_id" value="<?= e($log['id']) ?>" />
                      <button type="submit" title="Delete"
                        style="width:28px;height:28px;display:flex;align-items:center;justify-content:center;border-radius:6px;background:none;border:none;cursor:pointer;color:var(--text3);transition:all 0.12s;">
                        <svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
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

  <!-- Right column -->
  <div class="dash-right">

    <!-- Today's Status card -->
    <div class="dash-right-card" style="border-radius: 16px;">

      <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; background: #fafafa;">
        <div>
          <div style="font-size: 13px; font-weight: 800; color: var(--text);">Today's Status</div>
          <div style="font-size: 10px; color: var(--text3); margin-top: 2px;"><?= date('D, M j, Y') ?></div>
        </div>

        <?php
          $today_str = date('Y-m-d');
          $today_log = null; $today_hrs = 0;
          foreach ($all_logs as $l) {
            if ($l['date'] === $today_str) { $today_log = $l; $today_hrs += $l['hours']; }
          }
        ?>

        <span style="font-size: 10px; font-weight: 700; padding: 4px 10px; border-radius: 999px; border: 1px solid <?= $today_log ? '#a7f3d0' : '#fde68a' ?>; background: <?= $today_log ? '#ecfdf5' : '#fffbeb' ?>; color: <?= $today_log ? '#065f46' : '#92400e' ?>;">
          <?= $today_log ? 'Logged' : 'Pending' ?>
        </span>
      </div>

      <div style="padding: 1.25rem;">
        <?php if ($today_log): ?>
          <div style="text-align: center; padding: 0.75rem 0;">
            <div style="width: 52px; height: 52px; border-radius: 14px; background: var(--green); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
              <svg viewBox="0 0 24 24" fill="white" style="width: 26px; height: 26px;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
            </div>
            <div style="font-size: 16px; font-weight: 800; color: var(--text);">Log Recorded</div>
            <div style="font-size: 13px; color: var(--text2); margin-top: 5px;"><strong><?= number_format($today_hrs, 1) ?> hours</strong> added today</div>
          </div>
        <?php else: ?>
          <form method="POST" action="dashboard.php">
            <input type="hidden" name="action" value="log_hours" />
            <input type="hidden" name="date" value="<?= date('Y-m-d') ?>" />

            <div style="margin-bottom: 1rem;">
              <textarea name="description" placeholder="Briefly describe your tasks..."
                     style="width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 10px; font-size: 12px; outline: none; background: var(--bg); min-height: 80px; resize: none; box-sizing: border-box;"></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 1rem;">
              <div>
                <label style="font-size: 9px; font-weight: 700; color: var(--text3); text-transform: uppercase; margin-bottom: 4px; display: block;">From</label>
                <input type="time" name="from" value="08:00" style="width: 100%; padding: 7px 6px; border: 1px solid var(--border); border-radius: 8px; font-size: 11px; font-weight: 600; background: var(--bg); box-sizing: border-box;">
              </div>
              <div>
                <label style="font-size: 9px; font-weight: 700; color: var(--text3); text-transform: uppercase; margin-bottom: 4px; display: block;">To</label>
                <input type="time" name="to" value="16:00" style="width: 100%; padding: 7px 6px; border: 1px solid var(--border); border-radius: 8px; font-size: 11px; font-weight: 600; background: var(--bg); box-sizing: border-box;">
              </div>
            </div>

            <button type="submit" style="width: 100%; padding: 13px; background: var(--green); color: white; border: none; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer;">
              Save Log
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script>
function updateQuickPreview() {
  const from = document.getElementById('quick-from')?.value;
  const to   = document.getElementById('quick-to')?.value;
  const prev = document.getElementById('quick-preview');
  if (!from || !to || !prev) return;
  const [fh, fm] = from.split(':').map(Number);
  const [th, tm] = to.split(':').map(Number);
  const hrs = ((th * 60 + tm) - (fh * 60 + fm)) / 60;
  prev.textContent = hrs > 0 ? hrs.toFixed(1) + ' hrs' : '— invalid';
  prev.style.color = hrs > 0 ? 'var(--green)' : 'var(--red)';
}
</script>

<!-- Log Hours Modal -->
<div class="modal-overlay" id="log-modal">
  <div class="modal-card">
    <div class="modal-title">New Log Entry</div>
    <div class="modal-subtitle">Record your OJT hours for a specific day.</div>
    <?php foreach ($log_errors as $err): ?>
      <span class="form-error"><?= e($err) ?></span>
    <?php endforeach; ?>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="action" value="log_hours" />
      <div class="form-group">
        <label class="form-label">Date</label>
        <input class="form-input" type="date" id="log-date" name="date" value="<?= date('Y-m-d') ?>" required />
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <input class="form-input" type="text" id="log-desc" name="description" placeholder="What did you work on?" />
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">From</label>
          <input class="form-input" type="time" id="log-from" name="from" value="08:00" required />
        </div>
        <div class="form-group">
          <label class="form-label">To</label>
          <input class="form-input" type="time" id="log-to" name="to" value="16:00" required />
        </div>
      </div>
      <div style="font-size:12px;color:var(--text3);margin-top:-0.5rem;margin-bottom:0.5rem;">
        Duration: <strong id="hrs-preview" style="color:var(--green);">8.00 hrs</strong>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" id="modal-close-btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Log</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Log Modal -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal-card">
    <div class="modal-title">Edit Log</div>
    <div class="modal-subtitle">Update the details for this log entry.</div>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="action" value="edit_log" />
      <input type="hidden" name="log_id" id="edit-log-id" />
      <div class="form-group">
        <label class="form-label">Date</label>
        <input class="form-input" type="date" id="edit-date" name="date" required />
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <input class="form-input" type="text" id="edit-desc" name="description" />
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">From</label>
          <input class="form-input" type="time" id="edit-from" name="from" required />
        </div>
        <div class="form-group">
          <label class="form-label">To</label>
          <input class="form-input" type="time" id="edit-to" name="to" required />
        </div>
      </div>
      <div style="font-size:12px;color:var(--text3);margin-top:-0.5rem;margin-bottom:0.5rem;">
        Duration: <strong id="edit-hrs-preview" style="color:var(--green);"></strong>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" id="edit-close-btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Bulk Log Modal -->
<div class="modal-overlay" id="bulk-modal">
  <div class="modal-card" style="max-width:520px;">
    <div class="modal-title">Bulk Entry</div>
    <div class="modal-subtitle">Fill past days automatically. Already-logged days are skipped.</div>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="action" value="bulk_log" />
      <div style="display:grid;grid-template-columns:1fr 1fr 90px;gap:10px;margin-bottom:1rem;">
        <div class="form-group" style="margin:0;">
          <label class="form-label">From</label>
          <input class="form-input" type="date" name="bulk_start" id="bulk-start" required />
        </div>
        <div class="form-group" style="margin:0;">
          <label class="form-label">To</label>
          <input class="form-input" type="date" name="bulk_end" id="bulk-end" required />
        </div>
        <div class="form-group" style="margin:0;">
          <label class="form-label">Hrs/Day</label>
          <input class="form-input" type="number" name="bulk_hrs" id="bulk-hrs" value="8" min="0.5" max="24" step="0.5" required />
        </div>
      </div>
      <input type="hidden" name="bulk_from" value="08:00" />
      <input type="hidden" name="bulk_to" id="bulk-to-hidden" value="16:00" />
      <div class="form-group" style="margin-bottom:1rem;">
        <label class="form-label" style="margin-bottom:8px;">Exclude Days <span style="font-weight:400;color:var(--text3);">(selected = skip)</span></label>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <?php foreach (['MON','TUE','WED','THU','FRI','SAT','SUN'] as $i => $day): ?>
            <label class="day-toggle <?= in_array($day, ['SAT','SUN']) ? 'day-toggle--excluded' : '' ?>">
              <input type="checkbox" name="exclude_days[]" value="<?= $i + 1 ?>" <?= in_array($day, ['SAT','SUN']) ? 'checked' : '' ?> style="display:none;" />
              <span><?= $day ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Description (optional)</label>
        <input class="form-input" type="text" name="bulk_desc" placeholder="e.g. OJT at company" />
      </div>
      <div id="bulk-range-preview" style="font-size:12px;margin-bottom:1rem;min-height:16px;color:var(--green-dark);font-weight:600;"></div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" id="bulk-close-btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Fill Days in Range</button>
      </div>
    </form>
  </div>
</div>

<?php if (!empty($log_errors)) echo '<script>document.getElementById("log-modal").classList.add("open");</script>'; ?>
<?php include 'includes/footer.php'; ?>