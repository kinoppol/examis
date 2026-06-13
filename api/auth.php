<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

sessionBoot();

match (method()) {
    'GET'    => handleGet(),
    'POST'   => handlePost(),
    'DELETE' => handleDelete(),
    default  => fail('Method not allowed', 405),
};

function handleGet(): never
{
    $u = currentUser();
    if (!$u) {
        respond(['user' => null]);
    }
    respond(['user' => $u]);
}

function handlePost(): never
{
    $b = body();
    $username = trim((string)($b['username'] ?? ''));
    $password = trim((string)($b['password'] ?? ''));

    if ($username === '' || $password === '') {
        fail('กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');
    }

    $st = getDB()->prepare(
        'SELECT id, username, full_name, role, department, status, password
         FROM users WHERE username = ?'
    );
    $st->execute([$username]);
    $row = $st->fetch();

    if (!$row || !password_verify($password, $row['password'])) {
        fail('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 401);
    }
    if ($row['status'] !== 'active') {
        fail('บัญชีผู้ใช้ถูกระงับ', 403);
    }

    $_SESSION['uid'] = $row['id'];
    unset($row['password']);
    respond(['user' => $row]);
}

function handleDelete(): never
{
    session_destroy();
    respond(['ok' => true]);
}
