<?php
// ─── logout.php ───────────────────────────────────────────────
require_once 'includes/config.php';
session_destroy();
header('Location: landing.php');
exit;
