<?php
namespace App\Modules\Academic\Features\Admissions;

use App\Shared\{Access, ApiException, Store};

final class AdmissionsFilter
{
    public static function staff(string $action = 'view'): array
    {
        $user = Access::guard('TiepNhanYeuCau', $action, true);
        if ($user['role'] !== 'ADMIN' && !Store::one(
            "SELECT id FROM nhan_vien WHERE user_id=? AND trang_thai='dang_lam'", [$user['userId']]
        )) Access::forbidden();
        return $user;
    }

    public static function scope(array $user, string $alias = 'y'): array
    {
        if ($user['role'] === 'ADMIN' || !$user['coSoId']) return ['', []];
        return [" AND ($alias.co_so_mong_muon_id IS NULL OR $alias.co_so_mong_muon_id=?)".
            self::studentScope($alias.'.hoc_vien_id', (int)$user['coSoId']), [$user['coSoId']]];
    }

    public static function studentScope(string $studentColumn, int $campusId): string
    {
        return " AND (NOT EXISTS (SELECT 1 FROM lop_hoc_vien sx JOIN lop_hoc sl ON sl.id=sx.lop_id
                WHERE sx.hoc_vien_id=$studentColumn AND sl.deleted_at IS NULL AND sl.trang_thai IN ('sap_bat_dau','dang_hoc','tam_dung') AND sx.trang_thai IN ('dang_hoc','hoc_thu','cho_xep_lop'))
            OR EXISTS (SELECT 1 FROM lop_hoc_vien sx JOIN lop_hoc sl ON sl.id=sx.lop_id WHERE sx.hoc_vien_id=$studentColumn
                AND sl.deleted_at IS NULL AND sl.trang_thai IN ('sap_bat_dau','dang_hoc','tam_dung') AND sl.co_so_id=$campusId AND sx.trang_thai IN ('dang_hoc','hoc_thu','cho_xep_lop')))";
    }

    public static function participant(): array
    {
        $user = Access::user();
        if (!in_array($user['role'], ['PHU_HUYNH', 'GIAO_VIEN', 'GIA_SU'], true)) Access::forbidden();
        Access::guard('VanHanhBuoiHoc');
        return $user;
    }

    public static function employee(array $user): int
    {
        // Serialize creation of the bootstrap Admin's operational profile.
        Store::one('SELECT user_id FROM users WHERE user_id=? FOR UPDATE', [$user['userId']]);
        $employee = Store::one('SELECT id,trang_thai FROM nhan_vien WHERE user_id=? FOR UPDATE', [$user['userId']]);
        if ($employee) {
            if ($employee['trang_thai'] !== 'dang_lam') throw new ApiException(403, 'Hồ sơ nhân viên đã ngừng làm việc.');
            return (int)$employee['id'];
        }
        if ($user['role'] !== 'ADMIN') Access::forbidden();
        return Store::insert('nhan_vien', ['user_id'=>$user['userId'], 'ma_nv'=>'NV-AUTO-'.$user['userId'],
            'chuc_vu'=>'Quản trị viên', 'ngay_vao_lam'=>date('Y-m-d'), 'trang_thai'=>'dang_lam']);
    }
}
