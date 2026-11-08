<?php
if(getenv('TEST_WITH_DB')!=='1' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*_test$/D',getenv('DB_NAME')?:'')) {
    fwrite(STDERR,"Refusing administration tests outside an isolated *_test database.\n");exit(1);
}
require __DIR__.'/bootstrap.php';

use App\Shared\Store;
use App\Modules\Administration\Features\Auth\AuthService;

$server=null;
try {
    [$server]=startServer(); $suffix=bin2hex(random_bytes(4));
    $adminName='catalog_'.$suffix;
    AuthService::bootstrap(['username'=>$adminName,'password'=>'Admin@123','hoTen'=>'Admin danh mục',
        'email'=>"admin.catalog.$suffix@example.test",'sdt'=>null]);
    $login=requestOk('/api/auth/login','POST',['username'=>$adminName,'password'=>'Admin@123']);
    $admin=$login['user'];$token=$login['token'];
    $groups=requestOk('/api/administration/permissions/groups','GET',null,$token);
    $groupIds=array_column($groups,'groupId','groupName');
    check(count($groups)===5,'five canonical groups');
    $functions=requestOk('/api/administration/permissions/functions','GET',null,$token);
    check(count($functions)===8,'eight canonical functions');
    $functionIds=array_column($functions,'functionId','functionName');
    check(http('/api/administration/catalog')[0]===401,'catalog requires login');
    check(http('/api/administration/permissions/groups')[0]===401,'permissions require login');

    $parent=Store::insert('users',['hoten'=>'Phụ huynh danh mục','email'=>"parent.catalog.$suffix@example.test"]);
    $parentAccount=Store::insert('account',['user_id'=>$parent,'group_id'=>$groupIds['PhuHuynh'],'username'=>'parent_'.$suffix,
        'password_hash'=>password_hash('Parent@123',PASSWORD_BCRYPT),'status'=>'active']);
    $parentToken=requestOk('/api/auth/login','POST',['username'=>'parent_'.$suffix,'password'=>'Parent@123'])['token'];
    $staff=Store::insert('users',['hoten'=>'Giáo vụ danh mục','email'=>"staff.catalog.$suffix@example.test"]);
    Store::insert('nhan_vien',['user_id'=>$staff,'ma_nv'=>'NV-'.$suffix,'trang_thai'=>'dang_lam']);
    $staffAccount=Store::insert('account',['user_id'=>$staff,'group_id'=>$groupIds['NhanVien'],'username'=>'staff_'.$suffix,
        'password_hash'=>password_hash('Staff@123',PASSWORD_BCRYPT),'status'=>'active']);
    $staffToken=requestOk('/api/auth/login','POST',['username'=>'staff_'.$suffix,'password'=>'Staff@123'])['token'];
    $catalog='/api/administration/catalog';$permissions='/api/administration/permissions';
    $lookups=requestOk($catalog,'GET',null,$parentToken);
    check(count($lookups['subjects'])>=6 && count($lookups['grade-levels'])===12,'parent lookup bootstrap');
    check(http($catalog.'/settings','GET',null,$parentToken)[0]===403,'parent cannot read operational settings');
    check(http($permissions.'/groups','GET',null,$staffToken)[0]===403,'staff cannot administer permissions');
    check(http($permissions.'/accounts','GET',null,$parentToken)[0]===403,'parent cannot enumerate accounts');
    check(http($catalog.'/subjects','POST',['ma'=>'NO','ten'=>'Không được phép'],$staffToken)[0]===403,'staff cannot mutate system catalogs');

    $subject=requestOk($catalog.'/subjects','POST',['ma'=>'M-'.$suffix,'ten'=>'Môn kiểm thử '.$suffix],$token,201);
    check($subject['isActive']===true,'subject default active');
    check(http($catalog.'/subjects','POST',['ma'=>'M-'.$suffix,'ten'=>'Môn khác '.$suffix],$token)[0]===409,'duplicate subject code');
    $changed=requestOk($catalog.'/subjects/'.$subject['id'],'PUT',['ten'=>'Môn đã sửa '.$suffix],$token);
    check($changed['ma']===$subject['ma'] && $changed['ten']!==$subject['ten'],'catalog partial update');
    $search=requestOk($catalog.'/subjects?q='.urlencode($changed['ten']),'GET',null,$parentToken);
    check(count($search)===1 && $search[0]['id']===$subject['id'],'Unicode catalog search');
    requestOk($catalog.'/subjects/'.$subject['id'],'DELETE',null,$token);
    $active=requestOk($catalog.'/subjects?isActive=1','GET',null,$parentToken);
    check(!in_array($subject['id'],array_column($active,'id'),true),'inactive subject excluded from picker');
    check(Store::one('SELECT id FROM mon_hoc WHERE id=?',[$subject['id']])!==null,'deactivation retains referenced row');
    check(requestOk($catalog.'/subjects/'.$subject['id'],'GET',null,$token)['isActive']===false,'inactive detail retained');
    requestOk($catalog.'/subjects/'.$subject['id'],'PUT',['isActive'=>true],$token);
    check(http($catalog.'/subjects/99999999','PUT',['ten'=>'Missing'],$token)[0]===404,'missing catalog item');
    check(http($catalog.'/subjects','POST',['ma'=>str_repeat('X',21),'ten'=>'Quá dài'],$token)[0]===400,'catalog string length');
    check(http($catalog.'/subjects','POST',['ma'=>'Bad','ten'=>'Bad','isActive'=>'false'],$token)[0]===400,'strict boolean');
    check(http($catalog.'/subjects/'.$subject['id'],'PUT',['id'=>999],$token)[0]===400,'immutable id');
    check(http($catalog.'/subjects?isActive=garbage','GET',null,$token)[0]===400,'invalid list filter');
    check(http($catalog.'/account','GET',null,$token)[0]===404,'catalog table whitelist');
    $grade=requestOk($catalog.'/grade-levels/9','GET',null,$token);
    requestOk($catalog.'/grade-levels/9','PUT',['ten'=>'Lớp Chín'],$token);
    requestOk($catalog.'/grade-levels/9','PUT',['ten'=>$grade['ten']],$token);
    check(http($catalog.'/grade-levels/9','DELETE',null,$token)[0]===405,'fixed grade not deleted');
    check(http($catalog.'/grade-levels','POST',['ten'=>'Lớp mới'],$token)[0]===405,'fixed grade not created');
    check(http($catalog.'/grade-levels/9','PUT',['capHoc'=>'thpt'],$token)[0]===400,'grade curriculum identity immutable');

    $region=requestOk($catalog.'/regions','POST',['ten'=>'Khu vực '.$suffix],$token,201);
    check($region['thanhPho']==='TP. Hồ Chí Minh','region city default');
    $campus=requestOk($catalog.'/campuses','POST',['maCoSo'=>'CS-'.$suffix,'tenCoSo'=>'Cơ sở kiểm thử',
        'diaChi'=>'Thủ Đức','email'=>"campus.$suffix@example.test",'khuVucId'=>$region['id'],'quanLyId'=>$staff],$token,201);
    requestOk($catalog.'/regions/'.$region['id'],'DELETE',null,$token);
    check(requestOk($catalog.'/campuses/'.$campus['id'],'GET',null,$token)['khuVucId']===$region['id'],'region deactivation does not erase campus linkage');
    check(http($catalog.'/campuses','POST',['maCoSo'=>'BAD-'.$suffix,'tenCoSo'=>'Không hợp lệ','diaChi'=>'X','khuVucId'=>$region['id']],$token)[0]===400,'cannot assign inactive region');
    check(http($catalog.'/campuses/'.$campus['id'],'PUT',['quanLyId'=>$parent],$token)[0]===400,'campus manager requires employee profile');
    requestOk($catalog.'/campuses/'.$campus['id'],'PUT',['tenCoSo'=>'Cơ sở mới','email'=>null],$token);
    requestOk($catalog.'/campuses/'.$campus['id'],'DELETE',null,$token);
    requestOk($catalog.'/regions/'.$region['id'],'PUT',['isActive'=>true],$token);
    $settings=requestOk($catalog.'/settings','GET',null,$token);
    $initialSetting=array_column($settings,'giaTri','khoa')['checkin_truoc_phut'];
    check(requestOk($catalog.'/settings/checkin_truoc_phut','PUT',['giaTri'=>45],$token)['giaTri']==='45','integer setting persisted as string');
    requestOk($catalog.'/settings/checkin_truoc_phut','PUT',['giaTri'=>(int)$initialSetting],$token);
    check(http($catalog.'/settings/checkin_truoc_phut','PUT',['giaTri'=>-1],$token)[0]===400,'negative setting denied');
    check(http($catalog.'/settings/han_nhap_bao_cao_gio','PUT',['giaTri'=>0],$token)[0]===400,'report deadline must be positive');
    check(http($catalog.'/settings/JWT_SECRET','PUT',['giaTri'=>1],$token)[0]===404,'setting key whitelist');

    $staffGroup=$groupIds['NhanVien'];
    $initial=requestOk("$permissions/groups/$staffGroup/permissions",'GET',null,$token);
    check(count($initial['permissions'])===8,'matrix includes absent deny rows');
    check(http('/api/people/teachers','GET',null,$staffToken)[0]===403,'staff initially lacks personnel permission');
    $payload=['version'=>$initial['version'],'permissions'=>[]];
    foreach($initial['permissions'] as $row) {
        unset($row['functionName']);
        if($row['functionId']===$functionIds['QuanLyNhanSu']) { $row['view']=true;$row['add']=true; }
        $payload['permissions'][]=$row;
    }
    $saved=requestOk("$permissions/groups/$staffGroup/permissions",'PUT',$payload,$token);
    check($saved['version']!==$initial['version'],'matrix version changes');
    requestOk('/api/people/teachers','GET',null,$staffToken);
    check(http("$permissions/groups/$staffGroup/permissions",'PUT',$payload,$token)[0]===409,'stale matrix rejected');
    $bad=['version'=>$saved['version'],'permissions'=>$payload['permissions']];
    array_pop($bad['permissions']);
    check(http("$permissions/groups/$staffGroup/permissions",'PUT',$bad,$token)[0]===400,'partial matrix rejected');
    check(requestOk("$permissions/groups/$staffGroup/permissions",'GET',null,$token)['version']===$saved['version'],'invalid matrix rolls back');
    $bad=['version'=>$saved['version'],'permissions'=>$payload['permissions']];
    $bad['permissions'][]=$bad['permissions'][0];
    check(http("$permissions/groups/$staffGroup/permissions",'PUT',$bad,$token)[0]===400,'duplicate matrix row rejected');
    $restore=[];
    foreach($initial['permissions'] as $row) { unset($row['functionName']);$restore[]=$row; }
    requestOk("$permissions/groups/$staffGroup/permissions",'PUT',['version'=>$saved['version'],'permissions'=>$restore],$token);
    check(http('/api/people/teachers','GET',null,$staffToken)[0]===403,'revoked right takes effect on existing token');
    $adminMatrix=requestOk($permissions.'/groups/'.$groupIds['Admin'].'/permissions','GET',null,$token);
    check(!in_array(false,array_column($adminMatrix['permissions'],'delete'),true),'admin effective full rights');
    $adminPayload=['version'=>$adminMatrix['version'],'permissions'=>$restore];
    check(http($permissions.'/groups/'.$groupIds['Admin'].'/permissions','PUT',$adminPayload,$token)[0]===400,'admin matrix immutable');
    check(http($permissions.'/groups/999999/permissions','GET',null,$token)[0]===404,'unknown group');
    requestOk("$permissions/groups/$staffGroup",'PUT',['description'=>'Giáo vụ kiểm thử'],$token);
    requestOk("$permissions/groups/$staffGroup",'PUT',['description'=>$initial['group']['description']],$token);

    $accounts=requestOk($permissions.'/accounts?q='.urlencode('staff_'.$suffix).'&perPage=1','GET',null,$token);
    check($accounts['total']===1 && count($accounts['items'])===1,'account search and pagination');
    check(!str_contains(json_encode($accounts),'password_hash'),'account API excludes credentials');
    check(http($permissions.'/accounts?perPage=101','GET',null,$token)[0]===400,'bounded pagination');
    check(http($permissions.'/accounts/'.$admin['accountId'].'/group','PUT',['groupId'=>$groupIds['PhuHuynh']],$token)[0]===403,'cannot demote oneself');
    check(http($permissions.'/accounts/'.$parentAccount.'/group','PUT',['groupId'=>$staffGroup],$token)[0]===400,'employee role requires employee profile');
    check(http($permissions.'/accounts/'.$parentAccount.'/group','PUT',['groupId'=>$groupIds['GiangVien']],$token)[0]===400,'teacher role requires professional profile');
    check(http($permissions.'/accounts/'.$staffAccount.'/group','PUT',['groupId'=>$groupIds['Admin']],$staffToken)[0]===403,'staff cannot escalate role');
    requestOk($permissions.'/accounts/'.$staffAccount.'/group','PUT',['groupId'=>$groupIds['PhuHuynh']],$token);
    check(requestOk('/api/auth/me','GET',null,$staffToken)['user']['role']==='PHU_HUYNH','role reassignment reflected without relogin');
    check(http('/api/people/students','POST',['maHv'=>'NO','hoTen'=>'Không','phuHuynhUserId'=>$parent],$staffToken)[0]===403,'stale staff JWT cannot write after reassignment');
    requestOk($permissions.'/accounts/'.$staffAccount.'/group','PUT',['groupId'=>$staffGroup],$token);
    check(requestOk('/api/auth/me','GET',null,$staffToken)['user']['role']==='GIAO_VU','staff role restored');
    $otherName='other_admin_'.$suffix;
    AuthService::bootstrap(['username'=>$otherName,'password'=>'Other@123','hoTen'=>'Admin khác','email'=>"other.$suffix@example.test",'sdt'=>null]);
    $other=requestOk('/api/auth/login','POST',['username'=>$otherName,'password'=>'Other@123']);
    requestOk($permissions.'/accounts/'.$other['user']['accountId'].'/group','PUT',['groupId'=>$groupIds['PhuHuynh']],$token);
    check(http($permissions.'/groups','GET',null,$other['token'])[0]===403,'demoted admin immediately loses management access');
    check(Store::one("SELECT COUNT(*) AS n FROM audit_log WHERE user_id=? AND bang='role_group_function'",[$admin['userId']])['n']===2,'matrix writes audited, rejected writes not audited');
    check(Store::one("SELECT COUNT(*) AS n FROM audit_log WHERE user_id=? AND bang IN ('mon_hoc','khu_vuc','co_so','cap_lop','cau_hinh')",[$admin['userId']])['n']>=12,'catalog writes audited');
    echo "PASS: $assertions administration HTTP/MySQL assertions.\n";
} catch(\Throwable $error) { fwrite(STDERR,$error->getMessage()."\n");$failed=true; }
finally { if(is_resource($server)) { proc_terminate($server);proc_close($server); } }
exit(isset($failed)?1:0);
