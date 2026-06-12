<?php
declare(strict_types=1);

// ── Database connection ───────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'examis');
define('DB_USER', 'root');
define('DB_PASS', '');

// ── App ───────────────────────────────────────────────────────────────────────
define('APP_BASE', '/examis');
define('UPLOAD_DIR', __DIR__ . '/../uploads');

// ── Session ───────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name('examis_sess');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// ── Autoload ──────────────────────────────────────────────────────────────────
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../src/Auth.php';

// ── Response helpers ──────────────────────────────────────────────────────────

function json_ok(mixed $data = null, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data ?? ['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_fail(string $message, int $code = 400): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── API guards ────────────────────────────────────────────────────────────────

function api_user(array $roles = []): array
{
    $user = Auth::user();
    if (!$user) json_fail('Unauthorized', 401);
    if ($roles && !in_array($user['role'], $roles, true)) json_fail('Forbidden', 403);
    return $user;
}

// ── Request helpers ───────────────────────────────────────────────────────────

function req_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function req_body(): array
{
    static $body = null;
    if ($body !== null) return $body;
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($ct, 'application/json')) {
        $body = (array)(json_decode(file_get_contents('php://input'), true) ?? []);
    } else {
        $body = array_merge($_GET, $_POST);
    }
    return $body;
}

function req_int(string $key, int $default = 0): int
{
    $v = req_body()[$key] ?? $_GET[$key] ?? $default;
    return (int) $v;
}

function req_str(string $key, string $default = ''): string
{
    return trim((string)(req_body()[$key] ?? $_GET[$key] ?? $default));
}
