<?php
declare(strict_types=1);
// One-time migration: add buildings, exam_rooms, and room_id column to exam_sessions.
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

run($db, 'สร้างตาราง buildings', "
    CREATE TABLE IF NOT EXISTS `buildings` (
        `id`          INT NOT NULL AUTO_INCREMENT,
        `name`        VARCHAR(100) NOT NULL,
        `description` VARCHAR(255) DEFAULT NULL,
        `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

run($db, 'สร้างตาราง exam_rooms', "
    CREATE TABLE IF NOT EXISTS `exam_rooms` (
        `id`          INT NOT NULL AUTO_INCREMENT,
        `building_id` INT NOT NULL,
        `room_code`   VARCHAR(50) NOT NULL,
        `capacity`    INT NOT NULL DEFAULT 30,
        `description` VARCHAR(255) DEFAULT NULL,
        `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `fk_room_building` (`building_id`),
        CONSTRAINT `fk_room_building` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

run($db, 'เพิ่ม column room_id ใน exam_sessions',
    "ALTER TABLE `exam_sessions` ADD COLUMN `room_id` INT NULL AFTER `room`");

run($db, 'เพิ่ม foreign key fk_sess_room',
    "ALTER TABLE `exam_sessions` ADD CONSTRAINT `fk_sess_room` FOREIGN KEY (`room_id`) REFERENCES `exam_rooms`(`id`) ON DELETE SET NULL");

?><!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>EXAMIS Migration</title>
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
<h2>EXAMIS — Migration</h2>
<ul>
<?php foreach ($results as [$type, $msg]): ?>
  <li class="<?= $type ?>">
    <?= $type === 'ok' ? '✓' : '–' ?>
    <?= htmlspecialchars($msg) ?>
  </li>
<?php endforeach; ?>
</ul>
<p class="warn">⚠️ ลบไฟล์ migrate.php ออกจาก server หลังจากรันเสร็จแล้ว</p>
</body>
</html>
