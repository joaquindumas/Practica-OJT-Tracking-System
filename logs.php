<?php
// Ensure accurate "Today" calculations for the Philippines
date_default_timezone_set('Asia/Manila');

require_once 'includes/config.php';
require_login();

$user        = current_user();
$active_page = 'logs';
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
                header('Location: logs.php');
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
        if (!$date || !$from || !$to) {
            set_flash('error', 'Please fill in date, from, and to.');
            header('Location: logs.php'); exit;
        }
        [$fh, $fm] = array_map('intval', explode(':', $from));
        [$th, $tm] = array_map('intval', explode(':', $to));
        $hours = (($th * 60 + $tm) - ($fh * 60 + $fm)) / 60;
        if ($hours <= 0) {
            set_flash('error', '"To" time must be after "From" time.');
            header('Location: logs.php'); exit;
        }
        update_log($edit_id, $user['id'], [
            'date'        => $date,
            'description' => $desc,
            'from'        => $from,
            'to'          => $to,
            'hours'       => round($hours, 4),
        ]);
        set_flash('success', 'Log updated successfully!');
        header('Location: logs.php');
        exit;
    }

    if ($action === 'delete_log') {
        delete_log($_POST['log_id'] ?? '', $user['id']);
        set_flash('success', 'Log entry deleted.');
        header('Location: logs.php');
        exit;
    }

    if ($action === 'bulk_delete') {
        $ids   = $_POST['log_ids'] ?? [];
        $count = 0;
        foreach ($ids as $id) { delete_log($id, $user['id']); $count++; }
        set_flash('success', "{$count} log" . ($count !== 1 ? 's' : '') . " deleted.");
        header('Location: logs.php');
        exit;
    }

    if ($action === 'bulk_edit_time') {
        $ids       = $_POST['log_ids']  ?? [];
        $bulk_from = $_POST['bulk_from'] ?? '';
        $bulk_to   = $_POST['bulk_to']   ?? '';
        $bulk_desc = trim($_POST['bulk_desc'] ?? '');

        if ($bulk_from && $bulk_to) {
            [$fh, $fm] = array_map('intval', explode(':', $bulk_from));
            [$th, $tm] = array_map('intval', explode(':', $bulk_to));
            $hours = (($th * 60 + $tm) - ($fh * 60 + $fm)) / 60;
            if ($hours > 0) {
                $count = 0;
                foreach ($ids as $id) {
                    $stmt = db()->prepare('SELECT * FROM time_logs WHERE id = ? AND user_id = ?');
                    $stmt->execute([$id, $user['id']]);
                    $existing = $stmt->fetch();
                    if ($existing) {
                        update_log($id, $user['id'], [
                            'date'        => $existing['date'],
                            'description' => $bulk_desc ?: ($existing['description'] ?? ''),
                            'from'        => $bulk_from,
                            'to'          => $bulk_to,
                            'hours'       => round($hours, 4),
                        ]);
                        $count++;
                    }
                }
                set_flash('success', "{$count} log" . ($count !== 1 ? 's' : '') . " updated.");
            }
        }
        header('Location: logs.php');
        exit;
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
            $cursor    = strtotime($start);
            $endTs     = strtotime($end);
            while ($cursor <= $endTs) {
                $dow = (int) date('N', $cursor);
                if (!in_array($dow, $exclude_days)) {
                    $bulk_logs[] = ['date' => date('Y-m-d', $cursor), 'from' => $from_time, 'to' => $to_time, 'description' => $desc];
                }
                $cursor = strtotime('+1 day', $cursor);
            }
            $count = bulk_add_logs($user['id'], $bulk_logs);
            set_flash('success', "{$count} log" . ($count !== 1 ? 's' : '') . " added successfully!");
        } else {
            set_flash('error', 'Please fill in all fields and make sure start date is before end date.');
        }
        header('Location: logs.php');
        exit;
    }
}

$user      = current_user();
$all_logs  = $user['logs'] ?? [];
usort($all_logs, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
$total_hrs = total_logged($user);
$required  = $user['required_hours'] ?? 240;

// Build log map for calendar
$log_map = [];
foreach ($all_logs as $l) {
    $log_map[$l['date']][] = $l;
}

// Month navigation & Calendar Math (Sunday First)
$month_param = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month_param)) $month_param = date('Y-m');
[$cal_year, $cal_month] = explode('-', $month_param);
$cal_year      = (int) $cal_year;
$cal_month     = (int) $cal_month;
$first_date_ts = mktime(0, 0, 0, $cal_month, 1, $cal_year);
$prev_month    = date('Y-m', mktime(0, 0, 0, $cal_month - 1, 1, $cal_year));
$next_month    = date('Y-m', mktime(0, 0, 0, $cal_month + 1, 1, $cal_year));
$today         = date('Y-m-d');
$month_name    = date('F Y', $first_date_ts);
$days_in_month = (int) date('t', $first_date_ts);

$first_dow_zero_indexed = (int) date('w', $first_date_ts);

// Calculate Monthly Totals
$month_hrs = 0;
foreach ($log_map as $date => $dlogs) {
    if (substr($date, 0, 7) === $month_param) {
        $month_hrs += array_sum(array_column($dlogs, 'hours'));
    }
}
$month_target = 200.0;
$month_pct = min(100, ($month_hrs / $month_target) * 100);

