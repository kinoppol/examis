<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';

$user   = api_user(['admin']);
$method = req_method();

$roleBadge = [
    'admin'           => ['#475569','#f1f5f9'],
    'teacher'         => ['#d97706','#fffbeb'],
    'exam_manager'    => ['#2563eb','#eff6ff'],
    'academic_deputy' => ['#0891b2','#ecfeff'],
    'exam_supervisor' => ['#7c3aed','#f5f3ff'],
    'student'         => ['#0f766e','#f0fdfa'],
];
$roleLabels = [
    'admin' => 'Admin', 'teacher' => 'Teacher', 'exam_manager' => 'Manager',
    'academic_deputy' => 'Deputy', 'exam_supervisor' => 'Supervisor', 'student' => 'Student',
];
$avPalette = [['#ede9fe','#7c3aed'],['#dbeafe','#2563eb'],['#dcfce7','#16a34a'],['#fef3c7','#d97706'],['#fee2e2','#dc2626'],['#cffafe','#0891b2']];

// GET — list users
if ($method === 'GET') {
    $rows = Database::all(
        'SELECT id, username, full_name, role, student_code, is_active, created_at FROM users ORDER BY role, full_name'
    );
    $users = array_map(function ($u, $i) use ($roleLabels, $roleBadge, $avPalette) {
        $rb = $roleBadge[$u['role']] ?? ['#475569','#f1f5f9'];
        $av = $avPalette[$i % count($avPalette)];
        return array_merge($u, [
            'initial'     => mb_substr($u['full_name'], 0, 1),
            'roleLabel'   => $roleLabels[$u['role']] ?? $u['role'],
            'roleColor'   => $rb[0], 'roleBg' => $rb[1],
            'avBg'        => $av[0], 'avColor' => $av[1],
            'statusText'  => $u['is_active'] ? 'ใช้งาน' : 'ระงับ',
            'statusColor' => $u['is_active'] ? '#16a34a' : '#94a3b8',
        ]);
    }, $rows, array_keys($rows));

    $stats = [
        'total'   => count($rows),
        'staff'   => count(array_filter($rows, fn($r) => !in_array($r['role'], ['student','admin'], true))),
        'roles'   => count(array_unique(array_column($rows, 'role'))),
        'pending' => (int)Database::value("SELECT COUNT(*) FROM users WHERE is_active=1"),
    ];
    json_ok(['users' => $users, 'stats' => $stats]);
}

// POST — create user
if ($method === 'POST') {
    $b = req_body();
    $required = ['username','password','full_name','role'];
    foreach ($required as $f) if (empty($b[$f])) json_fail("Missing: $f");

    $exists = Database::one('SELECT id FROM users WHERE username = ?', [$b['username']]);
    if ($exists) json_fail('Username already exists', 409);

    if (strlen($b['password']) < 6) json_fail('Password must be at least 6 characters');

    $id = Database::insert('users', [
        'username'      => $b['username'],
        'password_hash' => password_hash($b['password'], PASSWORD_BCRYPT, ['cost' => 12]),
        'full_name'     => $b['full_name'],
        'role'          => $b['role'],
        'student_code'  => $b['student_code'] ?? null,
        'is_active'     => 1,
    ]);
    json_ok(['id' => $id], 201);
}

// PUT — update user
if ($method === 'PUT') {
    $id = req_int('id');
    if (!$id) json_fail('Missing id');
    if ($id === $user['id']) json_fail('Cannot modify your own account here');
    $b      = req_body();
    $allowed = ['full_name','role','student_code','is_active'];
    $data   = array_intersect_key($b, array_flip($allowed));
    if (!$data) json_fail('Nothing to update');
    Database::update('users', $data, 'id = ?', [$id]);
    json_ok();
}

// PATCH — reset password
if ($method === 'PATCH') {
    $b  = req_body();
    $id = (int)($b['id'] ?? 0);
    $pw = (string)($b['password'] ?? '');
    if (!$id || strlen($pw) < 6) json_fail('Missing id or password too short');
    Database::update('users', [
        'password_hash' => password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12]),
    ], 'id = ?', [$id]);
    json_ok();
}

json_fail('Not found', 404);
