<?php
declare(strict_types=1);
/**
 * examis — Web Installer
 * Visit http://localhost/examis/setup/install.php
 * Delete or restrict this file after installation.
 */

$step   = $_POST['step'] ?? 'form';
$errors = [];
$done   = false;

if ($step === 'run') {
    $host   = trim($_POST['db_host']   ?? 'localhost');
    $port   = (int)($_POST['db_port']  ?? 3306);
    $name   = trim($_POST['db_name']   ?? 'examis');
    $user   = trim($_POST['db_user']   ?? 'root');
    $pass   = $_POST['db_pass']        ?? '';
    $admPw  = $_POST['admin_pass']     ?? 'Admin@1234';

    try {
        // Connect without DB to create it
        $pdo = new PDO(
            "mysql:host=$host;port=$port;charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$name`");

        // Run schema
        $sql = file_get_contents(__DIR__ . '/schema.sql');
        // Strip USE/CREATE DATABASE lines (already done above)
        $sql = preg_replace('/^(CREATE DATABASE|USE)\b[^\n]+\n/im', '', $sql);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt) $pdo->exec($stmt);
        }

        // ── Seed users ──────────────────────────────────────────────────────
        $h = fn(string $p) => password_hash($p, PASSWORD_BCRYPT, ['cost' => 12]);
        $users = [
            ['admin',     $h($admPw),      'แอดมิน ระบบ',              'admin',           null],
            ['wichai.p',  $h('Pass@1234'), 'ดร.วิชัย ปัญญาดี',         'academic_deputy', null],
            ['prasert.j', $h('Pass@1234'), 'อ.ประเสริฐ จัดการ',        'exam_manager',    null],
            ['malee.j',   $h('Pass@1234'), 'อ.มาลี ใจงาม',             'teacher',         null],
            ['chan.s',     $h('Pass@1234'), 'อ.จันทร์ ส่องแสง',         'teacher',         null],
            ['somying.r',  $h('Pass@1234'), 'อ.สมหญิง รักเรียน',       'exam_supervisor', null],
            ['std13045',   $h('Student@1'), 'ด.ช.ธนกฤต ใจดี',           'student',         '13045'],
            ['std13046',   $h('Student@1'), 'ด.ญ.ปาริชาติ บุญมี',       'student',         '13046'],
            ['std13047',   $h('Student@1'), 'ด.ญ.พิมพ์ชนก ดวงแก้ว',    'student',         '13047'],
            ['std13048',   $h('Student@1'), 'ด.ช.มานพ เกียรติยศ',       'student',         '13048'],
            ['std13049',   $h('Student@1'), 'ด.ญ.วริศรา พงษ์ไพร',       'student',         '13049'],
            ['std13050',   $h('Student@1'), 'ด.ญ.กชกร แสงทอง',          'student',         '13050'],
        ];
        $ins = $pdo->prepare(
            'INSERT IGNORE INTO users (username, password_hash, full_name, role, student_code)
             VALUES (?,?,?,?,?)'
        );
        foreach ($users as $u) $ins->execute($u);

        // ── Semester ────────────────────────────────────────────────────────
        $pdo->exec(
            "INSERT IGNORE INTO semesters (id,name,start_date,end_date,is_active) VALUES
             (1,'ภาคเรียนที่ 2/2568','2025-11-01','2026-03-31',1)"
        );

        // ── Exam paper: วิทยาศาสตร์ ม.3 ──────────────────────────────────
        $teacherId = (int)$pdo->query("SELECT id FROM users WHERE username='malee.j'")->fetchColumn();
        $pdo->exec(
            "INSERT IGNORE INTO exam_papers (id,title,subject,level,paper_type,created_by,status)
             VALUES (1,'วิทยาศาสตร์ปลายภาค ม.3','วิทยาศาสตร์','ม.3','digital',$teacherId,'published')"
        );

        // ── Questions (40 MC) ────────────────────────────────────────────────
        $qdata = [
            [1, 'อวัยวะใดทำหน้าที่สูบฉีดเลือดไปเลี้ยงร่างกาย',     ['ปอด','หัวใจ','ตับ','ไต'], 1],
            [2, 'พืชสร้างอาหารด้วยกระบวนการใด',                       ['การหายใจ','การสังเคราะห์ด้วยแสง','การคายน้ำ','การลำเลียง'], 1],
            [3, 'หน่วยพื้นฐานที่เล็กที่สุดของสิ่งมีชีวิตคือข้อใด',  ['เนื้อเยื่อ','อวัยวะ','เซลล์','ระบบ'], 2],
            [4, 'แรงชนิดใดดึงวัตถุเข้าหาศูนย์กลางโลก',              ['แรงเสียดทาน','แรงตึง','แรงโน้มถ่วง','แรงพยุง'], 2],
            [5, 'สถานะใดของสสารมีปริมาตรคงที่แต่รูปร่างเปลี่ยนตามภาชนะ', ['ของแข็ง','ของเหลว','แก๊ส','พลาสมา'], 1],
            [6, 'กระแสไฟฟ้ามีหน่วยเป็นข้อใด',                        ['โวลต์','แอมแปร์','โอห์ม','วัตต์'], 1],
            [7, 'ดาวเคราะห์ดวงใดอยู่ใกล้ดวงอาทิตย์ที่สุด',           ['ศุกร์','โลก','พุธ','อังคาร'], 2],
            [8, 'สารใดเป็นตัวทำละลายสากล',                            ['แอลกอฮอล์','น้ำ','น้ำมัน','อากาศ'], 1],
            [9, 'เสียงเดินทางได้เร็วที่สุดในตัวกลางใด',               ['อากาศ','น้ำ','เหล็ก','สุญญากาศ'], 2],
            [10,'อวัยวะใดเป็นศูนย์กลางควบคุมระบบประสาท',              ['หัวใจ','สมอง','ปอด','ตับ'], 1],
        ];
        // Extend to 40 questions with filler
        $fillerQ = ['คำถามข้อนี้เกี่ยวกับวิทยาศาสตร์', 'ตัวเลือกที่ถูกต้องคือข้อใด'];
        for ($i = 11; $i <= 40; $i++) {
            $qdata[] = [$i, 'คำถามข้อที่ ' . $i . ' วิชาวิทยาศาสตร์ ม.3', ['ตัวเลือก ก','ตัวเลือก ข','ตัวเลือก ค','ตัวเลือก ง'], 0];
        }
        $labels = ['ก','ข','ค','ง'];
        $qIns = $pdo->prepare(
            'INSERT IGNORE INTO questions (paper_id,question_text,order_num,question_type,points)
             VALUES (1,?,?,\'multiple_choice\',1)'
        );
        $cIns = $pdo->prepare(
            'INSERT IGNORE INTO choices (question_id,label,choice_text,is_correct,order_num)
             VALUES (?,?,?,?,?)'
        );
        foreach ($qdata as [$num, $text, $opts, $correctIdx]) {
            $qIns->execute([$text, $num]);
            $qId = (int)$pdo->lastInsertId();
            if (!$qId) {
                $qId = (int)$pdo->query("SELECT id FROM questions WHERE paper_id=1 AND order_num=$num")->fetchColumn();
            }
            foreach ($opts as $oi => $ot) {
                $cIns->execute([$qId, $labels[$oi], $ot, ($oi === $correctIdx) ? 1 : 0, $oi + 1]);
            }
        }

        // ── Supervisor ──────────────────────────────────────────────────────
        $supId = (int)$pdo->query("SELECT id FROM users WHERE username='somying.r'")->fetchColumn();
        $mgId  = (int)$pdo->query("SELECT id FROM users WHERE username='prasert.j'")->fetchColumn();

        // ── Exam sessions ────────────────────────────────────────────────────
        $pdo->exec("INSERT IGNORE INTO exam_sessions
            (id,semester_id,paper_id,room_code,supervisor_id,scheduled_date,
             start_time,end_time,duration_minutes,pin_code,status,created_by)
            VALUES
            (1,1,1,'304',$supId,'2026-03-18','09:00:00','10:15:00',75,'482715','active',$mgId)");

        // ── Enroll students ──────────────────────────────────────────────────
        $students = $pdo->query("SELECT id FROM users WHERE role='student'")->fetchAll(PDO::FETCH_COLUMN);
        $eIns = $pdo->prepare(
            'INSERT IGNORE INTO session_enrollments (session_id,student_id,seat_number) VALUES (1,?,?)'
        );
        foreach ($students as $si => $sid) {
            $eIns->execute([$sid, $si + 1]);
        }
        // Mark first 3 as checked in
        $pdo->exec("UPDATE session_enrollments SET checked_in_at=NOW()
                    WHERE session_id=1 AND seat_number <= 3");

        // ── Late requests ─────────────────────────────────────────────────────
        $s4 = (int)$pdo->query("SELECT id FROM users WHERE username='std13048'")->fetchColumn();
        if ($s4) {
            $pdo->exec("INSERT IGNORE INTO late_requests (session_id,student_id,reason,status)
                        VALUES (1,$s4,'ป่วย (มีใบรับรองแพทย์)','pending')");
        }

        $done = true;

        // Write config hint
        $configHint = "<?php\n// Auto-generated by installer — copy to config/app.php\n"
            . "define('DB_HOST', '$host');\n"
            . "define('DB_PORT', $port);\n"
            . "define('DB_NAME', '$name');\n"
            . "define('DB_USER', '$user');\n"
            . "define('DB_PASS', '$pass');\n";
        @file_put_contents(__DIR__ . '/db_config_hint.php', $configHint);

    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . htmlspecialchars($e->getMessage());
    } catch (Throwable $e) {
        $errors[] = 'Error: ' . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>examis — ติดตั้งระบบ</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
  *{box-sizing:border-box}body{margin:0;font-family:'Sarabun',system-ui,sans-serif;background:#f4f7f7;color:#0f172a;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px}
  .card{background:#fff;border:1px solid #e6edec;border-radius:20px;padding:36px 32px;max-width:500px;width:100%;box-shadow:0 4px 24px -8px rgba(15,118,110,.15)}
  .logo{display:flex;align-items:center;gap:12px;margin-bottom:28px}
  .logo-icon{width:44px;height:44px;border-radius:13px;background:linear-gradient(150deg,#0f766e,#134e4a);display:flex;align-items:center;justify-content:center}
  h1{margin:0;font-size:22px;font-weight:800;letter-spacing:-.4px}
  .sub{font-size:13px;color:#5b7572;margin-top:-2px}
  label{display:block;font-size:13px;font-weight:600;color:#334155;margin:14px 0 6px}
  input{width:100%;padding:11px 14px;border:1px solid #e2e8f0;border-radius:11px;font-size:15px;font-family:inherit;outline:none}
  input:focus{border-color:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.12)}
  .btn{width:100%;margin-top:22px;padding:14px;border-radius:12px;border:none;background:#0f766e;color:#fff;font-size:16px;font-weight:700;cursor:pointer;font-family:inherit}
  .err{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px}
  .success{text-align:center}.check{width:72px;height:72px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;margin:0 auto 20px}
  h2{margin:0 0 10px;font-size:22px;font-weight:800}p{color:#64748b;font-size:14px;line-height:1.6;margin:0}
  .accounts{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin:18px 0;text-align:left}
  .accounts table{width:100%;border-collapse:collapse;font-size:13px}
  .accounts td{padding:4px 8px}.accounts td:first-child{color:#64748b;white-space:nowrap}
  .back{display:inline-flex;align-items:center;gap:6px;color:#0f766e;font-weight:700;text-decoration:none;font-size:15px;margin-top:20px}
  .section{font-size:11px;font-weight:700;color:#94a3b8;letter-spacing:.5px;margin:18px 0 4px;text-transform:uppercase}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="logo-icon">
      <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#99f6e4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15H6.5A2.5 2.5 0 0 0 4 20.5z"/><path d="M9 8h7M9 12h5"/></svg>
    </div>
    <div><h1>examis</h1><div class="sub">ติดตั้งระบบจัดการสอบปลายภาค</div></div>
  </div>

<?php if ($done): ?>
  <div class="success">
    <div class="check">
      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5L20 7"/></svg>
    </div>
    <h2>ติดตั้งเสร็จสมบูรณ์</h2>
    <p>สร้างฐานข้อมูล ตาราง และข้อมูลตัวอย่างเรียบร้อย</p>
    <div class="accounts">
      <div class="section">บัญชีผู้ใช้ตัวอย่าง</div>
      <table>
        <tr><td>Admin</td><td><b>admin</b> / <?= htmlspecialchars($_POST['admin_pass'] ?? 'Admin@1234') ?></td></tr>
        <tr><td>รองฯ วิชาการ</td><td><b>wichai.p</b> / Pass@1234</td></tr>
        <tr><td>ผู้จัดการสอบ</td><td><b>prasert.j</b> / Pass@1234</td></tr>
        <tr><td>ครู</td><td><b>malee.j</b> / Pass@1234</td></tr>
        <tr><td>ผู้คุมสอบ</td><td><b>somying.r</b> / Pass@1234</td></tr>
        <tr><td>นักเรียน</td><td><b>std13045</b> – std13050 / Student@1</td></tr>
      </table>
    </div>
    <a href="/examis/login.php" class="back">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
      เข้าสู่ระบบ
    </a>
  </div>

<?php else: ?>
  <?php foreach ($errors as $err): ?>
    <div class="err"><?= $err ?></div>
  <?php endforeach; ?>

  <form method="post">
    <input type="hidden" name="step" value="run">
    <div class="section">การเชื่อมต่อฐานข้อมูล</div>
    <label>Database Host</label>
    <input name="db_host" value="localhost" required>
    <label>Port</label>
    <input name="db_port" type="number" value="3306" required>
    <label>Database Name</label>
    <input name="db_name" value="examis" required>
    <label>Username</label>
    <input name="db_user" value="root" required>
    <label>Password</label>
    <input name="db_pass" type="password" value="" placeholder="(ว่างสำหรับ XAMPP ค่าเริ่มต้น)">
    <div class="section">บัญชี Admin เริ่มต้น</div>
    <label>รหัสผ่าน Admin</label>
    <input name="admin_pass" type="password" value="Admin@1234" required>
    <button class="btn" type="submit">ติดตั้งระบบ</button>
  </form>
<?php endif; ?>
</div>
</body>
</html>
