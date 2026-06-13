<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

// manager can only GET (to browse published papers for session creation)
$me = method() === 'GET'
    ? requireRole('teacher', 'admin', 'manager')
    : requireRole('teacher', 'admin');

match (method()) {
    'GET'    => listExams($me),
    'POST'   => createExam($me),
    'PUT'    => updateExam($me),
    'DELETE' => deleteExam($me),
    default  => fail('Method not allowed', 405),
};

function listExams(array $me): never
{
    $db = getDB();
    if ($me['role'] === 'admin') {
        $rows = $db->query(
            'SELECT p.*, u.full_name AS teacher_name,
                    (SELECT COUNT(*) FROM questions WHERE exam_paper_id = p.id) AS q_count
             FROM exam_papers p JOIN users u ON u.id = p.teacher_id
             ORDER BY p.updated_at DESC'
        )->fetchAll();
    } elseif ($me['role'] === 'manager') {
        // managers see all published papers (with teacher name) to assign to sessions
        $rows = $db->query(
            'SELECT p.*, u.full_name AS teacher_name,
                    (SELECT COUNT(*) FROM questions WHERE exam_paper_id = p.id) AS q_count
             FROM exam_papers p JOIN users u ON u.id = p.teacher_id
             WHERE p.status = "published"
             ORDER BY p.updated_at DESC'
        )->fetchAll();
    } else {
        $st = $db->prepare(
            'SELECT p.*,
                    (SELECT COUNT(*) FROM questions WHERE exam_paper_id = p.id) AS q_count
             FROM exam_papers p WHERE p.teacher_id = ? ORDER BY p.updated_at DESC'
        );
        $st->execute([$me['id']]);
        $rows = $st->fetchAll();
    }
    respond($rows);
}

function createExam(array $me): never
{
    $b     = body();
    $title = trim((string)($b['title'] ?? ''));
    if ($title === '') fail('กรุณาระบุชื่อข้อสอบ');

    $db = getDB();
    $st = $db->prepare(
        'INSERT INTO exam_papers (title, teacher_id, status) VALUES (?, ?, "draft")'
    );
    $st->execute([$title, $me['id']]);
    $id = (int)$db->lastInsertId();

    $row = $db->prepare('SELECT *, 0 AS q_count FROM exam_papers WHERE id = ?');
    $row->execute([$id]);
    respond($row->fetch(), 201);
}

function updateExam(array $me): never
{
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('ไม่ระบุ id');

    $db = getDB();
    // check ownership
    $chk = $db->prepare('SELECT id, teacher_id FROM exam_papers WHERE id = ?');
    $chk->execute([$id]);
    $paper = $chk->fetch();
    if (!$paper) fail('ไม่พบข้อสอบ', 404);
    if ($me['role'] !== 'admin' && $paper['teacher_id'] != $me['id']) fail('Forbidden', 403);

    $b    = body();
    $sets = [];
    $vals = [];

    if (isset($b['title']) && trim((string)$b['title']) !== '') {
        $sets[] = 'title = ?'; $vals[] = trim((string)$b['title']);
    }
    if (isset($b['status']) && in_array($b['status'], ['draft','published'], true)) {
        $sets[] = 'status = ?'; $vals[] = $b['status'];
    }
    if (!$sets) fail('ไม่มีข้อมูลที่จะอัปเดต');

    $vals[] = $id;
    $db->prepare('UPDATE exam_papers SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);

    $row = $db->prepare(
        'SELECT p.*, (SELECT COUNT(*) FROM questions WHERE exam_paper_id = p.id) AS q_count
         FROM exam_papers p WHERE p.id = ?'
    );
    $row->execute([$id]);
    respond($row->fetch());
}

function deleteExam(array $me): never
{
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('ไม่ระบุ id');

    $db = getDB();
    $chk = $db->prepare('SELECT teacher_id FROM exam_papers WHERE id = ?');
    $chk->execute([$id]);
    $paper = $chk->fetch();
    if (!$paper) fail('ไม่พบข้อสอบ', 404);
    if ($me['role'] !== 'admin' && $paper['teacher_id'] != $me['id']) fail('Forbidden', 403);

    $db->prepare('DELETE FROM exam_papers WHERE id = ?')->execute([$id]);
    respond(['ok' => true]);
}
