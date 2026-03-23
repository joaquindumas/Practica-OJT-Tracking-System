<?php
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
        $date    = $_POST['date']        ?? '';
        $desc    = trim($_POST['description'] ?? '');
        $from    = $_POST['from']        ?? '';
        $to      = $_POST['to']          ?? '';
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
        $ids       = $_POST['log_ids']   ?? [];
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

// Build log map for calendar
$log_map = [];
foreach ($all_logs as $l) {
    $log_map[$l['date']][] = $l;
}

// Month navigation
$month_param = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month_param)) $month_param = date('Y-m');
[$cal_year, $cal_month] = explode('-', $month_param);
$cal_year      = (int) $cal_year;
$cal_month     = (int) $cal_month;
$prev_month    = date('Y-m', mktime(0,0,0,$cal_month-1,1,$cal_year));
$next_month    = date('Y-m', mktime(0,0,0,$cal_month+1,1,$cal_year));
$today         = date('Y-m-d');
$month_name    = date('F Y', mktime(0,0,0,$cal_month,1,$cal_year));
$first_dow     = (int) date('N', mktime(0,0,0,$cal_month,1,$cal_year));
$days_in_month = (int) date('t', mktime(0,0,0,$cal_month,1,$cal_year));

include 'includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title">Time Logs</div>
    <div class="page-subtitle">
      All logged hours &mdash;
      <strong style="font-family:'DM Mono',monospace;color:var(--green);">
        <?= e(number_format($total_hrs, 2)) ?> hrs
      </strong> total
    </div>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <div class="bulk-tabs" style="margin-bottom:0;padding:3px;">
      <button type="button" class="bulk-tab active" id="view-cal-btn">
        <svg viewBox="0 0 24 24" fill="currentColor" style="width:13px;height:13px;margin-right:4px;vertical-align:-2px;"><path d="M20 3h-1V1h-2v2H7V1H5v2H4C2.9 3 2 3.9 2 5v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 18H4V8h16v13z"/></svg>
        Calendar
      </button>
      <button type="button" class="bulk-tab" id="view-list-btn">
        <svg viewBox="0 0 24 24" fill="currentColor" style="width:13px;height:13px;margin-right:4px;vertical-align:-2px;"><path d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>
        List
      </button>
    </div>
    <button class="btn btn-primary" id="open-modal-btn">
      <svg viewBox="0 0 24 24" fill="white"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
      Log Hours
    </button>
    <button class="btn btn-secondary" id="open-bulk-btn">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg>
      Bulk Log
    </button>
  </div>
</div>

