<?php
require_once 'includes/config.php';
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= e(APP_NAME) ?> — Track Your OJT Hours</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --green:       #3a7d5a;
      --green-dark:  #2d6347;
      --green-light: #e8f2ec;
      --green-mid:   #5a9e78;
      --green-xlight:#f2f8f4;
      --bg:          #f0ede6;
      --text:        #1a1a1a;
      --text2:       #5a5a5a;
      --text3:       #9a9a9a;
      --border:      rgba(0,0,0,0.08);
      --surface:     #ffffff;
    }

    body { font-family: 'DM Sans', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }
    a { text-decoration: none; color: inherit; }

    /* ── NAV ── */
    nav {
      position: sticky; top: 0; z-index: 100;
      background: rgba(240,237,230,0.85);
      backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--border);
      padding: 0 2rem; height: 60px;
      display: flex; align-items: center; justify-content: space-between;
    }
    .nav-brand { display: flex; align-items: center; gap: 10px; font-size: 16px; font-weight: 700; color: var(--text); }
    .nav-brand-icon { width: 32px; height: 32px; border-radius: 8px; background: var(--green); display: flex; align-items: center; justify-content: center; }
    .nav-brand-icon svg { width: 16px; height: 16px; fill: white; }
    .nav-links { display: flex; align-items: center; gap: 8px; }
    .nav-link { padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; color: var(--text2); transition: all 0.12s; }
    .nav-link:hover { background: var(--green-light); color: var(--green-dark); }
    .nav-btn { padding: 9px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.12s; border: none; font-family: inherit; }
    .nav-btn-outline { background: none; border: 1.5px solid var(--border); color: var(--text2); }
    .nav-btn-outline:hover { border-color: var(--green); color: var(--green); }
    .nav-btn-solid { background: var(--green); color: white; }
    .nav-btn-solid:hover { background: var(--green-dark); }

    /* ── HERO ── */
    .hero { padding: 6rem 2rem 4rem; text-align: center; max-width: 860px; margin: 0 auto; }
    .hero-badge {
      display: inline-flex; align-items: center; gap: 6px;
      background: var(--green-light); color: var(--green-dark);
      border: 1px solid rgba(58,125,90,0.2); border-radius: 999px;
      padding: 5px 14px; font-size: 12px; font-weight: 700;
      letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 1.5rem;
    }
    .hero-badge svg { width: 12px; height: 12px; fill: var(--green); }
    .hero-title { font-size: clamp(2.5rem, 6vw, 4rem); font-weight: 800; line-height: 1.1; color: var(--text); margin-bottom: 1.25rem; letter-spacing: -0.02em; }
    .hero-title span { color: var(--green); }
    .hero-sub { font-size: 1.1rem; color: var(--text2); line-height: 1.7; max-width: 560px; margin: 0 auto 2.5rem; }
    .hero-ctas { display: flex; align-items: center; justify-content: center; gap: 12px; flex-wrap: wrap; }
    .btn-hero-primary {
      padding: 14px 32px; background: var(--green); color: white;
      border-radius: 10px; font-size: 15px; font-weight: 700; border: none;
      cursor: pointer; font-family: inherit; transition: all 0.12s;
      display: inline-flex; align-items: center; gap: 8px;
    }
    .btn-hero-primary:hover { background: var(--green-dark); transform: translateY(-1px); box-shadow: 0 8px 24px rgba(58,125,90,0.3); }
    .btn-hero-secondary {
      padding: 14px 32px; background: white; color: var(--text);
      border-radius: 10px; font-size: 15px; font-weight: 600;
      border: 1.5px solid var(--border); cursor: pointer; font-family: inherit; transition: all 0.12s;
    }
    .btn-hero-secondary:hover { border-color: var(--green); color: var(--green); transform: translateY(-1px); }
    .hero-stats {
      display: flex; align-items: center; justify-content: center;
      gap: 2rem; margin-top: 3rem; padding-top: 2rem;
      border-top: 1px solid var(--border); flex-wrap: wrap;
    }
    .hero-stat-num { font-size: 1.75rem; font-weight: 800; color: var(--green); font-family: 'DM Mono', monospace; }
    .hero-stat-label { font-size: 12px; color: var(--text3); font-weight: 600; margin-top: 2px; }

    /* ── APP PREVIEW ── */
    .preview-section { padding: 2rem 2rem 5rem; max-width: 1100px; margin: 0 auto; }
    .preview-wrap {
      background: white; border-radius: 20px; border: 1px solid var(--border);
      overflow: hidden; box-shadow: 0 24px 80px rgba(0,0,0,0.1);
    }
    .preview-bar {
      background: var(--bg); padding: 12px 16px;
      display: flex; align-items: center; gap: 8px;
      border-bottom: 1px solid var(--border);
    }
    .preview-dot { width: 12px; height: 12px; border-radius: 50%; }
    .preview-url {
      flex: 1; background: white; border: 1px solid var(--border);
      border-radius: 6px; padding: 4px 12px; font-size: 12px;
      color: var(--text3); font-family: 'DM Mono', monospace; margin: 0 8px;
    }
    .preview-body { display: flex; min-height: 480px; }
    .preview-sidebar {
      width: 180px; background: white; border-right: 1px solid var(--border);
      padding: 1rem; flex-shrink: 0; position: relative;
    }
    .preview-main { flex: 1; background: var(--bg); padding: 1.25rem; overflow: hidden; }

    /* ── FEATURES ── */
    .features-section { padding: 5rem 2rem; max-width: 1100px; margin: 0 auto; }
    .section-label { text-align: center; font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--green); margin-bottom: 0.75rem; }
    .section-title { text-align: center; font-size: clamp(1.75rem, 4vw, 2.5rem); font-weight: 800; color: var(--text); margin-bottom: 0.75rem; letter-spacing: -0.02em; }
    .section-sub { text-align: center; font-size: 15px; color: var(--text2); max-width: 480px; margin: 0 auto 3.5rem; line-height: 1.6; }
    .features-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
    .feature-card { background: white; border: 1px solid var(--border); border-radius: 16px; padding: 1.75rem; transition: all 0.2s; }
    .feature-card:hover { box-shadow: 0 8px 32px rgba(0,0,0,0.08); transform: translateY(-2px); }
    .feature-icon { width: 44px; height: 44px; border-radius: 12px; background: var(--green-xlight); display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; }
    .feature-icon svg { width: 20px; height: 20px; fill: var(--green); }
    .feature-title { font-size: 16px; font-weight: 700; color: var(--text); margin-bottom: 0.5rem; }
    .feature-desc { font-size: 13px; color: var(--text2); line-height: 1.6; }

    /* ── HOW IT WORKS ── */
    .how-section { padding: 5rem 2rem; background: white; }
    .how-inner { max-width: 900px; margin: 0 auto; }
    .how-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-top: 3.5rem; position: relative; }
    .how-steps::before { content: ''; position: absolute; top: 28px; left: calc(16.66% + 14px); right: calc(16.66% + 14px); height: 2px; background: var(--green-light); }
    .how-step { text-align: center; position: relative; }
    .how-step-num { width: 56px; height: 56px; border-radius: 50%; background: var(--green); color: white; font-size: 18px; font-weight: 800; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; position: relative; z-index: 1; font-family: 'DM Mono', monospace; }
    .how-step-title { font-size: 16px; font-weight: 700; color: var(--text); margin-bottom: 0.5rem; }
    .how-step-desc { font-size: 13px; color: var(--text2); line-height: 1.6; }

    /* ── CTA ── */
    .cta-section { padding: 6rem 2rem; max-width: 700px; margin: 0 auto; text-align: center; }
    .cta-box { background: var(--green); border-radius: 24px; padding: 4rem 3rem; position: relative; overflow: hidden; }
    .cta-box::before { content: ''; position: absolute; width: 300px; height: 300px; border-radius: 50%; background: rgba(255,255,255,0.07); top: -100px; right: -80px; }
    .cta-box::after { content: ''; position: absolute; width: 200px; height: 200px; border-radius: 50%; background: rgba(255,255,255,0.05); bottom: -60px; left: -40px; }
    .cta-title { font-size: clamp(1.75rem, 4vw, 2.25rem); font-weight: 800; color: white; margin-bottom: 1rem; position: relative; z-index: 1; }
    .cta-sub { font-size: 15px; color: rgba(255,255,255,0.8); margin-bottom: 2rem; line-height: 1.6; position: relative; z-index: 1; }
    .cta-btns { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; position: relative; z-index: 1; }
    .btn-cta-white { padding: 13px 32px; background: white; color: var(--green); border-radius: 10px; font-size: 15px; font-weight: 700; border: none; cursor: pointer; font-family: inherit; transition: all 0.12s; display: inline-block; }
    .btn-cta-white:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(0,0,0,0.15); }
    .btn-cta-outline { padding: 13px 32px; background: none; color: white; border-radius: 10px; font-size: 15px; font-weight: 600; border: 1.5px solid rgba(255,255,255,0.4); cursor: pointer; font-family: inherit; transition: all 0.12s; display: inline-block; }
    .btn-cta-outline:hover { border-color: white; background: rgba(255,255,255,0.1); }

    /* ── FOOTER ── */
    footer { border-top: 1px solid var(--border); padding: 2rem; text-align: center; font-size: 13px; color: var(--text3); }
    footer a { color: var(--green); font-weight: 600; }

    @media (max-width: 768px) {
      .features-grid { grid-template-columns: 1fr; }
      .how-steps { grid-template-columns: 1fr; }
      .how-steps::before { display: none; }
      .preview-sidebar { display: none; }
      .hero-stats { gap: 1rem; }
    }
  </style>
