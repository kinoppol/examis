<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/app.php';

$user   = api_user();
$method = req_method();

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatSession(array $row): array
{
    $colors = ['draft' => ['#94a3b8','#f1f5f9'], 'ready' => ['#0f766e','#f0fdfa'], 'active' => ['#16a34a','#f0fdf4'], 'ended' => ['#64748b','#f1f5f9']];
    $c = $colors[$row['status']] ?? $colors['draft'];
    return array_merge($row, ['statusColor' => $c[0], 'statusBg' => $c[1]]);
}

// ── Supervisor view ───────────────────────────────────────────────────────────
if ($user['role'] === 'exam_supervisor' && $method === 'GET') {
    $session = Database::one(
        'SELECT es.*, ep.title, ep.subject, ep.level
         FROM exam_sessions es
         JOIN exam_papers ep ON ep.id = es.paper_id
         WHERE es.supervisor_id = ? AND es.status IN (\'ready\',\'active\')
         ORDER BY es.scheduled_date, es.start_time
         LIMIT 1',
        [$user['id']]
    );
    if (!$session) json_ok(['session' => null, 'students' => [], 'checked_in_count' => 0, 'total_count' => 0]);

    $students = Database::all(
        'SELECT u.id, u.full_name, u.student_code,
                se.seat_number, se.checked_in_at,
                (SELECT COUNT(*) FROM late_requests lr
                 WHERE lr.session_id = se.session_id AND lr.student_id = se.student_id
                   AND lr.status = \'approved\') AS has_late_permission
         FROM session_enrollments se
         JOIN users u ON u.id = se.student_id
         WHERE se.session_id = ?
         ORDER BY se.seat_number',
        [$session['id']]
    );

    $checkedIn = array_sum(array_map(fn($s) => $s['checked_in_at'] ? 1 : 0, $students));
    json_ok([
        'session'          => $session,
        'students'         => $students,
        'checked_in_count' => $checkedIn,
        'total_count'      => count($students),
    ]);
}

// ── Manager: list all sessions ────────────────────────────────────────────────
if ($user['role'] === 'exam_manager' && $method === 'GET') {
    $sem = Database::one('SELECT id FROM semesters WHERE is_active=1 LIMIT 1');
    $rows = $sem ? Database::all(
        'SELECT es.*, ep.subject, ep.level,
                SUBSTRING(ep.subject,1,4) AS subj_abbr,
                u.full_name AS supervisor_name
         FROM exam_sessions es
         JOIN exam_papers ep ON ep.id = es.paper_id
         JOIN users u ON u.id = es.supervisor_id
         WHERE es.semester_id = ?
         ORDER BY es.scheduled_date, es.start_time',
        [$sem['id']]
    ) : [];
    json_ok(['rows' => array_map('formatSession', $rows), 'semester' => $sem]);
}

// ── Manager: create session ───────────────────────────────────────────────────
if ($user['role'] === 'exam_manager' && $method === 'POST') {
    $b = req_body();
    $required = ['paper_id','room_code','supervisor_id','scheduled_date','start_time','end_time','duration_minutes'];
    foreach ($required as $f) {
        if (empty($b[$f])) json_fail("Missing field: $f");
    }
    $sem = Database::one('SELECT id FROM semesters WHERE is_active=1 LIMIT 1');
    if (!$sem) json_fail('No active semester found');

    $pin = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $id = Database::insert('exam_sessions', [
        'semester_id'      => $sem['id'],
        'paper_id'         => (int)$b['paper_id'],
        'room_code'        => $b['room_code'],
        'supervisor_id'    => (int)$b['supervisor_id'],
        'scheduled_date'   => $b['scheduled_date'],
        'start_time'       => $b['start_time'],
        'end_time'         => $b['end_time'],
        'duration_minutes' => (int)$b['duration_minutes'],
        'pin_code'         => $pin,
        'status'           => 'draft',
        'created_by'       => $user['id'],
    ]);
    json_ok(['id' => $id, 'pin_code' => $pin], 201);
}

// ── Manager: update status ────────────────────────────────────────────────────
if ($user['role'] === 'exam_manager' && $method === 'PUT') {
    $id = req_int('id');
    $b  = req_body();
    if (!$id) json_fail('Missing id');
    if (isset($b['status'])) {
        Database::update('exam_sessions', ['status' => $b['status']], 'id = ? AND created_by = ?', [$id, $user['id']]);
    }
    json_ok();
}

json_fail('Not found', 404);
