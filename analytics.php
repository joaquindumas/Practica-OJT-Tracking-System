<?php
require_once 'includes/config.php'; require_login();
$user = current_user(); $active_page = 'analytics'; $all_logs = $user['logs'] ?? []; $logged = total_logged($user); $required = $user['required_hours'] ?? 500; $remaining = hours_remaining($user); $allowance = $user['allowance_per_day'] ?? 150;
$months = []; for ($i = 5; $i >= 0; $i--) { $mts = strtotime("first day of -{$i} month"); $mkey = date('Y-m', $mts); $hrs = 0; $days = 0; foreach ($all_logs as $l) { if (substr($l['date'], 0, 7) === $mkey) { $hrs += $l['hours']; $days++; } } $months[] = ['label' => date('M', $mts), 'full' => date('M Y', $mts), 'key' => $mkey, 'hrs' => round($hrs, 1), 'days' => $days]; }
$weeks = []; for ($i = 7; $i >= 0; $i--) { $ws = strtotime("monday -{$i} week"); $we = strtotime("sunday -{$i} week"); $hrs = 0; foreach ($all_logs as $l) { $ts = strtotime($l['date']); if ($ts >= $ws && $ts <= $we) $hrs += $l['hours']; } $weeks[] = ['label' => date('M j', $ws), 'hrs' => round($hrs, 1)]; }
$dow_labels = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']; $dow_hrs = array_fill(0, 7, 0); $dow_count = array_fill(0, 7, 0); foreach ($all_logs as $l) { $d = (int) date('N', strtotime($l['date'])) - 1; $dow_hrs[$d] += $l['hours']; $dow_count[$d] += 1; }
$total_days = count(array_unique(array_column($all_logs, 'date'))); $total_allowance = $total_days * $allowance; $avg_hrs_day = $total_days > 0 ? round($logged / $total_days, 1) : 0; $projected_days = $avg_hrs_day > 0 ? (int) ceil($remaining / $avg_hrs_day) : 0; $projected_allowance = $projected_days * $allowance; $total_projected = $total_allowance + $projected_allowance;
$max_week = max(array_column($weeks, 'hrs') ?: [1]); $max_month = max(array_column($months, 'hrs') ?: [1]); $max_dow = max($dow_hrs ?: [1]);
include 'includes/header.php';
?>

<div class="page-header"><div class="page-header-top"><div><div class="page-eyebrow">Academic Performance</div><h1 class="page-title">Analytics</h1><p class="page-subtitle">Detailed internship allowance tracking and hour commitment metrics.</p></div></div></div>

