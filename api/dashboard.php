<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

requireRole('deputy', 'admin');

$db = getDB();

// Stats
$totalExams   = (int)$db->query('SELECT COUNT(*) FROM exam_sessions')->fetchColumn();
$activeNow    = (int)$db->query('SELECT COUNT(*) FROM exam_sessions WHERE status="active"')->fetchColumn();
$totalStudents= (int)$db->query('SELECT COUNT(*) FROM users WHERE role="student" AND status="active"')->fetchColumn();

$submitted = (int)$db->query('SELECT COUNT(*) FROM student_exams WHERE status="submitted"')->fetchColumn();
$total     = (int)$db->query('SELECT COUNT(*) FROM student_exams')->fetchColumn();
$avgSubmit = $total > 0 ? round($submitted / $total * 100) : 0;

// Today's sessions
$sessions = $db->query(
    'SELECT s.*, p.title AS paper_title,
            (SELECT COUNT(*) FROM student_exams WHERE session_id = s.id) AS enrolled,
            (SELECT COUNT(*) FROM student_exams WHERE session_id = s.id AND status="submitted") AS submitted
     FROM exam_sessions s
     JOIN exam_papers p ON p.id = s.exam_paper_id
     ORDER BY s.exam_date DESC, s.start_time ASC
     LIMIT 20'
)->fetchAll();

respond([
    'stats' => [
        'total_exams'    => $totalExams,
        'active_now'     => $activeNow,
        'total_students' => $totalStudents,
        'avg_submit'     => $avgSubmit,
    ],
    'sessions' => $sessions,
]);