// Calculate Weekly Summaries for the current month
$weeks_in_month = [];
$current_week_start = 1;
$week_num = 1;
while ($current_week_start <= $days_in_month) {
    $current_week_end = $current_week_start + (6 - date('w', mktime(0,0,0,$cal_month,$current_week_start,$cal_year)));
    if ($current_week_end > $days_in_month) $current_week_end = $days_in_month;
    
    $w_hrs = 0;
    $w_active = 0;
    for ($wd = $current_week_start; $wd <= $current_week_end; $wd++) {
        $w_date_str = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $wd);
        if (isset($log_map[$w_date_str])) {
            $w_hrs += array_sum(array_column($log_map[$w_date_str], 'hours'));
            $w_active++;
        }
    }
    
    if ($w_active > 0 || $current_week_start == 1) {
        $weeks_in_month[] = [
            'num' => "W$week_num",
            'label_dynamic' => "Week $week_num (" . date('M j', mktime(0,0,0,$cal_month,$current_week_start,$cal_year)) . "-" . date('j', mktime(0,0,0,$cal_month,$current_week_end,$cal_year)) . ")",
            'active' => $w_active,
            'hrs' => round($w_hrs, 1)
        ];
    }
    $current_week_start = $current_week_end + 1;
    $week_num++;
}

include 'includes/header.php';
?>

<style>
/* ═══════════════════════════════════════════════
   GLOBAL SCROLL UNLOCK
   Forces the page to scroll regardless of any
   global dashboard overrides on html/body/main.
   ═══════════════════════════════════════════════ */
html,
body {
    overflow-y: auto !important;
    overflow-x: hidden !important;
    height: auto !important;
    min-height: 100% !important;
}

main,
.main-content,
.content-wrapper,
.page-wrapper,
.dashboard-body {
    overflow-y: auto !important;
    overflow-x: hidden !important;
    height: auto !important;
    min-height: 100% !important;
}

/* ═══════════════════════════════════════════════
   LAYOUT SHELL
   ═══════════════════════════════════════════════ */
.dash-wrap {
    padding: 1.5rem;
    max-width: 1200px;
    margin: 0 auto;
    /* Extra bottom padding so the last element
       is never hidden behind a mobile nav bar   */
    padding-bottom: 7rem;
    box-sizing: border-box;
    width: 100%;
}

/* ═══════════════════════════════════════════════
   PAGE HEADER
   ═══════════════════════════════════════════════ */
.logs-header-wrap {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;          /* wraps on narrow screens */
}

.logs-eyebrow {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--green, #2d6a4f);
    margin-bottom: 4px;
}

