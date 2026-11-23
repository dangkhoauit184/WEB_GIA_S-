<?php
namespace App\Modules\Academic\Features\LeaveRequests;

use App\Shared\{ApiException,Store};
use App\Modules\Administration\Features\Permissions\PermissionsRepository;

final class LeaveRequestsRepository
{
    public static function personal(array $actor,?int $id=null): array
    {
        [$scope,$params]=\App\Shared\Access::classScope($actor);
        $sql='SELECT d.*,b.lop_id,b.ngay,b.gio_bat_dau,b.gio_ket_thuc,l.ma_lop,u.hoten AS nguoi_gui,hv.ho_ten AS ten_hoc_vien
            FROM don_xin_nghi d JOIN buoi_hoc b ON b.id=d.buoi_hoc_id JOIN lop_hoc l ON l.id=b.lop_id
            JOIN users u ON u.user_id=d.nguoi_gui_id LEFT JOIN hoc_vien hv ON hv.id=d.hoc_vien_id
            WHERE l.deleted_at IS NULL'.$scope;
        if($id!==null) { $sql.=' AND d.id=?';$params[]=$id; }
        return Store::rows($sql.' ORDER BY d.id DESC',$params);
    }

    public static function decidePersonal(int $id,array $data,array $actor): array
    {
        return Store::transaction(function() use($id,$data,$actor) {
            $row=self::personal($actor,$id)[0]??null;
            if(!$row) throw new ApiException(404,'Không tìm thấy đơn nghỉ cá nhân.');
            \App\Shared\Access::classAllowed((int)$row['lop_id'],$actor);
            $before=Store::one('SELECT * FROM don_xin_nghi WHERE id=? FOR UPDATE',[$id]);
            if($before['trang_thai']===$data['trangThai'] && $before['ly_do_tu_choi']===$data['lyDoTuChoi']) return $before;
            if($before['trang_thai']!=='cho_duyet') throw new ApiException(409,'Đơn đã được xử lý.');
            Store::execute('UPDATE don_xin_nghi SET trang_thai=?,ly_do_tu_choi=?,nguoi_duyet_id=?,duyet_luc=NOW() WHERE id=?',
                [$data['trangThai'],$data['lyDoTuChoi'],$actor['userId'],$id]);
            $after=Store::one('SELECT * FROM don_xin_nghi WHERE id=?',[$id]);
            Store::recordAudit((int)$actor['userId'],'duyet','don_xin_nghi',$id,$before,$after);
            Store::insert('thong_bao',['user_id'=>$before['nguoi_gui_id'],'loai'=>'duyet_don','tieu_de'=>'Kết quả đơn nghỉ cá nhân #'.$id,
                'noi_dung'=>$data['trangThai']==='da_duyet'?'Đơn đã được duyệt.':$data['lyDoTuChoi'],'doi_tuong_loai'=>'don_xin_nghi','doi_tuong_id'=>$id,'khoa_chong_trung'=>'leave-decision:'.$id,'co_gui_email'=>1]);
            return $after;
        });
    }

    public static function classRequests(?array $actor=null): array
    {
        [$scope,$params]=$actor?\App\Shared\Access::classScope($actor):['',[]];
        return Store::rows('SELECT r.*,l.ma_lop,b.ngay,u.hoten AS nguoi_gui FROM yeu_cau_nghi_lop r
            JOIN buoi_hoc b ON b.id=r.buoi_hoc_id JOIN lop_hoc l ON l.id=b.lop_id
            JOIN users u ON u.user_id=r.nguoi_gui_id WHERE l.deleted_at IS NULL'.$scope.' ORDER BY r.id DESC LIMIT 500',$params);
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
            $sessionInfo=Store::one('SELECT b.id, b.lop_id, b.ngay, b.gio_bat_dau, b.gio_ket_thuc, l.ma_lop, l.ten_lop FROM buoi_hoc b JOIN lop_hoc l ON l.id=b.lop_id WHERE b.id=?',[$before['buoi_hoc_id']]);
            $approved=$data['trangThai']==='da_duyet';
            Store::execute("INSERT IGNORE INTO thong_bao(user_id,loai,tieu_de,noi_dung,doi_tuong_loai,doi_tuong_id,khoa_chong_trung,co_gui_email)
                VALUES (?,'duyet_don',?,?,'yeu_cau_nghi_lop',?,?,1)", [
                (int)$before['nguoi_gui_id'],
                'Kết quả yêu cầu nghỉ cả lớp #'.$id.($sessionInfo?' · '.$sessionInfo['ma_lop']:''),
                $approved
                    ? 'Yêu cầu nghỉ cả lớp đã được Admin phê duyệt.'.($data['ghiChu']?' Ghi chú: '.$data['ghiChu']:'')
                    : 'Yêu cầu nghỉ cả lớp chưa được duyệt.'.($data['ghiChu']?' Lý do: '.$data['ghiChu']:''),
                $id,
                'class-leave-decision:'.$id,
            ]);
            if($approved && $sessionInfo) {
                $parents=Store::rows("SELECT DISTINCT hv.phu_huynh_user_id AS user_id FROM lop_hoc_vien x JOIN hoc_vien hv ON hv.id=x.hoc_vien_id
                    WHERE x.lop_id=? AND hv.deleted_at IS NULL AND x.trang_thai IN ('dang_hoc','hoc_thu')
                      AND x.ngay_vao<=? AND (x.ngay_roi IS NULL OR x.ngay_roi>=?)",
                    [$sessionInfo['lop_id'],$sessionInfo['ngay'],$sessionInfo['ngay']]);
                foreach($parents as $parent) {
                    Store::execute("INSERT IGNORE INTO thong_bao(user_id,loai,tieu_de,noi_dung,doi_tuong_loai,doi_tuong_id,khoa_chong_trung,co_gui_email)
                        VALUES (?,'doi_lich',?,?,'buoi_hoc',?,?,1)", [
                        (int)$parent['user_id'],
                        'Thông báo nghỉ buổi học · '.$sessionInfo['ma_lop'],
                        'Buổi học lớp '.$sessionInfo['ten_lop'].' ngày '.$sessionInfo['ngay'].' ('.substr($sessionInfo['gio_bat_dau'],0,5).'–'.substr($sessionInfo['gio_ket_thuc'],0,5).') đã được trung tâm phê duyệt nghỉ cả lớp.',
                        (int)$sessionInfo['id'],
                        'class-leave-parent:'.$id,
                    ]);
                }
            }
            return $after;
        });
    }
}
