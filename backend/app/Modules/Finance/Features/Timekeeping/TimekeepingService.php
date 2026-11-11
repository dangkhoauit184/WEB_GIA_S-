<?php
namespace App\Modules\Finance\Features\Timekeeping;

use App\Modules\Administration\Features\Permissions\PermissionsFilter;
use App\Modules\Academic\Features\LeaveRequests\LeaveRequestsValidator;

final class TimekeepingService
{
    public static function corrections(): array
    {
        PermissionsFilter::administer();
        return TimekeepingRepository::corrections();
    }
    public static function decide(int $id,array $body): array
    {
        $actor=PermissionsFilter::administer();
        return TimekeepingRepository::decide($id,LeaveRequestsValidator::decision($body),$actor);
    }
}
