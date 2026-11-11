<?php
namespace App\Modules\People\Features\Teachers;

use App\Shared\{Access,Store};

final class TeachersRepository
{
    private const SELECT='SELECT ns.*,ns.nghe_nghiep AS nghe_nghiep_ns,u.hoten,u.sdt,u.email,u.ngaysinh,u.diachi,
        a.account_id,a.username,a.status AS account_status FROM nhan_su_chuyen_mon ns
        JOIN users u ON u.user_id=ns.user_id LEFT JOIN account a ON a.user_id=ns.user_id AND a.is_deleted=0
        WHERE ns.deleted_at IS NULL AND u.is_deleted=0';
    private const USER_FIELDS=['hoTen'=>'hoten','email'=>'email','sdt'=>'sdt','ngaySinh'=>'ngaysinh','diaChi'=>'diachi'];
    private const NS_FIELDS=['maNs'=>'ma_ns','phanLoai'=>'phan_loai','gioiTinh'=>'gioi_tinh','cccd'=>'cccd',
        'ngheNghiep'=>'nghe_nghiep','donViCongTac'=>'don_vi_cong_tac','gioiThieu'=>'gioi_thieu',
        'kinhNghiemNam'=>'kinh_nghiem_nam','hinhThucDay'=>'hinh_thuc_day','donGiaGioChuan'=>'don_gia_gio_chuan',
        'nganHang'=>'ngan_hang','soTaiKhoan'=>'so_tai_khoan','chuTaiKhoan'=>'chu_tai_khoan','trangThai'=>'trang_thai'];

    public static function all(array $filters,array $user,?int $id=null): array
    {
        $sql=self::SELECT; $params=[];
        if ($id!==null) { $sql.=' AND ns.id=?'; $params[]=$id; }
        if (!Access::staff($user)) { $sql.=' AND ns.user_id=?'; $params[]=$user['userId']; }
        if (!empty($filters['q'])) {
            $sql.=' AND (u.hoten LIKE ? OR ns.ma_ns LIKE ? OR u.sdt LIKE ? OR u.email LIKE ?)';
            array_push($params,...array_fill(0,4,'%'.$filters['q'].'%'));
        }
        foreach (['phanLoai'=>'FIND_IN_SET(?,ns.phan_loai)>0','trangThai'=>'ns.trang_thai=?',
            'khuVucId'=>'EXISTS (SELECT 1 FROM nhan_su_khu_vuc kv WHERE kv.nhan_su_id=ns.id AND kv.khu_vuc_id=?)'] as $key=>$condition) {
            if (!empty($filters[$key])) { $sql.=' AND '.$condition; $params[]=$filters[$key]; }
        }
        if (!empty($filters['monHocId'])) {
            $sql.=' AND EXISTS (SELECT 1 FROM nhan_su_mon nsm WHERE nsm.nhan_su_id=ns.id AND nsm.mon_hoc_id=?';
            $params[]=$filters['monHocId'];
            if (!empty($filters['capLopId'])) { $sql.=' AND ? BETWEEN nsm.cap_lop_tu AND nsm.cap_lop_den'; $params[]=$filters['capLopId']; }
            $sql.=')';
        }
        return Store::rows($sql.' ORDER BY ns.id DESC',$params);
    }

    public static function match(array $filters,?string $type=null): array
    {
        $sql='SELECT ns.*,ns.nghe_nghiep AS nghe_nghiep_ns,u.hoten,u.sdt,u.email,nsm.hoc_phi_buoi_de_xuat
            FROM nhan_su_chuyen_mon ns JOIN users u ON u.user_id=ns.user_id
            JOIN nhan_su_mon nsm ON nsm.nhan_su_id=ns.id WHERE ns.deleted_at IS NULL AND u.is_deleted=0
            AND ns.trang_thai=\'hoat_dong\' AND nsm.mon_hoc_id=? AND ? BETWEEN nsm.cap_lop_tu AND nsm.cap_lop_den';
        $params=[$filters['monHocId'],$filters['capLopId']];
        if ($type) { $sql.=' AND FIND_IN_SET(?,ns.phan_loai)>0'; $params[]=$type; }
        if (isset($filters['nganSachMax'])) { $sql.=' AND nsm.hoc_phi_buoi_de_xuat<=?'; $params[]=$filters['nganSachMax']; }
        if (isset($filters['khuVucId'])) {
            $sql.=' AND EXISTS (SELECT 1 FROM nhan_su_khu_vuc kv WHERE kv.nhan_su_id=ns.id AND kv.khu_vuc_id=?)'; $params[]=$filters['khuVucId'];
        }
        if (isset($filters['thu'])) {
            $sql.=' AND EXISTS (SELECT 1 FROM nhan_su_lich_ranh lr WHERE lr.nhan_su_id=ns.id AND lr.thu=? AND lr.gio_bat_dau<=? AND lr.gio_ket_thuc>=?)';
            array_push($params,$filters['thu'],$filters['gioBatDau'],$filters['gioKetThuc']);
        }
        return Store::rows($sql.' ORDER BY nsm.hoc_phi_buoi_de_xuat,ns.kinh_nghiem_nam DESC',$params);
    }

