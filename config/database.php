<?php
declare(strict_types=1);

class Database
{
    private static ?PDO $instance = null;

    public static function pdo(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                DB_HOST, DB_PORT, DB_NAME
            );
            self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row ?: null;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function insert(string $table, array $data): int
    {
        $cols  = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
        $marks = implode(', ', array_fill(0, count($data), '?'));
        self::run("INSERT INTO `$table` ($cols) VALUES ($marks)", array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function upsert(string $table, array $data, array $updateKeys): void
    {
        $cols    = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
        $marks   = implode(', ', array_fill(0, count($data), '?'));
        $updates = implode(', ', array_map(fn($k) => "`$k` = VALUES(`$k`)", $updateKeys));
        self::run(
            "INSERT INTO `$table` ($cols) VALUES ($marks) ON DUPLICATE KEY UPDATE $updates",
            array_values($data)
        );
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set  = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
        $stmt = self::run(
            "UPDATE `$table` SET $set WHERE $where",
            [...array_values($data), ...$whereParams]
        );
        return $stmt->rowCount();
    }
}
