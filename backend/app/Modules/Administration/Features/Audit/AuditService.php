<?php
namespace App\Modules\Administration\Features\Audit;

final class AuditService
{
    public static function all(array $query): array
    {
        AuditFilter::read();
        return AuditRepository::all(AuditValidator::filters($query));
    }
    public static function detail(int $id): array
    {
        AuditFilter::read();
        return AuditRepository::find($id);
    }
}