</head>
<body>

<!-- ── NAV ── -->
<nav>
  <div class="nav-brand">
    <div class="nav-brand-icon">
      <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5C3.9 3 3 3.9 3 5v14c0 1.1.9 2 2 2h7.35c-.22-.62-.35-1.29-.35-2 0-3.31 2.69-6 6-6 .34 0 .67.03 1 .08V9H3V7h16v2.35c.72.22 1.39.57 2 1V5c0-1.1-.9-2-2-2z"/><path d="M19 15c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm1 4.5h-1.5V21h-1v-3H19v1.5h1v1z"/></svg>
    </div>
    <?= e(APP_NAME) ?>
  </div>
  <div class="nav-links">
    <a href="#features" class="nav-link">Features</a>
    <a href="#how" class="nav-link">How it works</a>
    <a href="index.php" class="nav-btn nav-btn-outline">Log in</a>
    <a href="index.php?mode=register" class="nav-btn nav-btn-solid">Get started</a>
  </div>
</nav>

<!-- ── HERO ── -->
<section class="hero">
  <div class="hero-badge">
    <svg viewBox="0 0 24 24"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
    Built for Filipino Interns
  </div>
  <h1 class="hero-title">
    Track Your OJT Hours<br>
    <span>Smarter & Easier</span>
  </h1>
  <p class="hero-sub">
    <?= e(APP_NAME) ?> helps you log, monitor, and analyze your On-the-Job Training hours — so you always know where you stand and when you'll finish.
  </p>
  <div class="hero-ctas">
    <a href="index.php?mode=register" class="btn-hero-primary">
      <svg viewBox="0 0 24 24" fill="white" style="width:16px;height:16px;"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
      Start tracking for free
    </a>
    <a href="index.php" class="btn-hero-secondary">Log in to my account</a>
  </div>
  <div class="hero-stats">
    <div>
      <div class="hero-stat-num">500+</div>
      <div class="hero-stat-label">Hours tracked</div>
    </div>
    <div style="width:1px;height:40px;background:var(--border);"></div>
    <div>
      <div class="hero-stat-num">100%</div>
      <div class="hero-stat-label">Free to use</div>
    </div>
    <div style="width:1px;height:40px;background:var(--border);"></div>
    <div>
      <div class="hero-stat-num">₱</div>
      <div class="hero-stat-label">Allowance tracker</div>
    </div>
    <div style="width:1px;height:40px;background:var(--border);"></div>
    <div>
      <div class="hero-stat-num">PH</div>
      <div class="hero-stat-label">Made in the Philippines</div>
    </div>
  </div>