<!-- ═══ CALENDAR VIEW ═══ -->
<div id="view-calendar">

  <div class="cal-nav">
    <a href="logs.php?month=<?= $prev_month ?>" class="cal-nav-btn">← Prev</a>
    <div class="cal-month-title"><?= $month_name ?></div>
    <a href="logs.php?month=<?= $next_month ?>" class="cal-nav-btn">Next →</a>
  </div>

  <!-- Action bar -->
  <div class="cal-action-bar" id="cal-action-bar">
    <span id="cal-selected-count">0 days selected</span>
    <div class="cal-action-bar-btns">
      <button type="button" class="btn btn-secondary" id="cal-deselect"
              style="padding:6px 14px;font-size:12px;">Deselect all</button>
      <button type="button" class="btn btn-secondary" id="cal-bulk-edit-btn"
              style="padding:6px 14px;font-size:12px;">✎ Edit time</button>
      <form method="POST" action="logs.php" id="cal-bulk-delete-form" style="display:inline;">
        <input type="hidden" name="action" value="bulk_delete" />
        <div id="cal-bulk-ids"></div>
        <button type="submit" class="btn btn-danger"
                style="padding:6px 14px;font-size:12px;border-radius:var(--radius-sm);">
          Delete selected
        </button>
      </form>
    </div>
  </div>

  <div class="log-table-wrap" style="padding:1.25rem;margin-bottom:1.5rem;">

    <!-- Select all -->
    <div class="cal-select-all-row">
      <input type="checkbox" id="cal-select-all" title="Select all logged days" />
      <label for="cal-select-all" style="cursor:pointer;">Select all logged days</label>
    </div>

    <!-- DOW headers -->
    <div class="cal-grid" style="margin-bottom:6px;">
      <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dow): ?>
        <div class="cal-dow"><?= $dow ?></div>
      <?php endforeach; ?>
    </div>

    <!-- Day cells -->
    <div class="cal-grid" id="cal-grid">
      <?php for ($e = 1; $e < $first_dow; $e++): ?>
        <div class="cal-day cal-day--empty"></div>
      <?php endfor; ?>

      <?php for ($d = 1; $d <= $days_in_month; $d++):
        $date_str  = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $d);
        $day_logs  = $log_map[$date_str] ?? [];
        $is_logged = count($day_logs) > 0;
        $is_today  = $date_str === $today;
        $total_day = array_sum(array_column($day_logs, 'hours'));
        $desc_first= $day_logs[0]['description'] ?? '';
        $tooltip   = $is_logged
          ? number_format($total_day, 2) . ' hrs' . ($desc_first ? ' — ' . $desc_first : '')
          : 'Click to log hours';

        $cls = 'cal-day';
        if ($is_logged) $cls .= ' cal-day--logged';
        if ($is_today)  $cls .= ' cal-day--today';
      ?>
        <div class="<?= $cls ?>"
             data-date="<?= $date_str ?>"
             data-logged="<?= $is_logged ? '1' : '0' ?>"
             data-log-ids="<?= e(implode(',', array_column($day_logs, 'id'))) ?>"
             onclick="handleDayClick(this, event)">

          <div class="cal-day-num"><?= $d ?></div>

          <?php if ($is_logged): ?>
            <div class="cal-day-hrs"><?= number_format($total_day, 1) ?>h</div>
            <?php if ($desc_first): ?>
              <div class="cal-day-desc"><?= e($desc_first) ?></div>
            <?php endif; ?>
            <div class="cal-day-dot"></div>
            <input type="checkbox" class="cal-day-cb"
                   data-date="<?= $date_str ?>"
                   onclick="event.stopPropagation(); toggleCalDay(this)"
                   title="Select <?= $date_str ?>" />
          <?php else: ?>
            <div class="cal-tooltip"><?= e($tooltip) ?></div>
          <?php endif; ?>

        </div>
      <?php endfor; ?>
    </div>

    <!-- Legend -->
    <div class="cal-legend">
      <div><span class="cal-legend-dot" style="background:var(--green-light);border:1.5px solid var(--green-light);"></span> Logged</div>
      <div><span class="cal-legend-dot" style="background:white;border:1.5px solid var(--green);"></span> Today</div>
      <div><span class="cal-legend-dot" style="background:var(--green);"></span> Selected</div>
      <div style="margin-left:auto;font-size:11px;color:var(--text3);">
        <?php
          $month_days = 0; $month_hrs = 0;
          foreach ($log_map as $date => $dlogs) {
              if (substr($date, 0, 7) === $month_param) {
                  $month_days++;
                  $month_hrs += array_sum(array_column($dlogs, 'hours'));
              }
          }
        ?>
        <?= $month_days ?> days &middot; <?= number_format($month_hrs, 1) ?> hrs this month
      </div>
    </div>
  </div>
</div>

