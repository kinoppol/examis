<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

$me = requireRole('supervisor', 'manager', 'admin');

$action = $_GET['action'] ?? 'students';

match ($action) {
    'my_sessions' => mySessions($me),
    'students'    => students($me),
    default       => fail('Unknown action', 400),
};

function mySessions(array $me): never
{
    $db = getDB();
    if (in_array($me['role'], ['manager','admin'], true)) {
        $rows = $db->query(
            'SELECT s.*, p.title AS paper_title FROM exam_sessions s
             JOIN exam_papers p ON p.id = s.exam_paper_id
             ORDER BY s.exam_date DESC, s.start_time DESC'
        )->fetchAll();
    } else {
        $st = $db->prepare(
            'SELECT s.*, p.title AS paper_title FROM exam_sessions s
             JOIN exam_papers p ON p.id = s.exam_paper_id
             JOIN session_supervisors ss ON ss.session_id = s.id
             WHERE ss.user_id = ?
             ORDER BY s.exam_date DESC, s.start_time DESC'
        );
        $st->execute([$me['id']]);
        $rows = $st->fetchAll();
    }
    respond($rows);
}

function students(array $me): never
{
    $sessionId = (int)($_GET['session_id'] ?? 0);
    if (!$sessionId) fail('ไม่ระบุ session_id');

    $db = getDB();

    // Session info
    $st = $db->prepare(
        'SELECT s.*, p.title AS paper_title,
                (SELECT COUNT(*) FROM questions WHERE exam_paper_id = s.exam_paper_id) AS q_count
         FROM exam_sessions s JOIN exam_papers p ON p.id = s.exam_paper_id WHERE s.id = ?'
    );
    $st->execute([$sessionId]);
    $session = $st->fetch();
    if (!$session) fail('ไม่พบห้องสอบ', 404);

    // Student list
    $stu = $db->prepare(
        'SELECT se.id, se.status, se.started_at, se.submitted_at, se.seat_number,
                u.full_name, u.username,
                (SELECT COUNT(*) FROM student_answers sa WHERE sa.student_exam_id = se.id) AS answered
         FROM student_exams se JOIN users u ON u.id = se.student_id
         WHERE se.session_id = ?
         ORDER BY u.full_name'
    );
    $stu->execute([$sessionId]);
    $students = $stu->fetchAll();

    respond(['session' => $session, 'students' => $students]);
}