</section>

<!-- ── APP PREVIEW ── -->
<section class="preview-section">
  <div class="preview-wrap">
    <div class="preview-bar">
      <div class="preview-dot" style="background:#ff5f57;"></div>
      <div class="preview-dot" style="background:#febc2e;"></div>
      <div class="preview-dot" style="background:#28c840;"></div>
      <div class="preview-url"><?= e(APP_NAME) ?> — Dashboard</div>
    </div>
    <div class="preview-body">

      <!-- Sidebar -->
      <div class="preview-sidebar">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:1.5rem;">
          <div style="width:24px;height:24px;border-radius:6px;background:var(--green);flex-shrink:0;"></div>
          <div style="width:70px;height:11px;background:var(--bg);border-radius:4px;"></div>
        </div>
        <div style="font-size:8px;color:var(--text3);font-weight:700;letter-spacing:0.08em;text-transform:uppercase;margin:0 0 6px 4px;">MAIN</div>
        <div style="display:flex;align-items:center;gap:6px;background:var(--green-light);border-radius:6px;padding:7px 8px;margin-bottom:3px;">
          <div style="width:10px;height:10px;border-radius:2px;background:var(--green);flex-shrink:0;"></div>
          <div style="width:55px;height:8px;border-radius:3px;background:var(--green);opacity:0.4;"></div>
        </div>
        <div style="display:flex;align-items:center;gap:6px;padding:7px 8px;margin-bottom:3px;">
          <div style="width:10px;height:10px;border-radius:2px;background:var(--bg);flex-shrink:0;"></div>
          <div style="width:48px;height:8px;border-radius:3px;background:var(--bg);"></div>
        </div>
        <div style="display:flex;align-items:center;gap:6px;padding:7px 8px;margin-bottom:3px;">
          <div style="width:10px;height:10px;border-radius:2px;background:var(--bg);flex-shrink:0;"></div>
          <div style="width:52px;height:8px;border-radius:3px;background:var(--bg);"></div>
        </div>
        <div style="font-size:8px;color:var(--text3);font-weight:700;letter-spacing:0.08em;text-transform:uppercase;margin:10px 0 6px 4px;">ACCOUNT</div>
        <div style="display:flex;align-items:center;gap:6px;padding:7px 8px;margin-bottom:3px;">
          <div style="width:10px;height:10px;border-radius:2px;background:var(--bg);flex-shrink:0;"></div>
          <div style="width:44px;height:8px;border-radius:3px;background:var(--bg);"></div>
        </div>
        <div style="position:absolute;bottom:16px;left:12px;right:12px;">
          <div style="height:1px;background:var(--border);margin-bottom:10px;"></div>
          <div style="display:flex;align-items:center;gap:6px;padding:7px 8px;">
            <div style="width:10px;height:10px;border-radius:2px;background:#fca5a5;flex-shrink:0;"></div>
            <div style="width:40px;height:8px;border-radius:3px;background:#fca5a5;opacity:0.5;"></div>
          </div>
        </div>
      </div>

      <!-- Main -->
      <div class="preview-main">

        <!-- Page header -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
          <div>
            <div style="width:130px;height:16px;background:white;border-radius:5px;margin-bottom:5px;"></div>
            <div style="width:180px;height:9px;background:rgba(0,0,0,0.06);border-radius:3px;"></div>
          </div>
          <div style="display:flex;gap:6px;">
            <div style="width:90px;height:30px;background:var(--green);border-radius:7px;opacity:0.9;"></div>
            <div style="width:80px;height:30px;background:white;border-radius:7px;border:1px solid var(--border);"></div>
          </div>
        </div>

        <!-- Stat cards -->
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:10px;">
          <div style="background:white;border-radius:9px;padding:12px;border:1px solid var(--border);">
            <div style="width:45px;height:7px;border-radius:3px;background:var(--bg);margin-bottom:8px;"></div>
            <div style="font-size:20px;font-weight:800;color:var(--text);font-family:'DM Mono',monospace;line-height:1;">500</div>
            <div style="width:55px;height:7px;border-radius:3px;background:var(--bg);margin-top:6px;"></div>
          </div>
          <div style="background:white;border-radius:9px;padding:12px;border:1px solid var(--border);">
            <div style="width:45px;height:7px;border-radius:3px;background:var(--bg);margin-bottom:8px;"></div>
            <div style="font-size:20px;font-weight:800;color:var(--green);font-family:'DM Mono',monospace;line-height:1;">320</div>
            <div style="width:65px;height:7px;border-radius:3px;background:var(--green-light);margin-top:6px;"></div>
          </div>
          <div style="background:white;border-radius:9px;padding:12px;border:1px solid var(--border);">
            <div style="width:45px;height:7px;border-radius:3px;background:var(--bg);margin-bottom:8px;"></div>
            <div style="font-size:20px;font-weight:800;color:#c0392b;font-family:'DM Mono',monospace;line-height:1;">180</div>
            <div style="width:55px;height:7px;border-radius:3px;background:var(--bg);margin-top:6px;"></div>
          </div>
        </div>

        <!-- Progress card -->
        <div style="background:white;border-radius:9px;padding:12px;margin-bottom:10px;border:1px solid var(--border);">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
            <div style="width:55px;height:8px;border-radius:3px;background:var(--bg);"></div>
            <div style="font-size:11px;font-weight:700;color:var(--green);font-family:'DM Mono',monospace;">64.0%</div>
          </div>
          <div style="height:7px;background:var(--bg);border-radius:999px;overflow:hidden;">
            <div style="width:64%;height:100%;background:var(--green);border-radius:999px;"></div>
          </div>
          <div style="background:var(--green-xlight);border:1px solid var(--green-light);border-radius:7px;padding:9px 10px;margin-top:8px;">
            <div style="font-size:8px;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:var(--green);margin-bottom:3px;">ESTIMATED COMPLETION</div>
            <div style="font-size:13px;font-weight:800;color:var(--green-dark);margin-bottom:2px;">June 14, 2026</div>
            <div style="font-size:9px;color:var(--green-mid);">Based on avg 8.0 hrs/day over 40 days</div>
          </div>
        </div>

        <!-- Recent logs section header -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <div style="font-size:9px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--text2);">RECENT LOGS</div>
          <div style="width:50px;height:20px;background:white;border-radius:5px;border:1px solid var(--border);"></div>
        </div>

        <!-- Table -->
        <div style="background:white;border-radius:9px;overflow:hidden;border:1px solid var(--border);">
          <!-- Header -->
          <div style="display:flex;align-items:center;gap:8px;padding:7px 10px;background:var(--bg);border-bottom:1px solid var(--border);">
            <div style="width:14px;height:14px;border-radius:3px;border:1.5px solid var(--border);flex-shrink:0;"></div>
            <div style="width:60px;height:7px;border-radius:3px;background:rgba(0,0,0,0.08);"></div>
            <div style="flex:1;height:7px;border-radius:3px;background:rgba(0,0,0,0.08);"></div>
            <div style="width:40px;height:7px;border-radius:3px;background:rgba(0,0,0,0.08);"></div>
            <div style="width:40px;height:7px;border-radius:3px;background:rgba(0,0,0,0.08);"></div>
            <div style="width:50px;height:7px;border-radius:3px;background:rgba(0,0,0,0.08);"></div>
            <div style="width:24px;"></div>
          </div>
          <!-- Rows -->
          <?php
            $rows = [
              ['March 23, 2026', '8:00 AM', '4:00 PM', '8.00', true],
              ['March 21, 2026', '8:00 AM', '4:00 PM', '8.00', false],
              ['March 20, 2026', '8:00 AM', '4:00 PM', '8.00', false],
            ];
            foreach ($rows as $i => $row):
          ?>
          <div style="display:flex;align-items:center;gap:8px;padding:8px 10px;<?= $i < 2 ? 'border-bottom:1px solid var(--border);' : '' ?><?= $row[4] ? 'background:var(--green-xlight);' : '' ?>">
            <div style="width:14px;height:14px;border-radius:3px;border:1.5px solid <?= $row[4] ? 'var(--green)' : 'var(--border)' ?>;background:<?= $row[4] ? 'var(--green)' : 'transparent' ?>;flex-shrink:0;"></div>
            <div style="width:72px;font-size:9px;font-weight:600;color:var(--text2);"><?= $row[0] ?></div>
            <div style="flex:1;height:8px;border-radius:3px;background:var(--bg);"></div>
            <div style="width:38px;font-size:9px;color:var(--text3);"><?= $row[1] ?></div>
            <div style="width:38px;font-size:9px;color:var(--text3);"><?= $row[2] ?></div>
            <div style="width:50px;height:18px;border-radius:999px;background:var(--green-light);display:flex;align-items:center;justify-content:center;">
              <span style="font-size:9px;font-weight:700;color:var(--green-dark);"><?= $row[3] ?> hrs</span>
            </div>
            <div style="display:flex;gap:3px;">
              <div style="width:18px;height:18px;border-radius:4px;background:var(--bg);"></div>
              <div style="width:18px;height:18px;border-radius:4px;background:var(--bg);"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

      </div>
    </div>
  </div>
