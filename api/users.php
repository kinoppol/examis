<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

$me = requireRole('admin');

match (method()) {
    'GET'    => listUsers(),
    'POST'   => createUser(),
    'PUT'    => updateUser(),
    'DELETE' => deleteUser(),
    default  => fail('Method not allowed', 405),
};

function listUsers(): never
{
    $rows = getDB()
        ->query('SELECT id, username, full_name, role, department, status, created_at FROM users ORDER BY id')
        ->fetchAll();
    respond($rows);
}

function createUser(): never
{
    $b = body();
    $username  = trim((string)($b['username']  ?? ''));
    $full_name = trim((string)($b['full_name'] ?? ''));
    $role      = trim((string)($b['role']      ?? ''));
    $dept      = trim((string)($b['department'] ?? ''));
    $password  = trim((string)($b['password']  ?? ''));

    $allowed = ['admin','deputy','manager','teacher','supervisor','student'];
    if ($username === '' || $full_name === '' || $password === '' || !in_array($role, $allowed, true)) {
        fail('ข้อมูลไม่ครบถ้วน');
    }

    $db = getDB();
    $chk = $db->prepare('SELECT id FROM users WHERE username = ?');
    $chk->execute([$username]);
    if ($chk->fetch()) {
        fail('ชื่อผู้ใช้นี้มีอยู่แล้ว');
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);
    $st = $db->prepare(
        'INSERT INTO users (username, password, full_name, role, department, status)
         VALUES (?, ?, ?, ?, ?, "active")'
    );
    $st->execute([$username, $hash, $full_name, $role, $dept]);
    $id = (int)$db->lastInsertId();

    $row = $db->prepare('SELECT id, username, full_name, role, department, status, created_at FROM users WHERE id = ?');
    $row->execute([$id]);
    respond($row->fetch(), 201);
}

function updateUser(): never
{
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('ไม่ระบุ id');

    $b    = body();
    $sets = [];
    $vals = [];

    foreach (['full_name', 'department'] as $f) {
        if (isset($b[$f])) { $sets[] = "$f = ?"; $vals[] = trim((string)$b[$f]); }
    }
    $allowed = ['admin','deputy','manager','teacher','supervisor','student'];
    if (isset($b['role']) && in_array($b['role'], $allowed, true)) {
        $sets[] = 'role = ?'; $vals[] = $b['role'];
    }
    if (isset($b['status']) && in_array($b['status'], ['active','inactive'], true)) {
        $sets[] = 'status = ?'; $vals[] = $b['status'];
    }
    if (!empty($b['password'])) {
        $sets[] = 'password = ?'; $vals[] = password_hash(trim((string)$b['password']), PASSWORD_BCRYPT);
    }

    if (!$sets) fail('ไม่มีข้อมูลที่จะอัปเดต');

    $vals[] = $id;
    getDB()->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);

    $row = getDB()->prepare('SELECT id, username, full_name, role, department, status, created_at FROM users WHERE id = ?');
    $row->execute([$id]);
    respond($row->fetch() ?: []);
}

function deleteUser(): never
{
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('ไม่ระบุ id');
    getDB()->prepare('UPDATE users SET status = "inactive" WHERE id = ?')->execute([$id]);
    respond(['ok' => true]);
}
