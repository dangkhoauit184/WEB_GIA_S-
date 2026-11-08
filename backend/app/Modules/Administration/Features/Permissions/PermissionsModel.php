<?php
namespace App\Modules\Administration\Features\Permissions;

final class PermissionsModel extends \CodeIgniter\Model
{
    public const GROUPS=['Admin','NhanVien','GiangVien','TroGiang','PhuHuynh'];
    public const ACTIONS=['view','add','edit','delete','download'];

    public static function flags(array $rows,bool $admin=false): array
    {
        foreach($rows as &$row) foreach(self::ACTIONS as $flag) $row[$flag]=$admin || (bool)$row[$flag];
        return $rows;
    }
    public static function version(array $permissions): string
    {
        return hash('sha256',json_encode($permissions,JSON_THROW_ON_ERROR));
    }
}
