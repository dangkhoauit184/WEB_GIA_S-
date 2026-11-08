<?php
namespace App\Modules\Academic\Features\Classes;

use App\Shared\{Access,ApiException,Store};

final class ClassesRepository
{
    private const SELECT='SELECT l.*,m.ten AS ten_mon_hoc,cl.ten AS ten_cap_lop,cs.ten_co_so,u.hoten AS ten_nguoi_day_chinh,
        (SELECT COUNT(*) FROM lop_hoc_vien lhv WHERE lhv.lop_id=l.id AND lhv.trang_thai IN (\'dang_hoc\',\'hoc_thu\')) AS si_so_hien_tai
        FROM lop_hoc l JOIN mon_hoc m ON m.id=l.mon_hoc_id JOIN cap_lop cl ON cl.id=l.cap_lop_id
        JOIN nhan_su_chuyen_mon ns ON ns.id=l.nguoi_day_chinh_id JOIN users u ON u.user_id=ns.user_id
        LEFT JOIN co_so cs ON cs.id=l.co_so_id WHERE l.deleted_at IS NULL';
    private const FIELDS=['maLop'=>'ma_lop','tenLop'=>'ten_lop','loaiLop'=>'loai_lop','deXuatId'=>'de_xuat_id',
        'monHocId'=>'mon_hoc_id','capLopId'=>'cap_lop_id','nguoiDayChinhId'=>'nguoi_day_chinh_id','coSoId'=>'co_so_id',
        'phongHoc'=>'phong_hoc','hinhThuc'=>'hinh_thuc','diaDiem'=>'dia_diem','linkHoc'=>'link_hoc','khuVucId'=>'khu_vuc_id',
        'siSoToiThieu'=>'si_so_toi_thieu','siSoToiDa'=>'si_so_toi_da','cachThuPhi'=>'cach_thu_phi',
        'hocPhiBuoi'=>'hoc_phi_buoi','hocPhiThang'=>'hoc_phi_thang','hocPhiKhoa'=>'hoc_phi_khoa','mucTraChinh'=>'muc_tra_chinh',
        'mucTraTroGiang'=>'muc_tra_tro_giang','cachTinhLuong'=>'cach_tinh_luong','soBuoiDuKien'=>'so_buoi_du_kien',
        'ngayBatDau'=>'ngay_bat_dau','ngayKetThuc'=>'ngay_ket_thuc','trangThai'=>'trang_thai','nhanVienId'=>'nhan_vien_id'];

    public static function all(array $filters,array $user,?int $id=null): array
    {
        [$scope,$params]=Access::classScope($user); $sql=self::SELECT.$scope;
        if($id!==null) { $sql.=' AND l.id=?'; $params[]=$id; }
        if(!empty($filters['q'])) {
            $sql.=' AND (l.ma_lop LIKE ? OR l.ten_lop LIKE ? OR u.hoten LIKE ?)';
            array_push($params,...array_fill(0,3,'%'.$filters['q'].'%'));
        }
        foreach(['loaiLop'=>'loai_lop','trangThai'=>'trang_thai','monHocId'=>'mon_hoc_id','nguoiDayChinhId'=>'nguoi_day_chinh_id'] as $key=>$col) {
            if(isset($filters[$key])) { $sql.=" AND l.$col=?"; $params[]=$filters[$key]; }
        }
        return Store::rows($sql.' ORDER BY l.id DESC',$params);
    }

    public static function enrollments(int $id,array $user): array
    {
        $sql='SELECT lhv.id,lhv.lop_id AS lopId,lhv.hoc_vien_id AS hocVienId,hv.ma_hv AS maHv,hv.ho_ten AS hoTen,
            hv.ngay_sinh AS ngaySinh,u.hoten AS tenPhuHuynh,u.sdt AS sdtPhuHuynh,lhv.ngay_vao AS ngayVao,
            lhv.ngay_roi AS ngayRoi,lhv.trang_thai AS trangThai FROM lop_hoc_vien lhv
            JOIN hoc_vien hv ON hv.id=lhv.hoc_vien_id JOIN users u ON u.user_id=hv.phu_huynh_user_id
            WHERE lhv.lop_id=? AND hv.deleted_at IS NULL';
        $params=[$id];
        if($user['role']==='PHU_HUYNH') { $sql.=' AND hv.phu_huynh_user_id=?'; $params[]=$user['userId']; }
        return Store::rows($sql.' ORDER BY lhv.id',$params);
    }

    public static function schedules(int $id): array
    {
        return Store::rows('SELECT id,lop_id AS lopId,thu,gio_bat_dau AS gioBatDau,gio_ket_thuc AS gioKetThuc,
            phong_hoc AS phongHoc,hieu_luc_tu AS hieuLucTu,hieu_luc_den AS hieuLucDen,is_active AS isActive
            FROM lich_hoc WHERE lop_id=? ORDER BY thu,gio_bat_dau',[$id]);
    }

    public static function create(array $data,array $user): int
    {
        return Store::transaction(function() use($data,$user) {
            if(!$data['nhanVienId']) {
                // Attribute the class to its actual operator; never choose an unrelated employee.
                $staff=Store::one('SELECT id FROM nhan_vien WHERE user_id=?',[$user['userId']]);
                $data['nhanVienId']=$staff['id']??Store::insert('nhan_vien',['user_id'=>$user['userId'],
                    'ma_nv'=>'NV-AUTO-'.$user['userId'],'chuc_vu'=>'Quản lý vận hành','ngay_vao_lam'=>date('Y-m-d'),'trang_thai'=>'dang_lam']);
            }
            $values=[];
            foreach(self::FIELDS as $key=>$col) $values[$col]=$data[$key];
            return Store::insert('lop_hoc',$values);
        });
    }

    public static function update(int $id,array $data): void
    {
        $map=self::FIELDS; unset($map['maLop'],$map['deXuatId'],$map['nhanVienId']);
        Store::update('lop_hoc','id',$id,$data,$map,'AND deleted_at IS NULL');
    }
    public static function enroll(int $id,array $data): int
    {
        return Store::insert('lop_hoc_vien',['lop_id'=>$id,'hoc_vien_id'=>$data['hocVienId'],
            'ngay_vao'=>$data['ngayVao'],'ngay_roi'=>$data['ngayRoi'],'trang_thai'=>$data['trangThai']]);
    }
    public static function updateEnrollment(int $id,int $enrollmentId,array $data): void
    {
        if(!Store::one('SELECT id FROM lop_hoc_vien WHERE id=? AND lop_id=?',[$enrollmentId,$id])) {
            throw new ApiException(404,'Không tìm thấy ghi danh.','ENROLLMENT_NOT_FOUND');
        }
        Store::update('lop_hoc_vien','id',$enrollmentId,$data,['ngayRoi'=>'ngay_roi','trangThai'=>'trang_thai']);
    }
}
