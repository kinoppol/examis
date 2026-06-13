<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

requireRole('manager', 'admin');

match (method()) {
    'GET'    => respond(listBuildings()),
    'POST'   => respond(saveBuilding(null, body()), 201),
    'PUT'    => respond(saveBuilding((int)($_GET['id'] ?? 0), body())),
    'DELETE' => deleteBuilding(),
    default  => fail('Method not allowed', 405),
};

function listBuildings(): array
{
    return getDB()->query(
        'SELECT b.*, COUNT(r.id) AS room_count
         FROM buildings b LEFT JOIN exam_rooms r ON r.building_id = b.id
         GROUP BY b.id ORDER BY b.name'
    )->fetchAll();
}

function saveBuilding(?int $id, array $b): array
{
    $name = trim((string)($b['name'] ?? ''));
    $desc = trim((string)($b['description'] ?? ''));
    if (!$name) fail('กรุณากรอกชื่ออาคาร');

    $db = getDB();
    if ($id) {
        $db->prepare('UPDATE buildings SET name=?, description=? WHERE id=?')->execute([$name, $desc ?: null, $id]);
    } else {
        $db->prepare('INSERT INTO buildings (name, description) VALUES (?, ?)')->execute([$name, $desc ?: null]);
        $id = (int)$db->lastInsertId();
    }
    $row = $db->prepare('SELECT b.*, COUNT(r.id) AS room_count FROM buildings b LEFT JOIN exam_rooms r ON r.building_id=b.id WHERE b.id=? GROUP BY b.id');
    $row->execute([$id]);
    return $row->fetch();
}

function deleteBuilding(): never
{
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('ไม่ระบุ id');
    getDB()->prepare('DELETE FROM buildings WHERE id=?')->execute([$id]);
    respond(['ok' => true]);
}
