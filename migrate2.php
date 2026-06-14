<?php
declare(strict_types=1);
// One-time migration: add settings table and semester column to exam_sessions.
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

run($db, 'สร้างตาราง settings', "
    CREATE TABLE IF NOT EXISTS `settings` (
        `key`        VARCHAR(100) NOT NULL,
        `value`      TEXT NOT NULL,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

run($db, 'เพิ่มค่าเริ่มต้น summer_term_enabled',
    "INSERT INTO `settings` (`key`, `value`) VALUES ('summer_term_enabled', '0')
     ON DUPLICATE KEY UPDATE `key` = `key`");

run($db, 'เพิ่ม column semester ใน exam_sessions',
    "ALTER TABLE `exam_sessions` ADD COLUMN `semester` VARCHAR(20) NULL AFTER `room_id`");

?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>EXAMIS Migration 2</title>
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
<h2>EXAMIS — Migration 2 (Semester Support)</h2>
<ul>
<?php foreach ($results as [$type, $msg]): ?>
  <li class="<?= $type ?>">
    <?= $type === 'ok' ? '✓' : '–' ?>
    <?= htmlspecialchars($msg) ?>
  </li>
<?php endforeach; ?>
</ul>
<p class="warn">⚠️ ลบไฟล์ migrate2.php ออกจาก server หลังจากรันเสร็จแล้ว</p>
</body>
</html>
