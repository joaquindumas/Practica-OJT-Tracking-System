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
        $date    = $_POST['date']        ?? '';
        $desc    = trim($_POST['description'] ?? '');
        $from    = $_POST['from']        ?? '';
        $to      = $_POST['to']          ?? '';
        if ($date && $from && $to) {
            [$fh, $fm] = array_map('intval', explode(':', $from));
            [$th, $tm] = array_map('intval', explode(':', $to));
            $hours = (($th * 60 + $tm) - ($fh * 60 + $fm)) / 60;
            if ($hours > 0) {
                update_log($edit_id, $user['id'], [
                    'date'        => $date,
                    'description' => $desc,
                    'from'        => $from,
                    'to'          => $to,
                    'hours'       => round($hours, 4),
                ]);
                set_flash('success', 'Log updated successfully!');
            }
        }

        if ($action === 'update_allowance') {
    $new_allowance = (float) ($_POST['allowance_per_day'] ?? 150);
    if ($new_allowance > 0) {
        $user['allowance_per_day'] = $new_allowance;
        save_user($user);
        set_flash('success', 'Allowance updated!');
    }
    header('Location: dashboard.php');
    exit;
}
        header('Location: dashboard.php');
        exit;
    }

    if ($action === 'delete_log') {
        delete_log($_POST['log_id'] ?? '', $user['id']);
        set_flash('success', 'Log entry deleted.');
        header('Location: dashboard.php');
        exit;
    }

    if ($action === 'bulk_delete') {
        $ids   = $_POST['log_ids'] ?? [];
        $count = 0;
        foreach ($ids as $id) { delete_log($id, $user['id']); $count++; }
        set_flash('success', "{$count} log" . ($count !== 1 ? 's' : '') . " deleted.");
        header('Location: dashboard.php');
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
        header('Location: dashboard.php');
        exit;
    }
}

$user      = current_user();
$logged    = total_logged($user);
$required  = $user['required_hours'] ?? 500;
$remaining = hours_remaining($user);
$pct       = completion_percent($user);
$est_date  = estimated_completion($user);
$est_basis = estimated_basis($user);
$all_logs  = $user['logs'] ?? [];
usort($all_logs, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
$recent_logs = array_slice($all_logs, 0, 3);

// ── Analytics ──────────────────────────────────────────────────
$allowance_per_day = $user['allowance_per_day'] ?? 150;

$weeks = [];
for ($i = 7; $i >= 0; $i--) {
    $ws  = strtotime("monday -{$i} week");
    $we  = strtotime("sunday -{$i} week");
    $hrs = 0;
    foreach ($all_logs as $l) {
        $ts = strtotime($l['date']);
        if ($ts >= $ws && $ts <= $we) $hrs += $l['hours'];
    }
    $weeks[] = ['label' => date('M j', $ws), 'hrs' => round($hrs, 1)];
}

$months = [];
for ($i = 5; $i >= 0; $i--) {
    $mts  = strtotime("first day of -{$i} month");
    $mkey = date('Y-m', $mts);
    $hrs  = 0; $days = 0;
    foreach ($all_logs as $l) {
        if (substr($l['date'], 0, 7) === $mkey) { $hrs += $l['hours']; $days++; }
    }
    $months[] = ['label' => date('M Y', $mts), 'hrs' => round($hrs, 1), 'days' => $days];
}

$dow_labels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
$dow_hrs    = array_fill(0, 7, 0);
$dow_count  = array_fill(0, 7, 0);
foreach ($all_logs as $l) {
    $d = (int) date('N', strtotime($l['date'])) - 1;
    $dow_hrs[$d]   += $l['hours'];
    $dow_count[$d] += 1;
}

$total_days          = count(array_unique(array_column($all_logs, 'date')));
$total_allowance     = $total_days * $allowance_per_day;
$avg_hrs_day         = $total_days > 0 ? round($logged / $total_days, 1) : 0;
$projected_days      = $avg_hrs_day > 0 ? (int) ceil($remaining / $avg_hrs_day) : 0;
$projected_allowance = $projected_days * $allowance_per_day;
$total_projected     = $total_allowance + $projected_allowance;

include 'includes/header.php';
?>

<div class="page-header">
  <div>
    <div class="page-title"><?= e($user['name'] ?? $user['username']) ?></div>
    <div class="page-subtitle">OJT Hours Dashboard</div>
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

<!-- Stats -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">Required</div>
    <div class="stat-value"><?= e(number_format($required, 0)) ?></div>
    <div class="stat-sub">total hours</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Logged</div>
    <div class="stat-value"><?= e(number_format($logged, 1)) ?></div>
    <div class="stat-sub"><?= e(number_format($pct, 1)) ?>% complete</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Remaining</div>
    <div class="stat-value stat-value--accent"><?= e(number_format($remaining, 1)) ?></div>
    <div class="stat-sub">hours to go</div>
  </div>
</div>

<!-- Progress -->
<div class="progress-card">
  <div class="progress-header">
    <div class="progress-title">Progress</div>
    <div class="progress-pct"><?= e(number_format($pct, 1)) ?>%</div>
  </div>
  <div class="progress-bar-bg">
    <div class="progress-bar-fill" style="width: <?= e(number_format($pct, 2)) ?>%"></div>
  </div>
  <div class="est-box">
    <div class="est-label">Estimated Completion</div>
    <div class="est-date">
      <?php if ($est_date === 'Completed'): ?>
        Completed! 🎉
      <?php elseif ($est_date): ?>
        <?= e($est_date) ?>
      <?php else: ?>
        —
      <?php endif; ?>
    </div>
    <div class="est-based">
      <?= $est_basis ? e($est_basis) : 'Log more entries to see estimate' ?>
    </div>
  </div>
</div>

<!-- Recent Logs -->
<div class="section-header" style="margin-bottom:0.75rem;">
  <div class="section-title">Recent Logs</div>
  <a href="logs.php" class="btn btn-secondary" style="padding:6px 14px;font-size:12px;">View all</a>
</div>

<div class="bulk-delete-bar" id="bulk-delete-bar">
  <span id="bulk-delete-count">0 selected</span>
  <div class="bulk-delete-bar-actions">
    <button type="button" class="btn btn-secondary" id="bulk-deselect" style="padding:6px 14px;font-size:12px;">Deselect all</button>
    <form method="POST" action="dashboard.php" id="bulk-delete-form">
      <input type="hidden" name="action" value="bulk_delete" />
      <div id="bulk-delete-ids"></div>
      <button type="submit" class="btn btn-danger" style="padding:6px 14px;font-size:12px;border-radius:var(--radius-sm);">Delete selected</button>
    </form>
  </div>
</div>

<div class="log-table-wrap" style="margin-bottom:2rem;">
  <table class="log-table">
    <thead>
      <tr>
        <th class="select-col"><input type="checkbox" class="log-checkbox" id="select-all" title="Select all" /></th>
        <th>Date</th><th>Description</th><th>From</th><th>To</th><th>Hours</th><th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($recent_logs)): ?>
        <tr><td colspan="7"><div class="empty-state">No logs yet. Click "Log Hours" to get started!</div></td></tr>
      <?php else: ?>
        <?php foreach ($recent_logs as $log): ?>
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
              <form method="POST" action="dashboard.php" class="delete-form">
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

