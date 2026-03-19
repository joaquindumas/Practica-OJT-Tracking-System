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
            'date' => $date, 'description' => $desc,
            'from' => $from, 'to' => $to, 'hours' => round($hours, 4),
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
        foreach ($ids as $id) {
            delete_log($id, $user['id']);
            $count++;
        }
        set_flash('success', "{$count} log" . ($count !== 1 ? 's' : '') . " deleted.");
        header('Location: logs.php');
        exit;
    }

    if ($action === 'bulk_log') {
        $start        = $_POST['bulk_start']  ?? '';
        $end          = $_POST['bulk_end']    ?? '';
        $hrs          = (float) ($_POST['bulk_hrs'] ?? 8);
        $desc         = trim($_POST['bulk_desc'] ?? '');
        $exclude_days = array_map('intval', $_POST['exclude_days'] ?? []);

        $from_time = '08:00';
        $total_min = (int) ($hrs * 60);
        $to_time   = sprintf('%02d:%02d', intdiv(480 + $total_min, 60), (480 + $total_min) % 60);

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
$logs      = $user['logs'] ?? [];
usort($logs, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
$total_hrs = total_logged($user);

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
  <div style="display:flex;gap:8px;">
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

<!-- Bulk delete bar -->
<div class="bulk-delete-bar" id="bulk-delete-bar">
  <span id="bulk-delete-count">0 selected</span>
  <div class="bulk-delete-bar-actions">
    <button type="button" class="btn btn-secondary" id="bulk-deselect" style="padding:6px 14px;font-size:12px;">
      Deselect all
    </button>
    <form method="POST" action="logs.php" id="bulk-delete-form">
      <input type="hidden" name="action" value="bulk_delete" />
      <div id="bulk-delete-ids"></div>
      <button type="submit" class="btn btn-danger" style="padding:6px 14px;font-size:12px;border-radius:var(--radius-sm);">
        Delete selected
      </button>
    </form>
  </div>
</div>

<div class="log-table-wrap">
  <table class="log-table">
    <thead>
      <tr>
        <th class="select-col"><input type="checkbox" class="log-checkbox" id="select-all" title="Select all" /></th>
        <th>Date</th><th>Description</th><th>From</th><th>To</th><th>Hours</th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($logs)): ?>
        <tr><td colspan="7"><div class="empty-state">No logs yet. Click "Log Hours" to get started!</div></td></tr>
      <?php else: ?>
        <?php foreach ($logs as $log): ?>
          <tr>
            <td><input type="checkbox" class="log-checkbox row-checkbox" value="<?= e($log['id']) ?>" /></td>
            <td><?= e($log['date']) ?></td>
            <td><?= e($log['description'] ?: '—') ?></td>
            <td><?= e($log['from']) ?></td>
            <td><?= e($log['to']) ?></td>
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
          <input class="form-input" type="time" id="log-to" name="to" value="17:00" required />
        </div>
      </div>
      <div style="font-size:12px;color:var(--text3);margin-top:-0.5rem;">
        Duration: <strong id="hrs-preview" style="color:var(--green);font-family:'DM Mono',monospace;">9.00 hrs</strong>
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
          <input class="form-input" type="number" name="bulk_hrs" id="bulk-hrs" value="8" min="0.5" max="24" step="0.5" required />
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
            <label class="day-toggle <?= in_array($day, ['SAT','SUN']) ? 'day-toggle--excluded' : '' ?>">
              <input type="checkbox" name="exclude_days[]" value="<?= $i + 1 ?>"
                     <?= in_array($day, ['SAT','SUN']) ? 'checked' : '' ?>
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

<?php
if (!empty($log_errors)) echo '<script>document.getElementById("log-modal").classList.add("open");</script>';
include 'includes/footer.php';
?>