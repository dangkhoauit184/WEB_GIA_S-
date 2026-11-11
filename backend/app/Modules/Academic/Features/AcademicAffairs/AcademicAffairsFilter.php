<?php
namespace App\Modules\Academic\Features\AcademicAffairs;

use App\Shared\{Access, Store};

final class AcademicAffairsFilter
{
    public static function check(string $function = 'VanHanhBuoiHoc', string $action = 'view'): array
    {
        $actor = Access::guard($function, $action, true);
        if ($actor['role'] !== 'ADMIN' && !Store::one("SELECT id FROM nhan_vien WHERE user_id=? AND trang_thai='dang_lam'", [$actor['userId']])) Access::forbidden();
        return $actor;
    }

    public static function allows(array $actor, string $function, string $action = 'view'): bool
    {
        if ($actor['role'] === 'ADMIN') return true;
        foreach ($actor['permissions'] as $permission) {
            if ($permission['functionName'] === $function && $permission[$action]) return true;
        }
        return false;
    }
}
