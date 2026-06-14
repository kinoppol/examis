<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';
requireRole('manager', 'admin');

match (method()) {
    'GET'  => respond(getSettings()),
    'PUT'  => respond(updateSetting()),
    default => fail('Method not allowed', 405),
};

function getSettings(): array
{
    $db = getDB();
    try {
        $rows = $db->query("SELECT `key`, `value` FROM `settings`")->fetchAll();
    } catch (\Exception $e) {
        return ['summer_term_enabled' => '0'];
    }
    $result = ['summer_term_enabled' => '0'];
    foreach ($rows as $row) {
        $result[$row['key']] = $row['value'];
    }
    return $result;
}

function updateSetting(): array
{
    $b     = body();
    $key   = trim((string)($b['key']   ?? ''));
    $value = (string)($b['value'] ?? '');
    if (!$key) fail('ไม่ระบุ key');
    $allowed = ['summer_term_enabled'];
    if (!in_array($key, $allowed, true)) fail('ไม่อนุญาต key นี้');
    getDB()->prepare(
        "INSERT INTO `settings` (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?"
    )->execute([$key, $value, $value]);
    return getSettings();
}
