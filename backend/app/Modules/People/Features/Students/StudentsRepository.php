<?php
namespace App\Modules\People\Features\Students;

use App\Shared\{Access, Store};

final class StudentsRepository
{
    private const SELECT = 'SELECT hv.*, cl.ten AS ten_cap_lop, kv.ten AS ten_khu_vuc,
        CASE WHEN hv.trang_thai<>\'dang_hoc\' THEN hv.trang_thai
            WHEN EXISTS (SELECT 1 FROM lop_hoc_vien x JOIN lop_hoc l ON l.id=x.lop_id
                WHERE x.hoc_vien_id=hv.id AND x.trang_thai IN (\'dang_hoc\',\'hoc_thu\')
                AND x.ngay_vao<=CURDATE() AND (x.ngay_roi IS NULL OR x.ngay_roi>=CURDATE())
                AND l.deleted_at IS NULL AND l.trang_thai=\'dang_hoc\' AND l.ngay_bat_dau<=CURDATE()
                AND (l.ngay_ket_thuc IS NULL OR l.ngay_ket_thuc>=CURDATE())) THEN \'dang_hoc\'
            WHEN EXISTS (SELECT 1 FROM lop_hoc_vien x JOIN lop_hoc l ON l.id=x.lop_id
                WHERE x.hoc_vien_id=hv.id AND x.trang_thai IN (\'dang_hoc\',\'hoc_thu\')
                AND (x.ngay_roi IS NULL OR x.ngay_roi>=CURDATE()) AND l.deleted_at IS NULL
                AND l.trang_thai IN (\'sap_bat_dau\',\'dang_hoc\')
                AND (l.ngay_ket_thuc IS NULL OR l.ngay_ket_thuc>=CURDATE())) THEN \'da_xep_lop\'
            WHEN EXISTS (SELECT 1 FROM dang_ky_khoa_hoc d WHERE d.hoc_vien_id=hv.id
                AND d.trang_thai IN (\'cho_xep_lop\',\'dang_hoc\')) THEN \'cho_xep_lop\'
            ELSE \'chua_xep_lop\' END AS trang_thai_hoc_tap,
        (SELECT GROUP_CONCAT(k.ten SEPARATOR \', \') FROM dang_ky_khoa_hoc d JOIN khoa_hoc k ON k.id=d.khoa_hoc_id WHERE d.hoc_vien_id=hv.id AND d.trang_thai<>\'thoi_hoc\') AS ten_khoa,
        u.hoten AS ten_phu_huynh, u.sdt AS sdt_phu_huynh, u.email AS email_phu_huynh
        FROM hoc_vien hv JOIN users u ON u.user_id=hv.phu_huynh_user_id
        LEFT JOIN cap_lop cl ON cl.id=hv.cap_lop_id LEFT JOIN khu_vuc kv ON kv.id=hv.khu_vuc_id
        WHERE hv.deleted_at IS NULL';
    private const FIELDS = ['phuHuynhUserId'=>'phu_huynh_user_id','maHv'=>'ma_hv','hoTen'=>'ho_ten',
        'ngaySinh'=>'ngay_sinh','gioiTinh'=>'gioi_tinh','truong'=>'truong','capLopId'=>'cap_lop_id',
        'diaChi'=>'dia_chi','khuVucId'=>'khu_vuc_id','ghiChu'=>'ghi_chu','trangThai'=>'trang_thai'];

    public static function all(array $filters, array $user, ?int $id = null): array
    {
        $sql = self::SELECT; $params = [];
        if ($id !== null) { $sql .= ' AND hv.id=?'; $params[] = $id; }
        if ($user['role'] === 'GIAO_VU' && $user['coSoId']) $sql .= \App\Modules\Academic\Features\Admissions\AdmissionsFilter::studentScope('hv.id', (int)$user['coSoId']);
        if (!Access::staff($user)) {
            if ($user['role'] === 'PHU_HUYNH') { $sql .= ' AND hv.phu_huynh_user_id=?'; $params[] = $user['userId']; }
            else {
                [$scope,$bound] = Access::classScope($user);
                $sql .= " AND EXISTS (SELECT 1 FROM lop_hoc_vien sl JOIN lop_hoc l ON l.id=sl.lop_id
                    WHERE sl.hoc_vien_id=hv.id AND l.deleted_at IS NULL AND sl.trang_thai IN ('dang_hoc','hoc_thu') $scope)";
                array_push($params, ...$bound);
            }
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (hv.ho_ten LIKE ? OR hv.ma_hv LIKE ? OR u.hoten LIKE ? OR u.sdt LIKE ?)';
            array_push($params, ...array_fill(0,4,'%'.$filters['q'].'%'));
        }
        foreach (['phuHuynhUserId'=>'phu_huynh_user_id','capLopId'=>'cap_lop_id','trangThai'=>'trang_thai'] as $key=>$col) {
            if (isset($filters[$key])) { $sql .= " AND hv.$col=?"; $params[] = $filters[$key]; }
        }
        return Store::rows($sql . ' ORDER BY hv.id DESC', $params);
    }

    public static function classes(int $id, array $user): array
    {
        [$scope,$params] = Access::classScope($user);
        return Store::rows('SELECT lhv.id AS ghiDanhId, lhv.lop_id AS lopId, l.ma_lop AS maLop,
            l.ten_lop AS tenLop, l.loai_lop AS loaiLop, m.ten AS tenMonHoc, lhv.ngay_vao AS ngayVao,
            lhv.ngay_roi AS ngayRoi, lhv.trang_thai AS trangThaiGhiDanh FROM lop_hoc_vien lhv
            JOIN lop_hoc l ON l.id=lhv.lop_id JOIN mon_hoc m ON m.id=l.mon_hoc_id
            WHERE lhv.hoc_vien_id=? AND l.deleted_at IS NULL' . $scope . ' ORDER BY lhv.id DESC', [$id,...$params]);
    }

    public static function create(array $data): int
    {
        $values=[];
        foreach (self::FIELDS as $key=>$column) $values[$column]=$data[$key];
        return Store::insert('hoc_vien',$values);
    }

    public static function update(int $id, array $data): void
    {
        $fields=self::FIELDS; unset($fields['maHv']);
        Store::update('hoc_vien','id',$id,$data,$fields,'AND deleted_at IS NULL');
    }

    public static function delete(int $id): void
    {
        Store::execute('UPDATE hoc_vien SET deleted_at=NOW() WHERE id=? AND deleted_at IS NULL',[$id]);
    }
}
