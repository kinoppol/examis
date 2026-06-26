<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

// Choice labels used for both option storage and the virtual answer sheet.
const PDF_CHOICE_LABELS = ['ก', 'ข', 'ค', 'ง', 'จ', 'ฉ'];

// manager can only GET (to browse published papers for session creation)
$me = method() === 'GET'
    ? requireRole('teacher', 'admin', 'manager')
    : requireRole('teacher', 'admin');

// PDF-scan exam actions (multipart / json, never available to managers)
$action = $_GET['action'] ?? '';
if ($action === 'setup_pdf')    setupPdf($me);
if ($action === 'save_pdf_key') savePdfKey($me);

match (method()) {
    'GET'    => listExams($me),
    'POST'   => createExam($me),
    'PUT'    => updateExam($me),
    'DELETE' => deleteExam($me),
    default  => fail('Method not allowed', 405),
};

function ownPaper(int $id, array $me): array
{
    $db = getDB();
    $st = $db->prepare('SELECT * FROM exam_papers WHERE id = ?');
    $st->execute([$id]);
    $paper = $st->fetch();
    if (!$paper) fail('ไม่พบข้อสอบ', 404);
    if ($me['role'] !== 'admin' && $paper['teacher_id'] != $me['id']) fail('Forbidden', 403);
    return $paper;
}

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
    $type = ($b['paper_type'] ?? 'builder') === 'pdf' ? 'pdf' : 'builder';

    $db = getDB();
    $st = $db->prepare(
        'INSERT INTO exam_papers (title, teacher_id, paper_type, status) VALUES (?, ?, ?, "draft")'
    );
    $st->execute([$title, $me['id'], $type]);
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
    $chk = $db->prepare('SELECT teacher_id, pdf_path FROM exam_papers WHERE id = ?');
    $chk->execute([$id]);
    $paper = $chk->fetch();
    if (!$paper) fail('ไม่พบข้อสอบ', 404);
    if ($me['role'] !== 'admin' && $paper['teacher_id'] != $me['id']) fail('Forbidden', 403);

    $db->prepare('DELETE FROM exam_papers WHERE id = ?')->execute([$id]);
    if (!empty($paper['pdf_path'])) deletePdfFile((string)$paper['pdf_path']);
    respond(['ok' => true]);
}

// Resolve a stored relative pdf_path to an absolute path inside uploads/exams.
function pdfAbsPath(string $rel): string
{
    return __DIR__ . '/../' . $rel;
}

function deletePdfFile(string $rel): void
{
    // Guard against path traversal — only ever touch uploads/exams.
    if (!str_starts_with($rel, 'uploads/exams/')) return;
    $abs = pdfAbsPath($rel);
    if (is_file($abs)) @unlink($abs);
}