<!-- ═══ LIST VIEW ═══ -->
<div id="view-list" style="display:none;">

  <div class="bulk-delete-bar" id="bulk-delete-bar">
    <span id="bulk-delete-count">0 selected</span>
    <div class="bulk-delete-bar-actions">
      <button type="button" class="btn btn-secondary" id="bulk-deselect"
              style="padding:6px 14px;font-size:12px;">Deselect all</button>
      <form method="POST" action="logs.php" id="bulk-delete-form">
        <input type="hidden" name="action" value="bulk_delete" />
        <div id="bulk-delete-ids"></div>
        <button type="submit" class="btn btn-danger"
                style="padding:6px 14px;font-size:12px;border-radius:var(--radius-sm);">
          Delete selected
        </button>
      </form>
    </div>
  </div>

  <div class="log-table-wrap">
    <table class="log-table">
      <thead>
        <tr>
          <th class="select-col"><input type="checkbox" class="log-checkbox" id="select-all" /></th>
          <th>Date</th><th>Description</th><th>From</th><th>To</th><th>Hours</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($all_logs)): ?>
          <tr><td colspan="7"><div class="empty-state">No logs yet.</div></td></tr>
        <?php else: ?>
          <?php foreach ($all_logs as $log): ?>
            <tr>
              <td><input type="checkbox" class="log-checkbox row-checkbox" value="<?= e($log['id']) ?>" /></td>
              <td><?= e(date('F j, Y', strtotime($log['date']))) ?></td>
              <td><?= e($log['description'] ?: '—') ?></td>
              <td><?= e(date('g:i A', strtotime($log['from']))) ?></td>
              <td><?= e(date('g:i A', strtotime($log['to']))) ?></td>
              <td><span class="badge badge--green"><?= e(number_format($log['hours'], 2)) ?> hrs</span></td>
              <td style="display:flex;gap:4px;align-items:center;">
                <button class="edit-btn"
                  data-id="<?= e($log['id']) ?>"
                  data-date="<?= e($log['date']) ?>"
                  data-desc="<?= e($log['description'] ?? '') ?>"
                  data-from="<?= e($log['from']) ?>"
                  data-to="<?= e($log['to']) ?>"
                  title="Edit">
                  <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                </button>
                <form method="POST" action="logs.php" class="delete-form">
                  <input type="hidden" name="action" value="delete_log" />
                  <input type="hidden" name="log_id" value="<?= e($log['id']) ?>" />
                  <button type="submit" class="delete-btn" title="Delete">✕</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Log Hours Modal -->
<div class="modal-overlay" id="log-modal">
  <div class="modal-card">
    <div class="modal-title">Log Hours</div>
    <?php foreach ($log_errors as $err): ?>
      <span class="form-error"><?= e($err) ?></span>
    <?php endforeach; ?>
    <form method="POST" action="logs.php">
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
      <div style="font-size:12px;color:var(--text3);margin-top:-0.5rem;">
        Duration: <strong id="hrs-preview" style="color:var(--green);font-family:'DM Mono',monospace;">8.00 hrs</strong>
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
    <form method="POST" action="logs.php">
      <input type="hidden" name="action" value="edit_log" />
      <input type="hidden" name="log_id" id="edit-log-id" />
      <div class="form-group">
        <label class="form-label">Date</label>
        <input class="form-input" type="date" id="edit-date" name="date" required />
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <input class="form-input" type="text" id="edit-desc" name="description" placeholder="What did you work on?" />
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
      <div style="font-size:12px;color:var(--text3);margin-top:-0.5rem;">
        Duration: <strong id="edit-hrs-preview" style="color:var(--green);font-family:'DM Mono',monospace;"></strong>
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
  <div class="modal-card" style="width:480px;">
    <div class="modal-title" id="day-modal-title">Logs for —</div>
    <div id="day-modal-body"></div>
    <div class="modal-actions">
      <button type="button" class="btn btn-secondary" id="day-modal-close">Close</button>
      <button type="button" class="btn btn-primary" id="day-modal-add">+ Add Log</button>
    </div>
  </div>
</div>

<!-- Calendar Bulk Edit Modal -->
<div class="modal-overlay" id="cal-bulk-edit-modal">
  <div class="modal-card" style="width:380px;">
    <div class="modal-title">Edit Time for Selected Days</div>
    <p style="font-size:13px;color:var(--text2);margin-bottom:1.25rem;margin-top:-0.75rem;">
      Updates the from/to time for all logs on selected days.
    </p>
    <form method="POST" action="logs.php" id="cal-bulk-edit-form">
      <input type="hidden" name="action" value="bulk_edit_time" />
      <div id="cal-bulk-edit-ids"></div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">From</label>
          <input class="form-input" type="time" name="bulk_from" id="cal-edit-from" value="08:00" required />
        </div>
        <div class="form-group">
          <label class="form-label">To</label>
          <input class="form-input" type="time" name="bulk_to" id="cal-edit-to" value="16:00" required />
        </div>
      </div>
      <div style="font-size:12px;color:var(--text3);margin-top:-0.5rem;margin-bottom:1rem;">
        Duration: <strong id="cal-edit-preview" style="color:var(--green);font-family:'DM Mono',monospace;">8.00 hrs</strong>
      </div>
      <div class="form-group">
        <label class="form-label">Description (optional — leave blank to keep existing)</label>
        <input class="form-input" type="text" name="bulk_desc" placeholder="Leave blank to keep existing" />
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" id="cal-bulk-edit-close">Cancel</button>
        <button type="submit" class="btn btn-primary">Apply to Selected</button>
      </div>
    </form>
  </div>
