<?php
namespace App\Modules\Academic\Features\LeaveRequests;

use App\Modules\Administration\Features\Permissions\PermissionsFilter;

final class LeaveRequestsService
{
    public static function classRequests(): array
    {
        PermissionsFilter::administer();
        return LeaveRequestsRepository::classRequests();
    }
    public static function decideClass(int $id,array $body): array
    {
        $actor=PermissionsFilter::administer();
        return LeaveRequestsRepository::decideClass($id,LeaveRequestsValidator::decision($body),$actor);
    }
}
