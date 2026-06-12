<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';

if (req_method() !== 'POST') json_fail('Method not allowed', 405);

$b    = req_body();
$user = Auth::login((string)($b['username'] ?? ''), (string)($b['password'] ?? ''));
if (!$user) json_fail('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', 401);

json_ok([
    'user'           => $user,
    'initial_screen' => Auth::defaultScreen($user['role']),
]);