</div>

<!-- Bulk Log Modal -->
<div class="modal-overlay" id="bulk-modal">
  <div class="modal-card" style="width:520px;max-width:95vw;">
    <div class="modal-title">Bulk Entry — Fill Past Days</div>
    <p style="font-size:13px;color:var(--text2);margin-bottom:1.5rem;margin-top:-0.75rem;">
      Fills all selected days with a default hour count. Already-logged days are skipped automatically.
    </p>
    <form method="POST" action="logs.php">
      <input type="hidden" name="action" value="bulk_log" />
      <div style="display:grid;grid-template-columns:1fr 1fr 100px;gap:12px;margin-bottom:1.25rem;">
        <div class="form-group" style="margin:0;">
          <label class="form-label">From</label>
          <input class="form-input" type="date" name="bulk_start" id="bulk-start" required />
        </div>
        <div class="form-group" style="margin:0;">
          <label class="form-label">To</label>
          <input class="form-input" type="date" name="bulk_end" id="bulk-end" required />
        </div>
        <div class="form-group" style="margin:0;">
          <label class="form-label">Hrs / Day</label>
          <input class="form-input" type="number" name="bulk_hrs" id="bulk-hrs"
                 value="8" min="0.5" max="24" step="0.5" required />
        </div>
      </div>
      <input type="hidden" name="bulk_from" value="08:00" />
      <input type="hidden" name="bulk_to" id="bulk-to-hidden" value="16:00" />
      <div class="form-group" style="margin-bottom:1.25rem;">
        <label class="form-label" style="margin-bottom:10px;">
          Exclude Days <span style="font-weight:400;color:var(--text3);">(selected = skip)</span>
        </label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <?php foreach (['MON','TUE','WED','THU','FRI','SAT','SUN'] as $i => $day): ?>
            <label class="day-toggle <?= in_array($day, ['','']) ? 'day-toggle--excluded' : '' ?>">
              <input type="checkbox" name="exclude_days[]" value="<?= $i + 1 ?>"
                     <?= in_array($day, ['','']) ? 'checked' : '' ?>
                     style="display:none;" />
              <span><?= $day ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Description (optional)</label>
        <input class="form-input" type="text" name="bulk_desc" placeholder="e.g. OJT at company" />
      </div>
      <div id="bulk-range-preview" style="font-size:12px;margin-bottom:1rem;min-height:18px;"></div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" id="bulk-close-btn">Cancel</button>
        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 24 24" fill="white" style="width:14px;height:14px;"><path d="M9 11H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2zm2-7h-1V2h-2v2H8V2H6v2H5C3.9 3 3 3.9 3 5v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/></svg>
          Fill Days in Range
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Helpers ────────────────────────────────────────────────────

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

// ── View toggle ────────────────────────────────────────────────
const viewCalBtn  = document.getElementById('view-cal-btn');
const viewListBtn = document.getElementById('view-list-btn');
const viewCal     = document.getElementById('view-calendar');
const viewList    = document.getElementById('view-list');

function setView(v) {
  if (v === 'calendar') {
    viewCal.style.display  = 'block';
    viewList.style.display = 'none';
    viewCalBtn.classList.add('active');
    viewListBtn.classList.remove('active');
    localStorage.setItem('logs_view', 'calendar');
  } else {
    viewCal.style.display  = 'none';
    viewList.style.display = 'block';
    viewListBtn.classList.add('active');
    viewCalBtn.classList.remove('active');
    localStorage.setItem('logs_view', 'list');
  }
}
const savedView = localStorage.getItem('logs_view') || 'calendar';
setView(savedView);
viewCalBtn.addEventListener('click',  () => setView('calendar'));
viewListBtn.addEventListener('click', () => setView('list'));

