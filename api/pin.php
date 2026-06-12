<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';

$user   = api_user();
$method = req_method();
$b      = req_body();

// ── Student: verify PIN to enter exam ────────────────────────────────────────
if ($user['role'] === 'student' && $method === 'POST' && ($b['action'] ?? '') !== 'regen') {
    $pin = preg_replace('/\D/', '', (string)($b['pin'] ?? ''));
    if (strlen($pin) !== 6) json_fail('รหัส PIN ต้องมี 6 หลัก');

    // Find active session with this PIN that the student is enrolled in
    $session = Database::one(
        'SELECT es.*, ep.title, ep.subject, ep.level, ep.paper_type, ep.pdf_filename
         FROM exam_sessions es
         JOIN exam_papers ep ON ep.id = es.paper_id
         JOIN session_enrollments se ON se.session_id = es.id AND se.student_id = ?
         WHERE es.pin_code = ? AND es.status IN (\'ready\',\'active\')
         LIMIT 1',
        [$user['id'], $pin]
    );
    if (!$session) json_fail('รหัสไม่ถูกต้อง หรือยังไม่ถึงเวลาสอบ', 403);

    // Check not already submitted
    $submitted = Database::one(
        'SELECT id FROM exam_submissions WHERE session_id = ? AND student_id = ?',
        [$session['id'], $user['id']]
    );
    if ($submitted) json_fail('คุณได้ส่งข้อสอบนี้ไปแล้ว', 409);

    // Mark check-in
    Database::update(
        'session_enrollments',
        ['checked_in_at' => date('Y-m-d H:i:s')],
        'session_id = ? AND student_id = ? AND checked_in_at IS NULL',
        [$session['id'], $user['id']]
    );

    // Load questions
    $questions = Database::all(
        'SELECT q.id, q.question_text, q.order_num, q.points
         FROM questions q
         WHERE q.paper_id = ?
         ORDER BY q.order_num',
        [$session['paper_id']]
    );
    foreach ($questions as &$q) {
        $q['choices'] = Database::all(
            'SELECT id, label, choice_text, order_num FROM choices WHERE question_id = ? ORDER BY order_num',
            [$q['id']]
        );
    }

    // Load existing answers (if student already answered some)
    $answerRows = Database::all(
        'SELECT question_id, answer_label FROM student_answers WHERE session_id = ? AND student_id = ?',
        [$session['id'], $user['id']]
    );
    $answers = [];
    foreach ($answerRows as $a) $answers[$a['question_id']] = $a['answer_label'];

    // Compute remaining seconds
    $startedAt = strtotime($session['scheduled_date'] . ' ' . $session['start_time']);
    $endsAt    = $startedAt + $session['duration_minutes'] * 60;
    $remaining = max(0, $endsAt - time());

    json_ok([
        'session'   => $session,
        'questions' => $questions,
        'answers'   => $answers,
        'time_left' => $remaining,
    ]);
}

// ── Supervisor: regenerate PIN ────────────────────────────────────────────────
if ($user['role'] === 'exam_supervisor' && $method === 'POST') {
    $sessionId = req_int('session_id');
    if (!$sessionId) json_fail('Missing session_id');

    $session = Database::one(
        'SELECT id FROM exam_sessions WHERE id = ? AND supervisor_id = ?',
        [$sessionId, $user['id']]
    );
    if (!$session) json_fail('Forbidden', 403);

    $newPin = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    Database::update('exam_sessions', ['pin_code' => $newPin], 'id = ?', [$sessionId]);
    json_ok(['pin_code' => $newPin]);
}

json_fail('Not found', 404);
