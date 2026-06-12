<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';

$user   = api_user();
$method = req_method();

// GET — list requests
if ($method === 'GET') {
    if (!in_array($user['role'], ['exam_manager','admin','academic_deputy'], true)) {
        json_fail('Forbidden', 403);
    }
    $rows = Database::all(
        'SELECT lr.*, u.full_name AS student_name, u.student_code,
                ep.subject, ep.level,
                es.room_code, es.scheduled_date,
                DATE_FORMAT(lr.created_at,\'%d %b.\') AS request_date_fmt
         FROM late_requests lr
         JOIN users u ON u.id = lr.student_id
         JOIN exam_sessions es ON es.id = lr.session_id
         JOIN exam_papers ep ON ep.id = es.paper_id
         ORDER BY lr.created_at DESC',
        []
    );
    json_ok(['requests' => $rows]);
}

// POST — student submits a request
if ($method === 'POST' && $user['role'] === 'student') {
    $b         = req_body();
    $sessionId = (int)($b['session_id'] ?? 0);
    $reason    = trim((string)($b['reason'] ?? ''));
    if (!$sessionId || !$reason) json_fail('Missing session_id or reason');

    $enrolled = Database::one(
        'SELECT 1 FROM session_enrollments WHERE session_id = ? AND student_id = ?',
        [$sessionId, $user['id']]
    );
    if (!$enrolled) json_fail('Not enrolled', 403);

    $id = Database::insert('late_requests', [
        'session_id' => $sessionId,
        'student_id' => $user['id'],
        'reason'     => $reason,
    ]);
    json_ok(['id' => $id], 201);
}

// PATCH/PUT — manager approves or rejects
if (($method === 'PATCH' || $method === 'PUT') && in_array($user['role'], ['exam_manager','admin'], true)) {
    $b      = req_body();
    $id     = (int)($b['id'] ?? req_int('id'));
    $action = $b['action'] ?? ''; // 'approve' | 'reject'
    if (!$id || !in_array($action, ['approve','reject'], true)) json_fail('Missing id or action');

    $status = $action === 'approve' ? 'approved' : 'rejected';
    Database::update('late_requests', [
        'status'      => $status,
        'reviewed_by' => $user['id'],
        'reviewed_at' => date('Y-m-d H:i:s'),
    ], 'id = ?', [$id]);

    json_ok(['status' => $status]);
}

json_fail('Not found', 404);
