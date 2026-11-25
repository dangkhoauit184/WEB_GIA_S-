<?php
namespace App\Modules\Administration\Features\Auth;

use App\Shared\Store;

final class AuthRepository
{
    private const SELECT = "SELECT a.account_id, a.user_id, a.group_id, a.username, a.password_hash, a.status,
        a.session_version, a.revoked_tokens_json,
        g.group_name, u.hoten, u.sdt, u.email, u.ngaysinh, u.diachi, u.nghe_nghiep, u.khu_vuc_id,
        nv.id AS nhan_vien_id, nv.ma_nv, nv.chuc_vu, nv.co_so_id,
        ns.id AS nhan_su_id, ns.ma_ns, ns.phan_loai
        FROM account a JOIN users u ON u.user_id=a.user_id JOIN role_group g ON g.group_id=a.group_id
        LEFT JOIN nhan_vien nv ON nv.user_id=a.user_id
        LEFT JOIN nhan_su_chuyen_mon ns ON ns.user_id=a.user_id AND ns.deleted_at IS NULL
        WHERE a.is_deleted=0 AND u.is_deleted=0 AND g.is_deleted=0";

    public static function username(string $username): ?array
    {
        return Store::one(self::SELECT . ' AND (a.username=? OR u.email=?) LIMIT 1', [$username, $username]);
    }

    public static function email(string $email): ?array
    {
        return Store::one(self::SELECT . ' AND LOWER(u.email)=LOWER(?) LIMIT 1', [trim($email)]);
    }

    public static function account(int $id): ?array
    {
        return Store::one(self::SELECT . ' AND a.account_id=? LIMIT 1', [$id]);
    }

    public static function latestOtp(int $accountId): ?array
    {
        $row = Store::one("SELECT id, du_lieu_moi, created_at FROM audit_log
            WHERE bang='account' AND ban_ghi_id=? AND hanh_dong='khac'
            AND JSON_UNQUOTE(JSON_EXTRACT(du_lieu_moi, '$.action'))='forgot_password_otp'
            ORDER BY id DESC LIMIT 1", [$accountId]);
        if (!$row || empty($row['du_lieu_moi'])) return null;
        $payload = json_decode((string)$row['du_lieu_moi'], true);
        return is_array($payload) ? ($payload + ['auditId' => (int)$row['id']]) : null;
    }

    public static function permissions(int $groupId): array
    {
        $rows = Store::rows('SELECT f.function_id AS functionId, f.name_function AS functionName,
            rgf.view, rgf.`add`, rgf.edit, rgf.`delete`, rgf.download
            FROM role_group_function rgf JOIN `function` f ON f.function_id=rgf.function_id
            WHERE rgf.group_id=? AND rgf.is_deleted=0 AND f.is_deleted=0 ORDER BY f.function_id', [$groupId]);
        foreach ($rows as &$row) {
            foreach (['view', 'add', 'edit', 'delete', 'download'] as $key) $row[$key] = (bool)$row[$key];
        }
        return $rows;
    }

    public static function bumpSessionVersion(int $accountId): void
    {
        Store::execute('UPDATE account SET session_version = session_version + 1 WHERE account_id = ?', [$accountId]);
    }

    public static function revokeToken(int $accountId, string $hash, int $expiresAt): void
    {
        Store::transaction(function () use ($accountId, $hash, $expiresAt) {
            $row = Store::one('SELECT revoked_tokens_json FROM account WHERE account_id = ? FOR UPDATE', [$accountId]);
            if (!$row) {
                return;
            }
            $now = time();
            $map = !empty($row['revoked_tokens_json']) ? json_decode((string)$row['revoked_tokens_json'], true) : [];
            if (!is_array($map)) {
                $map = [];
            }
            // Prune expired hashes so the JSON column stays compact.
            $map = array_filter($map, static fn($exp) => is_int($exp) && $exp > $now);
            $map[$hash] = max($expiresAt, $now + 60);
            if (count($map) > 100) {
                asort($map, SORT_NUMERIC);
                $map = array_slice($map, -100, null, true);
            }
            Store::execute('UPDATE account SET revoked_tokens_json = ? WHERE account_id = ?', [
                json_encode($map, JSON_THROW_ON_ERROR),
                $accountId,
            ]);
        });
    }

    public static function isTokenRevoked(array $account, string $hash): bool
    {
        if (!empty($account['revoked_tokens_json'])) {
            $map = json_decode((string)$account['revoked_tokens_json'], true);
            if (is_array($map) && isset($map[$hash]) && is_numeric($map[$hash]) && (int)$map[$hash] > time()) {
                return true;
            }
        }
        return false;
    }

    public static function revoked(int $accountId, string $hash): bool
    {
        $row = Store::one('SELECT revoked_tokens_json FROM account WHERE account_id = ?', [$accountId]);
        return $row ? self::isTokenRevoked($row, $hash) : false;
    }
}
