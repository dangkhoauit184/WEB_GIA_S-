<?php
namespace App\Modules\Communication\Features\Notifications;

use App\Shared\{Access, ApiException};

final class NotificationsService
{
    public static function index(): array
    {
        $user = Access::user();
        return NotificationsRepository::forUser((int)$user['userId']);
    }

    public static function markRead(int $id): array
    {
        $user = Access::user();
        NotificationsRepository::markRead($id, (int)$user['userId']);
        return ['id' => $id, 'daDoc' => true];
    }

    public static function readAll(): array
    {
        $user = Access::user();
        $updated = NotificationsRepository::markAllRead((int)$user['userId']);
        return ['updated' => $updated, 'daDoc' => true];
    }

    public static function create(array $body): array
    {
        $user = Access::user();
        if (!Access::staff($user)) {
            throw new ApiException(403, 'Chỉ nhân sự trung tâm hoặc Admin được tạo thông báo hệ thống.', 'FORBIDDEN');
        }
        $data = NotificationsValidator::create($body);
        return NotificationsRepository::create($data);
    }
}
