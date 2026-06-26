<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

$me     = requireRole('student');
$action = $_GET['action'] ?? '';

match ($action) {
    'enter'  => enterExam($me),
    'start'  => startExam($me),
    'answer' => saveAnswer($me),
    'submit' => submitExam($me),
    'status' => examStatus($me),
    default  => fail('Unknown action', 400),
};

// Verify access code and return exam details + questions (without correct answers)
function enterExam(array $me): never
{
    $b    = body();
    $code = strtoupper(trim((string)($b['access_code'] ?? '')));
    if ($code === '') fail('กรุณากรอกรหัสเข้าสอบ');

    $db = getDB();
    $st = $db->prepare(
        'SELECT s.*, p.title AS paper_title, p.id AS paper_id,
                p.paper_type, p.pdf_path, p.pdf_choices
         FROM exam_sessions s
         JOIN exam_papers p ON p.id = s.exam_paper_id
         WHERE s.access_code = ?'
    );
    $st->execute([$code]);
    $session = $st->fetch();
    if (!$session) fail('รหัสเข้าสอบไม่ถูกต้อง', 404);
    if ($session['status'] === 'done') fail('การสอบนี้สิ้นสุดแล้ว');

    // Get or create student_exam record
    $check = $db->prepare(
        'SELECT * FROM student_exams WHERE session_id = ? AND student_id = ?'
    );
    $check->execute([$session['id'], $me['id']]);
    $se = $check->fetch();

    if (!$se) {
        $ins = $db->prepare(
            'INSERT INTO student_exams (session_id, student_id, status) VALUES (?, ?, "not_started")'
        );
        $ins->execute([$session['id'], $me['id']]);
        $seId = (int)$db->lastInsertId();
        $check->execute([$session['id'], $me['id']]);
        $se = $check->fetch();
    }

    if ($se['status'] === 'submitted') fail('คุณได้ส่งข้อสอบนี้แล้ว');

    // Return questions WITHOUT correct_answer
    $qSt = $db->prepare(
        'SELECT id, type, question_text, options, score, order_num
         FROM questions WHERE exam_paper_id = ? ORDER BY order_num, id'
    );
    $qSt->execute([$session['paper_id']]);
    $questions = $qSt->fetchAll();
    foreach ($questions as &$q) {
        $q['options'] = $q['options'] ? json_decode($q['options'], true) : null;
    }

    // Get existing answers if any
    $ansSt = $db->prepare(
        'SELECT sa.question_id, sa.answer FROM student_answers sa
         WHERE sa.student_exam_id = ?'
    );
    $ansSt->execute([$se['id']]);
    $answers = [];
    foreach ($ansSt->fetchAll() as $a) {
        $answers[$a['question_id']] = json_decode($a['answer'], true);
    }

    // Time remaining
    $timeLeft = null;
    if ($se['status'] === 'in_progress' && $se['started_at']) {
        $elapsed  = time() - strtotime($se['started_at']);
        $limit    = (int)$session['time_limit_minutes'] * 60;
        $timeLeft = max(0, $limit - $elapsed);
    }

    respond([
        'session'        => $session,
        'student_exam'   => $se,
        'questions'      => $questions,
        'saved_answers'  => $answers,
        'time_left'      => $timeLeft,
    ]);
}

// Mark exam as started, record start timestamp
function startExam(array $me): never
{
    $b  = body();
    $seId = (int)($b['student_exam_id'] ?? 0);
    if (!$seId) fail('ไม่ระบุ student_exam_id');

    $db = getDB();
    $row = $db->prepare('SELECT * FROM student_exams WHERE id = ? AND student_id = ?');
    $row->execute([$seId, $me['id']]);
    $se = $row->fetch();
    if (!$se) fail('ไม่พบการสอบ', 404);
    if ($se['status'] === 'submitted') fail('ส่งข้อสอบแล้ว');

    if ($se['status'] === 'not_started') {
        $db->prepare(
            'UPDATE student_exams SET status = "in_progress", started_at = NOW() WHERE id = ?'
        )->execute([$seId]);
    }

    $row->execute([$seId, $me['id']]);
    $se = $row->fetch();

    // Time left
    $sessSt = $db->prepare('SELECT time_limit_minutes FROM exam_sessions WHERE id = ?');
    $sessSt->execute([$se['session_id']]);
    $sess = $sessSt->fetch();
    $elapsed  = time() - strtotime($se['started_at']);
    $limit    = (int)$sess['time_limit_minutes'] * 60;
    $timeLeft = max(0, $limit - $elapsed);

    respond(['student_exam' => $se, 'time_left' => $timeLeft]);
}

