<?php
namespace App\Modules\Academic\Features\LeaveRequests;

use App\Shared\{ApiException,Store};
use App\Modules\Administration\Features\Permissions\PermissionsRepository;

final class LeaveRequestsRepository
{
    public static function classRequests(): array
    {
        return Store::rows('SELECT r.*,l.ma_lop,b.ngay,u.hoten AS nguoi_gui FROM yeu_cau_nghi_lop r
            JOIN buoi_hoc b ON b.id=r.buoi_hoc_id JOIN lop_hoc l ON l.id=b.lop_id
            JOIN users u ON u.user_id=r.nguoi_gui_id ORDER BY r.id DESC LIMIT 500');
    }
    public static function decideClass(int $id,array $data,array $actor): array
    {
        return Store::transaction(function() use($id,$data,$actor) {
            PermissionsRepository::lockAdministrator($actor);
            $before=Store::one('SELECT * FROM yeu_cau_nghi_lop WHERE id=? FOR UPDATE',[$id]);
            if(!$before) throw new ApiException(404,'Không tìm thấy yêu cầu nghỉ lớp.');
            if($before['trang_thai']===$data['trangThai']) return $before;
            if($before['trang_thai']!=='cho_duyet') throw new ApiException(409,'Yêu cầu đã được xử lý.','DECISION_CONFLICT');
            Store::execute('UPDATE yeu_cau_nghi_lop SET trang_thai=?,nguoi_duyet_id=?,duyet_luc=NOW(),ghi_chu=? WHERE id=?',
                [$data['trangThai'],$actor['userId'],$data['ghiChu'],$id]);
            $after=Store::one('SELECT * FROM yeu_cau_nghi_lop WHERE id=?',[$id]);
            Store::recordAudit((int)$actor['userId'],'duyet','yeu_cau_nghi_lop',$id,$before,$after);
            return $after;
        });
    }
}