</section>

<!-- ── FEATURES ── -->
<section class="features-section" id="features">
  <div class="section-label">Features</div>
  <h2 class="section-title">Everything you need for OJT</h2>
  <p class="section-sub">All the tools to track, manage and analyze your internship hours in one place.</p>
  <div class="features-grid">
    <div class="feature-card">
      <div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg></div>
      <div class="feature-title">Easy Hour Logging</div>
      <div class="feature-desc">Log your OJT hours in seconds. Set your time in, time out, and description. Hours are calculated automatically.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5C3.9 3 3 3.9 3 5v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/></svg></div>
      <div class="feature-title">Calendar View</div>
      <div class="feature-desc">Visualize your logged days on a calendar. See which days you've worked at a glance and spot any gaps.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M4 6h16v2H4zm0 5h16v2H4zm0 5h16v2H4z"/></svg></div>
      <div class="feature-title">Bulk Log Entry</div>
      <div class="feature-desc">Log multiple days at once using a date range. Perfect for catching up on missed entries with custom day exclusions.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg></div>
      <div class="feature-title">Allowance Tracker</div>
      <div class="feature-desc">Set your daily allowance and automatically see how much you've earned and your projected total earnings.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg></div>
      <div class="feature-title">Analytics Dashboard</div>
      <div class="feature-desc">View weekly and monthly charts, day-of-week breakdowns, and estimated completion dates based on your pace.</div>
    </div>
    <div class="feature-card">
      <div class="feature-icon"><svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 4l5 2.18V11c0 3.5-2.33 6.79-5 7.93-2.67-1.14-5-4.43-5-7.93V7.18L12 5z"/></svg></div>
      <div class="feature-title">Secure & Private</div>
      <div class="feature-desc">Your data is protected with secure password hashing and security question recovery. Your hours are yours alone.</div>
    </div>
  </div>