<!-- ── Analytics ── -->
<div class="section-header" style="margin-bottom:1.25rem;">
  <div class="section-title">Analytics</div>
</div>

<!-- Allowance — full width -->
<div class="progress-card" style="margin-bottom:16px;">
  <div class="progress-header">
    <div class="progress-title">💰 Allowance Summary</div>
    <div style="display:flex;align-items:center;gap:10px;">
      <div style="font-size:11px;color:var(--text3);">₱<?= number_format($allowance_per_day, 2) ?>/day</div>
      <button type="button" class="btn btn-secondary" id="open-allowance-btn"
              style="padding:4px 12px;font-size:11px;border-radius:999px;">
        Edit
      </button>
    </div>
  </div>
  <!-- rest of allowance card stays the same -->
  <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:0;margin-top:0.75rem;">
    <?php
      $allowance_items = [
        ['Days logged',              $total_days . ' days',                    'var(--text)'],
        ['Earned so far',            '₱' . number_format($total_allowance),    'var(--green)'],
        ['Remaining days (est.)',    $projected_days . ' days',                'var(--text2)'],
        ['Remaining allowance (est.)','₱' . number_format($projected_allowance),'var(--text2)'],
        ['Total projected',          '₱' . number_format($total_projected),    'var(--green)'],
      ];
    ?>
    <?php foreach ($allowance_items as $i => $item): ?>
      <div style="padding:1rem 1.25rem;<?= $i < 4 ? 'border-right:0.5px solid var(--border);' : '' ?>">
        <div style="font-size:11px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--text3);margin-bottom:6px;">
          <?= $item[0] ?>
        </div>
        <div style="font-size:22px;font-weight:700;color:<?= $item[2] ?>;font-family:'DM Mono',monospace;">
          <?= $item[1] ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Day of week + Weekly chart -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">

  <!-- Day of week -->
  <div class="progress-card" style="margin-bottom:0;">
    <div class="progress-header">
      <div class="progress-title">📅 Day of Week Breakdown</div>
      <div style="font-size:11px;color:var(--text3);">Total hours per day</div>
    </div>
    <?php $max_dow = max($dow_hrs) ?: 1; ?>
    <div style="display:flex;flex-direction:column;gap:12px;margin-top:0.75rem;">
      <?php foreach ($dow_labels as $i => $dlabel): ?>
        <?php $bar_pct = ($dow_hrs[$i] / $max_dow) * 100; ?>
        <div style="display:flex;align-items:center;gap:12px;">
          <div style="width:32px;font-size:12px;font-weight:700;color:var(--text2);"><?= $dlabel ?></div>
          <div style="flex:1;background:var(--surface2);border-radius:999px;height:10px;overflow:hidden;">
            <div style="width:<?= round($bar_pct, 1) ?>%;height:100%;background:<?= $bar_pct > 0 ? 'var(--green)' : 'transparent' ?>;border-radius:999px;transition:width 0.3s;"></div>
          </div>
          <div style="width:60px;text-align:right;font-size:12px;font-weight:700;color:var(--text2);font-family:'DM Mono',monospace;">
            <?= $dow_hrs[$i] > 0 ? $dow_hrs[$i] . ' h' : '—' ?>
          </div>
          <div style="width:28px;text-align:right;font-size:11px;color:var(--text3);">
            <?= $dow_count[$i] > 0 ? $dow_count[$i] . 'd' : '' ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Weekly chart -->
  <div class="progress-card" style="margin-bottom:0;">
    <div class="progress-header">
      <div class="progress-title">📊 Hours per Week</div>
      <div style="font-size:11px;color:var(--text3);">Last 8 weeks</div>
    </div>
    <?php $max_week = max(array_column($weeks, 'hrs')) ?: 1; ?>
    <div style="display:flex;align-items:flex-end;gap:8px;height:140px;padding-top:1.5rem;margin-top:0.5rem;">
      <?php foreach ($weeks as $wk): ?>
        <?php $h = $max_week > 0 ? max(6, ($wk['hrs'] / $max_week) * 110) : 6; ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
          <div style="font-size:10px;font-weight:700;color:var(--green-dark);min-height:14px;">
            <?= $wk['hrs'] > 0 ? $wk['hrs'] : '' ?>
          </div>
          <div style="width:100%;height:<?= $h ?>px;background:<?= $wk['hrs'] > 0 ? 'var(--green)' : 'var(--surface2)' ?>;border-radius:5px 5px 0 0;"></div>
          <div style="font-size:9px;color:var(--text3);font-weight:600;white-space:nowrap;"><?= $wk['label'] ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Monthly chart — full width -->
