<?php
declare(strict_types=1);
// One-time migration: add PDF-scan exam support to exam_papers.
// DELETE this file from the server after running it.

if (!file_exists(__DIR__ . '/config/.installed')) {
    die('ระบบยังไม่ได้ติดตั้ง กรุณารัน setup.php ก่อน');
}
require_once __DIR__ . '/config/db.php';

$db = getDB();
$results = [];

function run(PDO $db, string $label, string $sql): void {
    global $results;
    try {
        $db->exec($sql);
        $results[] = ['ok', $label];
    } catch (\Exception $e) {
        $results[] = ['skip', $label . ' — ' . $e->getMessage()];
    }
}

run($db, "เพิ่ม column paper_type ใน exam_papers",
    "ALTER TABLE `exam_papers` ADD COLUMN `paper_type` ENUM('builder','pdf') NOT NULL DEFAULT 'builder' AFTER `teacher_id`");

run($db, "เพิ่ม column pdf_path ใน exam_papers",
    "ALTER TABLE `exam_papers` ADD COLUMN `pdf_path` VARCHAR(255) DEFAULT NULL AFTER `paper_type`");

run($db, "เพิ่ม column pdf_choices ใน exam_papers",
    "ALTER TABLE `exam_papers` ADD COLUMN `pdf_choices` TINYINT DEFAULT NULL AFTER `pdf_path`");

// Ensure the upload directory exists
$uploadDir = __DIR__ . '/uploads/exams';
if (!is_dir($uploadDir)) {
    if (@mkdir($uploadDir, 0775, true)) {
        $results[] = ['ok', 'สร้างโฟลเดอร์ uploads/exams'];
    } else {
        $results[] = ['skip', 'สร้างโฟลเดอร์ uploads/exams ไม่สำเร็จ — สร้างเองด้วยสิทธิ์เขียนไฟล์'];
    }
} else {
    $results[] = ['ok', 'มีโฟลเดอร์ uploads/exams อยู่แล้ว'];
}

?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>EXAMIS Migration 3</title>
<style>
  body { font-family: system-ui, sans-serif; background: #0f172a; color: #f1f5f9; padding: 40px; }
  h2 { color: #fca5a5; margin-bottom: 24px; }
  ul { list-style: none; padding: 0; }
  li { padding: 10px 14px; border-radius: 8px; margin-bottom: 8px; font-size: 14px; display: flex; gap: 10px; align-items: center; }
  .ok   { background: #14532d; color: #4ade80; }
  .skip { background: #1e293b; color: #94a3b8; }
  .warn { background: #451a03; color: #fcd34d; padding: 14px; border-radius: 10px; margin-top: 24px; font-weight: 600; }
</style>
</head>
<body>
<h2>EXAMIS — Migration 3 (PDF-Scan Exam Support)</h2>
<ul>
<?php foreach ($results as [$type, $msg]): ?>
  <li class="<?= $type ?>">
    <?= $type === 'ok' ? '✓' : '–' ?>
    <?= htmlspecialchars($msg) ?>
  </li>
<?php endforeach; ?>
</ul>
<p class="warn">⚠️ ลบไฟล์ migrate3.php ออกจาก server หลังจากรันเสร็จแล้ว</p>
</body>
</html>