// ── Calendar state ─────────────────────────────────────────────
const selectedDays     = new Set();
const actionBar        = document.getElementById('cal-action-bar');
const selectedCount    = document.getElementById('cal-selected-count');
const bulkIdsDiv       = document.getElementById('cal-bulk-ids');
const calDeselect      = document.getElementById('cal-deselect');
const calBulkForm      = document.getElementById('cal-bulk-delete-form');
const calSelectAll     = document.getElementById('cal-select-all');
const calBulkEditBtn   = document.getElementById('cal-bulk-edit-btn');
const calBulkEditModal = document.getElementById('cal-bulk-edit-modal');
const calBulkEditClose = document.getElementById('cal-bulk-edit-close');
const calBulkEditIds   = document.getElementById('cal-bulk-edit-ids');
const calEditFrom      = document.getElementById('cal-edit-from');
const calEditTo        = document.getElementById('cal-edit-to');
const calEditPreview   = document.getElementById('cal-edit-preview');

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

function buildBulkIds() {
  [bulkIdsDiv, calBulkEditIds].forEach(container => {
    if (!container) return;
    container.innerHTML = '';
    selectedDays.forEach(date => {
      (logData[date] || []).forEach(l => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'log_ids[]'; inp.value = l.id;
        container.appendChild(inp);
      });
    });
  });
}

function updateCalActionBar() {
  if (selectedDays.size > 0) {
    actionBar.classList.add('show');
    selectedCount.textContent = selectedDays.size + ' day' + (selectedDays.size > 1 ? 's' : '') + ' selected';
    buildBulkIds();
  } else {
    actionBar.classList.remove('show');
  }
  const allCbs = document.querySelectorAll('.cal-day-cb');
  if (calSelectAll) calSelectAll.checked = allCbs.length > 0 && selectedDays.size === allCbs.length;
}

function toggleCalDay(cb) {
  const date = cb.dataset.date;
  const el   = cb.closest('.cal-day');
  if (cb.checked) {
    selectedDays.add(date);
    el.classList.add('cal-day--selected');
  } else {
    selectedDays.delete(date);
    el.classList.remove('cal-day--selected');
  }
  updateCalActionBar();
}

// Select all logged days
if (calSelectAll) {
  calSelectAll.addEventListener('change', () => {
    document.querySelectorAll('.cal-day-cb').forEach(cb => {
      cb.checked = calSelectAll.checked;
      const date = cb.dataset.date;
      const el   = cb.closest('.cal-day');
      if (calSelectAll.checked) { selectedDays.add(date); el.classList.add('cal-day--selected'); }
      else { selectedDays.delete(date); el.classList.remove('cal-day--selected'); }
    });
    updateCalActionBar();
  });
}

// Deselect all
if (calDeselect) {
  calDeselect.addEventListener('click', () => {
    document.querySelectorAll('.cal-day-cb').forEach(cb => { cb.checked = false; });
    document.querySelectorAll('.cal-day--selected').forEach(el => el.classList.remove('cal-day--selected'));
    selectedDays.clear();
    updateCalActionBar();
  });
}

// Bulk delete confirm
if (calBulkForm) {
  calBulkForm.addEventListener('submit', e => {
    const total = document.querySelectorAll('#cal-bulk-ids input').length;
    if (!confirm(`Delete all logs for ${selectedDays.size} day(s)? (${total} entries)`)) e.preventDefault();
  });
}

// Bulk edit modal
if (calBulkEditBtn) {
  calBulkEditBtn.addEventListener('click', () => {
    buildBulkIds();
    calBulkEditModal.classList.add('open');
  });
}
if (calBulkEditClose) calBulkEditClose.addEventListener('click', () => calBulkEditModal.classList.remove('open'));
if (calBulkEditModal) calBulkEditModal.addEventListener('click', e => { if (e.target === calBulkEditModal) calBulkEditModal.classList.remove('open'); });

function updateCalEditPreview() {
  if (!calEditFrom || !calEditTo || !calEditPreview) return;
  const hrs = calcHrs(calEditFrom.value, calEditTo.value);
  calEditPreview.textContent = hrs > 0 ? hrs.toFixed(2) + ' hrs' : '— invalid';
  calEditPreview.style.color = hrs > 0 ? 'var(--green)' : 'var(--red)';
}
if (calEditFrom) calEditFrom.addEventListener('change', updateCalEditPreview);
if (calEditTo)   calEditTo.addEventListener('change',   updateCalEditPreview);

