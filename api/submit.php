<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';

$user = api_user(['student']);
if (req_method() !== 'POST') json_fail('Method not allowed', 405);

$sessionId = (int)(req_body()['session_id'] ?? 0);
if (!$sessionId) json_fail('Missing session_id');

// Verify enrollment
$enrolled = Database::one(
    'SELECT 1 FROM session_enrollments WHERE session_id = ? AND student_id = ?',
    [$sessionId, $user['id']]
);
if (!$enrolled) json_fail('Forbidden', 403);

// Idempotent — if already submitted return OK
$existing = Database::one(
    'SELECT answered_count FROM exam_submissions WHERE session_id = ? AND student_id = ?',
    [$sessionId, $user['id']]
);
if ($existing) json_ok(['answered_count' => $existing['answered_count'], 'already_submitted' => true]);

$count = (int)Database::value(
    'SELECT COUNT(*) FROM student_answers WHERE session_id = ? AND student_id = ?',
    [$sessionId, $user['id']]
);

Database::insert('exam_submissions', [
    'session_id'     => $sessionId,
    'student_id'     => $user['id'],
    'answered_count' => $count,
    'submitted_at'   => date('Y-m-d H:i:s'),
]);

json_ok(['answered_count' => $count]);
