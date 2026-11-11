<?php
namespace App\Modules\Administration\Features\Audit;

final class AuditFilter
{
    public static function read(): array
    {
        return \App\Modules\Administration\Features\Permissions\PermissionsFilter::administer();
    }
}