// Day click
function handleDayClick(el, event) {
  if (event.target.classList.contains('cal-day-cb')) return;
  const date     = el.dataset.date;
  const isLogged = el.dataset.logged === '1';
  if (isLogged) openDayModal(date);
  else openLogModal(date);
}

// ── Log modal ──────────────────────────────────────────────────
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
}
if (openModalBtn)  openModalBtn.addEventListener('click',  () => openLogModal(null));
if (logModalClose) logModalClose.addEventListener('click', () => logModal.classList.remove('open'));
if (logModal)      logModal.addEventListener('click', e => { if (e.target === logModal) logModal.classList.remove('open'); });

function updateHrsPreview() {
  if (!logFrom || !logTo || !hrsPreview) return;
  const hrs = calcHrs(logFrom.value, logTo.value);
  hrsPreview.textContent = hrs > 0 ? hrs.toFixed(2) + ' hrs' : '— invalid';
  hrsPreview.style.color = hrs > 0 ? 'var(--green)' : 'var(--red)';
}
if (logFrom) logFrom.addEventListener('change', updateHrsPreview);
if (logTo)   logTo.addEventListener('change',   updateHrsPreview);

// ── Day detail modal ───────────────────────────────────────────
const dayModal      = document.getElementById('day-modal');
const dayModalTitle = document.getElementById('day-modal-title');
const dayModalBody  = document.getElementById('day-modal-body');
const dayModalClose = document.getElementById('day-modal-close');
const dayModalAdd   = document.getElementById('day-modal-add');
let currentDayDate  = '';

function openDayModal(date) {
  currentDayDate = date;
  const logs = logData[date] || [];
  dayModalTitle.textContent = 'Logs for ' + date;
  let html = '<div style="display:flex;flex-direction:column;gap:8px;">';
  logs.forEach(l => {
    html += `
      <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;background:var(--surface2);border-radius:var(--radius-sm);">
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--text);">
           ${formatTime(l.from)} — ${formatTime(l.to)}
            <span style="font-family:'DM Mono',monospace;color:var(--green);margin-left:6px;">${parseFloat(l.hours).toFixed(2)} hrs</span>
          </div>
          ${l.desc ? `<div style="font-size:12px;color:var(--text3);margin-top:2px;">${l.desc}</div>` : ''}
        </div>
        <div style="display:flex;gap:6px;align-items:center;">
          <button type="button" class="edit-btn"
            onclick="openEditFromDay('${l.id}','${l.date}','${l.from}','${l.to}',\`${l.desc.replace(/`/g,"'")}\`)"
            title="Edit">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
          </button>
          <form method="POST" action="logs.php" style="display:inline;"
                onsubmit="return confirm('Delete this log?')">
            <input type="hidden" name="action"  value="delete_log" />
            <input type="hidden" name="log_id"  value="${l.id}" />
            <button type="submit" class="delete-btn" title="Delete">✕</button>
          </form>
        </div>
      </div>`;
  });
  html += '</div>';
  dayModalBody.innerHTML = html;
  dayModal.classList.add('open');
}

if (dayModalClose) dayModalClose.addEventListener('click', () => dayModal.classList.remove('open'));
if (dayModal)      dayModal.addEventListener('click', e => { if (e.target === dayModal) dayModal.classList.remove('open'); });
if (dayModalAdd) {
  dayModalAdd.addEventListener('click', () => {
    dayModal.classList.remove('open');
    openLogModal(currentDayDate);
  });
}

// ── Edit modal ─────────────────────────────────────────────────
const editModal    = document.getElementById('edit-modal');
const editCloseBtn = document.getElementById('edit-close-btn');
const editFrom     = document.getElementById('edit-from');
const editTo       = document.getElementById('edit-to');
const editPreview  = document.getElementById('edit-hrs-preview');

function updateEditPreview() {
  if (!editFrom || !editTo || !editPreview) return;
  const hrs = calcHrs(editFrom.value, editTo.value);
  editPreview.textContent = hrs > 0 ? hrs.toFixed(2) + ' hrs' : '— invalid';
  editPreview.style.color = hrs > 0 ? 'var(--green)' : 'var(--red)';
}
if (editFrom) editFrom.addEventListener('change', updateEditPreview);
if (editTo)   editTo.addEventListener('change',   updateEditPreview);
if (editCloseBtn) editCloseBtn.addEventListener('click', () => editModal.classList.remove('open'));
if (editModal)    editModal.addEventListener('click', e => { if (e.target === editModal) editModal.classList.remove('open'); });

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

