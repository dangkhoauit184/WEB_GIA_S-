<?php
namespace App\Modules\People\Features\Parents;

use App\Shared\{Access, Store};

final class ParentsRepository
{
    private const SELECT='SELECT u.*, kv.ten AS ten_khu_vuc, a.account_id, a.username, a.status AS account_status,
        (SELECT COUNT(*) FROM hoc_vien hv WHERE hv.phu_huynh_user_id=u.user_id AND hv.deleted_at IS NULL) AS so_con
        FROM users u LEFT JOIN account a ON a.user_id=u.user_id AND a.is_deleted=0
        LEFT JOIN role_group g ON g.group_id=a.group_id LEFT JOIN khu_vuc kv ON kv.id=u.khu_vuc_id WHERE u.is_deleted=0';
    private const FIELDS=['hoTen'=>'hoten','email'=>'email','sdt'=>'sdt','ngaySinh'=>'ngaysinh',
        'diaChi'=>'diachi','ngheNghiep'=>'nghe_nghiep','khuVucId'=>'khu_vuc_id'];

    public static function all(array $filters,array $user,?int $id=null): array
    {
        $sql=self::SELECT; $params=[];
        $sql.=" AND (g.group_name='PhuHuynh' OR EXISTS (SELECT 1 FROM hoc_vien h WHERE h.phu_huynh_user_id=u.user_id AND h.deleted_at IS NULL)
            OR EXISTS (SELECT 1 FROM audit_log al WHERE al.bang='users' AND al.ban_ghi_id=u.user_id AND JSON_UNQUOTE(JSON_EXTRACT(al.du_lieu_moi,'$.action'))='parent_profile_created'))";
        if ($id !== null) { $sql.=' AND u.user_id=?'; $params[]=$id; }
        if ($user['role'] === 'GIAO_VU' && $user['coSoId']) {
            $childScope=\App\Modules\Academic\Features\Admissions\AdmissionsFilter::studentScope('h.id', (int)$user['coSoId']);
            $sql.=" AND (NOT EXISTS (SELECT 1 FROM hoc_vien h WHERE h.phu_huynh_user_id=u.user_id AND h.deleted_at IS NULL)
                OR EXISTS (SELECT 1 FROM hoc_vien h WHERE h.phu_huynh_user_id=u.user_id AND h.deleted_at IS NULL $childScope))";
        }
        if (!Access::staff($user)) { $sql.=' AND u.user_id=?'; $params[]=$user['userId']; }
        if (!empty($filters['q'])) {
            $sql.=' AND (u.hoten LIKE ? OR u.sdt LIKE ? OR u.email LIKE ?)';
            array_push($params,...array_fill(0,3,'%'.$filters['q'].'%'));
        }
        if (!empty($filters['khuVucId'])) { $sql.=' AND u.khu_vuc_id=?'; $params[]=$filters['khuVucId']; }
        return Store::rows($sql.' ORDER BY u.user_id DESC',$params);
    }

    public static function children(int $id, array $user): array
    {
        $scope=$user['role']==='GIAO_VU' && $user['coSoId'] ? \App\Modules\Academic\Features\Admissions\AdmissionsFilter::studentScope('hv.id', (int)$user['coSoId']) : '';
        return Store::rows('SELECT hv.id,hv.ma_hv AS maHv,hv.ho_ten AS hoTen,hv.ngay_sinh AS ngaySinh,
            hv.gioi_tinh AS gioiTinh,hv.truong,hv.cap_lop_id AS capLopId,cl.ten AS tenCapLop,hv.trang_thai AS trangThai,
            (SELECT GROUP_CONCAT(DISTINCT lhv.lop_id ORDER BY lhv.lop_id)
                FROM lop_hoc_vien lhv WHERE lhv.hoc_vien_id=hv.id
                AND lhv.trang_thai IN (\'dang_hoc\',\'hoc_thu\')) AS lopIds
            FROM hoc_vien hv LEFT JOIN cap_lop cl ON cl.id=hv.cap_lop_id
            WHERE hv.phu_huynh_user_id=? AND hv.deleted_at IS NULL'.$scope.' ORDER BY hv.id',[$id]);
    }

    public static function create(array $data, int $actor): int
    {
        return Store::transaction(function() use($data,$actor) {
            $values=[];
            foreach(self::FIELDS as $key=>$column) $values[$column]=$data[$key];
            $id=Store::insert('users',$values);
            Store::recordAudit($actor,'them','users',$id,null,['action'=>'parent_profile_created','hoTen'=>$data['hoTen']]);
            if ($data['username'] !== null) {
                $group=Store::one("SELECT group_id FROM role_group WHERE group_name='PhuHuynh' AND is_deleted=0");
                Store::insert('account',['user_id'=>$id,'group_id'=>$group['group_id'],'username'=>$data['username'],
                    'password_hash'=>password_hash($data['password'],PASSWORD_BCRYPT),'status'=>'active']);
            }
            return $id;
        });
    }

    public static function update(int $id,array $data): void
    {
        Store::update('users','user_id',$id,$data,self::FIELDS,'AND is_deleted=0');
    }
}