// Save a single answer (upsert)
function saveAnswer(array $me): never
{
    $b      = body();
    $seId   = (int)($b['student_exam_id'] ?? 0);
    $qId    = (int)($b['question_id']     ?? 0);
    $answer = $b['answer'] ?? null;

    if (!$seId || !$qId) fail('ข้อมูลไม่ครบ');

    $db = getDB();
    $row = $db->prepare('SELECT id, status FROM student_exams WHERE id = ? AND student_id = ?');
    $row->execute([$seId, $me['id']]);
    $se = $row->fetch();
    if (!$se) fail('ไม่พบการสอบ', 404);
    if ($se['status'] === 'submitted') fail('ส่งข้อสอบแล้ว');

    $encoded = json_encode($answer, JSON_UNESCAPED_UNICODE);

    $chk = $db->prepare('SELECT id FROM student_answers WHERE student_exam_id = ? AND question_id = ?');
    $chk->execute([$seId, $qId]);
    if ($chk->fetch()) {
        $db->prepare('UPDATE student_answers SET answer = ? WHERE student_exam_id = ? AND question_id = ?')
           ->execute([$encoded, $seId, $qId]);
    } else {
        $db->prepare('INSERT INTO student_answers (student_exam_id, question_id, answer) VALUES (?, ?, ?)')
           ->execute([$seId, $qId, $encoded]);
    }
    respond(['ok' => true]);
}

// Submit exam + auto-grade MCQ/TF/fill
function submitExam(array $me): never
{
    $b    = body();
    $seId = (int)($b['student_exam_id'] ?? 0);
    if (!$seId) fail('ไม่ระบุ student_exam_id');

    $db = getDB();
    $row = $db->prepare('SELECT se.*, s.exam_paper_id FROM student_exams se JOIN exam_sessions s ON s.id=se.session_id WHERE se.id = ? AND se.student_id = ?');
    $row->execute([$seId, $me['id']]);
    $se = $row->fetch();
    if (!$se) fail('ไม่พบการสอบ', 404);
    if ($se['status'] === 'submitted') fail('ส่งข้อสอบแล้ว');

    // Auto-grade
    $qSt = $db->prepare(
        'SELECT q.id, q.type, q.correct_answer, q.score, sa.answer AS student_answer
         FROM questions q
         LEFT JOIN student_answers sa ON sa.question_id = q.id AND sa.student_exam_id = ?
         WHERE q.exam_paper_id = ?'
    );
    $qSt->execute([$seId, $se['exam_paper_id']]);
    $questions = $qSt->fetchAll();

    $totalScore = 0;
    $maxScore   = 0;

    $upd = $db->prepare('UPDATE student_answers SET score = ? WHERE student_exam_id = ? AND question_id = ?');
    $ins = $db->prepare('INSERT INTO student_answers (student_exam_id, question_id, answer, score) VALUES (?,?,?,0)');

    foreach ($questions as $q) {
        $maxScore += (int)$q['score'];
        $correct   = $q['correct_answer'] !== null ? json_decode($q['correct_answer'], true) : null;
        $given     = $q['student_answer']  !== null ? json_decode($q['student_answer'],  true) : null;
        $score     = null; // null = needs manual grading

        switch ($q['type']) {
            case 'mcq':
            case 'truefalse':
                $score = ($given !== null && $given === $correct) ? (int)$q['score'] : 0;
                $totalScore += $score;
                break;
            case 'fill':
                if ($correct !== null && $given !== null) {
                    $score = (mb_strtolower(trim((string)$given)) === mb_strtolower(trim((string)$correct)))
                        ? (int)$q['score'] : 0;
                    $totalScore += $score;
                } else {
                    $score = 0;
                }
                break;
            case 'matching':
                // Compare each pair
                if (is_array($correct) && is_array($given)) {
                    $matched = 0;
                    foreach ($correct as $li => $ri) {
                        if (isset($given[$li]) && (string)$given[$li] === (string)$ri) $matched++;
                    }
                    $perPair = count($correct) > 0 ? (int)$q['score'] / count($correct) : 0;
                    $score   = (int)round($matched * $perPair);
                    $totalScore += $score;
                } else {
                    $score = 0;
                }
                break;
            // short/drag: manual
            default:
                $score = null;
        }

        if ($q['student_answer'] !== null) {
            $upd->execute([$score, $seId, $q['id']]);
        } elseif ($score !== null) {
            // Record a 0-score row for an unanswered but auto-gradable question.
            $ins->execute([$seId, $q['id'], null]);
        }
    }

    $db->prepare('UPDATE student_exams SET status="submitted", submitted_at=NOW() WHERE id=?')->execute([$seId]);

    respond([
        'ok'          => true,
        'total_score' => $totalScore,
        'max_score'   => $maxScore,
        'answered'    => count(array_filter($questions, fn($q) => $q['student_answer'] !== null)),
        'total'       => count($questions),
    ]);
}

function examStatus(array $me): never
{
    $seId = (int)($_GET['id'] ?? 0);
    if (!$seId) fail('ไม่ระบุ id');
    $db  = getDB();
    $row = $db->prepare('SELECT se.*, s.time_limit_minutes FROM student_exams se JOIN exam_sessions s ON s.id=se.session_id WHERE se.id=? AND se.student_id=?');
    $row->execute([$seId, $me['id']]);
    $se = $row->fetch();
    if (!$se) fail('ไม่พบ', 404);

    $timeLeft = null;
    if ($se['status'] === 'in_progress' && $se['started_at']) {
        $elapsed  = time() - strtotime($se['started_at']);
        $limit    = (int)$se['time_limit_minutes'] * 60;
        $timeLeft = max(0, $limit - $elapsed);
    }
    respond(['student_exam' => $se, 'time_left' => $timeLeft]);
}
