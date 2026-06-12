<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';

$user = api_user(['academic_deputy','admin']);
if (req_method() !== 'GET') json_fail('Method not allowed', 405);

// ── Stats ─────────────────────────────────────────────────────────────────────
$activeSessions = (int)Database::value("SELECT COUNT(*) FROM exam_sessions WHERE status='active'");
$activeStudents = (int)Database::value(
    "SELECT COUNT(*) FROM session_enrollments se
     JOIN exam_sessions es ON es.id = se.session_id
     WHERE es.status = 'active' AND se.checked_in_at IS NOT NULL"
);
$avgProgress = 0;
if ($activeSessions > 0) {
    $avgProgress = (int)Database::value(
        "SELECT AVG(pct) FROM (
           SELECT es.id,
             IFNULL(submitted/(enrolled)*100,0) AS pct
           FROM exam_sessions es
           LEFT JOIN (SELECT session_id, COUNT(*) AS submitted FROM exam_submissions GROUP BY session_id) sub ON sub.session_id=es.id
           LEFT JOIN (SELECT session_id, COUNT(*) AS enrolled FROM session_enrollments GROUP BY session_id) enr ON enr.session_id=es.id
           WHERE es.status='active'
         ) t"
    );
}
$pendingPerms = (int)Database::value("SELECT COUNT(*) FROM late_requests WHERE status='pending'");

$stats = [
    ['value' => (string)$activeSessions, 'label' => 'ห้องที่กำลังสอบ',       'delta' => "จาก " . (int)Database::value("SELECT COUNT(*) FROM exam_sessions WHERE status IN('ready','active')") . " ห้อง", 'deltaColor' => '#64748b', 'iconKey' => 'door',  'iconColor' => '#0891b2', 'iconBg' => '#ecfeff'],
    ['value' => (string)$activeStudents, 'label' => 'นักเรียนกำลังสอบ',      'delta' => '+0 เข้าใหม่',   'deltaColor' => '#16a34a', 'iconKey' => 'users', 'iconColor' => '#0f766e', 'iconBg' => '#f0fdfa'],
    ['value' => $avgProgress . '%',      'label' => 'ความคืบหน้าเฉลี่ย',     'delta' => 'ตามแผน',        'deltaColor' => '#16a34a', 'iconKey' => 'check', 'iconColor' => '#2563eb', 'iconBg' => '#eff6ff'],
    ['value' => (string)$pendingPerms,   'label' => 'คำขอสอบนอกเวลา',       'delta' => 'รอพิจารณา',     'deltaColor' => '#d97706', 'iconKey' => 'clock', 'iconColor' => '#d97706', 'iconBg' => '#fffbeb'],
];

// ── Live exams ────────────────────────────────────────────────────────────────
$palette = ['#0f766e','#2563eb','#7c3aed','#0891b2','#d97706','#16a34a'];
$liveRows = Database::all(
    "SELECT es.id, es.room_code,
            ep.subject, ep.level,
            u.full_name AS supervisor_name,
            (SELECT COUNT(*) FROM session_enrollments se WHERE se.session_id=es.id) AS total,
            (SELECT COUNT(*) FROM exam_submissions sub WHERE sub.session_id=es.id) AS submitted
     FROM exam_sessions es
     JOIN exam_papers ep ON ep.id = es.paper_id
     JOIN users u ON u.id = es.supervisor_id
     WHERE es.status = 'active'
     LIMIT 8"
);
$liveExams = array_map(function ($r, $i) use ($palette) {
    $pct    = $r['total'] > 0 ? round($r['submitted'] / $r['total'] * 100) : 0;
    $status = $pct >= 80 ? 'ใกล้จบ' : 'กำลังสอบ';
    $sc     = $pct >= 80 ? ['#d97706','#fffbeb'] : ['#16a34a','#f0fdf4'];
    return [
        'room'        => $r['room_code'],
        'subject'     => $r['subject'],
        'level'       => $r['level'],
        'supervisor'  => $r['supervisor_name'],
        'submitted'   => $r['submitted'],
        'total'       => $r['total'],
        'pct'         => $pct . '%',
        'status'      => $status,
        'statusColor' => $sc[0],
        'statusBg'    => $sc[1],
        'color'       => $palette[$i % count($palette)],
    ];
}, $liveRows, array_keys($liveRows));

// ── Bar chart (last 7 days) ───────────────────────────────────────────────────
$thaiDays = ['อา','จ','อ','พ','พฤ','ศ','ส'];
$bars = [];
for ($d = 6; $d >= 0; $d--) {
    $date  = date('Y-m-d', strtotime("-$d days"));
    $count = (int)Database::value(
        "SELECT COUNT(*) FROM exam_sessions WHERE scheduled_date = ? AND status != 'draft'", [$date]
    );
    $dow    = (int)date('w', strtotime($date));
    $maxH   = 5;
    $bars[] = [
        'label' => $thaiDays[$dow],
        'h'     => max(8, min(100, $count * 20)) . '%',
        'color' => $d === 0 ? '#0f766e' : '#99f6e4',
    ];
}

// ── Alerts ────────────────────────────────────────────────────────────────────
$alerts = [];
if ($pendingPerms > 0) {
    $alerts[] = ['iconKey' => 'flag',  'iconColor' => '#0891b2', 'bg' => '#ecfeff',
                 'text'    => "คำขอสอบนอกเวลา $pendingPerms รายการรอผู้จัดการสอบพิจารณา"];
}
$lateStudents = (int)Database::value(
    "SELECT COUNT(*) FROM session_enrollments se
     JOIN exam_sessions es ON es.id = se.session_id
     WHERE es.status = 'active' AND se.checked_in_at IS NULL"
);
if ($lateStudents > 0) {
    $alerts[] = ['iconKey' => 'clock', 'iconColor' => '#d97706', 'bg' => '#fffbeb',
                 'text'    => "มีนักเรียน $lateStudents คนยังไม่เข้าห้องสอบ"];
}
if (empty($alerts)) {
    $alerts[] = ['iconKey' => 'check', 'iconColor' => '#16a34a', 'bg' => '#f0fdf4',
                 'text'    => 'ทุกห้องสอบดำเนินการเป็นปกติ'];
}

json_ok(['stats' => $stats, 'liveExams' => $liveExams, 'bars' => $bars, 'alerts' => $alerts]);
