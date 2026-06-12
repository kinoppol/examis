<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';

$user   = api_user(['teacher', 'exam_manager', 'admin']);
$method = req_method();

$statusColors = ['draft' => ['#d97706','#fffbeb'], 'published' => ['#16a34a','#f0fdf4']];
$subjectColors = ['#0f766e','#2563eb','#7c3aed','#0891b2','#d97706','#16a34a'];

function paperRow(array $p, int $idx, array $statusColors, array $subjectColors): array
{
    $sc = $statusColors[$p['status']] ?? $statusColors['draft'];
    $abbr = mb_substr($p['subject'], 0, 3);
    return array_merge($p, [
        'abbr'        => $abbr,
        'color'       => $subjectColors[$idx % count($subjectColors)],
        'statusColor' => $sc[0],
        'statusBg'    => $sc[1],
        'types'       => ['เลือกตอบ'],
    ]);
}

// GET — list papers
if ($method === 'GET') {
    $filter = req_str('status');
    $where  = $user['role'] === 'teacher' ? 'WHERE created_by = ?' : 'WHERE 1=1';
    $params = $user['role'] === 'teacher' ? [$user['id']] : [];
    if ($filter) { $where .= ' AND status = ?'; $params[] = $filter; }

    $rows = Database::all(
        "SELECT p.*, u.full_name AS creator_name,
                (SELECT COUNT(*) FROM questions q WHERE q.paper_id = p.id) AS question_count,
                (SELECT SUM(points) FROM questions q WHERE q.paper_id = p.id) AS total_points,
                DATE_FORMAT(p.updated_at,'%d/%m/%y') AS updated_label
         FROM exam_papers p JOIN users u ON u.id = p.created_by $where ORDER BY p.updated_at DESC",
        $params
    );

    $papers = array_values(array_map(
        fn($p, $i) => paperRow($p, $i, $statusColors, $subjectColors),
        $rows, array_keys($rows)
    ));
    json_ok(['papers' => $papers]);
}

// POST — create paper
if ($method === 'POST' && $user['role'] === 'teacher') {
    $b = req_body();
    if (empty($b['title']) || empty($b['subject']) || empty($b['level'])) {
        json_fail('title, subject, level are required');
    }
    $id = Database::insert('exam_papers', [
        'title'      => $b['title'],
        'subject'    => $b['subject'],
        'level'      => $b['level'],
        'paper_type' => $b['paper_type'] ?? 'digital',
        'created_by' => $user['id'],
        'status'     => 'draft',
    ]);
    json_ok(['id' => $id], 201);
}

// PUT — update status / title
if ($method === 'PUT') {
    $id = req_int('id');
    if (!$id) json_fail('Missing id');
    $b  = req_body();
    $allowed = ['title','subject','level','status'];
    $data    = array_intersect_key($b, array_flip($allowed));
    if (!$data) json_fail('Nothing to update');
    Database::update('exam_papers', $data, 'id = ?', [$id]);
    json_ok();
}

json_fail('Not found', 404);
