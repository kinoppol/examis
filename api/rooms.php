<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

requireRole('manager', 'admin');

if (isset($_GET['report'])) { respond(roomReport()); }

match (method()) {
    'GET'    => respond(listRooms()),
    'POST'   => respond(saveRoom(null, body()), 201),
    'PUT'    => respond(saveRoom((int)($_GET['id'] ?? 0), body())),
    'DELETE' => deleteRoom(),
    default  => fail('Method not allowed', 405),
};

function listRooms(): array
{
    $bid = isset($_GET['building_id']) ? (int)$_GET['building_id'] : 0;
    $sql = 'SELECT r.*, b.name AS building_name
            FROM exam_rooms r JOIN buildings b ON b.id = r.building_id';
    $params = [];
    if ($bid) { $sql .= ' WHERE r.building_id = ?'; $params[] = $bid; }
    $sql .= ' ORDER BY b.name, r.room_code';
    $st = getDB()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function saveRoom(?int $id, array $b): array
{
    $buildingId = (int)($b['building_id'] ?? 0);
    $code       = trim((string)($b['room_code']    ?? ''));
    $capacity   = max(1, (int)($b['capacity']      ?? 30));
    $desc       = trim((string)($b['description']  ?? ''));
    if (!$buildingId || !$code) fail('กรุณาระบุอาคารและรหัสห้อง');

    $db = getDB();
    if ($id) {
        $db->prepare('UPDATE exam_rooms SET building_id=?, room_code=?, capacity=?, description=? WHERE id=?')
           ->execute([$buildingId, $code, $capacity, $desc ?: null, $id]);
    } else {
        $db->prepare('INSERT INTO exam_rooms (building_id, room_code, capacity, description) VALUES (?,?,?,?)')
           ->execute([$buildingId, $code, $capacity, $desc ?: null]);
        $id = (int)$db->lastInsertId();
    }
    $st = $db->prepare('SELECT r.*, b.name AS building_name FROM exam_rooms r JOIN buildings b ON b.id=r.building_id WHERE r.id=?');
    $st->execute([$id]);
    return $st->fetch();
}

function deleteRoom(): never
{
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('ไม่ระบุ id');
    getDB()->prepare('DELETE FROM exam_rooms WHERE id=?')->execute([$id]);
    respond(['ok' => true]);
}

function roomReport(): array
{
    $rows = getDB()->query(
        'SELECT b.name AS building_name, r.room_code, r.capacity, r.id AS room_id,
                s.id AS session_id, s.exam_date, s.start_time, s.end_time, s.status, s.access_code,
                p.title AS exam_title,
                GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR ", ") AS supervisors
         FROM exam_rooms r
         JOIN buildings b ON b.id = r.building_id
         LEFT JOIN exam_sessions s ON s.room_id = r.id
         LEFT JOIN exam_papers p ON p.id = s.exam_paper_id
         LEFT JOIN session_supervisors sv ON sv.session_id = s.id
         LEFT JOIN users u ON u.id = sv.user_id
         GROUP BY r.id, s.id
         ORDER BY b.name, r.room_code, s.exam_date, s.start_time'
    )->fetchAll();
    return $rows;
}
