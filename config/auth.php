<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

function sessionBoot(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function currentUser(): ?array
{
    sessionBoot();
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $st = getDB()->prepare(
        'SELECT id, username, full_name, role, department, status
         FROM users WHERE id = ? AND status = "active"'
    );
    $st->execute([$_SESSION['uid']]);
    return $st->fetch() ?: null;
}

function requireAuth(): array
{
    $u = currentUser();
    if (!$u) {
        respond(['error' => 'Unauthorized'], 401);
    }
    return $u;
}

function requireRole(string ...$roles): array
{
    $u = requireAuth();
    if (!in_array($u['role'], $roles, true)) {
        respond(['error' => 'Forbidden'], 403);
    }
    return $u;
}

function respond(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(string $msg, int $code = 400): never
{
    respond(['error' => $msg], $code);
}

function body(): array
{
    static $decoded;
    if (!isset($decoded)) {
        $raw = file_get_contents('php://input');
        $decoded = (array)(json_decode($raw, true) ?? []);
    }
    return $decoded;
}

function method(): string
{
    return $_SERVER['REQUEST_METHOD'];
}

function apiHeaders(): void
{
    header('Content-Type: application/json; charset=utf-8');
}
