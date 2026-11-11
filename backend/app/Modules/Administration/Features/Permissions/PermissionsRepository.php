<?php
namespace App\Modules\Administration\Features\Permissions;

use App\Shared\{ApiException,Input,Store};

final class PermissionsRepository
{
    private const ACCOUNT_SELECT='SELECT a.account_id AS accountId,a.user_id AS userId,a.username,a.status,
        a.group_id AS groupId,g.group_name AS groupName,u.hoten AS hoTen
        FROM account a JOIN users u ON u.user_id=a.user_id JOIN role_group g ON g.group_id=a.group_id
        WHERE a.is_deleted=0 AND u.is_deleted=0 AND g.is_deleted=0';

    public static function groups(): array
    {
        return Store::rows('SELECT g.group_id AS groupId,g.group_name AS groupName,g.description,
            (SELECT COUNT(*) FROM account a JOIN users u ON u.user_id=a.user_id
            WHERE a.group_id=g.group_id AND a.is_deleted=0 AND u.is_deleted=0) AS accountCount
            FROM role_group g WHERE g.is_deleted=0 ORDER BY g.group_id');
    }
    public static function group(int $id,bool $lock=false): array
    {
        $row=Store::one('SELECT group_id AS groupId,group_name AS groupName,description FROM role_group
            WHERE group_id=? AND is_deleted=0'.($lock?' FOR UPDATE':''),[$id]);
        if(!$row || !in_array($row['groupName'],PermissionsModel::GROUPS,true)) throw new ApiException(404,'Không tìm thấy nhóm quyền chuẩn.','GROUP_NOT_FOUND');
        return $row;
    }
    public static function functions(): array
    {
        return Store::rows('SELECT function_id AS functionId,name_function AS functionName FROM `function` WHERE is_deleted=0 ORDER BY function_id');
    }
    public static function matrix(int $id): array
    {
        $group=self::group($id);
        $rows=Store::rows('SELECT f.function_id AS functionId,f.name_function AS functionName,
            COALESCE(p.view,0) AS view,COALESCE(p.`add`,0) AS `add`,COALESCE(p.edit,0) AS edit,
            COALESCE(p.`delete`,0) AS `delete`,COALESCE(p.download,0) AS download
            FROM `function` f LEFT JOIN role_group_function p ON p.function_id=f.function_id AND p.group_id=? AND p.is_deleted=0
            WHERE f.is_deleted=0 ORDER BY f.function_id',[$id]);
        $permissions=PermissionsModel::flags($rows,$group['groupName']==='Admin');
        return ['group'=>$group,'permissions'=>$permissions,'version'=>PermissionsModel::version($permissions)];
    }
    public static function updateMatrix(int $id,array $data,int $actor): array
    {
        return Store::transaction(function() use($id,$data,$actor) {
            $group=self::group($id,true);
            if($group['groupName']==='Admin') throw new ApiException(400,'Admin luôn có toàn quyền; không chỉnh ma trận nhóm này.','ADMIN_PERMISSIONS_FIXED');
            $before=self::matrix($id);
            if(!hash_equals($before['version'],$data['version'])) throw new ApiException(409,'Ma trận đã thay đổi. Hãy tải lại trước khi lưu.','PERMISSIONS_VERSION_CONFLICT');
            $actual=array_column($data['permissions'],'functionId'); sort($actual);
            if($actual!==array_column($before['permissions'],'functionId')) Input::fail('permissions: cần đủ từng chức năng đang hoạt động');
            $names=array_column($before['permissions'],'functionName','functionId');
            foreach($data['permissions'] as $row) {
                if($names[$row['functionId']]==='ChamCongLuong' && array_sum(array_intersect_key($row,array_flip(PermissionsModel::ACTIONS)))>0) {
                    Input::fail('permissions: công/lương chỉ dành cho Admin');
                }
                Store::execute('INSERT INTO role_group_function (group_id,function_id,view,`add`,edit,`delete`,download,is_deleted)
                    VALUES (?,?,?,?,?,?,?,0) ON DUPLICATE KEY UPDATE view=?,`add`=?,edit=?,`delete`=?,download=?,is_deleted=0',
                    [$id,$row['functionId'],$row['view'],$row['add'],$row['edit'],$row['delete'],$row['download'],
                        $row['view'],$row['add'],$row['edit'],$row['delete'],$row['download']]);
            }
            $after=self::matrix($id);
            Store::recordAudit($actor,'sua','role_group_function',$id,$before,$after);
            return $after;
        });
    }
    public static function describe(int $id,?string $description,int $actor): array
    {
        return Store::transaction(function() use($id,$description,$actor) {
            $before=self::group($id,true);
            Store::execute('UPDATE role_group SET description=? WHERE group_id=?',[$description,$id]);
            $after=self::group($id);
            Store::recordAudit($actor,'sua','role_group',$id,$before,$after);
            return $after;
        });
    }
    public static function accounts(array $filters): array
    {
        $where=''; $params=[];
        if(!empty($filters['q'])) {
            $where.=' AND (a.username LIKE ? OR u.hoten LIKE ?)'; array_push($params,'%'.$filters['q'].'%','%'.$filters['q'].'%');
        }
        foreach(['groupId'=>'a.group_id','status'=>'a.status'] as $field=>$column) {
            if(isset($filters[$field])) { $where.=" AND $column=?"; $params[]=$filters[$field]; }
        }
        $count=Store::one('SELECT COUNT(*) AS total FROM ('.self::ACCOUNT_SELECT.$where.') AS filtered',$params)['total'];
        $limit=$filters['perPage']; $offset=($filters['page']-1)*$limit;
        return ['items'=>Store::rows(self::ACCOUNT_SELECT.$where." ORDER BY a.account_id DESC LIMIT $limit OFFSET $offset",$params),
            'total'=>(int)$count,'page'=>$filters['page'],'perPage'=>$limit];
    }
    public static function account(int $id,bool $lock=false): array
    {
        return Store::one(self::ACCOUNT_SELECT.' AND a.account_id=?'.($lock?' FOR UPDATE':''),[$id])
            ?? throw new ApiException(404,'Không tìm thấy tài khoản.','ACCOUNT_NOT_FOUND');
    }
    public static function validateProfile(int $userId,string $group): void
    {
        if($group==='NhanVien' && !Store::one("SELECT id FROM nhan_vien WHERE user_id=? AND trang_thai='dang_lam'",[$userId])) {
            throw new ApiException(400,'Cần có hồ sơ nhân viên đang làm trước khi gán nhóm Nhân viên.','ROLE_PROFILE_REQUIRED');
        }
        if(in_array($group,['GiangVien','TroGiang'],true)) {
            $profile=Store::one("SELECT phan_loai FROM nhan_su_chuyen_mon WHERE user_id=? AND deleted_at IS NULL AND trang_thai='hoat_dong'",[$userId]);
            $allowed=$group==='GiangVien'?['giao_vien','gia_su']:['tro_giang'];
            if(!$profile || !array_intersect(explode(',',$profile['phan_loai']),$allowed)) {
                throw new ApiException(400,'Cần có hồ sơ chuyên môn phù hợp trước khi gán nhóm người dạy.','ROLE_PROFILE_REQUIRED');
            }
        }
    }
    public static function assignGroup(int $accountId,int $groupId,array $actor): array
    {
        return Store::transaction(function() use($accountId,$groupId,$actor) {
            // Serialize admin-role changes, then recheck the actor inside the transaction.
            // Two admins cannot concurrently demote each other using stale authentication.
            self::lockAdministrator($actor);
            $before=self::account($accountId,true); $group=self::group($groupId);
            if($before['groupId']===$groupId) return $before;
            if($accountId===(int)$actor['accountId']) throw new ApiException(403,'Không tự thay đổi nhóm quyền của chính mình.','SELF_ROLE_CHANGE_FORBIDDEN');
            if($before['groupName']==='Admin' && $before['status']==='active') {
                $count=Store::one("SELECT COUNT(*) AS n FROM account a JOIN users u ON u.user_id=a.user_id
                    JOIN role_group g ON g.group_id=a.group_id WHERE g.group_name='Admin' AND g.is_deleted=0
                    AND a.is_deleted=0 AND a.status='active' AND u.is_deleted=0")['n'];
                if($count<=1) throw new ApiException(409,'Phải giữ ít nhất một quản trị viên hoạt động.','LAST_ADMIN_REQUIRED');
            }
            self::validateProfile((int)$before['userId'],$group['groupName']);
            Store::execute('UPDATE account SET group_id=? WHERE account_id=?',[$groupId,$accountId]);
            $after=self::account($accountId);
            Store::recordAudit((int)$actor['userId'],'sua','account',$accountId,$before,$after);
            return $after;
        });
    }

    public static function lockAdministrator(array $actor): void
    {
        Store::one("SELECT group_id FROM role_group WHERE group_name='Admin' AND is_deleted=0 FOR UPDATE");
        $operator=self::account((int)$actor['accountId']);
        if($operator['groupName']!=='Admin' || $operator['status']!=='active') {
            throw new ApiException(403,'Quyền quản trị đã thay đổi.','FORBIDDEN');
        }
    }

    public static function candidates(array $filters): array
    {
        $term='%'.($filters['q']??'').'%';
        return Store::rows("SELECT u.user_id AS userId,u.hoten AS hoTen,u.email FROM users u
            WHERE u.is_deleted=0 AND NOT EXISTS (SELECT 1 FROM account a WHERE a.user_id=u.user_id)
            AND (u.hoten LIKE ? OR u.email LIKE ?) ORDER BY u.user_id DESC LIMIT 100",[$term,$term]);
    }

    public static function createAccount(array $data,array $actor): array
    {
        return Store::transaction(function() use($data,$actor) {
            self::lockAdministrator($actor);
            $group=self::group($data['groupId']);
            if(!Store::one('SELECT user_id FROM users WHERE user_id=? AND is_deleted=0 FOR UPDATE',[$data['userId']])) {
                throw new ApiException(404,'Không tìm thấy hồ sơ người dùng.','USER_NOT_FOUND');
            }
            self::validateProfile($data['userId'],$group['groupName']);
            $id=Store::insert('account',['user_id'=>$data['userId'],'group_id'=>$data['groupId'],
                'username'=>$data['username'],'password_hash'=>password_hash($data['password'],PASSWORD_BCRYPT),
                'status'=>$data['status']]);
            $after=self::account($id);
            Store::recordAudit((int)$actor['userId'],'them','account',$id,null,$after);
            return $after;
        });
    }

    public static function updateAccount(int $id,array $data,array $actor): array
    {
        return Store::transaction(function() use($id,$data,$actor) {
            self::lockAdministrator($actor);
            $before=self::account($id,true);
            if($id===(int)$actor['accountId'] && isset($data['status']) && $data['status']!=='active') {
                throw new ApiException(403,'Không tự khóa tài khoản đang sử dụng.','SELF_LOCK_FORBIDDEN');
            }
            if(($data['status']??null)==='active') {
                self::validateProfile((int)$before['userId'],$before['groupName']);
            }
            Store::update('account','account_id',$id,$data,['username'=>'username','status'=>'status']);
            $after=self::account($id);
            Store::recordAudit((int)$actor['userId'],'sua','account',$id,$before,$after);
            if($before['status']!==$after['status']) {
                Store::audit((int)$actor['userId'],'khac',$id,['action'=>'account_status_changed','status'=>$after['status']]);
            }
            return $after;
        });
    }

    public static function resetPassword(int $id,string $password,array $actor): array
    {
        return Store::transaction(function() use($id,$password,$actor) {
            self::lockAdministrator($actor);
            self::account($id,true);
            Store::execute('UPDATE account SET password_hash=? WHERE account_id=?',[password_hash($password,PASSWORD_BCRYPT),$id]);
            Store::audit((int)$actor['userId'],'sua',$id,['action'=>'admin_reset_password']);
            return ['updated'=>true,'requiresLogin'=>true];
        });
    }
}
