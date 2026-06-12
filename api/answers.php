<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';

$user   = api_user(['student']);
$method = req_method();

// GET — load existing answers for a session
if ($method === 'GET') {
    $sessionId = req_int('session_id');
    if (!$sessionId) json_fail('Missing session_id');

    // Verify enrollment
    $enrolled = Database::one(
        'SELECT 1 FROM session_enrollments WHERE session_id = ? AND student_id = ?',
        [$sessionId, $user['id']]
    );
    if (!$enrolled) json_fail('Forbidden', 403);

    $rows = Database::all(
        'SELECT question_id, answer_label FROM student_answers WHERE session_id = ? AND student_id = ?',
        [$sessionId, $user['id']]
    );
    $answers = [];
    foreach ($rows as $r) $answers[$r['question_id']] = $r['answer_label'];
    json_ok(['answers' => $answers]);
}

// POST/PUT — save a single answer (autosave)
if ($method === 'POST' || $method === 'PUT') {
    $b          = req_body();
    $sessionId  = (int)($b['session_id']  ?? 0);
    $questionId = (int)($b['question_id'] ?? 0);
    $label      = strtoupper(trim((string)($b['answer'] ?? '')));

    if (!$sessionId || !$questionId || !$label) json_fail('Missing fields');

    // Verify enrollment & session active
    $session = Database::one(
        'SELECT es.id FROM exam_sessions es
         JOIN session_enrollments se ON se.session_id = es.id AND se.student_id = ?
         WHERE es.id = ? AND es.status IN (\'ready\',\'active\')',
        [$user['id'], $sessionId]
    );
    if (!$session) json_fail('Session not found or not active', 403);

    // Check not submitted
    $submitted = Database::one(
        'SELECT id FROM exam_submissions WHERE session_id = ? AND student_id = ?',
        [$sessionId, $user['id']]
    );
    if ($submitted) json_fail('Already submitted', 409);

    // Upsert answer
    Database::upsert('student_answers', [
        'session_id'   => $sessionId,
        'student_id'   => $user['id'],
        'question_id'  => $questionId,
        'answer_label' => $label,
        'answered_at'  => date('Y-m-d H:i:s'),
    ], ['answer_label', 'answered_at']);

    json_ok();
}

json_fail('Method not allowed', 405);
