<?php
namespace App\Modules\People\Features\Parents;

use App\Shared\{Access,ApiException};

final class ParentsService
{
    private static function reader(): array
    {
        $user=ParentsFilter::check();
        if (!Access::staff($user) && $user['role']!=='PHU_HUYNH') Access::forbidden();
        return $user;
    }
    public static function all(array $filters): array
    {
        return array_map(fn($row)=>ParentsModel::map($row),ParentsRepository::all($filters,self::reader()));
    }
    public static function detail(int $id): array
    {
        $row=ParentsRepository::all([],self::reader(),$id)[0]??null;
        if (!$row) throw new ApiException(404,'Không tìm thấy phụ huynh.','PARENT_NOT_FOUND');
        return ParentsModel::map($row,['hocViens'=>ParentsRepository::children($id,self::reader())]);
    }
    public static function create(array $data): array
    {
        $actor=ParentsFilter::check('add',true);
        if ($actor['role']!=='ADMIN' && (isset($data['username']) || isset($data['password']))) Access::forbidden();
        return self::detail(ParentsRepository::create(ParentsValidator::validate($data),(int)$actor['userId']));
    }
    public static function update(int $id,array $data): array
    {
        ParentsFilter::check('edit',true); self::detail($id);
        ParentsRepository::update($id,ParentsValidator::validate($data,false));
        return self::detail($id);
    }

    public static function publicLanding(): array
    {
        return ParentsRepository::publicLanding();
    }

    public static function createPublicConsultation(array $body): array
    {
        $data = ParentsValidator::consultation($body);
        return ParentsRepository::createPublicConsultation($data);
    }

    private static function parentUser(): array
    {
        $user = Access::user();
        if (!in_array($user['role'] ?? '', ['PHU_HUYNH', 'ADMIN'], true)) {
            Access::forbidden();
        }
        return $user;
    }

    public static function portal(): array
    {
        $user = self::parentUser();
        return ParentsRepository::portalData((int)$user['userId']);
    }

    public static function createLeaveRequest(array $body): array
    {
        $user = self::parentUser();
        $data = ParentsValidator::leaveRequest($body);
        return ParentsRepository::createLeaveRequest((int)$user['userId'], $data);
    }

    public static function createOnlinePayment(array $body): array
    {
        $user = self::parentUser();
        $data = ParentsValidator::onlinePayment($body);
        return ParentsRepository::createOnlinePayment((int)$user['userId'], $data);
    }
}
