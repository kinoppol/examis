<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>EXAMIS — ติดตั้งระบบ</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Noto Sans Thai',system-ui,sans-serif;background:linear-gradient(145deg,#3D0A0A,#7B1C1C);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:white;border-radius:20px;padding:40px;max-width:540px;width:100%;box-shadow:0 24px 80px rgba(0,0,0,0.4)}
h1{font-size:28px;font-weight:700;color:#7B1C1C;letter-spacing:3px;font-family:'IBM Plex Mono',monospace;margin-bottom:4px}
.sub{color:#6B7280;font-size:14px;margin-bottom:28px}
label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:5px}
input{width:100%;padding:10px 14px;border:1.5px solid #E5E7EB;border-radius:8px;font-size:15px;outline:none;font-family:inherit;margin-bottom:14px}
input:focus{border-color:#7B1C1C;box-shadow:0 0 0 3px rgba(123,28,28,0.1)}
button{width:100%;padding:13px;background:#7B1C1C;color:white;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;margin-top:4px}
button:hover{background:#5A1010}
.log{margin-top:24px;background:#F9FAFB;border-radius:10px;padding:16px;font-size:13px;line-height:1.9}
.ok{color:#16A34A} .err{color:#DC2626} .info{color:#374151}
.done-box{background:#F0FDF4;border:1px solid #BBF7D0;border-radius:12px;padding:20px;margin-top:20px;text-align:center}
.done-box a{display:inline-block;margin-top:12px;padding:12px 28px;background:#7B1C1C;color:white;border-radius:8px;text-decoration:none;font-weight:600;font-size:15px}
.done-box a:hover{background:#5A1010}
</style>
</head>
<body>
<div class="card">
  <h1>EXAMIS</h1>
  <div class="sub">ติดตั้งระบบจัดการสอบปลายภาคออนไลน์ — วิทยาลัยอาชีวศึกษาร้อยเอ็ด</div>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host   = trim($_POST['host']   ?? 'localhost');
    $dbname = trim($_POST['dbname'] ?? 'examis');
    $user   = trim($_POST['user']   ?? 'root');
    $pass   = trim($_POST['pass']   ?? '');

    echo '<div class="log">';

    function step(string $msg, bool $ok = true): void {
        $cls = $ok ? 'ok' : 'err';
        $icon = $ok ? '✓' : '✗';
        echo "<div class=\"{$cls}\">{$icon} {$msg}</div>\n";
        flush();
        ob_flush();
    }
    function info(string $msg): void {
        echo "<div class=\"info\">  → {$msg}</div>\n";
        flush(); ob_flush();
    }

    try {
        // 1. Connect without database
        $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        step("เชื่อมต่อ MariaDB/MySQL สำเร็จ ($host)");

        // 2. Create (or recreate) database
        $fresh = !empty($_POST['fresh']);
        if ($fresh) {
            $pdo->exec("DROP DATABASE IF EXISTS `{$dbname}`");
        }
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbname}`");
        step("สร้างฐานข้อมูล `{$dbname}` สำเร็จ" . ($fresh ? ' (สร้างใหม่ทั้งหมด)' : ''));

        // 3. Create tables (execute each individually with FK checks disabled)
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
            `id`          INT          NOT NULL AUTO_INCREMENT,
            `username`    VARCHAR(50)  NOT NULL,
            `password`    VARCHAR(255) NOT NULL,
            `full_name`   VARCHAR(150) NOT NULL,
            `role`        ENUM('admin','deputy','manager','teacher','supervisor','student') NOT NULL,
            `department`  VARCHAR(100) DEFAULT NULL,
            `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
            `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `exam_papers` (
            `id`          INT          NOT NULL AUTO_INCREMENT,
            `title`       VARCHAR(255) NOT NULL,
            `teacher_id`  INT          NOT NULL,
            `status`      ENUM('draft','published') NOT NULL DEFAULT 'draft',
            `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `fk_paper_teacher` (`teacher_id`),
            CONSTRAINT `fk_paper_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `questions` (
            `id`             INT  NOT NULL AUTO_INCREMENT,
            `exam_paper_id`  INT  NOT NULL,
            `type`           ENUM('mcq','truefalse','fill','matching','short','drag') NOT NULL DEFAULT 'mcq',
            `question_text`  TEXT NOT NULL,
            `options`        LONGTEXT DEFAULT NULL,
            `correct_answer` LONGTEXT DEFAULT NULL,
            `score`          INT  NOT NULL DEFAULT 1,
            `order_num`      INT  NOT NULL DEFAULT 0,
            `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `fk_q_paper` (`exam_paper_id`),
            CONSTRAINT `fk_q_paper` FOREIGN KEY (`exam_paper_id`) REFERENCES `exam_papers` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `exam_sessions` (
            `id`                 INT         NOT NULL AUTO_INCREMENT,
            `exam_paper_id`      INT         NOT NULL,
            `room`               VARCHAR(50) NOT NULL,
            `exam_date`          DATE        NOT NULL,
            `start_time`         TIME        NOT NULL,
            `end_time`           TIME        NOT NULL,
            `access_code`        VARCHAR(20) NOT NULL,
            `time_limit_minutes` INT         NOT NULL DEFAULT 90,
            `status`             ENUM('upcoming','active','done') NOT NULL DEFAULT 'upcoming',
            `manager_id`         INT         NOT NULL,
            `created_at`         TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_access_code` (`access_code`),
            KEY `fk_sess_paper` (`exam_paper_id`),
            KEY `fk_sess_manager` (`manager_id`),
            CONSTRAINT `fk_sess_paper`   FOREIGN KEY (`exam_paper_id`) REFERENCES `exam_papers` (`id`),
            CONSTRAINT `fk_sess_manager` FOREIGN KEY (`manager_id`)    REFERENCES `users` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `session_supervisors` (
            `session_id` INT NOT NULL,
            `user_id`    INT NOT NULL,
            PRIMARY KEY (`session_id`, `user_id`),
            KEY `fk_sv_user` (`user_id`),
            CONSTRAINT `fk_sv_session` FOREIGN KEY (`session_id`) REFERENCES `exam_sessions` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_sv_user`    FOREIGN KEY (`user_id`)    REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `student_exams` (
            `id`           INT NOT NULL AUTO_INCREMENT,
            `session_id`   INT NOT NULL,
            `student_id`   INT NOT NULL,
            `status`       ENUM('not_started','in_progress','submitted') NOT NULL DEFAULT 'not_started',
            `seat_number`  INT     DEFAULT NULL,
            `started_at`   TIMESTAMP NULL DEFAULT NULL,
            `submitted_at` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_student_session` (`session_id`, `student_id`),
            KEY `fk_se_student` (`student_id`),
            CONSTRAINT `fk_se_session` FOREIGN KEY (`session_id`) REFERENCES `exam_sessions` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_se_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `student_answers` (
            `id`              INT NOT NULL AUTO_INCREMENT,
            `student_exam_id` INT NOT NULL,
            `question_id`     INT NOT NULL,
            `answer`          LONGTEXT DEFAULT NULL,
            `score`           DECIMAL(6,2) DEFAULT NULL,
            `graded_by`       INT DEFAULT NULL,
            `graded_at`       TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_exam_question` (`student_exam_id`, `question_id`),
            KEY `fk_ans_question` (`question_id`),
            KEY `fk_ans_grader`   (`graded_by`),
            CONSTRAINT `fk_ans_se`       FOREIGN KEY (`student_exam_id`) REFERENCES `student_exams` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_ans_question` FOREIGN KEY (`question_id`)     REFERENCES `questions` (`id`),
            CONSTRAINT `fk_ans_grader`   FOREIGN KEY (`graded_by`)       REFERENCES `users` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        step("สร้างตารางฐานข้อมูลทั้งหมดสำเร็จ (7 ตาราง)");

        // 4. Seed users
        $seedUsers = [
            ['admin',      'admin',      'ผู้ดูแลระบบ',            'admin',      'ฝ่ายเทคโนโลยี'],
            ['deputy',     'deputy',     'รอง ผอ.ฝ่ายวิชาการ',     'deputy',     'ฝ่ายบริหาร'],
            ['manager',    'manager',    'นายสมชาย ใจดี',           'manager',    'ฝ่ายวิชาการ'],
            ['teacher',    'teacher',    'อ.สมหญิง รักเรียน',       'teacher',    'แผนกคอมพิวเตอร์'],
            ['supervisor', 'supervisor', 'อ.สมศักดิ์ ตั้งใจ',      'supervisor', 'แผนกอิเล็กทรอนิกส์'],
            ['student',    'student',    'นายอภิสิทธิ์ ดีเลิศ',    'student',    'ปวช.2/1'],
            ['student02',  'student02',  'น.ส.สุดา ใฝ่รู้',         'student',    'ปวช.2/1'],
            ['student03',  'student03',  'นายวิทยา เรืองปัญญา',    'student',    'ปวช.2/2'],
        ];
        $chk = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $ins = $pdo->prepare('INSERT INTO users (username,password,full_name,role,department) VALUES (?,?,?,?,?)');
        $seeded = 0;
        foreach ($seedUsers as [$uname,$pass2,$fname,$role,$dept]) {
            $chk->execute([$uname]);
            if (!$chk->fetch()) {
                $ins->execute([$uname, password_hash($pass2, PASSWORD_BCRYPT), $fname, $role, $dept]);
                $seeded++;
            }
        }
        step("เพิ่มข้อมูลผู้ใช้ตัวอย่าง {$seeded} คน");

        // 5. Seed sample exam paper + questions
        $chkPaper = $pdo->prepare("SELECT id FROM exam_papers WHERE title LIKE '%ตัวอย่าง%' LIMIT 1");
        $chkPaper->execute();
        if (!$chkPaper->fetch()) {
            $teacherSt = $pdo->prepare("SELECT id FROM users WHERE username='teacher'");
            $teacherSt->execute();
            $teacherId = (int)$teacherSt->fetchColumn();

            $pdo->prepare("INSERT INTO exam_papers (title,teacher_id,status) VALUES (?,?,'published')")
                ->execute(['คอมพิวเตอร์และเทคโนโลยีสารสนเทศ — ตัวอย่าง', $teacherId]);
            $paperId = (int)$pdo->lastInsertId();

            $sampleQ = [
                ['mcq',       'ข้อใดต่อไปนี้เป็น "ภาษาโปรแกรมเชิงวัตถุ" (OOP)?',
                 json_encode(['HTML','CSS','Java','SQL']), json_encode(2), 2, 1],
                ['truefalse', 'Windows 11 เป็นผลิตภัณฑ์ของบริษัท Apple Inc.',
                 json_encode(['ถูกต้อง','ไม่ถูกต้อง']), json_encode(1), 1, 2],
                ['fill',      'ภาษาโปรแกรมที่ใช้พัฒนาเว็บฝั่ง Server เช่น ________ และ Python',
                 null, json_encode('PHP'), 2, 3],
                ['matching',  'จงจับคู่คำศัพท์คอมพิวเตอร์กับความหมาย',
                 json_encode(['left'=>['CPU','RAM','HDD','GPU'],'right'=>['หน่วยประมวลผลกลาง','หน่วยความจำชั่วคราว','อุปกรณ์เก็บข้อมูล','ชิปประมวลผลกราฟิก']]),
                 json_encode(['0'=>0,'1'=>1,'2'=>2,'3'=>3]), 4, 4],
                ['short',     'จงอธิบายความสำคัญของระบบฐานข้อมูล (Database) ในองค์กรยุคดิจิทัล',
                 null, json_encode('ฐานข้อมูลช่วยในการจัดเก็บ ค้นหา และจัดการข้อมูลขนาดใหญ่ได้อย่างมีประสิทธิภาพ'), 5, 5],
            ];
            $qIns = $pdo->prepare('INSERT INTO questions (exam_paper_id,type,question_text,options,correct_answer,score,order_num) VALUES (?,?,?,?,?,?,?)');
            foreach ($sampleQ as $q) {
                $qIns->execute(array_merge([$paperId], $q));
            }
            info("สร้างชุดข้อสอบตัวอย่าง (5 ข้อ) พร้อม MCQ/T-F/เติมคำ/จับคู่/อัตนัย");

            // Seed session
            $pdo->prepare(
                "INSERT INTO exam_sessions (exam_paper_id,room,exam_date,start_time,end_time,access_code,time_limit_minutes,status,manager_id)
                 VALUES (?,?,CURDATE(),'09:00:00','11:00:00','EX-2024',90,'active',(SELECT id FROM users WHERE username='manager'))"
            )->execute([$paperId, 'ห้อง C201']);
            info("สร้างห้องสอบตัวอย่าง ห้อง C201 รหัส EX-2024");
        } else {
            info("พบข้อมูลตัวอย่างอยู่แล้ว ข้ามการสร้างซ้ำ");
        }

        step("บันทึกข้อมูลตัวอย่างสำเร็จ");

        // 6. Write config
        $configContent = <<<PHP
        <?php
        declare(strict_types=1);

        const DB_HOST    = '{$host}';
        const DB_NAME    = '{$dbname}';
        const DB_USER    = '{$user}';
        const DB_PASS    = '{$pass}';
        const DB_CHARSET = 'utf8mb4';

        function getDB(): PDO
        {
            static \$pdo;
            if (!isset(\$pdo)) {
                \$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
                \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            }
            return \$pdo;
        }
        PHP;
        file_put_contents(__DIR__ . '/config/db.php', $configContent);
        file_put_contents(__DIR__ . '/config/.installed', date('Y-m-d H:i:s'));
        step("บันทึกการตั้งค่าฐานข้อมูลสำเร็จ");

        echo '</div>';
        echo '<div class="done-box">';
        echo '<div style="font-size:18px;font-weight:700;color:#15803D;">✓ ติดตั้งระบบสำเร็จ!</div>';
        echo '<div style="margin-top:8px;font-size:14px;color:#374151;">บัญชีตัวอย่าง: admin / deputy / manager / teacher / supervisor / student (รหัสผ่าน = username)</div>';
        echo '<a href="index.php">เข้าสู่ระบบ EXAMIS →</a>';
        echo '</div>';

    } catch (Exception $e) {
        step("เกิดข้อผิดพลาด: " . htmlspecialchars($e->getMessage()), false);
        echo '</div>';
    }
} else {
?>
  <form method="POST">
    <label>Host</label>
    <input name="host" value="localhost" required>
    <label>ชื่อฐานข้อมูล</label>
    <input name="dbname" value="examis" required>
    <label>ชื่อผู้ใช้ MySQL</label>
    <input name="user" value="root" required>
    <label>รหัสผ่าน MySQL</label>
    <input name="pass" type="password" placeholder="(ว่างถ้าไม่มีรหัสผ่าน)">
    <label style="display:flex;align-items:center;gap:8px;margin-bottom:14px;font-weight:400;color:#374151;cursor:pointer;">
      <input type="checkbox" name="fresh" value="1" style="width:auto;margin:0;"> ลบและสร้างฐานข้อมูลใหม่ทั้งหมด (ใช้เมื่อติดตั้งซ้ำ)
    </label>
    <button type="submit">ติดตั้งระบบ</button>
  </form>
  <div style="margin-top:20px;padding:14px;background:#FEF9C3;border-radius:8px;font-size:13px;color:#854D0E;">
    ⚠️ หน้านี้จะสร้างฐานข้อมูลและตารางทั้งหมดใหม่ รันเพียงครั้งเดียว หลังจากติดตั้งแล้วสามารถลบหรือเพิ่มความปลอดภัยให้ไฟล์นี้ได้
  </div>
<?php } ?>
</div>
</body>
</html>
