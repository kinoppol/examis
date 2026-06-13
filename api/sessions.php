<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

$me = requireRole('manager', 'admin');

match (method()) {
    'GET'    => listSessions($me),
    'POST'   => createSession($me),
    'PUT'    => updateSession($me),
    'DELETE' => deleteSession($me),
    default  => fail('Method not allowed', 405),
};

function generateCode(): string
{
    $db = getDB();
    do {
        $code = 'EX-' . strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 4));
        $st = $db->prepare('SELECT id FROM exam_sessions WHERE access_code = ?');
        $st->execute([$code]);
    } while ($st->fetch());
    return $code;
}

function listSessions(array $me): never
{
    $db = getDB();
    $rows = $db->query(
        'SELECT s.*, p.title AS paper_title, p.status AS paper_status,
                u.full_name AS manager_name,
                (SELECT COUNT(*) FROM student_exams WHERE session_id = s.id) AS enrolled,
                (SELECT COUNT(*) FROM student_exams WHERE session_id = s.id AND status = "submitted") AS submitted
         FROM exam_sessions s
         JOIN exam_papers p ON p.id = s.exam_paper_id
         JOIN users u ON u.id = s.manager_id
         ORDER BY s.exam_date DESC, s.start_time DESC'
    )->fetchAll();
    respond($rows);
}

function createSession(array $me): never
{
    $b        = body();
    $paperId  = (int)($b['exam_paper_id'] ?? 0);
    $room     = trim((string)($b['room']       ?? ''));
    $date     = trim((string)($b['exam_date']  ?? ''));
    $start    = trim((string)($b['start_time'] ?? ''));
    $end      = trim((string)($b['end_time']   ?? ''));
    $limitMin = max(1, (int)($b['time_limit_minutes'] ?? 90));

    if (!$paperId || !$room || !$date || !$start || !$end) {
        fail('ข้อมูลไม่ครบถ้วน');
    }

    $db   = getDB();
    $code = generateCode();

    $st = $db->prepare(
        'INSERT INTO exam_sessions
         (exam_paper_id, room, exam_date, start_time, end_time, access_code, time_limit_minutes, status, manager_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, "upcoming", ?)'
    );
    $st->execute([$paperId, $room, $date, $start, $end, $code, $limitMin, $me['id']]);
    $id = (int)$db->lastInsertId();

    $row = $db->prepare(
        'SELECT s.*, p.title AS paper_title FROM exam_sessions s
         JOIN exam_papers p ON p.id = s.exam_paper_id WHERE s.id = ?'
    );
    $row->execute([$id]);
    respond($row->fetch(), 201);
}

function updateSession(array $me): never
{
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('ไม่ระบุ id');

    $b    = body();
    $sets = [];
    $vals = [];

    if (isset($b['exam_paper_id']) && (int)$b['exam_paper_id'] > 0) {
        $sets[] = 'exam_paper_id = ?'; $vals[] = (int)$b['exam_paper_id'];
    }
    foreach (['room', 'exam_date', 'start_time', 'end_time'] as $f) {
        if (isset($b[$f]) && trim((string)$b[$f]) !== '') {
            $sets[] = "$f = ?"; $vals[] = trim((string)$b[$f]);
        }
    }
    if (isset($b['time_limit_minutes'])) {
        $sets[] = 'time_limit_minutes = ?'; $vals[] = max(1,(int)$b['time_limit_minutes']);
    }
    $statuses = ['upcoming','active','done'];
    if (isset($b['status']) && in_array($b['status'], $statuses, true)) {
        $sets[] = 'status = ?'; $vals[] = $b['status'];
    }

    if ($sets) {
        $vals[] = $id;
        getDB()->prepare('UPDATE exam_sessions SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    }

    $row = getDB()->prepare(
        'SELECT s.*, p.title AS paper_title,
                (SELECT COUNT(*) FROM student_exams WHERE session_id = s.id) AS enrolled,
                (SELECT COUNT(*) FROM student_exams WHERE session_id = s.id AND status = "submitted") AS submitted
         FROM exam_sessions s JOIN exam_papers p ON p.id = s.exam_paper_id WHERE s.id = ?'
    );
    $row->execute([$id]);
    respond($row->fetch() ?: []);
}

function deleteSession(array $me): never
{
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('ไม่ระบุ id');
    getDB()->prepare('DELETE FROM exam_sessions WHERE id = ?')->execute([$id]);
    respond(['ok' => true]);
}
