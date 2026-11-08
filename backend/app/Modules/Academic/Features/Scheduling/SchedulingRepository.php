<?php
namespace App\Modules\Academic\Features\Scheduling;

use App\Shared\{Access,ApiException,Store};

final class SchedulingRepository
{
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
        $sql='SELECT b.*,l.ma_lop,l.ten_lop,pc.user_id AS day_chinh_user_id,u.hoten AS ten_nguoi_day_chinh
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
            if(in_array($data['trangThai']??'', ['huy','doi'],true)) {
                Store::execute('UPDATE buoi_hoc SET nguoi_huy_id=?,huy_luc=NOW() WHERE id=?',[$callerId,$id]);
            }
            Store::update('buoi_hoc','id',$id,$data,$map);
        });
    }
}
