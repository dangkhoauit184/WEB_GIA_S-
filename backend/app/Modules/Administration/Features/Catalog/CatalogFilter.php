<?php
namespace App\Modules\Administration\Features\Catalog;

use App\Shared\Access;

final class CatalogFilter
{
    public static function read(): array
    {
        return Access::guard('QuanLyLopHoc');
    }

    public static function administer(): array
    {
        $user=Access::user();
        if($user['role']!=='ADMIN') Access::forbidden();
        return $user;
    }
}
