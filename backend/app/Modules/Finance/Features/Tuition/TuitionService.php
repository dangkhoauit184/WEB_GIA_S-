<?php
namespace App\Modules\Finance\Features\Tuition;

use App\Modules\Administration\Features\Permissions\PermissionsFilter;

final class TuitionService
{
    public static function transactions(): array
    {
        PermissionsFilter::administer();
        return TuitionRepository::transactions();
    }
    public static function confirm(int $id): array
    {
        $actor=PermissionsFilter::administer();
        TuitionRepository::find($id);
        \App\Shared\Store::call('CALL sp_xac_nhan_giao_dich(?,?)',[$id,$actor['userId']]);
        return TuitionRepository::find($id);
    }
}
