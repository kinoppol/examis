<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

$me = requireRole('teacher', 'admin');

$action = $_GET['action'] ?? '';
if ($action === 'import_text') importText($me);
if ($action === 'reorder')     reorderQuestions($me);

match (method()) {
    'GET'    => listQuestions($me),
    'POST'   => createQuestion($me),
    'PUT'    => updateQuestion($me),
    'DELETE' => deleteQuestion($me),
    default  => fail('Method not allowed', 405),
};

function ownerCheck(int $examId, array $me): array
{
    $db = getDB();
    $st = $db->prepare('SELECT id, teacher_id, status FROM exam_papers WHERE id = ?');
    $st->execute([$examId]);
    $paper = $st->fetch();
    if (!$paper) fail('ไม่พบชุดข้อสอบ', 404);
    if ($me['role'] !== 'admin' && $paper['teacher_id'] != $me['id']) fail('Forbidden', 403);
    return $paper;
}

function listQuestions(array $me): never
{
    $examId = (int)($_GET['exam_id'] ?? 0);
    if (!$examId) fail('ไม่ระบุ exam_id');
    ownerCheck($examId, $me);

    $st = getDB()->prepare(
        'SELECT * FROM questions WHERE exam_paper_id = ? ORDER BY order_num, id'
    );
    $st->execute([$examId]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['options']        = $r['options']        ? json_decode($r['options'], true)        : null;
        $r['correct_answer'] = $r['correct_answer'] ? json_decode($r['correct_answer'], true) : null;
    }
    respond($rows);
}

