<?php
namespace App\Modules\Communication\Features\Notifications;

final class NotificationsFilter
{
    public static function user(): array
    {
        return \App\Shared\Access::user();
    }
}
