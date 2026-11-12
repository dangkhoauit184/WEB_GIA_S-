<?php
namespace App\Shared;

use App\Modules\Administration\Features\Auth\AuthRepository;
use App\Modules\Administration\Features\Auth\AuthModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class Access
{
    private static ?array $current = null;
    public static ?object $claims = null;
    public static ?string $token = null;

    public static function secret(): string
    {
        $key = getenv('JWT_SECRET') ?: '';
        if (strlen($key) < 32 || $key === 'educenter_erp_secret_key_2026') {
            throw new ApiException(503, 'Cần cấu hình JWT_SECRET riêng, tối thiểu 32 ký tự.', 'SERVER_NOT_CONFIGURED');
        }
        return $key;
    }

    public static function role(string $group, string $types = ''): ?string
    {
        if ($group === 'GiangVien') {
            foreach (['giao_vien' => 'GIAO_VIEN', 'gia_su' => 'GIA_SU', 'tro_giang' => 'TRO_GIANG'] as $type => $role) {
                if (in_array($type, explode(',', $types), true)) return $role;
            }
            return 'GIAO_VIEN';
        }
        return ['Admin'=>'ADMIN', 'NhanVien'=>'GIAO_VU', 'TroGiang'=>'TRO_GIANG', 'PhuHuynh'=>'PHU_HUYNH'][$group] ?? null;
    }

    public static function fingerprint(array $account): string
    {
        $version=(int)($account['session_version']??0);
        $value=$account['password_hash'].($version>0?'|'.$version:'');
        return hash_hmac('sha256', $value, self::secret());
    }

    public static function user(): array
    {
        if (self::$current !== null) return self::$current;
        $header = service('request')->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(\S+)$/iD', $header, $match)) {
            throw new ApiException(401, 'Vui lòng đăng nhập để tiếp tục.', 'UNAUTHORIZED');
        }
        try {
            $claims = JWT::decode($match[1], new Key(self::secret(), 'HS256'));
            if (($claims->iss ?? '') !== 'educenter-php' || empty($claims->accountId) || empty($claims->pwd)) throw new \UnexpectedValueException();
        } catch (ApiException $error) {
            throw $error;
        } catch (\Throwable) {
            throw new ApiException(401, 'Phiên đăng nhập không hợp lệ hoặc đã hết hạn.', 'INVALID_TOKEN');
        }
        $account = AuthRepository::account((int)$claims->accountId);
        if (!$account || $account['status'] !== 'active' || !hash_equals(self::fingerprint($account), $claims->pwd)
            || AuthRepository::revoked((int)$claims->accountId, hash('sha256', $match[1]))) {
            throw new ApiException(401, 'Phiên đăng nhập đã bị thu hồi. Vui lòng đăng nhập lại.', 'INVALID_TOKEN');
        }
        self::$token = $match[1]; self::$claims = $claims;
        return self::$current = AuthModel::map($account, AuthRepository::permissions((int)$account['group_id']));
    }

    public static function staff(array $user): bool
    {
        return in_array($user['role'], ['ADMIN', 'GIAO_VU'], true);
    }

    public static function guard(string $function, string $action = 'view', bool $staffOnly = false): array
    {
        $user = self::user();
        if ($user['role'] === null || ($staffOnly && !self::staff($user))) self::forbidden();
        if ($user['role'] === 'ADMIN') return $user;
        // Personal reads use the operational portal permission plus repository ownership scopes.
        if ($action === 'view' && !self::staff($user)) $function = 'VanHanhBuoiHoc';
        foreach ($user['permissions'] as $permission) {
            if ($permission['functionName'] === $function && ($permission[$action] ?? false)) return $user;
        }
        self::forbidden();
    }

    public static function forbidden(): never
    {
        throw new ApiException(403, 'Bạn không có quyền thực hiện thao tác này.', 'FORBIDDEN');
    }

    public static function classScope(array $user, string $alias = 'l'): array
    {
        if (self::staff($user)) {
            if ($user['role'] === 'GIAO_VU' && $user['coSoId']) return [" AND ($alias.co_so_id=? OR $alias.co_so_id IS NULL)", [$user['coSoId']]];
            return ['', []];
        }
        if ($user['role'] === 'PHU_HUYNH') {
            return [" AND EXISTS (SELECT 1 FROM lop_hoc_vien sc JOIN hoc_vien sh ON sh.id=sc.hoc_vien_id
                WHERE sc.lop_id=$alias.id AND sh.phu_huynh_user_id=? AND sh.deleted_at IS NULL
                AND sc.trang_thai IN ('dang_hoc','hoc_thu'))", [$user['userId']]];
        }
        return [" AND ($alias.nguoi_day_chinh_id=? OR EXISTS (SELECT 1 FROM buoi_hoc sb
            JOIN phan_cong_buoi_hoc sp ON sp.buoi_hoc_id=sb.id WHERE sb.lop_id=$alias.id
            AND sp.user_id=? AND sp.trang_thai<>'da_go'))", [$user['nhanSuId'] ?? 0, $user['userId']]];
    }

    public static function classAllowed(int $id, array $user): void
    {
        [$scope, $params] = self::classScope($user);
        if (!Store::one('SELECT l.id FROM lop_hoc l WHERE l.id=? AND l.deleted_at IS NULL' . $scope, [$id, ...$params])) {
            throw new ApiException(404, 'Không tìm thấy lớp học.', 'CLASS_NOT_FOUND');
        }
    }
}
