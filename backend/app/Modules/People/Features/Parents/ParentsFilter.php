<?php
namespace App\Modules\People\Features\Parents;

final class ParentsFilter
{
    public static function check(string $action = 'view', bool $staffOnly = false): array
    {
        return \App\Shared\Access::guard('TiepNhanYeuCau', $action, $staffOnly);
    }
}