    public static function extras(int $id): array
    {
        return [
            'monDays'=>Store::rows('SELECT nsm.id,nsm.mon_hoc_id AS monHocId,m.ma AS maMon,m.ten AS tenMon,
                nsm.cap_lop_tu AS capLopTu,nsm.cap_lop_den AS capLopDen,nsm.hoc_phi_buoi_de_xuat AS hocPhiBuoiDeXuat
                FROM nhan_su_mon nsm JOIN mon_hoc m ON m.id=nsm.mon_hoc_id WHERE nsm.nhan_su_id=? ORDER BY nsm.id',[$id]),
            'khuVucs'=>Store::rows('SELECT kv.id AS khuVucId,kv.ten AS tenKhuVuc,kv.thanh_pho AS thanhPho
                FROM nhan_su_khu_vuc nskv JOIN khu_vuc kv ON kv.id=nskv.khu_vuc_id WHERE nskv.nhan_su_id=? ORDER BY kv.id',[$id]),
            'lichRanhs'=>Store::rows('SELECT id,thu,gio_bat_dau AS gioBatDau,gio_ket_thuc AS gioKetThuc
                FROM nhan_su_lich_ranh WHERE nhan_su_id=? ORDER BY thu,gio_bat_dau',[$id]),
        ];
    }

    private static function relations(int $id,array $data): void
    {
        if (isset($data['monDays'])) {
            Store::execute('DELETE FROM nhan_su_mon WHERE nhan_su_id=?',[$id]);
            foreach($data['monDays'] as $item) Store::insert('nhan_su_mon',['nhan_su_id'=>$id,'mon_hoc_id'=>$item['monHocId'],
                'cap_lop_tu'=>$item['capLopTu'],'cap_lop_den'=>$item['capLopDen'],'hoc_phi_buoi_de_xuat'=>$item['hocPhiBuoiDeXuat']]);
        }
        if (isset($data['khuVucIds'])) {
            Store::execute('DELETE FROM nhan_su_khu_vuc WHERE nhan_su_id=?',[$id]);
            foreach($data['khuVucIds'] as $kv) Store::insert('nhan_su_khu_vuc',['nhan_su_id'=>$id,'khu_vuc_id'=>$kv]);
        }
        if (isset($data['lichRanhs'])) {
            Store::execute('DELETE FROM nhan_su_lich_ranh WHERE nhan_su_id=?',[$id]);
            foreach($data['lichRanhs'] as $item) Store::insert('nhan_su_lich_ranh',['nhan_su_id'=>$id,'thu'=>$item['thu'],
                'gio_bat_dau'=>$item['gioBatDau'],'gio_ket_thuc'=>$item['gioKetThuc']]);
        }
    }

    public static function create(array $data): int
    {
        return Store::transaction(function() use($data) {
            $values=[];
            foreach(self::USER_FIELDS as $key=>$column) $values[$column]=$data[$key];
            $values['nghe_nghiep']=$data['ngheNghiep'];
            $userId=Store::insert('users',$values);
            if ($data['username']!==null) {
                $group=Store::one('SELECT group_id FROM role_group WHERE group_name=? AND is_deleted=0',[$data['phanLoai']==='tro_giang'?'TroGiang':'GiangVien']);
                Store::insert('account',['user_id'=>$userId,'group_id'=>$group['group_id'],'username'=>$data['username'],
                    'password_hash'=>password_hash($data['password'],PASSWORD_BCRYPT),'status'=>'active']);
            }
            $values=['user_id'=>$userId];
            foreach(self::NS_FIELDS as $key=>$column) $values[$column]=$data[$key];
            $id=Store::insert('nhan_su_chuyen_mon',$values);
            self::relations($id,$data);
            return $id;
        });
    }

    public static function update(int $id,int $userId,array $data,?int $actor=null): void
    {
        Store::transaction(function() use($id,$userId,$data,$actor) {
            $before=Store::one('SELECT * FROM nhan_su_chuyen_mon WHERE id=? FOR UPDATE',[$id]);
            if($actor!==null) $before+=['user'=>Store::one('SELECT * FROM users WHERE user_id=?',[$userId]),'relations'=>self::extras($id)];
            Store::update('users','user_id',$userId,$data,self::USER_FIELDS);
            $map=self::NS_FIELDS; unset($map['maNs']);
            Store::update('nhan_su_chuyen_mon','id',$id,$data,$map,'AND deleted_at IS NULL');
            self::relations($id,$data);
            if($actor!==null) Store::recordAudit($actor,'sua','nhan_su_chuyen_mon',$id,$before,
                Store::one('SELECT * FROM nhan_su_chuyen_mon WHERE id=?',[$id])+['user'=>Store::one('SELECT * FROM users WHERE user_id=?',[$userId]),'relations'=>self::extras($id)]);
        });
    }
}
