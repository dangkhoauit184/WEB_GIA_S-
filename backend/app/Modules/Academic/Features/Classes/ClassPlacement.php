<?php
namespace App\Modules\Academic\Features\Classes;

use App\Shared\{ApiException,Input,Store};
use App\Modules\Administration\Features\Permissions\{PermissionsFilter,PermissionsRepository};

final class ClassPlacement
{
    public static function registrations(): array
    {
        PermissionsFilter::administer();
        return Store::rows("SELECT dk.*,hv.ho_ten,hv.ma_hv,k.ma AS ma_khoa,k.ten AS ten_khoa,
            hd.ma_hd,hd.trang_thai AS tinh_trang_thu,hd.da_thu,hd.phai_thu,
            (SELECT l.ma_lop FROM lop_hoc_vien x JOIN lop_hoc l ON l.id=x.lop_id
                WHERE x.dang_ky_khoa_hoc_id=dk.id AND x.trang_thai IN ('dang_hoc','hoc_thu','cho_xep_lop') ORDER BY x.id DESC LIMIT 1) AS ma_lop,
            (SELECT u.hoten FROM lop_hoc_vien x JOIN users u ON u.user_id=x.xep_boi
                WHERE x.dang_ky_khoa_hoc_id=dk.id ORDER BY x.id DESC LIMIT 1) AS nguoi_xep,
            (SELECT x.xep_luc FROM lop_hoc_vien x WHERE x.dang_ky_khoa_hoc_id=dk.id ORDER BY x.id DESC LIMIT 1) AS xep_luc
            FROM dang_ky_khoa_hoc dk JOIN hoc_vien hv ON hv.id=dk.hoc_vien_id
            JOIN khoa_hoc k ON k.id=dk.khoa_hoc_id LEFT JOIN hoa_don hd ON hd.dang_ky_khoa_hoc_id=dk.id
            WHERE hv.deleted_at IS NULL ORDER BY dk.id DESC LIMIT 1000");
    }

    public static function assign(int $id,array $body): array
    {
        $actor=PermissionsFilter::administer();
        if(array_diff(array_keys($body),['lopId'])) Input::fail('fields');
        $classId=Input::validate($body,['lopId'=>['id',true]])['lopId'];
        return Store::transaction(function() use($id,$classId,$actor) {
            PermissionsRepository::lockAdministrator($actor);
            $registration=Store::one('SELECT * FROM dang_ky_khoa_hoc WHERE id=? FOR UPDATE',[$id]);
            if(!$registration) throw new ApiException(404,'Không tìm thấy đăng ký khóa.');
            if(!in_array($registration['trang_thai'],['cho_xep_lop','dang_hoc'],true)) throw new ApiException(409,'Đăng ký đã kết thúc hoặc thôi học.');
            $class=Store::one('SELECT * FROM lop_hoc WHERE id=? AND deleted_at IS NULL FOR UPDATE',[$classId]);
            if(!$class || (int)$class['khoa_hoc_id']!==(int)$registration['khoa_hoc_id']) throw new ApiException(400,'Lớp phải thuộc đúng khóa đã đăng ký.');
            if(!in_array($class['trang_thai'],['sap_bat_dau','dang_hoc'],true)) throw new ApiException(409,'Lớp chưa sẵn sàng nhận học viên.');
            $current=Store::rows("SELECT * FROM lop_hoc_vien WHERE dang_ky_khoa_hoc_id=? AND trang_thai IN ('dang_hoc','hoc_thu','cho_xep_lop') FOR UPDATE",[$id]);
            foreach($current as $old) {
                if((int)$old['lop_id']===$classId) return $old;
            }
            foreach($current as $old) {
                Store::execute("UPDATE lop_hoc_vien SET trang_thai='da_roi',ngay_roi=CURRENT_DATE() WHERE id=?",[$old['id']]);
            }
            $existing=Store::one('SELECT * FROM lop_hoc_vien WHERE lop_id=? AND hoc_vien_id=? FOR UPDATE',[$classId,$registration['hoc_vien_id']]);
            if($existing && (int)$existing['dang_ky_khoa_hoc_id']!==$id) throw new ApiException(409,'Học viên đã có ghi danh khác trong lớp này.');
            if($existing) {
                $placementId=(int)$existing['id'];
                Store::execute("UPDATE lop_hoc_vien SET trang_thai='dang_hoc',ngay_roi=NULL,xep_boi=?,xep_luc=NOW() WHERE id=?",[$actor['userId'],$placementId]);
            } else {
                $placementId=Store::insert('lop_hoc_vien',['lop_id'=>$classId,'hoc_vien_id'=>$registration['hoc_vien_id'],
                    'dang_ky_khoa_hoc_id'=>$id,'ngay_vao'=>date('Y-m-d'),'trang_thai'=>'dang_hoc',
                    'xep_boi'=>$actor['userId'],'xep_luc'=>date('Y-m-d H:i:s')]);
            }
            Store::execute("UPDATE dang_ky_khoa_hoc SET trang_thai='dang_hoc' WHERE id=?",[$id]);
            $after=Store::one('SELECT * FROM lop_hoc_vien WHERE id=?',[$placementId]);
            Store::recordAudit((int)$actor['userId'],'sua','lop_hoc_vien',$placementId,['previous'=>$current],$after);
            return $after;
        });
    }
}
