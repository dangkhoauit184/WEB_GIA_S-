<?php
namespace App\Modules\Administration\Features\Permissions;

final class PermissionsService
{
    public static function groups(): array
    {
        PermissionsFilter::administer(); return PermissionsRepository::groups();
    }
    public static function functions(): array
    {
        PermissionsFilter::administer(); return PermissionsRepository::functions();
    }
    public static function matrix(int $id): array
    {
        PermissionsFilter::administer(); return PermissionsRepository::matrix($id);
    }
    public static function updateMatrix(int $id,array $input): array
    {
        $user=PermissionsFilter::administer();
        return PermissionsRepository::updateMatrix($id,PermissionsValidator::matrix($input),(int)$user['userId']);
    }
    public static function describe(int $id,array $input): array
    {
        $user=PermissionsFilter::administer();
        return PermissionsRepository::describe($id,PermissionsValidator::description($input),(int)$user['userId']);
    }
    public static function accounts(array $filters): array
    {
        PermissionsFilter::administer(); return PermissionsRepository::accounts(PermissionsValidator::filters($filters));
    }
    public static function assign(int $id,array $input): array
    {
        $user=PermissionsFilter::administer();
        return PermissionsRepository::assignGroup($id,PermissionsValidator::accountGroup($input),$user);
    }
}