</section>

<!-- ── HOW IT WORKS ── -->
<section class="how-section" id="how">
  <div class="how-inner">
    <div class="section-label">How it works</div>
    <h2 class="section-title">Up and running in minutes</h2>
    <p class="section-sub">Three simple steps to start tracking your OJT hours today.</p>
    <div class="how-steps">
      <div class="how-step">
        <div class="how-step-num">1</div>
        <div class="how-step-title">Create your account</div>
        <div class="how-step-desc">Sign up in under a minute. Set your name, username, required OJT hours, and you're ready to go.</div>
      </div>
      <div class="how-step">
        <div class="how-step-num">2</div>
        <div class="how-step-title">Log your hours</div>
        <div class="how-step-desc">After each OJT day, log your time in and time out. Use bulk log to catch up on multiple days at once.</div>
      </div>
      <div class="how-step">
        <div class="how-step-num">3</div>
        <div class="how-step-title">Track your progress</div>
        <div class="how-step-desc">Watch your progress bar grow. See your estimated completion date and how much allowance you've earned.</div>
      </div>
    </div>
  </div>
</section>

<!-- ── CTA ── -->
<section class="cta-section">
  <div class="cta-box">
    <h2 class="cta-title">Start tracking your OJT today</h2>
    <p class="cta-sub">Free to use. No setup needed. Just create an account and start logging your hours right away.</p>
    <div class="cta-btns">
      <a href="index.php?mode=register" class="btn-cta-white">Create free account</a>
      <a href="index.php" class="btn-cta-outline">Already have an account</a>
    </div>
  </div>
</section>

<!-- ── FOOTER ── -->
<footer>
  <p>
    &copy; <?= date('Y') ?> <strong><?= e(APP_NAME) ?></strong> &mdash;
    Built for Filipino OJT interns &mdash; Joaquin Miguel Dumas
  </p>
</footer>

</body>
</html>