// Upload a scanned PDF + (re)generate N machine-gradable MCQ questions.
// Accepts multipart/form-data: exam_paper_id, num_questions, num_choices, pdf (optional on re-setup).
function setupPdf(array $me): never
{
    if (method() !== 'POST') fail('Method not allowed', 405);

    $examId      = (int)($_POST['exam_paper_id'] ?? 0);
    $numQuestions = (int)($_POST['num_questions'] ?? 0);
    $numChoices   = (int)($_POST['num_choices']   ?? 0);
    if (!$examId) fail('ไม่ระบุ exam_paper_id');
    if ($numQuestions < 1 || $numQuestions > 300) fail('จำนวนข้อต้องอยู่ระหว่าง 1–300');
    if ($numChoices < 2 || $numChoices > count(PDF_CHOICE_LABELS)) {
        fail('จำนวนตัวเลือกต้องอยู่ระหว่าง 2–' . count(PDF_CHOICE_LABELS));
    }

    $paper = ownPaper($examId, $me);
    $db    = getDB();

    // Handle the uploaded file (required on first setup; optional when only adjusting counts).
    $pdfPath = $paper['pdf_path'];
    if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['pdf'];
        if ($f['error'] !== UPLOAD_ERR_OK) fail('อัปโหลดไฟล์ไม่สำเร็จ (error ' . $f['error'] . ')');
        if ($f['size'] > 25 * 1024 * 1024) fail('ไฟล์ใหญ่เกินไป (จำกัด 25 MB)');

        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($f['tmp_name']);
        $head  = (string)file_get_contents($f['tmp_name'], false, null, 0, 5);
        if ($ext !== 'pdf' || $mime !== 'application/pdf' || strncmp($head, '%PDF-', 5) !== 0) {
            fail('กรุณาอัปโหลดไฟล์ PDF เท่านั้น');
        }

        $dir = __DIR__ . '/../uploads/exams';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) fail('สร้างโฟลเดอร์อัปโหลดไม่สำเร็จ');

        $newRel = 'uploads/exams/exam_' . $examId . '_' . bin2hex(random_bytes(6)) . '.pdf';
        if (!move_uploaded_file($f['tmp_name'], pdfAbsPath($newRel))) fail('บันทึกไฟล์ไม่สำเร็จ');

        if (!empty($paper['pdf_path']) && $paper['pdf_path'] !== $newRel) deletePdfFile((string)$paper['pdf_path']);
        $pdfPath = $newRel;
    }
    if (empty($pdfPath)) fail('กรุณาอัปโหลดไฟล์ข้อสอบ PDF');

    // Preserve any previously-set answer key by question order (where still valid).
    $prev = $db->prepare('SELECT order_num, correct_answer FROM questions WHERE exam_paper_id = ?');
    $prev->execute([$examId]);
    $keyByOrder = [];
    foreach ($prev->fetchAll() as $r) {
        $ans = $r['correct_answer'] !== null ? json_decode($r['correct_answer'], true) : null;
        if ($ans !== null) $keyByOrder[(int)$r['order_num']] = $ans;
    }

    $options = json_encode(array_slice(PDF_CHOICE_LABELS, 0, $numChoices), JSON_UNESCAPED_UNICODE);
    $valid   = array_slice(PDF_CHOICE_LABELS, 0, $numChoices);

    $db->beginTransaction();
    try {
        $db->prepare('UPDATE exam_papers SET paper_type = "pdf", pdf_path = ?, pdf_choices = ? WHERE id = ?')
           ->execute([$pdfPath, $numChoices, $examId]);
        $db->prepare('DELETE FROM questions WHERE exam_paper_id = ?')->execute([$examId]);

        $ins = $db->prepare(
            'INSERT INTO questions (exam_paper_id, type, question_text, options, correct_answer, score, order_num)
             VALUES (?, "mcq", ?, ?, ?, 1, ?)'
        );
        for ($i = 1; $i <= $numQuestions; $i++) {
            $keep = (isset($keyByOrder[$i]) && in_array($keyByOrder[$i], $valid, true))
                ? json_encode($keyByOrder[$i], JSON_UNESCAPED_UNICODE) : null;
            $ins->execute([$examId, 'ข้อที่ ' . $i, $options, $keep, $i]);
        }
        $db->commit();
    } catch (\Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    $row = $db->prepare(
        'SELECT p.*, (SELECT COUNT(*) FROM questions WHERE exam_paper_id = p.id) AS q_count
         FROM exam_papers p WHERE p.id = ?'
    );
    $row->execute([$examId]);
    respond($row->fetch());
}

// Save the answer key for a PDF exam — body: { exam_paper_id, key: { "<question_id>": "ก"|null } }
function savePdfKey(array $me): never
{
    if (method() !== 'POST') fail('Method not allowed', 405);

    $b      = body();
    $examId = (int)($b['exam_paper_id'] ?? 0);
    $key    = (array)($b['key'] ?? []);
    if (!$examId) fail('ไม่ระบุ exam_paper_id');

    $paper = ownPaper($examId, $me);
    if ($paper['paper_type'] !== 'pdf') fail('ชุดข้อสอบนี้ไม่ใช่แบบ PDF');

    $valid = array_slice(PDF_CHOICE_LABELS, 0, max(2, (int)$paper['pdf_choices']));

    $db  = getDB();
    $own = $db->prepare('SELECT id FROM questions WHERE id = ? AND exam_paper_id = ?');
    $upd = $db->prepare('UPDATE questions SET correct_answer = ? WHERE id = ? AND exam_paper_id = ?');
    $saved = 0;
    foreach ($key as $qid => $label) {
        $qid = (int)$qid;
        $own->execute([$qid, $examId]);
        if (!$own->fetch()) continue;
        $enc = ($label !== null && $label !== '' && in_array($label, $valid, true))
            ? json_encode($label, JSON_UNESCAPED_UNICODE) : null;
        $upd->execute([$enc, $qid, $examId]);
        $saved++;
    }
    respond(['ok' => true, 'saved' => $saved]);
}
