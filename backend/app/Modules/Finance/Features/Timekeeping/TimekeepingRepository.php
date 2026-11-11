<?php
namespace App\Modules\Finance\Features\Timekeeping;

use App\Shared\{ApiException,Store};
use App\Modules\Administration\Features\Permissions\PermissionsRepository;

final class TimekeepingRepository
{
    public static function corrections(): array
    {
        return Store::rows('SELECT d.*,u.hoten,b.ngay,l.ma_lop,pc.user_id,
            GREATEST(0,TIMESTAMPDIFF(MINUTE,GREATEST(COALESCE(d.checkin_moi,pc.checkin_luc),pc.gio_lich_bat_dau),
                LEAST(COALESCE(d.checkout_moi,pc.checkout_luc),pc.gio_lich_ket_thuc))) AS phut_de_xuat
            FROM dieu_chinh_cong d JOIN phan_cong_buoi_hoc pc ON pc.id=d.phan_cong_id
            JOIN users u ON u.user_id=pc.user_id JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
            JOIN lop_hoc l ON l.id=b.lop_id ORDER BY d.id DESC LIMIT 500');
    }

    public static function decide(int $id,array $data,array $actor): array
    {
        return Store::transaction(function() use($id,$data,$actor) {
            PermissionsRepository::lockAdministrator($actor);
            $target=Store::one('SELECT pc.user_id,pc.id FROM dieu_chinh_cong d JOIN phan_cong_buoi_hoc pc ON pc.id=d.phan_cong_id WHERE d.id=?',[$id]);
            if(!$target) throw new ApiException(404,'Không tìm thấy điều chỉnh công.');
            Store::one('SELECT user_id FROM users WHERE user_id=? FOR UPDATE',[$target['user_id']]);
            $pc=Store::one('SELECT * FROM phan_cong_buoi_hoc WHERE id=? FOR UPDATE',[$target['id']]);
            $before=Store::one('SELECT * FROM dieu_chinh_cong WHERE id=? FOR UPDATE',[$id]);
            if($before['trang_thai']===$data['trangThai']) return $before;
            if($before['trang_thai']!=='cho_duyet') throw new ApiException(409,'Yêu cầu đã được xử lý.','DECISION_CONFLICT');
            if($data['trangThai']==='da_duyet') {
                if($before['checkin_cu']!==$pc['checkin_luc'] || $before['checkout_cu']!==$pc['checkout_luc']) {
                    throw new ApiException(409,'Giờ công đã thay đổi sau khi gửi yêu cầu. Cần kiểm tra lại.','TIME_VERSION_CONFLICT');
                }
                if($before['loai']==='huy_cong') {
                    Store::execute("UPDATE phan_cong_buoi_hoc SET trang_thai_cong='tu_choi' WHERE id=?",[$pc['id']]);
                } else {
                    $checkin=$before['checkin_moi']??$pc['checkin_luc'];
                    $checkout=$before['checkout_moi']??$pc['checkout_luc'];
                    if(!$checkin || !$checkout || $checkout<=$checkin) throw new ApiException(400,'Cần đủ giờ vào/ra hợp lệ trước khi duyệt.');
                    $session=Store::one('SELECT * FROM buoi_hoc WHERE id=? FOR UPDATE',[$pc['buoi_hoc_id']]);
                    if(in_array($session['trang_thai'],['huy','doi'],true)) throw new ApiException(409,'Buổi đã hủy/dời cần xử lý lịch trước khi duyệt công.');
                    Store::execute("UPDATE phan_cong_buoi_hoc SET checkin_luc=?,checkout_luc=?,trang_thai_cong='hop_le',trang_thai='da_day' WHERE id=?",[$checkin,$checkout,$pc['id']]);
                    Store::execute("UPDATE buoi_hoc SET trang_thai='da_day' WHERE id=?",[$pc['buoi_hoc_id']]);
                }
                Store::recordAudit((int)$actor['userId'],'duyet','phan_cong_buoi_hoc',(int)$pc['id'],$pc,Store::one('SELECT * FROM phan_cong_buoi_hoc WHERE id=?',[$pc['id']]));
            }
            Store::execute('UPDATE dieu_chinh_cong SET trang_thai=?,nguoi_duyet_id=?,duyet_luc=NOW(),ghi_chu_duyet=? WHERE id=?',
                [$data['trangThai'],$actor['userId'],$data['ghiChu'],$id]);
            $after=Store::one('SELECT * FROM dieu_chinh_cong WHERE id=?',[$id]);
            Store::recordAudit((int)$actor['userId'],'duyet','dieu_chinh_cong',$id,$before,$after);
            return $after;
        });
    }
}
