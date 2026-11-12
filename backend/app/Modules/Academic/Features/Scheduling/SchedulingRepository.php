<?php
namespace App\Modules\Academic\Features\Scheduling;

use App\Shared\{Access,ApiException,Store};

final class SchedulingRepository
{
    private static function deletionBlockerSql(): string
    {
        return "CASE
            WHEN b.trang_thai<>'du_kien' OR TIMESTAMP(b.ngay,b.gio_bat_dau)<=NOW()
                THEN 'Chỉ xóa được ca chưa diễn ra.'
            WHEN EXISTS (SELECT 1 FROM phan_cong_buoi_hoc p WHERE p.buoi_hoc_id=b.id
                AND (p.checkin_luc IS NOT NULL OR p.checkout_luc IS NOT NULL OR p.bang_cong_id IS NOT NULL))
                THEN 'Ca đã có check-in hoặc dữ liệu công.'
            WHEN EXISTS (SELECT 1 FROM so_theo_doi_buoi_hoc s WHERE s.buoi_hoc_id=b.id)
                THEN 'Ca đã có dữ liệu điểm danh hoặc theo dõi học tập.'
            WHEN EXISTS (SELECT 1 FROM bai_tap t WHERE t.buoi_hoc_id=b.id)
                THEN 'Ca đã có bài tập hoặc bài chấm.'
            WHEN EXISTS (SELECT 1 FROM hoa_don_chi_tiet h WHERE h.buoi_hoc_id=b.id)
                THEN 'Ca đã liên kết với chi tiết hóa đơn.'
            WHEN EXISTS (SELECT 1 FROM don_xin_nghi d WHERE d.buoi_hoc_id=b.id OR d.buoi_bu_id=b.id)
                THEN 'Ca đã có đơn nghỉ liên kết. Xem đơn trong Duyệt nghỉ cá nhân & Học bù.'
            WHEN EXISTS (SELECT 1 FROM yeu_cau_nghi_lop y WHERE y.buoi_hoc_id=b.id)
                THEN 'Ca đã có yêu cầu nghỉ cả lớp. Admin cần xử lý yêu cầu này.'
            WHEN EXISTS (SELECT 1 FROM buoi_hoc g WHERE g.buoi_goc_id=b.id)
                THEN 'Ca đang là buổi gốc của lịch học bù. Kiểm tra ca bù liên kết trong thời khóa biểu.'
            WHEN EXISTS (SELECT 1 FROM dieu_chinh_cong d JOIN phan_cong_buoi_hoc p ON p.id=d.phan_cong_id WHERE p.buoi_hoc_id=b.id)
                THEN 'Ca đã có yêu cầu điều chỉnh công.'
            ELSE NULL END";
    }

