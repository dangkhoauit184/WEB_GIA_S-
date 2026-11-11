<?php
namespace App\Modules\Administration\Features\Staff;

use App\Shared\{ApiException,Store};
use App\Modules\Administration\Features\Permissions\PermissionsRepository;

final class StaffRepository
{
    public static function all(): array
    {
        return Store::rows("SELECT u.user_id,u.hoten,u.email,u.sdt,a.account_id,a.username,a.status,g.group_name,
            ns.id AS nhan_su_id,ns.phan_loai,ns.don_gia_gio_chuan,ns.gioi_thieu,ns.ngan_hang,ns.so_tai_khoan,
            (SELECT GROUP_CONCAT(CONCAT(m.ten,' ',sm.cap_lop_tu,'–',sm.cap_lop_den) SEPARATOR ', ')
                FROM nhan_su_mon sm JOIN mon_hoc m ON m.id=sm.mon_hoc_id WHERE sm.nhan_su_id=ns.id) AS chuyen_mon,
            (SELECT GROUP_CONCAT(CONCAT('T',lr.thu,' ',TIME_FORMAT(lr.gio_bat_dau,'%H:%i'),'–',TIME_FORMAT(lr.gio_ket_thuc,'%H:%i')) SEPARATOR ', ')
                FROM nhan_su_lich_ranh lr WHERE lr.nhan_su_id=ns.id) AS lich_ranh
            FROM account a JOIN users u ON u.user_id=a.user_id JOIN role_group g ON g.group_id=a.group_id
            LEFT JOIN nhan_su_chuyen_mon ns ON ns.user_id=u.user_id AND ns.deleted_at IS NULL
            WHERE a.is_deleted=0 AND u.is_deleted=0 AND g.is_deleted=0 AND g.group_name<>'PhuHuynh'
            ORDER BY u.hoten,u.user_id LIMIT 1000");
    }
    public static function find(int $userId): array
    {
        return Store::one('SELECT u.*,ns.id AS nhan_su_id FROM users u LEFT JOIN nhan_su_chuyen_mon ns
            ON ns.user_id=u.user_id AND ns.deleted_at IS NULL WHERE u.user_id=? AND u.is_deleted=0',[$userId])
            ?? throw new ApiException(404,'Không tìm thấy nhân sự.');
    }
    public static function create(array $data,array $actor): int
    {
        return Store::transaction(function() use($data,$actor) {
            PermissionsRepository::lockAdministrator($actor);
            $userId=Store::insert('users',['hoten'=>$data['hoTen'],'email'=>$data['email'],'sdt'=>$data['sdt']]);
            $professional=in_array($data['role'],['giao_vien','gia_su','tro_giang'],true);
            $groupName=$professional?($data['role']==='tro_giang'?'TroGiang':'GiangVien'):$data['role'];
            $group=Store::one('SELECT group_id FROM role_group WHERE group_name=? AND is_deleted=0',[$groupName]);
            if(!$group) throw new ApiException(400,'Nhóm quyền chưa được khởi tạo.');
            if($professional) {
                $nsId=Store::insert('nhan_su_chuyen_mon',['user_id'=>$userId,'ma_ns'=>'NS-'.bin2hex(random_bytes(6)),
                    'phan_loai'=>$data['role'],'don_gia_gio_chuan'=>$data['donGiaGio'],'gioi_thieu'=>$data['gioiThieu']]);
                Store::execute('INSERT INTO lich_su_don_gia_gio(nhan_su_id,don_gia_gio,hieu_luc_tu,nguoi_dat_id) VALUES(?,?,NOW(6),?)',[$nsId,$data['donGiaGio'],$actor['userId']]);
            } else {
                Store::insert('nhan_vien',['user_id'=>$userId,'ma_nv'=>'NV-'.bin2hex(random_bytes(6)),
                    'chuc_vu'=>$data['role']==='Admin'?'Quản trị viên':'Giáo vụ','trang_thai'=>'dang_lam']);
            }
            $accountId=Store::insert('account',['user_id'=>$userId,'group_id'=>$group['group_id'],'username'=>$data['username'],
                'password_hash'=>password_hash($data['password'],PASSWORD_BCRYPT),'status'=>'active']);
            Store::recordAudit((int)$actor['userId'],'them','account',$accountId,null,
                ['userId'=>$userId,'username'=>$data['username'],'groupName'=>$groupName]);
            return $userId;
        });
    }
}