// ── List view edit btns ────────────────────────────────────────
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

// ── List view multi-select ─────────────────────────────────────
const selectAll      = document.getElementById('select-all');
const bulkDeleteBar  = document.getElementById('bulk-delete-bar');
const bulkDeleteCount= document.getElementById('bulk-delete-count');
const bulkDeleteIds  = document.getElementById('bulk-delete-ids');
const bulkDeselect   = document.getElementById('bulk-deselect');
const bulkDeleteForm = document.getElementById('bulk-delete-form');

function getChecked() { return Array.from(document.querySelectorAll('.row-checkbox:checked')); }
function updateBulkBar() {
  const checked = getChecked();
  if (!bulkDeleteBar) return;
  if (checked.length > 0) {
    bulkDeleteBar.classList.add('show');
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
if (bulkDeleteForm) {
  bulkDeleteForm.addEventListener('submit', e => {
    const count = getChecked().length;
    if (!confirm(`Delete ${count} log${count !== 1 ? 's' : ''}? This cannot be undone.`)) e.preventDefault();
  });
}
document.querySelectorAll('.delete-form').forEach(form => {
  form.addEventListener('submit', e => {
    if (!confirm('Delete this log entry?')) e.preventDefault();
  });
});

// ── Bulk log modal ─────────────────────────────────────────────
const bulkModal    = document.getElementById('bulk-modal');
const openBulkBtn  = document.getElementById('open-bulk-btn');
const bulkCloseBtn = document.getElementById('bulk-close-btn');
if (openBulkBtn)  openBulkBtn.addEventListener('click',  () => bulkModal.classList.add('open'));
if (bulkCloseBtn) bulkCloseBtn.addEventListener('click', () => bulkModal.classList.remove('open'));
if (bulkModal)    bulkModal.addEventListener('click', e => { if (e.target === bulkModal) bulkModal.classList.remove('open'); });

// ── Day toggles ────────────────────────────────────────────────
document.querySelectorAll('.day-toggle').forEach(label => {
  label.addEventListener('click', () => {
    const cb = label.querySelector('input[type="checkbox"]');
    cb.checked = !cb.checked;
    label.classList.toggle('day-toggle--excluded', cb.checked);
    updateRangePreview();
  });
});

// ── Bulk hrs/day ───────────────────────────────────────────────
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

// ── Range preview ──────────────────────────────────────────────
const bulkStart    = document.getElementById('bulk-start');
const bulkEnd      = document.getElementById('bulk-end');
const rangePreview = document.getElementById('bulk-range-preview');
function updateRangePreview() {
  if (!bulkStart || !bulkEnd || !rangePreview) return;
  if (!bulkStart.value || !bulkEnd.value) { rangePreview.textContent = ''; return; }
  const start = new Date(bulkStart.value);
  const end   = new Date(bulkEnd.value);
  if (start > end) { rangePreview.textContent = 'Start must be before end.'; rangePreview.style.color = 'var(--red)'; return; }
  const excluded = Array.from(document.querySelectorAll('.day-toggle input:checked')).map(cb => parseInt(cb.value));
  let count = 0, cursor = new Date(start);
  while (cursor <= end) {
    const iso = cursor.getDay() === 0 ? 7 : cursor.getDay();
    if (!excluded.includes(iso)) count++;
    cursor.setDate(cursor.getDate() + 1);
  }
  const hrs = parseFloat(bulkHrs?.value) || 8;
  rangePreview.style.color = 'var(--green-dark)';
  rangePreview.textContent = `${count} day${count !== 1 ? 's' : ''} will be filled — ${(hrs * count).toFixed(1)} hrs total`;
}
if (bulkStart) bulkStart.addEventListener('change', updateRangePreview);
if (bulkEnd)   bulkEnd.addEventListener('change',   updateRangePreview);

<?php if (!empty($log_errors)): ?>
document.getElementById('log-modal').classList.add('open');
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>