    public static function weekly(array $filters,array $user,?int $id=null): array
    {
        [$scope,$params]=Access::classScope($user);
        $sql='SELECT lh.*,l.ma_lop,l.ten_lop FROM lich_hoc lh JOIN lop_hoc l ON l.id=lh.lop_id WHERE l.deleted_at IS NULL'.$scope;
        if(isset($filters['lopId'])) { $sql.=' AND lh.lop_id=?'; $params[]=$filters['lopId']; }
        if($id!==null) { $sql.=' AND lh.id=?'; $params[]=$id; }
        return Store::rows($sql.' ORDER BY lh.lop_id DESC,lh.thu,lh.gio_bat_dau',$params);
    }
    public static function createWeekly(array $data): int
    {
        return Store::insert('lich_hoc',['lop_id'=>$data['lopId'],'thu'=>$data['thu'],'gio_bat_dau'=>$data['gioBatDau'],
            'gio_ket_thuc'=>$data['gioKetThuc'],'phong_hoc'=>$data['phongHoc'],'hieu_luc_tu'=>$data['hieuLucTu'],
            'hieu_luc_den'=>$data['hieuLucDen'],'is_active'=>$data['isActive']]);
    }
    public static function sessions(array $filters,array $user,?int $id=null): array
    {
        [$scope,$params]=Access::classScope($user);
        $sql='SELECT b.*,l.ma_lop,l.ten_lop,'.self::deletionBlockerSql().' AS ly_do_khong_xoa,pc.user_id AS day_chinh_user_id,u.hoten AS ten_nguoi_day_chinh,
            (b.trang_thai=\'du_kien\' AND TIMESTAMP(b.ngay,b.gio_bat_dau)>NOW()) AS chua_bat_dau,
            (SELECT GROUP_CONCAT(ta.user_id) FROM phan_cong_buoi_hoc ta WHERE ta.buoi_hoc_id=b.id AND ta.vai_tro=\'tro_giang\' AND ta.trang_thai<>\'da_go\') AS tro_giang_ids,
            (SELECT GROUP_CONCAT(hb.hoc_vien_id) FROM hoc_bu_hoc_vien hb WHERE hb.buoi_bu_id=b.id) AS hoc_bu_ids,
            (SELECT GROUP_CONCAT(tu.hoten SEPARATOR \', \') FROM phan_cong_buoi_hoc ta JOIN users tu ON tu.user_id=ta.user_id WHERE ta.buoi_hoc_id=b.id AND ta.vai_tro=\'tro_giang\' AND ta.trang_thai<>\'da_go\') AS ten_tro_giang
            FROM buoi_hoc b JOIN lop_hoc l ON l.id=b.lop_id
            LEFT JOIN phan_cong_buoi_hoc pc ON pc.buoi_hoc_id=b.id AND pc.vai_tro=\'day_chinh\' AND pc.trang_thai<>\'da_go\'
            LEFT JOIN users u ON u.user_id=pc.user_id WHERE l.deleted_at IS NULL'.$scope;
        foreach(['lopId'=>'b.lop_id=?','tuNgay'=>'b.ngay>=?','denNgay'=>'b.ngay<=?','trangThai'=>'b.trang_thai=?'] as $key=>$condition) {
            if(isset($filters[$key])) { $sql.=' AND '.$condition; $params[]=$filters[$key]; }
        }
        if($id!==null) { $sql.=' AND b.id=?'; $params[]=$id; }
        if(!empty($filters['userId'])) {
            $sql.=' AND EXISTS (SELECT 1 FROM phan_cong_buoi_hoc sp WHERE sp.buoi_hoc_id=b.id AND sp.user_id=? AND sp.trang_thai<>\'da_go\')';
            $params[]=$filters['userId'];
        }
        if(!Access::staff($user) && $user['role']!=='PHU_HUYNH') {
            $sql.=' AND EXISTS (SELECT 1 FROM phan_cong_buoi_hoc own WHERE own.buoi_hoc_id=b.id AND own.user_id=? AND own.trang_thai<>\'da_go\')';
            $params[]=$user['userId'];
        }
        return Store::rows($sql.' ORDER BY b.ngay,b.gio_bat_dau,b.id',$params);
    }
    public static function generate(array $data): void
    {
        Store::call('CALL sp_sinh_buoi_hoc(?,?,?)',[$data['lopId'],$data['tuNgay'],$data['denNgay']]);
    }
    public static function assignment(int $id,array $data,int $callerId): array
    {
        Store::insert('phan_cong_buoi_hoc',['buoi_hoc_id'=>$id,'user_id'=>$data['userId'],'vai_tro'=>$data['vaiTro'],
            'don_gia'=>$data['donGia'],'cach_tinh_luong'=>$data['cachTinhLuong'],'phan_cong_boi'=>$callerId]);
        return Store::rows('SELECT pc.id,pc.buoi_hoc_id AS buoiHocId,pc.user_id AS userId,u.hoten AS hoTen,
            pc.vai_tro AS vaiTro,pc.trang_thai AS trangThai,pc.don_gia AS donGia,pc.cach_tinh_luong AS cachTinhLuong
            FROM phan_cong_buoi_hoc pc JOIN users u ON u.user_id=pc.user_id
            WHERE pc.buoi_hoc_id=? AND pc.trang_thai<>\'da_go\' ORDER BY pc.id',[$id]);
    }
    public static function update(int $id,array $data,int $callerId): void
    {
        $map=['ngay'=>'ngay','gioBatDau'=>'gio_bat_dau','gioKetThuc'=>'gio_ket_thuc','phongHoc'=>'phong_hoc',
            'coSoId'=>'co_so_id','chuDeBaiHoc'=>'chu_de_bai_hoc','noiDungBaiHoc'=>'noi_dung_bai_hoc',
            'fileBaiGiangUrl'=>'file_bai_giang_url','trangThai'=>'trang_thai','huyBoi'=>'huy_boi','lyDoHuyDoi'=>'ly_do_huy_doi'];
        Store::transaction(function() use($id,$data,$callerId,$map) {
            $before=Store::one('SELECT * FROM buoi_hoc WHERE id=? FOR UPDATE',[$id]);
            if(in_array($data['trangThai']??'', ['huy','doi'],true)) {
                Store::execute('UPDATE buoi_hoc SET nguoi_huy_id=?,huy_luc=NOW() WHERE id=?',[$callerId,$id]);
            }
            Store::update('buoi_hoc','id',$id,$data,$map);
            Store::recordAudit($callerId,'sua','buoi_hoc',$id,$before,Store::one('SELECT * FROM buoi_hoc WHERE id=?',[$id]));
        });
    }

    public static function deletePlanned(int $id,int $classId,array $actor): array
    {
        return Store::transaction(function() use($id,$classId,$actor) {
            Store::one('SELECT id FROM lop_hoc WHERE id=? FOR UPDATE',[$classId]);
            Access::classAllowed($classId,$actor);
            $before=Store::one('SELECT *,TIMESTAMP(ngay,gio_bat_dau)>NOW() AS chua_bat_dau FROM buoi_hoc WHERE id=? FOR UPDATE',[$id]);
            if(!$before) throw new ApiException(404,'Không tìm thấy ca học.');
            $assignments=Store::rows('SELECT * FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=? ORDER BY user_id FOR UPDATE',[$id]);
            $blocker=Store::one('SELECT '.self::deletionBlockerSql().' AS reason FROM buoi_hoc b WHERE b.id=?',[$id]);
            if($blocker['reason']!==null) throw new ApiException(409,$blocker['reason']);
            $makeups=Store::rows('SELECT * FROM hoc_bu_hoc_vien WHERE buoi_bu_id=? FOR UPDATE',[$id]);
            Store::execute('DELETE FROM hoc_bu_hoc_vien WHERE buoi_bu_id=?',[$id]);
            Store::execute('DELETE FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=?',[$id]);
            Store::execute('DELETE FROM buoi_hoc WHERE id=?',[$id]);
            Store::recordAudit((int)$actor['userId'],'xoa','buoi_hoc',$id,$before+['phanCong'=>$assignments,'hocBu'=>$makeups],null);
            return ['deleted'=>true,'id'=>$id];
        });
    }
}
