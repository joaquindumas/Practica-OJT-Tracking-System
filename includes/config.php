<?php
define('APP_NAME', 'OJT Tracker');

// ── Database config ───────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'ojt_tracker');
define('DB_USER', 'root');
define('DB_PASS', '');        // ← change if your MySQL has a password

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Database connection ───────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    return $pdo;
}

// ── User helpers ──────────────────────────────────────────────
function get_user(string $username): ?array {
    $stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user) return null;

    // Attach logs
    $user['logs']           = get_logs($user['id']);
    $user['required_hours'] = (float) $user['required_hours'];
    $user['allowance_per_day'] = (float) ($user['allowance_per_day'] ?? 150);
    return $user;
}

function get_user_by_id(int $id): ?array {
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) return null;
    $user['logs']           = get_logs($user['id']);
    $user['required_hours'] = (float) $user['required_hours'];
    return $user;
}

function save_user(array $user): void {
    $db = db();

    if (isset($user['id']) && $user['id']) {
        $stmt = $db->prepare('
            UPDATE users SET
                name              = ?,
                password          = ?,
                required_hours    = ?,
                allowance_per_day = ?,
                security_question = ?,
                security_answer   = ?,
                email             = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $user['name'],
            $user['password'],
            $user['required_hours'] ?? 500,
            $user['allowance_per_day'] ?? 150,
            $user['security_question'] ?? null,
            $user['security_answer']   ?? null,
            $user['email']             ?? null,
            $user['id'],
        ]);
    } else {
        $stmt = $db->prepare('
            INSERT INTO users (name, username, password, required_hours, allowance_per_day, security_question, security_answer, email)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $user['name'],
            $user['username'],
            $user['password'],
            $user['required_hours']    ?? 500,
            $user['allowance_per_day'] ?? 150,
            $user['security_question'] ?? null,
            $user['security_answer']   ?? null,
            $user['email']             ?? null,
        ]);
    }
}

// ── Log helpers ───────────────────────────────────────────────
function get_logs(int $user_id): array {
    $stmt = db()->prepare('SELECT * FROM time_logs WHERE user_id = ? ORDER BY date DESC');
    $stmt->execute([$user_id]);
    $rows = $stmt->fetchAll();
    // Normalize to match old format
    return array_map(fn($r) => [
        'id'          => $r['id'],
        'date'        => $r['date'],
        'description' => $r['description'] ?? '',
        'from'        => $r['time_from'],
        'to'          => $r['time_to'],
        'hours'       => (float) $r['hours'],
        'created_at'  => $r['created_at'],
    ], $rows);
}

function add_log(int $user_id, array $log): void {
    $stmt = db()->prepare('
        INSERT INTO time_logs (id, user_id, date, description, time_from, time_to, hours)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $log['id'],
        $user_id,
        $log['date'],
        $log['description'] ?? null,
        $log['from'],
        $log['to'],
        $log['hours'],
    ]);
}

function update_log(string $log_id, int $user_id, array $log): void {
    $stmt = db()->prepare('
        UPDATE time_logs SET
            date        = ?,
            description = ?,
            time_from   = ?,
            time_to     = ?,
            hours       = ?
        WHERE id = ? AND user_id = ?
    ');
    $stmt->execute([
        $log['date'],
        $log['description'] ?? null,
        $log['from'],
        $log['to'],
        $log['hours'],
        $log_id,
        $user_id,
    ]);
}

function delete_log(string $log_id, int $user_id): void {
    $stmt = db()->prepare('DELETE FROM time_logs WHERE id = ? AND user_id = ?');
    $stmt->execute([$log_id, $user_id]);
}

// ── Auth helpers ──────────────────────────────────────────────
function is_logged_in(): bool {
    return isset($_SESSION['username']);
}

function current_user(): ?array {
    if (!is_logged_in()) return null;
    return get_user($_SESSION['username']);
}

function require_login(): void {
    if (!is_logged_in()) { header('Location: index.php'); exit; }
}

function require_guest(): void {
    if (is_logged_in()) { header('Location: dashboard.php'); exit; }
}

function hash_password(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

function set_flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    if (!isset($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

// ── Hours helpers ─────────────────────────────────────────────
function total_logged(array $user): float {
    return array_sum(array_column($user['logs'] ?? [], 'hours'));
}

function hours_remaining(array $user): float {
    return max(0, ($user['required_hours'] ?? 500) - total_logged($user));
}

function completion_percent(array $user): float {
    $req = $user['required_hours'] ?? 500;
    if ($req <= 0) return 100;
    return min(100, (total_logged($user) / $req) * 100);
}

function estimated_completion(array $user): ?string {
    $logs = $user['logs'] ?? [];
    if (count($logs) < 1) return null;
    $unique_days = count(array_unique(array_column($logs, 'date')));
    if ($unique_days < 1) return null;
    $avg = total_logged($user) / $unique_days;
    if ($avg <= 0) return null;
    $remaining = hours_remaining($user);
    if ($remaining <= 0) return 'Completed';
    $working_days_needed = (int) ceil($remaining / $avg);
    $current = strtotime('today');
    $days_counted = 0;
    while ($days_counted < $working_days_needed) {
        $current = strtotime('+1 day', $current);
        if ((int) date('N', $current) < 6) $days_counted++;
    }
    return date('F j, Y', $current);
}

function estimated_basis(array $user): ?string {
    $logs = $user['logs'] ?? [];
    if (count($logs) < 1) return null;
    $unique_days = count(array_unique(array_column($logs, 'date')));
    if ($unique_days < 1) return null;
    $avg = round(total_logged($user) / $unique_days, 1);
    return "Based on avg {$avg} hrs/day over {$unique_days} day" . ($unique_days > 1 ? 's' : '');
}

function get_security_question(string $username): ?string {
    $user = get_user($username);
    return $user['security_question'] ?? null;
}

function verify_security_answer(string $username, string $answer): bool {
    $user = get_user($username);
    if (!$user || !isset($user['security_answer'])) return false;
    return strtolower(trim($answer)) === strtolower(trim($user['security_answer']));
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function generate_id(): string {
    return uniqid('', true);
}

function bulk_add_logs(int $user_id, array $logs): int {
    $count = 0;
    foreach ($logs as $log) {
        if (empty($log['date']) || empty($log['from']) || empty($log['to'])) continue;
        [$fh, $fm] = array_map('intval', explode(':', $log['from']));
        [$th, $tm] = array_map('intval', explode(':', $log['to']));
        $hours = (($th * 60 + $tm) - ($fh * 60 + $fm)) / 60;
        if ($hours <= 0) continue;

        // Skip if already logged for this date
        $stmt = db()->prepare('SELECT id FROM time_logs WHERE user_id = ? AND date = ?');
        $stmt->execute([$user_id, $log['date']]);
        if ($stmt->fetch()) continue;

        add_log($user_id, [
            'id'          => generate_id(),
            'date'        => $log['date'],
            'description' => $log['description'] ?? '',
            'from'        => $log['from'],
            'to'          => $log['to'],
            'hours'       => round($hours, 4),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        $count++;
    }
    return $count;
}