<div class="content">
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:1.5rem;">
    <div class="stat-card"><div class="stat-eyebrow">Earned so far</div><div class="stat-value stat-value--green" style="font-size:2rem;">₱<?= number_format($total_allowance) ?></div><div style="height:3px;background:var(--green-light);border-radius:999px;margin:10px 0;"><div style="width:<?= $total_projected > 0 ? min(100, ($total_allowance / $total_projected * 100)) : 0 ?>%;height:100%;background:var(--green);border-radius:999px;"></div></div><div class="stat-meta"><?= $total_days ?> days × ₱<?= number_format($allowance) ?></div></div>
    <div class="stat-card"><div class="stat-eyebrow">Remaining Allowance</div><div class="stat-value" style="font-size:2rem;">₱<?= number_format($projected_allowance) ?></div><div style="height:3px;background:var(--bg2);border-radius:999px;margin:10px 0;"><div style="width:<?= $total_projected > 0 ? min(100, ($projected_allowance / $total_projected * 100)) : 0 ?>%;height:100%;background:var(--text3);border-radius:999px;"></div></div><div class="stat-meta"><?= $projected_days ?> days remaining (est.)</div></div>
    <div class="stat-card stat-card--featured"><div class="stat-eyebrow">Total Projected</div><div class="stat-value" style="font-size:2rem;">₱<?= number_format($total_projected) ?></div><div class="stat-meta" style="margin-top:8px;">Based on <?= number_format($required, 0) ?> required hours at ₱<?= number_format($allowance) ?>/day</div></div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 280px;gap:16px;margin-bottom:1.5rem;">
    <div class="card card-pad">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;"><div><div style="font-size:14px;font-weight:700;color:var(--text);">Weekly Performance</div><div style="font-size:12px;color:var(--text3);margin-top:2px;">Hours logged per week</div></div><span class="badge badge--green">Last 8 weeks</span></div>
      <div class="chart-bars" style="height:160px;"><?php foreach ($weeks as $wk): $h = $max_week > 0 ? max(4, ($wk['hrs'] / $max_week) * 130) : 4; ?><div class="chart-bar-wrap"><div class="chart-bar <?= $wk['hrs'] > 0 ? 'active' : '' ?>" style="height:<?= $h ?>px;"><?php if ($wk['hrs'] > 0): ?><div class="chart-bar-val"><?= $wk['hrs'] ?></div><?php endif; ?></div><div class="chart-bar-label"><?= $wk['label'] ?></div></div><?php endforeach; ?></div>
    </div>
    <div class="card card-pad" style="background:var(--green-dark);border-color:var(--green-dark);">
      <div style="font-size:14px;font-weight:700;color:white;margin-bottom:4px;">Daily Commitment</div><div style="font-size:12px;color:rgba(149,213,178,0.7);margin-bottom:1.5rem;">Target vs. Reality</div>
      <div style="text-align:center;margin-bottom:1.25rem;">
        <?php $target = 8; $actual = $avg_hrs_day; $ring_pct = min(100, ($actual / $target) * 100); $circumference = 2 * M_PI * 45; $dashoffset = $circumference * (1 - $ring_pct / 100); ?>
        <svg width="120" height="120" viewBox="0 0 120 120"><circle cx="60" cy="60" r="45" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="10"/><circle cx="60" cy="60" r="45" fill="none" stroke="var(--green-muted)" stroke-width="10" stroke-dasharray="<?= $circumference ?>" stroke-dashoffset="<?= $dashoffset ?>" stroke-linecap="round" transform="rotate(-90 60 60)"/><text x="60" y="55" text-anchor="middle" fill="white" font-family="Fraunces,serif" font-size="22" font-weight="600"><?= $avg_hrs_day ?></text><text x="60" y="70" text-anchor="middle" fill="rgba(149,213,178,0.7)" font-family="DM Sans,sans-serif" font-size="10">HRS / DAY</text></svg>
      </div>
      <div style="background:rgba(255,255,255,0.08);border-radius:var(--radius-sm);padding:10px 14px;display:flex;justify-content:space-between;align-items:center;"><span style="font-size:12px;color:rgba(255,255,255,0.6);">Avg Weekly</span><span style="font-family:'DM Mono',monospace;font-size:14px;font-weight:600;color:var(--green-muted);"><?= round($avg_hrs_day * 5, 1) ?> hrs</span></div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:1.5rem;">
    <div class="card card-pad">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;"><div><div style="font-size:14px;font-weight:700;color:var(--text);">Monthly Trajectory</div><div style="font-size:12px;color:var(--text3);margin-top:2px;">Hours per month</div></div></div>
      <div class="chart-bars" style="height:120px;margin-bottom:1rem;"><?php foreach ($months as $mo): $h = $max_month > 0 ? max(4, ($mo['hrs'] / $max_month) * 95) : 4; ?><div class="chart-bar-wrap"><div class="chart-bar" style="height:<?= $h ?>px;background:<?= $mo['hrs'] > 0 ? 'var(--green-mid)' : 'var(--green-light)' ?>;"><?php if ($mo['hrs'] > 0): ?><div class="chart-bar-val"><?= $mo['hrs'] ?></div><?php endif; ?></div><div class="chart-bar-label"><?= $mo['label'] ?></div></div><?php endforeach; ?></div>
      <div style="display:flex;flex-direction:column;gap:0;"><?php foreach (array_slice(array_reverse($months), 0, 3) as $mo): ?><div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);"><span style="font-size:12px;color:var(--text2);"><?= $mo['full'] ?></span><div style="display:flex;gap:12px;align-items:center;"><span style="font-size:12px;color:var(--text3);"><?= $mo['days'] ?>d</span><span style="font-family:'DM Mono',monospace;font-size:12px;font-weight:600;color:var(--green);"><?= $mo['hrs'] ?>h</span><span style="font-size:12px;font-weight:600;color:var(--green-mid);">₱<?= number_format($mo['days'] * $allowance) ?></span></div></div><?php endforeach; ?></div>
    </div>
    <div class="card card-pad">
      <div style="font-size:14px;font-weight:700;color:var(--text);margin-bottom:4px;">Day of Week Breakdown</div><div style="font-size:12px;color:var(--text3);margin-bottom:1.25rem;">Total hours per day of week</div>
      <div style="display:flex;flex-direction:column;gap:10px;"><?php foreach ($dow_labels as $i => $dlabel): $bar_pct = $max_dow > 0 ? ($dow_hrs[$i] / $max_dow) * 100 : 0; ?><div style="display:flex;align-items:center;gap:12px;"><div style="width:30px;font-size:11px;font-weight:700;color:var(--text2);"><?= $dlabel ?></div><div style="flex:1;background:var(--bg2);border-radius:999px;height:8px;overflow:hidden;"><div style="width:<?= round($bar_pct, 1) ?>%;height:100%;background:<?= $bar_pct > 0 ? 'var(--green)' : 'transparent' ?>;border-radius:999px;transition:width 0.5s;"></div></div><div style="width:55px;text-align:right;font-size:11px;font-weight:600;color:var(--text2);font-family:'DM Mono',monospace;"><?= $dow_hrs[$i] > 0 ? $dow_hrs[$i] . 'h' : '—' ?></div><div style="width:24px;font-size:10px;color:var(--text3);"><?= $dow_count[$i] > 0 ? $dow_count[$i] . 'd' : '' ?></div></div><?php endforeach; ?></div>
    </div>
  </div>

  <?php $milestones = array_slice(array_filter($all_logs, fn($l) => !empty($l['description'])), 0, 4); usort($milestones, fn($a, $b) => strtotime($b['date']) - strtotime($a['date'])); ?>
  <?php if (!empty($milestones)): ?>
  <div style="margin-bottom:1.5rem;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;"><div style="font-size:14px;font-weight:700;color:var(--text);">Recent Log Milestones</div><a href="logs.php" class="btn btn-ghost btn-sm">View all logs →</a></div>
    <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
      <?php foreach ($milestones as $m): ?>
      <div class="card card-pad" style="display:flex;gap:12px;align-items:flex-start;"><div style="width:36px;height:36px;border-radius:10px;background:var(--green-xlight);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><svg viewBox="0 0 24 24" fill="var(--green)" style="width:16px;height:16px;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg></div><div style="flex:1;min-width:0;"><div style="font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($m['description']) ?></div><div style="font-size:11px;color:var(--text3);margin-top:2px;"><?= e(date('M j, Y', strtotime($m['date']))) ?></div></div><div style="text-align:right;flex-shrink:0;"><div style="font-size:13px;font-weight:700;color:var(--green);">+₱<?= number_format($m['hours'] / ($avg_hrs_day ?: 8) * $allowance, 0) ?></div><div style="font-size:10px;color:var(--text3);"><?= number_format($m['hours'], 1) ?> hrs</div></div></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>