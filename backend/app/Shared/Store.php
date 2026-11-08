<?php
namespace App\Shared;

use CodeIgniter\Database\BaseConnection;

final class Store
{
    private static ?BaseConnection $connection = null;

    public static function db(): BaseConnection
    {
        if (self::$connection === null) {
            $db = \Config\Database::connect();
            $db->initialize();
            $db->query("SET SESSION time_zone = '+07:00'");
            $db->query('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
            $db->transException(true);
            self::$connection = $db;
        }
        return self::$connection;
    }

    public static function rows(string $sql, array $params = []): array
    {
        return self::db()->query($sql, $params)->getResultArray();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        return self::rows($sql, $params)[0] ?? null;
    }

    public static function execute(string $sql, array $params = []): int
    {
        self::db()->query($sql, $params);
        return (int) self::db()->insertID();
    }

    public static function insert(string $table, array $values): int
    {
        // Table/columns only come from repository constants, never from request keys.
        $columns = implode('`, `', array_keys($values));
        $marks = implode(', ', array_fill(0, count($values), '?'));
        return self::execute("INSERT INTO `$table` (`$columns`) VALUES ($marks)", array_values($values));
    }

    public static function update(string $table, string $key, int $id, array $input, array $map, string $extra = ''): void
    {
        $sets = []; $params = [];
        foreach ($map as $field => $column) {
            if (array_key_exists($field, $input)) {
                $sets[] = "`$column` = ?"; $params[] = $input[$field];
            }
        }
        if ($sets !== []) {
            $params[] = $id;
            self::execute("UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$key` = ? $extra", $params);
        }
    }

    public static function transaction(callable $work): mixed
    {
        $db = self::db();
        $db->transBegin();
        try {
            $value = $work();
            if (!$db->transStatus()) {
                throw new \RuntimeException('Transaction failed');
            }
            $db->transCommit();
            return $value;
        } catch (\Throwable $error) {
            $db->transRollback();
            throw $error;
        }
    }

    public static function call(string $sql, array $params): void
    {
        // Procedures manage their own transaction; drain all MySQL result sets.
        $result = self::db()->query($sql, $params);
        if (is_object($result)) {
            $result->freeResult();
        }
        $mysqli = self::db()->connID;
        while ($mysqli->more_results()) {
            $mysqli->next_result();
            $next = $mysqli->store_result();
            if ($next) $next->free();
        }
    }

    public static function audit(int $userId, string $action, int $record, array $data): void
    {
        self::recordAudit($userId, $action, 'account', $record, null, $data);
    }

    public static function recordAudit(int $userId, string $action, string $table, ?int $record, ?array $before, ?array $after): void
    {
        self::insert('audit_log', [
            'user_id' => $userId, 'hanh_dong' => $action, 'bang' => $table, 'ban_ghi_id' => $record,
            'du_lieu_cu' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'du_lieu_moi' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'ip' => service('request')->getIPAddress(),
        ]);
    }
}