<div class="progress-card" style="margin-bottom:2rem;">
  <div class="progress-header">
    <div class="progress-title">📆 Hours per Month</div>
    <div style="font-size:11px;color:var(--text3);">Last 6 months</div>
  </div>
  <?php $max_month = max(array_column($months, 'hrs')) ?: 1; ?>
  <div style="display:flex;align-items:flex-end;gap:16px;height:140px;padding-top:1.5rem;margin-top:0.5rem;">
    <?php foreach ($months as $mo): ?>
      <?php $h = $max_month > 0 ? max(6, ($mo['hrs'] / $max_month) * 110) : 6; ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
        <div style="font-size:11px;font-weight:700;color:var(--green-dark);min-height:16px;">
          <?= $mo['hrs'] > 0 ? $mo['hrs'] : '' ?>
        </div>
        <div style="width:100%;height:<?= $h ?>px;background:<?= $mo['hrs'] > 0 ? 'var(--green-mid)' : 'var(--surface2)' ?>;border-radius:6px 6px 0 0;"></div>
        <div style="font-size:11px;color:var(--text3);font-weight:600;"><?= date('M', strtotime($mo['label'])) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <!-- Monthly detail rows -->
  <div style="margin-top:1.25rem;display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
    <?php foreach (array_slice(array_reverse($months), 0, 6) as $mo): ?>
      <div style="background:var(--surface2);border-radius:var(--radius-sm);padding:12px 14px;">
        <div style="font-size:11px;font-weight:700;color:var(--text2);margin-bottom:6px;"><?= $mo['label'] ?></div>
        <div style="font-size:18px;font-weight:700;color:var(--text);font-family:'DM Mono',monospace;margin-bottom:2px;"><?= $mo['hrs'] ?> hrs</div>
        <div style="font-size:11px;color:var(--text3);"><?= $mo['days'] ?> day<?= $mo['days'] !== 1 ? 's' : '' ?> logged</div>
        <div style="font-size:12px;font-weight:700;color:var(--green);margin-top:4px;">₱<?= number_format($mo['days'] * $allowance_per_day) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Log Hours Modal -->
<div class="modal-overlay" id="log-modal">
  <div class="modal-card">
    <div class="modal-title">Log Hours</div>
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
    <form method="POST" action="dashboard.php">
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
    <form method="POST" action="dashboard.php">
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

<!-- Quick Edit Allowance Modal -->
<div class="modal-overlay" id="allowance-modal">
  <div class="modal-card" style="width:340px;">
    <div class="modal-title">Edit Daily Allowance</div>
    <p style="font-size:13px;color:var(--text2);margin-bottom:1.25rem;margin-top:-0.75rem;">
      Set your daily allowance in Philippine Peso (₱).
    </p>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="action" value="update_allowance" />
      <div class="form-group">
        <label class="form-label">Allowance per Day (₱)</label>
        <input class="form-input" type="number" name="allowance_per_day"
               value="<?= e(number_format($allowance_per_day, 2, '.', '')) ?>"
               min="0" step="0.01" required />
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" id="allowance-close-btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>


<?php
if (!empty($log_errors)) echo '<script>document.getElementById("log-modal").classList.add("open");</script>';
include 'includes/footer.php';
?>