<?php
namespace App\Modules\Administration\Features\Permissions;

use App\Shared\Access;

final class PermissionsFilter
{
    public static function administer(): array
    {
        $user=Access::user();
        if($user['role']!=='ADMIN') Access::forbidden();
        return $user;
    }
}