.logs-title {
    font-size: clamp(1.5rem, 5vw, 2rem);  /* fluid font size */
    font-weight: 800;
    color: var(--text, #1a1a1a);
    letter-spacing: -0.03em;
    margin: 0 0 6px 0;
}

.logs-subtitle {
    font-size: 13px;
    color: var(--text3, #555);
    max-width: 500px;
    line-height: 1.5;
    margin: 0;
}

.logs-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    flex-shrink: 0;
}

/* ═══════════════════════════════════════════════
   TOP STATS GRID
   ═══════════════════════════════════════════════ */
.top-stats-grid {
    display: grid;
    grid-template-columns: 1fr 1.5fr;
    gap: 16px;
    margin-bottom: 20px;
}

/* Stack to single column on tablets and below */
@media (max-width: 860px) {
    .top-stats-grid {
        grid-template-columns: 1fr;
    }
}

/* ── Monthly Progress Card ── */
.monthly-progress-card {
    background: #f0faf2;
    border: 1px solid #c8e6d0;
    border-radius: 16px;
    padding: 1.5rem 1.75rem;
    position: relative;
    overflow: hidden;
    box-sizing: border-box;
}

.monthly-progress-card .label {
    font-size: 11px;
    font-weight: 600;
    color: var(--green-mid, #40916c);
    margin-bottom: 12px;
}

.monthly-progress-card .val-row {
    display: flex;
    align-items: baseline;
    gap: 6px;
    margin-bottom: 6px;
    flex-wrap: wrap;
}

.monthly-progress-card .val-main {
    font-size: clamp(2rem, 6vw, 2.75rem);
    font-weight: 800;
    color: var(--green-dark, #1b4332);
    line-height: 1;
    letter-spacing: -0.04em;
}

.monthly-progress-card .val-sub {
    font-size: 13px;
    font-weight: 500;
    color: var(--text3, #555);
}

.monthly-progress-card .pct-text {
    font-size: 11px;
    font-weight: 700;
    color: var(--green, #2d6a4f);
}

.monthly-progress-bar-wrap {
    height: 8px;
    background: var(--green-light, #b7e4c7);
    border-radius: 999px;
    margin-top: 30px;
    position: relative;
    /* shrink bar so watermark doesn't overlap on small screens */
    width: min(80%, calc(100% - 90px));
    z-index: 2;
}

.monthly-progress-bar-fill {
    height: 100%;
    background: var(--green-dark, #1b4332);
    border-radius: 999px;
}

.watermark-clock {
    position: absolute;
    right: -20px;
    bottom: -20px;
    width: 100px;
    height: 100px;
    background: rgba(255,255,255,0.5);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}

.watermark-clock::after {
    content: '';
    width: 40px;
    height: 40px;
    border-left: 4px solid white;
    border-bottom: 4px solid white;
    transform: rotate(-15deg);
    margin-top: -10px;
    margin-left: 10px;
}

/* ── Weekly Summaries Card ── */
.weekly-summaries-card {
    background: var(--surface, white);
    border-radius: 16px;
    padding: 1.5rem 1.75rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    border: 1px solid var(--border, #eee);
    box-sizing: border-box;
    /* Let it scroll internally if there are many weeks */
    overflow-y: auto;
    max-height: 320px;
}

.weekly-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
    flex-wrap: wrap;
    gap: 4px;
}

.weekly-header h3 {
    font-size: 13px;
    font-weight: 700;
    color: var(--text, #1a1a1a);
    margin: 0;
}

.weekly-header span {
    font-size: 10px;
    font-weight: 600;
    color: var(--text3, #888);
}

.week-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid var(--border, #f0f0f0);
    gap: 8px;
}

.week-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.week-left {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;       /* allows text truncation */
}

.week-badge {
    width: 32px;
    height: 32px;
    min-width: 32px;    /* prevent shrinking */
    background: rgba(45,106,79,0.1);
    color: var(--green-dark, #1b4332);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    font-weight: 800;
}

.week-info h4 {
    font-size: 12px;
    font-weight: 700;
    color: var(--text, #1a1a1a);
    margin: 0 0 2px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.week-info p {
    font-size: 10px;
    color: var(--text3, #888);
    margin: 0;
}

.week-right {
    text-align: right;
    flex-shrink: 0;
}

.week-hrs {
    font-size: 13px;
    font-weight: 700;
    color: var(--green, #2d6a4f);
    margin-bottom: 2px;
    white-space: nowrap;
}

.week-dots {
    color: var(--green, #2d6a4f);
    font-size: 16px;
    line-height: 0.5;
    letter-spacing: 2px;
}

/* ═══════════════════════════════════════════════
   CALENDAR
   ═══════════════════════════════════════════════ */
.new-cal-card {
    background: var(--surface, white);
    border-radius: 16px;
    padding: 1.75rem;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    border: 1px solid var(--border, #eee);
    margin-bottom: 20px;
    box-sizing: border-box;
}

.new-cal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    gap: 12px;
    flex-wrap: wrap;
}

.new-cal-title-area {
    display: flex;
    align-items: center;
    gap: 16px;
}

.new-cal-title-area h2 {
    font-size: clamp(1rem, 4vw, 1.25rem);
    font-weight: 800;
    color: var(--text, #1a1a1a);
    margin: 0;
    white-space: nowrap;
}

.new-cal-nav {
    display: flex;
    gap: 8px;
}

.new-cal-nav a {
    width: 32px;           /* slightly larger tap target */
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text, #1a1a1a);
    border-radius: 6px;
    text-decoration: none;
    transition: background 0.15s;
}

.new-cal-nav a:hover,
.new-cal-nav a:focus-visible {
    background: var(--bg, #f0f0f0);
    outline: none;
}

.new-cal-legend {
    display: flex;
    gap: 16px;
    font-size: 11px;
    font-weight: 500;
    color: var(--text3, #555);
    flex-wrap: wrap;
}

.new-cal-legend .dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-right: 6px;
    vertical-align: middle;
}

.dot-logged { background: var(--green, #2d6a4f); }
.dot-empty  { background: #e0e0e0; }

/* Scroll container — horizontal on small screens */
.new-cal-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    /* subtle scrollbar styling for webkit */
    scrollbar-width: thin;
    scrollbar-color: #c8e6d0 transparent;
}

.new-cal-wrapper::-webkit-scrollbar {
    height: 4px;
}

.new-cal-wrapper::-webkit-scrollbar-track {
    background: transparent;
}

.new-cal-wrapper::-webkit-scrollbar-thumb {
    background: #c8e6d0;
    border-radius: 4px;
}

/* The actual grid — enforces a minimum width so
   cells never collapse below a usable size.       */
.new-cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    border-radius: 12px;
    overflow: hidden;
    min-width: 560px;      /* prevents extreme squishing */
}

.new-cal-dow {
    padding: 10px 8px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text3, #888);
    text-align: center;
    background: #fafafa;
    border-bottom: 1px solid var(--border, #eee);
    letter-spacing: 0.05em;
}

.new-cal-cell {
    min-height: 100px;
    padding: 10px;
    border-bottom: 1px solid var(--border, #f5f5f5);
    border-right: 1px solid var(--border, #f5f5f5);
    position: relative;
    transition: background 0.15s ease;
    cursor: pointer;
    box-sizing: border-box;
}

.new-cal-cell:nth-child(7n) { border-right: none; }

.new-cal-cell:hover { background: #fafafa; }

.new-cal-cell.empty {
    background: transparent;
    cursor: default;
    pointer-events: none;
}

.new-cal-date {
    font-size: 12px;
    font-weight: 500;
    color: var(--text3, #555);
    margin-bottom: 8px;
    display: block;
}

.pill-logged {
    background: rgba(45,106,79,0.08);
    border-left: 3px solid var(--green, #2d6a4f);
    color: var(--green-dark, #1b4332);
    padding: 4px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    width: max-content;
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pill-progress {
    background: #f0faf2;
    border-left: 3px solid var(--green-mid, #40916c);
    color: var(--green-dark, #1b4332);
    padding: 4px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    width: max-content;
    max-width: 100%;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 4px;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pill-progress::after {
    content: '';
    width: 4px;
    height: 4px;
    background: var(--green-mid, #40916c);
    border-radius: 50%;
    flex-shrink: 0;
}

/* ═══════════════════════════════════════════════
   INSIGHT CARD
   ═══════════════════════════════════════════════ */
.insight-card {
    background: rgba(45,106,79,0.04);
    border-radius: 12px;
    padding: 1.25rem 1.5rem;
    display: flex;
    gap: 16px;
    align-items: flex-start;   /* align top for tall text */
    border: 1px solid rgba(45,106,79,0.1);
    margin-bottom: 1rem;
    flex-wrap: wrap;
}

.insight-icon {
    width: 40px;
    height: 40px;
    min-width: 40px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.insight-icon svg { width: 20px; height: 20px; fill: var(--green, #2d6a4f); }

.insight-text h4 {
    font-size: 13px;
    font-weight: 700;
    color: var(--green-dark, #1b4332);
    margin: 0 0 4px 0;
}

.insight-text p {
    font-size: 11px;
    color: var(--text3, #555);
    margin: 0;
    line-height: 1.5;
}

/* ═══════════════════════════════════════════════
   BUTTONS
   ═══════════════════════════════════════════════ */
.btn-outline {
    background: transparent;
    color: var(--text3, #555);
    border: 1px solid var(--border, #ddd);
    font-size: 12px;
    font-weight: 600;
    padding: 10px 16px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
}
.btn-outline:hover { background: #fafafa; color: var(--text, #1a1a1a); }

.btn-secondary-green {
    background: rgba(45,106,79,0.1);
    color: var(--green-dark, #1b4332);
    font-weight: 700;
    border: none;
    padding: 10px 16px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    font-size: 12px;
    transition: background 0.2s;
    white-space: nowrap;
}
.btn-secondary-green:hover { background: rgba(45,106,79,0.15); }

.btn-primary-green {
    background: var(--green, #2d6a4f);
    color: white;
    font-weight: 700;
    border: none;
    padding: 10px 16px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    font-size: 12px;
    transition: background 0.2s;
    white-space: nowrap;
}
.btn-primary-green:hover { background: var(--green-dark, #1b4332); }

/* ═══════════════════════════════════════════════
   LIST VIEW — TABLE
   ═══════════════════════════════════════════════ */
.list-table-wrap {
    background: var(--surface, white);
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.03);
    border: 1px solid var(--border, #eee);
    /* Horizontal scroll for narrow screens */
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #c8e6d0 transparent;
    box-sizing: border-box;
    width: 100%;
}

.list-table-wrap::-webkit-scrollbar { height: 4px; }
.list-table-wrap::-webkit-scrollbar-track { background: transparent; }
.list-table-wrap::-webkit-scrollbar-thumb {
    background: #c8e6d0;
    border-radius: 4px;
}

.log-table {
    width: 100%;
    border-collapse: collapse;
    /* Minimum width keeps columns readable;
       the wrapper handles scrolling.            */
    min-width: 560px;
}

.log-table thead tr {
    border-bottom: 1px solid var(--border, #eee);
    text-align: left;
    font-size: 10px;
    color: var(--text3, #888);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.log-table th,
.log-table td {
    padding: 12px 10px;
    vertical-align: middle;
}

.log-table th:first-child,
.log-table td:first-child {
    width: 40px;
    padding-left: 12px;
}

.log-table th:last-child,
.log-table td:last-child {
    padding-right: 12px;
}

.log-table tbody tr {
    border-bottom: 1px solid var(--border, #f5f5f5);
    font-size: 13px;
    transition: background 0.15s;
}

.log-table tbody tr:hover { background: #fafafa; }
.log-table tbody tr:last-child { border-bottom: none; }

/* Action buttons inside table cells */
.log-action-btns {
    display: flex;
    gap: 8px;
    align-items: center;
}

/* ═══════════════════════════════════════════════
   BULK DELETE BAR
   ═══════════════════════════════════════════════ */
.bulk-delete-bar {
    display: none;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    padding: 12px 16px;
    background: #fff5f5;
    border: 1px solid #fecaca;
    border-radius: 8px;
    margin-bottom: 16px;
}

/* ═══════════════════════════════════════════════
   MODALS
   ═══════════════════════════════════════════════ */
/* Make modals scroll on short viewports */
.modal-overlay {
    /* Should already be defined globally; these
       ensure the modal itself is scrollable.    */
    align-items: flex-start !important;
    padding: 1rem !important;
    overflow-y: auto !important;
}

.modal-card {
    max-height: calc(100dvh - 2rem) !important;
    overflow-y: auto !important;
    box-sizing: border-box;
    width: 100%;
    max-width: 480px;
    margin: auto;
    border-radius: 16px;
    padding: 1.5rem;
}

/* Wider modal variant */
.modal-card.modal-wide {
    max-width: 520px;
}

/* ═══════════════════════════════════════════════
   RESPONSIVE BREAKPOINTS
   ═══════════════════════════════════════════════ */

/* Tablet (≤ 768px) */
@media (max-width: 768px) {
    .dash-wrap {
        padding: 1rem;
        padding-bottom: 6rem;
    }

    .logs-header-wrap {
        flex-direction: column;
        align-items: flex-start;
    }

    .logs-actions {
        width: 100%;
    }

    .logs-actions button {
        flex: 1;
        justify-content: center;
    }

    .new-cal-card {
        padding: 1.25rem 1rem;
    }

    .weekly-summaries-card {
        max-height: none;   /* no artificial cap on mobile */
    }
}

/* Mobile (≤ 480px) */
@media (max-width: 480px) {
    .logs-actions {
        flex-direction: column;
    }

    .logs-actions button {
        width: 100%;
    }

    .insight-card {
        padding: 1rem;
    }

    .new-cal-cell {
        min-height: 72px;
        padding: 6px;
    }

    .new-cal-date {
        font-size: 10px;
    }

    .pill-logged,
    .pill-progress {
        font-size: 9px;
        padding: 3px 5px;
    }
}
</style>

<div class="dash-wrap">

  <!-- ── Page Header ── -->
  <div class="logs-header-wrap">
    <div>
      <div class="logs-eyebrow">Attendance Tracking</div>
      <h1 class="logs-title">Time Logs</h1>
      <p class="logs-subtitle">Review and manage your OJT rendering progress for the current academic term.</p>
    </div>
    <div class="logs-actions">
      <button type="button" class="btn-outline" id="view-list-btn">View List</button>
      <button class="btn-secondary-green" id="open-bulk-btn">
        <svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;margin-right:6px;"><path d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        Bulk Log
      </button>
      <button class="btn-primary-green" id="open-modal-btn">
        <svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;margin-right:6px;"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
        Log Hours
      </button>
    </div>
  </div>

  <!-- ══════════════════════════════
       CALENDAR VIEW
  ══════════════════════════════ -->
  <div id="view-calendar">

    <!-- Top Stats Grid -->
    <div class="top-stats-grid">

      <!-- Monthly Progress -->
      <div class="monthly-progress-card">
        <div class="label">Monthly Progress</div>
        <div class="val-row">
          <span class="val-main"><?= number_format($month_hrs, 1) ?></span>
          <span class="val-sub">/ <?= number_format($month_target, 1) ?> hrs</span>
        </div>
        <div class="pct-text"><?= number_format($month_pct, 0) ?>% of target reached</div>
        <div class="monthly-progress-bar-wrap">
          <div class="monthly-progress-bar-fill" style="width: <?= $month_pct ?>%;"></div>
        </div>
        <div class="watermark-clock"></div>
      </div>

      <!-- Weekly Summaries -->
      <div class="weekly-summaries-card">
        <div class="weekly-header">
          <h3>Weekly Summaries</h3>
          <span><?= date('F Y', $first_date_ts) ?></span>
        </div>

        <?php if (empty($weeks_in_month)): ?>
          <p style="font-size:12px;color:var(--text3);">No weeks recorded yet.</p>
        <?php else: ?>
          <?php foreach ($weeks_in_month as $wk): ?>
            <div class="week-row">
              <div class="week-left">
                <div class="week-badge"><?= $wk['num'] ?></div>
                <div class="week-info">
                  <h4><?= $wk['label_dynamic'] ?></h4>
                  <p><?= $wk['active'] ?> active log<?= $wk['active'] !== 1 ? 's' : '' ?> recorded</p>
                </div>
              </div>
              <div class="week-right">
                <div class="week-hrs"><?= number_format($wk['hrs'], 1) ?> hrs</div>
                <div class="week-dots">&middot;&middot;&middot;</div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Calendar Card -->
    <div class="new-cal-card">
      <div class="new-cal-header">
        <div class="new-cal-title-area">
          <h2><?= date('F Y', $first_date_ts) ?></h2>
          <div class="new-cal-nav">
            <a href="?month=<?= $prev_month ?>" aria-label="Previous month">
              <svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>
            </a>
            <a href="?month=<?= $next_month ?>" aria-label="Next month">
              <svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
            </a>
          </div>
        </div>
        <div class="new-cal-legend">
          <span class="legend-item"><span class="dot dot-logged"></span> Logged Hours</span>
          <span class="legend-item"><span class="dot dot-empty"></span> Empty Day</span>
        </div>
      </div>

      <!-- Horizontal-scroll wrapper around the grid -->
      <div class="new-cal-wrapper">
        <div class="new-cal-grid">
          <div class="new-cal-dow">SUN</div>
          <div class="new-cal-dow">MON</div>
          <div class="new-cal-dow">TUE</div>
          <div class="new-cal-dow">WED</div>
          <div class="new-cal-dow">THU</div>
          <div class="new-cal-dow">FRI</div>
          <div class="new-cal-dow">SAT</div>

          <?php for ($e = 0; $e < $first_dow_zero_indexed; $e++): ?>
            <div class="new-cal-cell empty"></div>
          <?php endfor; ?>

          <?php for ($d = 1; $d <= $days_in_month; $d++):
              $date_str  = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $d);
              $day_logs  = $log_map[$date_str] ?? [];
              $is_logged = count($day_logs) > 0;
              $is_today  = $date_str === $today;
              $total_day = array_sum(array_column($day_logs, 'hours'));
          ?>
            <div class="new-cal-cell"
                 data-date="<?= $date_str ?>"
                 data-logged="<?= $is_logged ? '1' : '0' ?>"
                 onclick="handleDayClick(this, event)">

              <span class="new-cal-date"
                    <?= $is_today ? 'style="color:var(--green-dark);font-weight:800;"' : '' ?>>
                <?= $d ?>
              </span>

              <?php if ($is_logged): ?>
                <div class="pill-logged"><?= number_format($total_day, 1) ?> hrs</div>
              <?php elseif ($is_today): ?>
                <div class="pill-progress">In Progress</div>
              <?php endif; ?>

            </div>
          <?php endfor; ?>
        </div><!-- /.new-cal-grid -->
      </div><!-- /.new-cal-wrapper -->
    </div><!-- /.new-cal-card -->

    <!-- Insight Card -->
    <div class="insight-card">
      <div class="insight-icon">
        <svg viewBox="0 0 24 24"><path d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7zm2.85 11.1l-.85.6V16h-4v-2.3l-.85-.6A4.997 4.997 0 0 1 7 9c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.63-.8 3.16-2.15 4.1z"/></svg>
      </div>
      <div class="insight-text">
        <h4>Study Insight: Rendering Efficiency</h4>
        <p>Your average daily logged hours remain consistent. Logging morning hours (8:00 AM – 10:00 AM) correlates with higher overall supervisor productivity scores.</p>
      </div>
    </div>

  </div><!-- /#view-calendar -->

  <!-- ══════════════════════════════
       LIST VIEW
  ══════════════════════════════ -->
  <div id="view-list" style="display:none;">

    <!-- Bulk delete bar -->
    <div class="bulk-delete-bar" id="bulk-delete-bar">
      <span id="bulk-delete-count" style="color:#dc2626; font-weight:700; font-size:12px;">0 selected</span>
      <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <button type="button" class="btn-outline" id="bulk-deselect"
                style="padding:6px 14px;font-size:12px;background:white;">
          Deselect all
        </button>
        <form method="POST" action="logs.php" id="bulk-delete-form" style="margin:0;">
          <input type="hidden" name="action" value="bulk_delete" />
          <div id="bulk-delete-ids"></div>
          <button type="submit" class="btn-primary-green"
                  style="background:#dc2626; padding:6px 14px; font-size:12px;">
            Delete selected
          </button>
        </form>
      </div>
    </div>

    <!-- Scrollable table wrapper -->
    <div class="list-table-wrap">
      <table class="log-table">
        <thead>
          <tr>
            <th><input type="checkbox" id="select-all" style="cursor:pointer;" /></th>
            <th>Date</th>
            <th>Description</th>
            <th>From</th>
            <th>To</th>
            <th>Hours</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($all_logs)): ?>
            <tr>
              <td colspan="7">
                <div style="padding:40px;text-align:center;color:var(--text3);font-size:12px;">
                  No logs yet.
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($all_logs as $log): ?>
              <tr>
                <td>
                  <input type="checkbox" class="row-checkbox" value="<?= e($log['id']) ?>"
                         style="cursor:pointer; accent-color:var(--green);" />
                </td>
                <td style="font-weight:600; color:var(--text);">
                  <?= e(date('M j, Y', strtotime($log['date']))) ?>
                </td>
                <td style="color:var(--text3);"><?= e($log['description'] ?: '—') ?></td>
                <td style="color:var(--text3); white-space:nowrap;">
                  <?= e(date('g:i A', strtotime($log['from']))) ?>
                </td>
                <td style="color:var(--text3); white-space:nowrap;">
                  <?= e(date('g:i A', strtotime($log['to']))) ?>
                </td>
                <td style="font-weight:700; color:var(--green); white-space:nowrap;">
                  <?= e(number_format($log['hours'], 1)) ?>
                </td>
                <td>
                  <div class="log-action-btns">
                    <button class="edit-btn"
                            style="background:none;border:none;cursor:pointer;color:var(--text3);transition:color 0.15s;"
                            onmouseover="this.style.color='var(--green)'"
                            onmouseout="this.style.color='var(--text3)'"
                            title="Edit"
                            data-id="<?= e($log['id']) ?>"
                            data-date="<?= e($log['date']) ?>"
                            data-desc="<?= e($log['description'] ?? '') ?>"
                            data-from="<?= e($log['from']) ?>"
                            data-to="<?= e($log['to']) ?>">
                      <svg viewBox="0 0 24 24" fill="currentColor" style="width:14px;height:14px;">
                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                      </svg>
                    </button>
                    <form method="POST" action="logs.php" style="margin:0;"
                          onsubmit="return confirm('Delete this log?')">
                      <input type="hidden" name="action" value="delete_log" />
                      <input type="hidden" name="log_id" value="<?= e($log['id']) ?>" />
                      <button type="submit" title="Delete"
                              style="background:none;border:none;cursor:pointer;color:var(--text3);transition:color 0.15s;"
                              onmouseover="this.style.color='#dc2626'"
                              onmouseout="this.style.color='var(--text3)'">✕</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div><!-- /.list-table-wrap -->

  </div><!-- /#view-list -->

</div><!-- /.dash-wrap -->

<!-- ══════════════════════════════
     MODALS
══════════════════════════════ -->

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

<!-- Day Detail Modal -->
<div class="modal-overlay" id="day-modal">
  <div class="modal-card modal-wide">
    <div class="modal-title" id="day-modal-title" style="font-weight:800; font-size:18px; margin-bottom:16px;">Logs for —</div>
    <div id="day-modal-body"></div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px;flex-wrap:wrap;">
      <button type="button" class="btn-outline" id="day-modal-close">Close</button>
      <button type="button" class="btn-secondary-green" id="day-modal-add">+ Add Log</button>
    </div>
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

<script>
// ── Core Helpers ──────────────────────────────────────────────────────────────
function formatTime(t) {
  if (!t) return '';
  const [h, m] = t.split(':').map(Number);
  const ampm = h >= 12 ? 'PM' : 'AM';
  const hour  = h % 12 || 12;
  return `${hour}:${m.toString().padStart(2, '0')} ${ampm}`;
}

function calcHrs(from, to) {
  if (!from || !to) return 0;
  const [fh, fm] = from.split(':').map(Number);
  const [th, tm] = to.split(':').map(Number);
  return ((th * 60 + tm) - (fh * 60 + fm)) / 60;
}

// ── View Toggle ───────────────────────────────────────────────────────────────
const viewListBtn = document.getElementById('view-list-btn');
const viewCal     = document.getElementById('view-calendar');
const viewList    = document.getElementById('view-list');

function toggleView() {
  const current = localStorage.getItem('logs_view') || 'calendar';
  if (current === 'calendar') {
    viewCal.style.display  = 'none';
    viewList.style.display = 'block';
    viewListBtn.textContent = 'View Calendar';
    localStorage.setItem('logs_view', 'list');
  } else {
    viewCal.style.display  = 'block';
    viewList.style.display = 'none';
    viewListBtn.textContent = 'View List';
    localStorage.setItem('logs_view', 'calendar');
  }
}

// Restore saved view on load
if (localStorage.getItem('logs_view') === 'list') {
  viewCal.style.display  = 'none';
  viewList.style.display = 'block';
  viewListBtn.textContent = 'View Calendar';
}
viewListBtn.addEventListener('click', toggleView);

// ── Data bridge for Modals ───────────────────────────────────────────────────
const logData = <?php
  $js_map = [];
  foreach ($log_map as $date => $dlogs) {
      if (substr($date, 0, 7) !== $month_param) continue;
      $js_map[$date] = array_map(fn($l) => [
          'id'    => $l['id'],
          'from'  => $l['from'],
          'to'    => $l['to'],
          'hours' => $l['hours'],
          'desc'  => $l['description'] ?? '',
          'date'  => $l['date'],
      ], $dlogs);
  }
  echo json_encode($js_map);
?>;

// ── Day Cell Click Handler ───────────────────────────────────────────────────
function handleDayClick(el, event) {
  const date     = el.dataset.date;
  const isLogged = el.dataset.logged === '1';
  if (isLogged) openDayModal(date);
  else          openLogModal(date);
}

// ── Log Modal ────────────────────────────────────────────────────────────────
const logModal      = document.getElementById('log-modal');
const logModalClose = document.getElementById('modal-close-btn');
const logDateInput  = document.getElementById('log-date');
const logFrom       = document.getElementById('log-from');
const logTo         = document.getElementById('log-to');
const hrsPreview    = document.getElementById('hrs-preview');
const openModalBtn  = document.getElementById('open-modal-btn');

function openLogModal(date) {
  if (logDateInput && date) logDateInput.value = date;
  logModal.classList.add('open');
  logModal.style.display = 'flex';
}

if (openModalBtn)  openModalBtn.addEventListener('click',  () => openLogModal(null));
if (logModalClose) logModalClose.addEventListener('click', () => logModal.classList.remove('open'));
if (logModal)      logModal.addEventListener('click', e => {
  if (e.target === logModal) logModal.classList.remove('open');
});

function updateHrsPreview() {
  if (!logFrom || !logTo || !hrsPreview) return;
  const hrs = calcHrs(logFrom.value, logTo.value);
  hrsPreview.textContent = hrs > 0 ? hrs.toFixed(2) + ' hrs' : '— invalid';
  hrsPreview.style.color = hrs > 0 ? 'var(--green, #2d6a4f)' : '#dc2626';
}
if (logFrom) logFrom.addEventListener('change', updateHrsPreview);
if (logTo)   logTo.addEventListener('change',   updateHrsPreview);

// ── Day Detail Modal ─────────────────────────────────────────────────────────
const dayModal      = document.getElementById('day-modal');
const dayModalTitle = document.getElementById('day-modal-title');
const dayModalBody  = document.getElementById('day-modal-body');
const dayModalClose = document.getElementById('day-modal-close');
const dayModalAdd   = document.getElementById('day-modal-add');
let   currentDayDate = '';

function openDayModal(date) {
  currentDayDate = date;
  const logs = logData[date] || [];
  dayModalTitle.textContent = 'Logs for ' + date;
  let html = '<div style="display:flex;flex-direction:column;gap:10px;">';
  logs.forEach(l => {
    html += `
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;background:#f9f9f9;border-radius:8px;border:1px solid #eee;flex-wrap:wrap;gap:8px;">
        <div style="min-width:0;">
          <div style="font-size:13px;font-weight:700;color:var(--text);">
            ${formatTime(l.from)} — ${formatTime(l.to)}
            <span style="color:var(--green-dark);margin-left:8px;">${parseFloat(l.hours).toFixed(2)} hrs</span>
          </div>
          ${l.desc ? `<div style="font-size:12px;color:var(--text3);margin-top:4px;word-break:break-word;">${l.desc}</div>` : ''}
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-shrink:0;">
          <button type="button"
                  style="background:none;border:none;cursor:pointer;color:var(--text3);transition:color 0.15s;"
                  onmouseover="this.style.color='var(--green)'"
                  onmouseout="this.style.color='var(--text3)'"
                  onclick="openEditFromDay('${l.id}','${l.date}','${l.from}','${l.to}',\`${l.desc.replace(/`/g,"'")}\`)"
                  title="Edit">
            <svg viewBox="0 0 24 24" fill="currentColor" style="width:16px;height:16px;">
              <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
            </svg>
          </button>
          <form method="POST" action="logs.php" style="display:inline;margin:0;"
                onsubmit="return confirm('Delete this log?')">
            <input type="hidden" name="action"  value="delete_log" />
            <input type="hidden" name="log_id"  value="${l.id}" />
            <button type="submit"
                    style="background:none;border:none;cursor:pointer;color:var(--text3);transition:color 0.15s;"
                    onmouseover="this.style.color='#dc2626'"
                    onmouseout="this.style.color='var(--text3)'"
                    title="Delete">✕</button>
          </form>
        </div>
      </div>`;
  });
  html += '</div>';
  dayModalBody.innerHTML = html;
  dayModal.classList.add('open');
}

if (dayModalClose) dayModalClose.addEventListener('click', () => dayModal.classList.remove('open'));
if (dayModal)      dayModal.addEventListener('click', e => {
  if (e.target === dayModal) dayModal.classList.remove('open');
});
if (dayModalAdd) {
  dayModalAdd.addEventListener('click', () => {
    dayModal.classList.remove('open');
    openLogModal(currentDayDate);
  });
}

// ── Edit Modal ───────────────────────────────────────────────────────────────
const editModal    = document.getElementById('edit-modal');
const editCloseBtn = document.getElementById('edit-close-btn');
const editFrom     = document.getElementById('edit-from');
const editTo       = document.getElementById('edit-to');
const editPreview  = document.getElementById('edit-hrs-preview');

function updateEditPreview() {
  if (!editFrom || !editTo || !editPreview) return;
  const hrs = calcHrs(editFrom.value, editTo.value);
  editPreview.textContent = hrs > 0 ? hrs.toFixed(2) + ' hrs' : '— invalid';
  editPreview.style.color = hrs > 0 ? 'var(--green, #2d6a4f)' : '#dc2626';
}
if (editFrom) editFrom.addEventListener('change', updateEditPreview);
if (editTo)   editTo.addEventListener('change',   updateEditPreview);
if (editCloseBtn) editCloseBtn.addEventListener('click', () => editModal.classList.remove('open'));
if (editModal)    editModal.addEventListener('click', e => {
  if (e.target === editModal) editModal.classList.remove('open');
});

function openEditFromDay(id, date, from, to, desc) {
  dayModal.classList.remove('open');
  document.getElementById('edit-log-id').value = id;
  document.getElementById('edit-date').value   = date;
  document.getElementById('edit-from').value   = from;
  document.getElementById('edit-to').value     = to;
  document.getElementById('edit-desc').value   = desc;
  updateEditPreview();
  editModal.classList.add('open');
}

// List View Edit Buttons
document.querySelectorAll('.edit-btn').forEach(btn => {
  if (!btn.dataset.id) return;
  btn.addEventListener('click', () => {
    document.getElementById('edit-log-id').value = btn.dataset.id;
    document.getElementById('edit-date').value   = btn.dataset.date;
    document.getElementById('edit-desc').value   = btn.dataset.desc;
    document.getElementById('edit-from').value   = btn.dataset.from;
    document.getElementById('edit-to').value     = btn.dataset.to;
    updateEditPreview();
    editModal.classList.add('open');
  });
});

// ── Bulk Log Modal ───────────────────────────────────────────────────────────
const bulkModal    = document.getElementById('bulk-modal');
const openBulkBtn  = document.getElementById('open-bulk-btn');
const bulkCloseBtn = document.getElementById('bulk-close-btn');
if (openBulkBtn)  openBulkBtn.addEventListener('click',  () => bulkModal.classList.add('open'));
if (bulkCloseBtn) bulkCloseBtn.addEventListener('click', () => bulkModal.classList.remove('open'));
if (bulkModal)    bulkModal.addEventListener('click', e => {
  if (e.target === bulkModal) bulkModal.classList.remove('open');
});

document.querySelectorAll('.day-toggle').forEach(label => {
  label.addEventListener('click', () => {
    const cb = label.querySelector('input[type="checkbox"]');
    cb.checked = !cb.checked;
    label.style.background   = cb.checked ? '#f0f0f0' : 'transparent';
    label.style.borderColor  = cb.checked ? '#ccc'    : '#ddd';
    updateRangePreview();
  });
});

const bulkHrs      = document.getElementById('bulk-hrs');
const bulkToHidden = document.getElementById('bulk-to-hidden');

function updateBulkToTime() {
  if (!bulkHrs || !bulkToHidden) return;
  const hrs   = parseFloat(bulkHrs.value) || 8;
  const toMin = 480 + Math.round(hrs * 60);
  bulkToHidden.value = `${Math.floor(toMin/60).toString().padStart(2,'0')}:${(toMin%60).toString().padStart(2,'0')}`;
  updateRangePreview();
}
if (bulkHrs) bulkHrs.addEventListener('change', updateBulkToTime);

const bulkStart    = document.getElementById('bulk-start');
const bulkEnd      = document.getElementById('bulk-end');
const rangePreview = document.getElementById('bulk-range-preview');

function updateRangePreview() {
  if (!bulkStart || !bulkEnd || !rangePreview) return;
  if (!bulkStart.value || !bulkEnd.value) { rangePreview.textContent = ''; return; }
  const start = new Date(bulkStart.value);
  const end   = new Date(bulkEnd.value);
  if (start > end) {
    rangePreview.textContent = 'Start must be before end.';
    rangePreview.style.color = '#dc2626';
    return;
  }
  const excluded = Array.from(document.querySelectorAll('.day-toggle input:checked')).map(cb => parseInt(cb.value));
  let count = 0, cursor = new Date(start);
  while (cursor <= end) {
    const iso = cursor.getDay() === 0 ? 7 : cursor.getDay();
    if (!excluded.includes(iso)) count++;
    cursor.setDate(cursor.getDate() + 1);
  }
  const hrs = parseFloat(bulkHrs?.value) || 8;
  rangePreview.style.color = 'var(--green, #2d6a4f)';
  rangePreview.textContent = `${count} day${count !== 1 ? 's' : ''} will be filled — ${(hrs * count).toFixed(1)} hrs total`;
}
if (bulkStart) bulkStart.addEventListener('change', updateRangePreview);
if (bulkEnd)   bulkEnd.addEventListener('change',   updateRangePreview);

// ── List View Bulk Delete Logic ──────────────────────────────────────────────
const selectAll       = document.getElementById('select-all');
const bulkDeleteBar   = document.getElementById('bulk-delete-bar');
const bulkDeleteCount = document.getElementById('bulk-delete-count');
const bulkDeleteIds   = document.getElementById('bulk-delete-ids');
const bulkDeselect    = document.getElementById('bulk-deselect');

function getChecked() {
  return Array.from(document.querySelectorAll('.row-checkbox:checked'));
}

function updateBulkBar() {
  const checked = getChecked();
  if (!bulkDeleteBar) return;
  if (checked.length > 0) {
    bulkDeleteBar.style.display = 'flex';
    if (bulkDeleteCount) bulkDeleteCount.textContent = checked.length + ' selected';
    if (bulkDeleteIds) {
      bulkDeleteIds.innerHTML = '';
      checked.forEach(cb => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'log_ids[]'; inp.value = cb.value;
        bulkDeleteIds.appendChild(inp);
      });
    }
  } else {
    bulkDeleteBar.style.display = 'none';
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
    if (selectAll) selectAll.checked = getChecked().length === all.length;
    updateBulkBar();
  });
});

if (bulkDeselect) {
  bulkDeselect.addEventListener('click', () => {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
    if (selectAll) selectAll.checked = false;
    updateBulkBar();
  });
}

// Re-open log modal if there were validation errors
<?php if (!empty($log_errors)): ?>
document.getElementById('log-modal').classList.add('open');
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>