function createQuestion(array $me): never
{
    $b      = body();
    $examId = (int)($b['exam_paper_id'] ?? 0);
    if (!$examId) fail('ไม่ระบุ exam_paper_id');
    ownerCheck($examId, $me);

    $type = $b['type'] ?? 'mcq';
    $allowed = ['mcq','truefalse','fill','matching','short','drag'];
    if (!in_array($type, $allowed, true)) fail('ประเภทข้อสอบไม่ถูกต้อง');

    $text    = trim((string)($b['question_text'] ?? ''));
    $score   = max(1, (int)($b['score'] ?? 1));
    $options = isset($b['options']) ? json_encode($b['options'], JSON_UNESCAPED_UNICODE) : null;
    $correct = isset($b['correct_answer']) ? json_encode($b['correct_answer'], JSON_UNESCAPED_UNICODE) : null;

    $db = getDB();
    $maxOrd = $db->prepare('SELECT COALESCE(MAX(order_num),0)+1 FROM questions WHERE exam_paper_id = ?');
    $maxOrd->execute([$examId]);
    $ord = (int)$maxOrd->fetchColumn();

    $st = $db->prepare(
        'INSERT INTO questions (exam_paper_id, type, question_text, options, correct_answer, score, order_num)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $st->execute([$examId, $type, $text, $options, $correct, $score, $ord]);
    $id = (int)$db->lastInsertId();

    $row = $db->prepare('SELECT * FROM questions WHERE id = ?');
    $row->execute([$id]);
    $q = $row->fetch();
    $q['options']        = $q['options']        ? json_decode($q['options'], true)        : null;
    $q['correct_answer'] = $q['correct_answer'] ? json_decode($q['correct_answer'], true) : null;
    respond($q, 201);
}

function updateQuestion(array $me): never
{
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('ไม่ระบุ id');

    $db = getDB();
    $st = $db->prepare('SELECT * FROM questions WHERE id = ?');
    $st->execute([$id]);
    $q = $st->fetch();
    if (!$q) fail('ไม่พบข้อสอบ', 404);
    ownerCheck((int)$q['exam_paper_id'], $me);

    $b    = body();
    $sets = [];
    $vals = [];

    if (isset($b['question_text'])) { $sets[] = 'question_text = ?'; $vals[] = trim((string)$b['question_text']); }
    if (isset($b['score']))          { $sets[] = 'score = ?';         $vals[] = max(1,(int)$b['score']); }
    if (isset($b['order_num']))      { $sets[] = 'order_num = ?';     $vals[] = (int)$b['order_num']; }
    if (array_key_exists('options', $b)) {
        $sets[] = 'options = ?';
        $vals[] = $b['options'] !== null ? json_encode($b['options'], JSON_UNESCAPED_UNICODE) : null;
    }
    if (array_key_exists('correct_answer', $b)) {
        $sets[] = 'correct_answer = ?';
        $vals[] = $b['correct_answer'] !== null ? json_encode($b['correct_answer'], JSON_UNESCAPED_UNICODE) : null;
    }

    if ($sets) {
        $vals[] = $id;
        $db->prepare('UPDATE questions SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
    }

    $row = $db->prepare('SELECT * FROM questions WHERE id = ?');
    $row->execute([$id]);
    $q = $row->fetch();
    $q['options']        = $q['options']        ? json_decode($q['options'], true)        : null;
    $q['correct_answer'] = $q['correct_answer'] ? json_decode($q['correct_answer'], true) : null;
    respond($q);
}

function deleteQuestion(array $me): never
{
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('ไม่ระบุ id');

    $db = getDB();
    $st = $db->prepare('SELECT exam_paper_id FROM questions WHERE id = ?');
    $st->execute([$id]);
    $q = $st->fetch();
    if (!$q) fail('ไม่พบข้อสอบ', 404);
    ownerCheck((int)$q['exam_paper_id'], $me);

    $db->prepare('DELETE FROM questions WHERE id = ?')->execute([$id]);
    respond(['ok' => true]);
}

function reorderQuestions(array $me): never
{
    $b   = body();
    $ids = array_values(array_filter(array_map('intval', (array)($b['ids'] ?? [])), fn($id) => $id > 0));
    if (empty($ids)) fail('ไม่ระบุ ids');

    $db = getDB();
    $st = $db->prepare('SELECT exam_paper_id FROM questions WHERE id = ?');
    $st->execute([$ids[0]]);
    $q = $st->fetch();
    if (!$q) fail('ไม่พบข้อสอบ', 404);
    ownerCheck((int)$q['exam_paper_id'], $me);

    $upd = $db->prepare('UPDATE questions SET order_num = ? WHERE id = ?');
    foreach ($ids as $i => $id) {
        $upd->execute([$i + 1, $id]);
    }
    respond(['ok' => true]);
}

function importText(array $me): never
{
    $b      = body();
    $examId = (int)($b['exam_paper_id'] ?? 0);
    $text   = trim((string)($b['text'] ?? ''));

    if (!$examId || $text === '') fail('ข้อมูลไม่ครบ');
    ownerCheck($examId, $me);

    $blocks = preg_split('/\n\s*\n/', $text);
    $imported = 0;
    $db = getDB();

    $maxOrd = $db->prepare('SELECT COALESCE(MAX(order_num),0) FROM questions WHERE exam_paper_id = ?');
    $maxOrd->execute([$examId]);
    $ord = (int)$maxOrd->fetchColumn();

    $ins = $db->prepare(
        'INSERT INTO questions (exam_paper_id, type, question_text, options, correct_answer, score, order_num)
         VALUES (?, "mcq", ?, ?, ?, 1, ?)'
    );

    foreach ($blocks as $block) {
        $block = trim($block);
        if ($block === '') continue;
        $lines = explode("\n", $block);
        $qMatch = [];
        if (!preg_match('/^\d+\.\s*(.+)/', $lines[0], $qMatch)) continue;
        $qText = trim($qMatch[1]);
        $opts = [];
        $answer = '';
        for ($i = 1; $i < count($lines); $i++) {
            if (preg_match('/^[กขคงจฉ]\.\s*(.+)/u', trim($lines[$i]), $m)) {
                $opts[] = trim($m[1]);
            }
            if (preg_match('/^เฉลย\s*[:：]\s*(.+)/iu', trim($lines[$i]), $m)) {
                $answer = trim($m[1]);
            }
        }
        if (empty($opts)) continue;

        // Find correct index
        $thaiChars = ['ก','ข','ค','ง','จ','ฉ'];
        $correctIdx = 0;
        foreach ($thaiChars as $ci => $ch) {
            if (mb_strtolower($answer) === mb_strtolower($ch)) { $correctIdx = $ci; break; }
        }

        $ord++;
        $ins->execute([
            $examId,
            $qText,
            json_encode($opts, JSON_UNESCAPED_UNICODE),
            json_encode($correctIdx),
            $ord,
        ]);
        $imported++;
    }

    respond(['imported' => $imported]);
}
