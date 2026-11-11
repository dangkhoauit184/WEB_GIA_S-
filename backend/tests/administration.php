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
    check(http($catalog.'/settings/checkin_truoc_phut','PUT',['giaTri'=>45],$token)[0]===400,'fixed unlimited checkin policy cannot be overridden');
    requestOk($catalog.'/settings/checkin_truoc_phut','PUT',['giaTri'=>(int)$initialSetting],$token);
    check(http($catalog.'/settings/checkin_truoc_phut','PUT',['giaTri'=>-2],$token)[0]===400,'values below unlimited sentinel denied');
    check(requestOk($catalog.'/settings/checkout_sau_phut','PUT',['giaTri'=>-1],$token)['giaTri']==='-1','unlimited checkout policy persisted');
    check(requestOk($catalog.'/settings/checkin_minh_chung','PUT',['giaTri'=>'khong'],$token)['giaTri']==='khong','no proof policy persisted');
    check(http($catalog.'/settings/checkin_minh_chung','PUT',['giaTri'=>'gps'],$token)[0]===400,'proof collection not enabled by current policy');
    check(http($catalog.'/settings/han_nhap_bao_cao_gio','PUT',['giaTri'=>0],$token)[0]===400,'report deadline must be positive');
    check(http($catalog.'/settings/JWT_SECRET','PUT',['giaTri'=>1],$token)[0]===404,'setting key whitelist');
    check(http($catalog.'/settings/checkin_tre_sau_phut','PUT',['giaTri'=>15],$token)[0]===400,'late threshold stays zero');
    $course=requestOk($catalog.'/courses','POST',['ma'=>'K-'.$suffix,'ten'=>'Khóa cơ bản học kỳ 1 đầy đủ','hocPhi'=>1200000],$token,201);
    check($course['hocPhi']===1200000 && $course['isActive'],'course created');
    check(http($catalog.'/courses','POST',['ma'=>'K-'.$suffix,'ten'=>'Trùng','hocPhi'=>1],$token)[0]===409,'duplicate course code');
    check(http($catalog.'/courses','POST',['ma'=>'FRAC-'.$suffix,'ten'=>'Lỗi','hocPhi'=>1.5],$token)[0]===400,'VND course fee is an integer');
    check(http($catalog.'/courses','GET',null,$staffToken)[0]===403,'course administration is admin only');
    requestOk($catalog.'/courses/'.$course['id'],'PUT',['hocPhi'=>1500000],$token);
    requestOk($catalog.'/courses/'.$course['id'],'DELETE',null,$token);
    check(Store::one('SELECT is_active FROM khoa_hoc WHERE id=?',[$course['id']])['is_active']===0,'course deactivation preserves row');

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
    $salaryPayload=['version'=>$saved['version'],'permissions'=>$payload['permissions']];
    foreach($salaryPayload['permissions'] as &$salaryRow) {
        if($salaryRow['functionId']===$functionIds['ChamCongLuong']) $salaryRow['view']=true;
    }
    unset($salaryRow);
    check(http("$permissions/groups/$staffGroup/permissions",'PUT',$salaryPayload,$token)[0]===400,'non admin cannot be granted salary access');
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
    $candidate=Store::insert('users',['hoten'=>'Hồ sơ chưa có tài khoản','email'=>"candidate.$suffix@example.test"]);
    $candidates=requestOk($permissions.'/account-candidates?q='.urlencode("candidate.$suffix"),'GET',null,$token);
    check(in_array($candidate,array_column($candidates,'userId'),true),'candidate lookup finds unassigned profile');
    $created=requestOk($permissions.'/accounts','POST',['userId'=>$candidate,'groupId'=>$groupIds['PhuHuynh'],
        'username'=>'created_'.$suffix,'password'=>'Initial@123'],$token,201);
    $createdId=$created['accountId'];
    check(!str_contains(json_encode($created),'password'),'created account excludes password');
    $oldToken=requestOk('/api/auth/login','POST',['username'=>'created_'.$suffix,'password'=>'Initial@123'])['token'];
    requestOk($permissions.'/accounts/'.$createdId,'PUT',['status'=>'locked'],$token);
    check(http('/api/auth/me','GET',null,$oldToken)[0]===401,'locking invalidates the authenticated session');
    requestOk($permissions.'/accounts/'.$createdId,'PUT',['status'=>'active'],$token);
    check(http('/api/auth/me','GET',null,$oldToken)[0]===401,'unlocking does not restore revoked sessions');
    $newToken=requestOk('/api/auth/login','POST',['username'=>'created_'.$suffix,'password'=>'Initial@123'])['token'];
    requestOk($permissions.'/accounts/'.$createdId.'/password','PUT',['password'=>'Changed@123'],$token);
    check(http('/api/auth/me','GET',null,$newToken)[0]===401,'reset password revokes old session');
    check(http('/api/auth/login','POST',['username'=>'created_'.$suffix,'password'=>'Initial@123'])[0]===401,'old password rejected');
    requestOk('/api/auth/login','POST',['username'=>'created_'.$suffix,'password'=>'Changed@123']);
    check(http($permissions.'/accounts/'.$admin['accountId'],'PUT',['status'=>'locked'],$token)[0]===403,'self lock is forbidden');
    check(http($permissions.'/accounts/'.$createdId.'/password','PUT',['password'=>'NoRight@123'],$staffToken)[0]===403,'non admin cannot reset passwords');
    check(http($permissions.'/accounts','POST',['userId'=>$candidate,'groupId'=>$groupIds['PhuHuynh'],'username'=>'dup_'.$suffix,'password'=>'Duplicate@123'],$token)[0]===409,'one account per user');
    Store::recordAudit((int)$admin['userId'],'khac','account',$createdId,null,['password_hash'=>'sensitive','nested'=>['tokenHash'=>'sensitive']]);
    $sensitiveId=(int)Store::db()->insertID();
    $audit=requestOk('/api/administration/audit?bang=account&perPage=100','GET',null,$token);
    check($audit['total']>0,'audit pagination');
    $sensitive=requestOk('/api/administration/audit/'.$sensitiveId,'GET',null,$token);
    check($sensitive['duLieuMoi']['password_hash']==='[đã ẩn]' && $sensitive['duLieuMoi']['nested']['tokenHash']==='[đã ẩn]','audit recursively redacts secrets');
    check(http('/api/administration/audit','GET',null,$staffToken)[0]===403,'audit is admin only');
    check(http('/api/administration/audit?tuNgay=2026-11-20&denNgay=2026-11-01','GET',null,$token)[0]===400,'audit date order validated');
    check(http('/api/administration/audit/99999999','GET',null,$token)[0]===404,'missing audit returns 404');
    $summary=requestOk('/api/reporting/dashboard?month=2026-11','GET',null,$token);
    check(count($summary['revenue'])===4 && isset($summary['counts']['debt']),'dashboard returns four months and debt');
    check(http('/api/reporting/dashboard','GET',null,$staffToken)[0]===403,'admin dashboard blocked for staff');
    check(http('/api/reporting/dashboard?month=2026-99','GET',null,$token)[0]===400,'invalid dashboard month');
    check(http('/admin')[0]===200,'administration entry loads');
    check(http('/admin-assets/administration.js')[0]===200,'administration assets served');
    check(http('/admin-assets/server.env')[0]===404,'asset whitelist blocks private paths');

    // Approved prototype flows use the same isolated database as the administration suite.
    $phone='09'.str_pad((string)random_int(0,99999999),8,'0',STR_PAD_LEFT);
    $person=requestOk('/api/administration/staff','POST',['hoTen'=>'Người dạy quản trị','email'=>"operator.$suffix@example.test",
        'sdt'=>$phone,'role'=>'giao_vien','username'=>'operator_'.$suffix,'password'=>'Operator@123','donGiaGio'=>180000],$token,201);
    $personId=(int)$person['user']['userId']; $nsId=(int)$person['professional']['id'];
    check($person['professional']['donGiaGioChuan']==180000,'staff creation includes professional profile and initial rate');
    check(Store::one('SELECT COUNT(*) AS n FROM lich_su_don_gia_gio WHERE nhan_su_id=?',[$nsId])['n']===1,'initial rate history recorded');
    requestOk('/api/administration/staff','GET',null,$token);
    check(http('/api/administration/staff','GET',null,$staffToken)[0]===403,'personnel administration is admin only');
    $operatorToken=requestOk('/api/auth/login','POST',['username'=>'operator_'.$suffix,'password'=>'Operator@123'])['token'];
    $own=requestOk('/api/people/teachers/'.$nsId,'GET',null,$operatorToken);
    check(!array_key_exists('donGiaGioChuan',$own),'teacher cannot read hourly wage through legacy profile');
    requestOk('/api/administration/staff/'.$personId.'/profile','PUT',['monDays'=>[['monHocId'=>1,'capLopTu'=>1,'capLopDen'=>12,'hocPhiBuoiDeXuat'=>150000]],
        'lichRanhs'=>[['thu'=>2,'gioBatDau'=>'18:00','gioKetThuc'=>'20:00']],'nganHang'=>'Test bank'],$token);
    check(count(requestOk('/api/administration/staff/'.$personId.'/profile','GET',null,$token)['professional']['lichRanhs'])===1,'professional subjects and availability persisted');
    check(http('/api/people/teachers/'.$nsId,'PUT',['donGiaGioChuan'=>1],$token)[0]===400,'legacy rate change must use snapshot-aware procedure');
    $room=requestOk($catalog.'/rooms','POST',['ten'=>'Phong-'.$suffix],$token,201);
    check(http($catalog.'/rooms','POST',['ten'=>'Phong-'.$suffix],$token)[0]===409,'duplicate room label rejected');
    requestOk($catalog.'/rooms','GET',null,$token);
    $activeCourse=requestOk($catalog.'/courses','POST',['ma'=>'LIVE-'.$suffix,'ten'=>'Khóa theo prototype','hocPhi'=>1950000],$token,201);
    $studentId=Store::insert('hoc_vien',['ma_hv'=>'HVA-'.$suffix,'ho_ten'=>'Học viên prototype','phu_huynh_user_id'=>$parent,'cap_lop_id'=>9]);
    $registrationId=Store::insert('dang_ky_khoa_hoc',['khoa_hoc_id'=>$activeCourse['id'],'hoc_vien_id'=>$studentId,'hoc_phi_snapshot'=>1950000]);
    $day=(new DateTimeImmutable('yesterday',new DateTimeZone('Asia/Ho_Chi_Minh')))->format('Y-m-d');
    $future=(new DateTimeImmutable($day))->modify('+2 months')->format('Y-m-d');
    $class=requestOk('/api/academic/classes','POST',['maLop'=>'LPA-'.$suffix,'tenLop'=>'Lớp quản trị','loaiLop'=>'nhom',
        'monHocId'=>1,'capLopId'=>9,'nguoiDayChinhId'=>$nsId,'khoaHocId'=>$activeCourse['id'],
        'siSoToiDa'=>2,'ngayBatDau'=>$day,'cachThuPhi'=>'theo_khoa','cachTinhLuong'=>'theo_gio'],$token,201);
    $classId=(int)$class['id'];
    $placement=requestOk('/api/academic/classes/registrations/'.$registrationId.'/assign','POST',['lopId'=>$classId],$token);
    check((int)$placement['xepBoi']===(int)$admin['userId'],'admin may place an unpaid registration without an invoice');
    check(requestOk('/api/academic/classes/registrations/'.$registrationId.'/assign','POST',['lopId'=>$classId],$token)['id']===$placement['id'],'repeat placement is idempotent');
    check(http('/api/academic/classes/registrations/'.$registrationId.'/assign','POST',['lopId'=>$classId],$staffToken)[0]===403,'staff cannot place students');
    check(http('/api/academic/classes/'.$classId.'/enrollments','POST',['hocVienId'=>$studentId],$staffToken)[0]===403,'legacy placement also requires admin');
    check(!array_key_exists('mucTraChinh',requestOk('/api/academic/classes/'.$classId,'GET',null,$operatorToken)),'class response hides wage from teacher');
    requestOk($catalog.'/courses/'.$activeCourse['id'],'PUT',['hocPhi'=>2300000],$token);
    check((int)Store::one('SELECT hoc_phi_snapshot FROM dang_ky_khoa_hoc WHERE id=?',[$registrationId])['hoc_phi_snapshot']===1950000,'editing course does not rewrite registration price');
    $sessionBody=['lopId'=>$classId,'ngay'=>$day,'gioBatDau'=>'18:00','gioKetThuc'=>'20:00','userId'=>$personId,'troGiangIds'=>[],
        'phongHoc'=>'Phong-'.$suffix,'repeatCount'=>1];
    $assistantIds=[];
    foreach(['tro_giang','giao_vien'] as $index=>$type) {
        $assistantId=Store::insert('users',['hoten'=>'Kiểm tra vai trò '.$index,'email'=>"role.$index.$suffix@example.test"]);
        Store::insert('nhan_su_chuyen_mon',['user_id'=>$assistantId,'ma_ns'=>'ROLE-'.$index.'-'.$suffix,'phan_loai'=>$type]);
        $assistantIds[]=$assistantId;
    }
    check(http('/api/academic/scheduling/admin-sessions','POST',array_replace($sessionBody,['troGiangIds'=>$assistantIds]),$token)[0]===400,'every assistant must have the assistant profile, including the second');
    check(!Store::one('SELECT id FROM buoi_hoc WHERE lop_id=?',[$classId]),'invalid secondary assistant leaves no partial session');
    $sessionId=requestOk('/api/academic/scheduling/admin-sessions','POST',$sessionBody,$token,201)['ids'][0];
    $pc=Store::one('SELECT * FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=? AND user_id=?',[$sessionId,$personId]);
    check((int)$pc['don_gia']===180000,'new session snapshots the person hourly rate');
    Store::execute("UPDATE phan_cong_buoi_hoc SET checkin_luc=?,checkout_luc=?,trang_thai='da_day',trang_thai_cong='hop_le' WHERE id=?",[$day.' 18:10:00',$day.' 20:05:00',$pc['id']]);
    Store::execute("UPDATE buoi_hoc SET trang_thai='da_day' WHERE id=?",[$sessionId]);
    $adjustment=Store::insert('dieu_chinh_cong',['phan_cong_id'=>$pc['id'],'loai'=>'sua_gio','checkin_cu'=>$day.' 18:10:00','checkout_cu'=>$day.' 20:05:00',
        'checkin_moi'=>$day.' 18:00:00','checkout_moi'=>$day.' 20:00:00','ly_do'=>'Điều chỉnh có xác nhận','nguoi_yeu_cau_id'=>$personId]);
    $period=substr($day,0,7);
    check(http('/api/finance/payroll/close','POST',['userId'=>$personId,'month'=>$period],$token)[0]===400,'pending corrections prevent closing payroll');
    check(http('/api/finance/timekeeping/corrections/'.$adjustment.'/decision','POST',['trangThai'=>'da_duyet'],$staffToken)[0]===403,'staff cannot approve time corrections');
    requestOk('/api/finance/timekeeping/corrections/'.$adjustment.'/decision','POST',['trangThai'=>'da_duyet'],$token);
    requestOk('/api/finance/timekeeping/corrections/'.$adjustment.'/decision','POST',['trangThai'=>'da_duyet'],$token);
    check((int)Store::one('SELECT so_phut_tinh_cong FROM phan_cong_buoi_hoc WHERE id=?',[$pc['id']])['so_phut_tinh_cong']===120,'approval atomically applies corrected hours');
    $futureBody=array_merge($sessionBody,['ngay'=>$future]);
    $futureId=requestOk('/api/academic/scheduling/admin-sessions','POST',$futureBody,$token,201)['ids'][0];

    $editedFuture=array_merge($futureBody,['gioBatDau'=>'18:30','gioKetThuc'=>'20:30','phongHoc'=>'Ca-'.$suffix]);
    requestOk('/api/academic/scheduling/admin-sessions/'.$futureId,'PUT',$editedFuture,$token);
    check(in_array('Ca-'.$suffix,array_column(requestOk($catalog.'/rooms','GET',null,$token),'ten'),true),'room catalogue includes a label used only by a session');
    check(Store::one('SELECT gio_bat_dau FROM buoi_hoc WHERE id=?',[$futureId])['gio_bat_dau']==='18:30:00','editing unstarted session updates date/time');
    check(Store::one('SELECT gio_lich_bat_dau FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=? AND trang_thai<>\'da_go\'',[$futureId])['gio_lich_bat_dau']===$future.' 18:30:00','edited assignment refreshes schedule snapshot');
    check(http('/api/academic/scheduling/admin-sessions/'.$sessionId,'PUT',$sessionBody,$token)[0]===409,'completed session cannot be silently rescheduled');

    requestOk('/api/finance/payroll/rates/'.$nsId,'PUT',['donGiaGio'=>195000],$token);
    check((int)Store::one('SELECT don_gia FROM phan_cong_buoi_hoc WHERE id=?',[$pc['id']])['don_gia']===180000,'rate increase keeps started session price');
    check((int)Store::one('SELECT don_gia FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=?',[$futureId])['don_gia']===195000,'rate increase updates future unstarted session');
    $summary=requestOk('/api/finance/payroll/close','POST',['userId'=>$personId,'month'=>$period],$token);
    $payrollRow=array_values(array_filter($summary['items'],fn($row)=>(int)$row['userId']===$personId))[0];
    check((int)$payrollRow['thucNhan']===360000,'payroll uses historical snapshot rather than current rate');
    $payrollId=(int)$payrollRow['id'];
    requestOk('/api/finance/payroll/'.$payrollId.'/unlock','POST',['lyDo'=>'Kiểm tra trước chi trả'],$token);
    requestOk('/api/finance/payroll/close','POST',['userId'=>$personId,'month'=>$period],$token);
    requestOk('/api/finance/payroll/'.$payrollId.'/pay','POST',['maGiaoDich'=>'SAL-'.$suffix],$token);
    requestOk('/api/finance/payroll/'.$payrollId.'/pay','POST',['maGiaoDich'=>'SAL-'.$suffix],$token);
    check(http('/api/finance/payroll/'.$payrollId.'/unlock','POST',['lyDo'=>'Sai quy trình'],$token)[0]===400,'paid payroll remains protected');
    requestOk('/api/finance/payroll/'.$personId.'/sessions?month='.$period,'GET',null,$token);
    $classLeave=Store::insert('yeu_cau_nghi_lop',['buoi_hoc_id'=>$futureId,'nguoi_gui_id'=>$staff,'ly_do'=>'Nghỉ cả lớp theo thông báo']);
    requestOk('/api/academic/leave-requests/classes/'.$classLeave.'/decision','POST',['trangThai'=>'da_duyet'],$token);
    check(Store::one('SELECT trang_thai FROM buoi_hoc WHERE id=?',[$futureId])['trang_thai']==='huy','class leave approval cancels the source session');
    check(http('/api/academic/leave-requests/classes/'.$classLeave.'/decision','POST',['trangThai'=>'tu_choi'],$token)[0]===409,'cannot change a processed class decision');
    $makeupBody=array_merge($futureBody,['ngay'=>(new DateTimeImmutable($future))->modify('+1 day')->format('Y-m-d'),
        'loai'=>'bu','buoiGocId'=>$futureId,'phamViBu'=>'ca_lop']);
    requestOk('/api/academic/scheduling/admin-sessions','POST',$makeupBody,$token,201);
    $personalBody=array_merge($futureBody,['ngay'=>(new DateTimeImmutable($future))->modify('+2 days')->format('Y-m-d'),
        'loai'=>'bu','buoiGocId'=>$sessionId,'phamViBu'=>'ca_nhan','hocVienIds'=>[$studentId],'ghiChuBu'=>'Đã chốt ngoài hệ thống']);
    $personalId=requestOk('/api/academic/scheduling/admin-sessions','POST',$personalBody,$token,201)['ids'][0];
    check((int)Store::one('SELECT hoc_vien_id FROM hoc_bu_hoc_vien WHERE buoi_bu_id=?',[$personalId])['hoc_vien_id']===$studentId,'individual makeup links the agreed student without quota/fee');
    $invoiceId=Store::insert('hoa_don',['ma_hd'=>'HDA-'.$suffix,'dang_ky_khoa_hoc_id'=>$registrationId,'tong_tien'=>1950000,'nguoi_lap_id'=>$staff]);
    Store::execute("UPDATE hoa_don SET trang_thai='chua_thanh_toan',phat_hanh_boi=?,phat_hanh_luc=NOW() WHERE id=?",[$staff,$invoiceId]);
    $transactionId=Store::insert('giao_dich_truc_tuyen',['ma_yeu_cau'=>'GDA-'.$suffix,'hoa_don_id'=>$invoiceId,'phu_huynh_id'=>$parent,'nha_cung_cap'=>'sandbox','so_tien'=>1950000]);
    check(http('/api/finance/tuition/transactions/'.$transactionId.'/confirm','POST',[],$token)[0]===400,'initial transaction cannot be treated as received money');
    Store::execute("UPDATE giao_dich_truc_tuyen SET trang_thai='cho_xac_nhan' WHERE id=?",[$transactionId]);
    check(http('/api/finance/tuition/transactions/'.$transactionId.'/confirm','POST',[],$staffToken)[0]===403,'staff cannot confirm receipt');
    requestOk('/api/finance/tuition/transactions','GET',null,$token);
    requestOk('/api/finance/tuition/transactions/'.$transactionId.'/confirm','POST',[],$token);
    requestOk('/api/finance/tuition/transactions/'.$transactionId.'/confirm','POST',[],$token);
    check(Store::one('SELECT COUNT(*) AS n FROM thanh_toan WHERE hoa_don_id=?',[$invoiceId])['n']===1,'repeated reconciliation creates one receipt');
    check((int)Store::one('SELECT da_thu FROM hoa_don WHERE id=?',[$invoiceId])['da_thu']===1950000,'reconciliation updates the invoice balance');

    // Admissions uses the existing feature files and an isolated database only.
    $admissions='/api/academic/admissions';
    check(http($admissions.'/requests')[0]===401,'intake requires authentication');
    check(http($admissions.'/requests','GET',null,$parentToken)[0]===403,'parents cannot enumerate intake requests');
    check(http($admissions.'/registrations','GET',null,$operatorToken)[0]===403,'teachers cannot enumerate course registrations');
    check(http($admissions.'/lookups','GET',null,$parentToken)[0]===403,'parent cannot search unrelated intake students');
    $intakeParent=requestOk('/api/people/parents','POST',['hoTen'=>'Phụ huynh tuyển sinh','email'=>"intake.parent.$suffix@example.test",
        'username'=>'intake_parent_'.$suffix,'password'=>'Parent@123'],$token,201);
    $intakeParentToken=requestOk('/api/auth/login','POST',['username'=>'intake_parent_'.$suffix,'password'=>'Parent@123'])['token'];
    $intakeStudent=requestOk('/api/people/students','POST',['maHv'=>'INT-'.$suffix,'hoTen'=>'Học viên tuyển sinh',
        'phuHuynhUserId'=>$intakeParent['userId'],'capLopId'=>9,'khuVucId'=>1],$token,201);
    $intakeTeacherInput=['maNs'=>'INT-NS-'.$suffix,'hoTen'=>'Người dạy tuyển sinh','email'=>"intake.teacher.$suffix@example.test",
        'username'=>'intake_teacher_'.$suffix,'password'=>'Teacher@123','phanLoai'=>['giao_vien','gia_su'],'hinhThucDay'=>['online','offline'],
        'donGiaGioChuan'=>333333,'kinhNghiemNam'=>3,'nganHang'=>'PRIVATE_BANK',
        'monDays'=>[['monHocId'=>1,'capLopTu'=>6,'capLopDen'=>9,'hocPhiBuoiDeXuat'=>180000]],'khuVucIds'=>[1],
        'lichRanhs'=>[['thu'=>3,'gioBatDau'=>'17:00','gioKetThuc'=>'21:00'],['thu'=>5,'gioBatDau'=>'17:00','gioKetThuc'=>'21:00']]];
    $intakeTeacher=requestOk('/api/people/teachers','POST',$intakeTeacherInput,$token,201);
    $intakeTeacher2=requestOk('/api/people/teachers','POST',array_replace($intakeTeacherInput,['maNs'=>'INT-ALT-'.$suffix,
        'email'=>"intake.alt.$suffix@example.test",'username'=>'intake_alt_'.$suffix,'hoTen'=>'Người dạy thay thế']),$token,201);
    $intakeTeacherToken=requestOk('/api/auth/login','POST',['username'=>'intake_teacher_'.$suffix,'password'=>'Teacher@123'])['token'];
    $intakeTeacher2Token=requestOk('/api/auth/login','POST',['username'=>'intake_alt_'.$suffix,'password'=>'Teacher@123'])['token'];
    $intakeBody=['hocVienId'=>$intakeStudent['id'],'monHocId'=>1,'capLopId'=>9,'khuVucId'=>1,
        'nganSachMin'=>1000000,'nganSachMax'=>2000000,'donViNganSach'=>'theo_khoa',
        'lichYeuCau'=>[['thu'=>3,'gioBatDau'=>'18:00','gioKetThuc'=>'20:00'],['thu'=>5,'gioBatDau'=>'18:00','gioKetThuc'=>'20:00']]];
    $intakeRequest=requestOk($admissions.'/requests','POST',$intakeBody,$staffToken,201);
    $intakeId=$intakeRequest['id'];
    check($intakeRequest['trangThai']==='moi' && count($intakeRequest['lichYeuCau'])===2,'intake starts new and saves all weekly requirements');
    check((int)$intakeRequest['nguoiGuiId']===$staff,'intake author comes from authenticated account');
    check(http($admissions.'/requests','POST',$intakeBody+['nhanVienId'=>999],$staffToken)[0]===400,'client cannot forge intake operator');
    check(http($admissions.'/requests','POST',array_replace($intakeBody,['nganSachMin'=>3000000]),$staffToken)[0]===400,'inverted budget rejected');
    check(http($admissions.'/requests','POST',array_replace($intakeBody,['donViNganSach'=>null]),$staffToken)[0]===400,'budget requires explicit unit');
    check(http($admissions.'/requests','POST',array_replace($intakeBody,['lichYeuCau'=>[]]),$staffToken)[0]===400,'intake requires a matching schedule');
    check(http($admissions.'/requests','POST',array_replace($intakeBody,['lichYeuCau'=>[
        ['thu'=>3,'gioBatDau'=>'18:00','gioKetThuc'=>'20:00'],['thu'=>3,'gioBatDau'=>'19:00','gioKetThuc'=>'21:00']]]),$staffToken)[0]===400,'overlapping requested slots rejected');
    $intakeMatches=requestOk($admissions.'/requests/'.$intakeId.'/candidates','GET',null,$staffToken);
    check(in_array($intakeTeacher['id'],array_column($intakeMatches,'id'),true),'matching covers all slots, subject, grade and region');
    check($intakeMatches[0]['nganSachCanThoaThuan']===true,'course budget is never equated to per-session profile price');
    $intakeSerialized=json_encode($intakeMatches);
    check(!str_contains($intakeSerialized,'donGia') && !str_contains($intakeSerialized,'nganHang') && !str_contains($intakeSerialized,'PRIVATE_BANK'),'matching response contains no internal pay or bank fields');
    Store::execute('DELETE FROM nhan_su_lich_ranh WHERE nhan_su_id=? AND thu=5',[$intakeTeacher['id']]);
    check(!in_array($intakeTeacher['id'],array_column(requestOk($admissions.'/requests/'.$intakeId.'/candidates','GET',null,$staffToken),'id'),true),'candidate missing one requested slot excluded');
    Store::insert('nhan_su_lich_ranh',['nhan_su_id'=>$intakeTeacher['id'],'thu'=>5,'gio_bat_dau'=>'17:00','gio_ket_thuc'=>'21:00']);
    $intakeClass=requestOk('/api/academic/classes','POST',['maLop'=>'INT-CL-'.$suffix,'tenLop'=>'Lịch trùng tuyển sinh',
        'monHocId'=>1,'capLopId'=>9,'nguoiDayChinhId'=>$intakeTeacher['id'],'ngayBatDau'=>date('Y-m-d'),
        'cachThuPhi'=>'theo_khoa','cachTinhLuong'=>'theo_gio'],$token,201);
    $intakeWeekly=Store::insert('lich_hoc',['lop_id'=>$intakeClass['id'],'thu'=>3,'gio_bat_dau'=>'19:00','gio_ket_thuc'=>'21:00','hieu_luc_tu'=>date('Y-m-d')]);
    check(!in_array($intakeTeacher['id'],array_column(requestOk($admissions.'/requests/'.$intakeId.'/candidates','GET',null,$staffToken),'id'),true),'future weekly conflicts excluded before sessions are generated');
    check(http($admissions.'/requests/'.$intakeId.'/proposals','POST',['nhanSuId'=>$intakeTeacher['id']],$staffToken)[0]===409,'proposal rechecks candidate availability');
    Store::execute('UPDATE lich_hoc SET is_active=0 WHERE id=?',[$intakeWeekly]);
    $intakeReceived=requestOk($admissions.'/requests/'.$intakeId.'/receive','POST',[],$staffToken);
    check($intakeReceived['trangThai']==='dang_tim','receive moves new intake to matching');
    $intakeProposal=requestOk($admissions.'/requests/'.$intakeId.'/proposals','POST',['nhanSuId'=>$intakeTeacher['id'],'hocPhiDeXuat'=>1500000],$staffToken,201);
    $intakeProposal2=requestOk($admissions.'/requests/'.$intakeId.'/proposals','POST',['nhanSuId'=>$intakeTeacher2['id']],$staffToken,201);
    check($intakeProposal['ttNhanSu']==='cho' && $intakeProposal['ttPhuHuynh']==='cho' && $intakeProposal['cachThuPhiDeXuat']==='theo_khoa','proposal waits for two independent responses');
    check(http($admissions.'/requests/'.$intakeId.'/proposals','POST',['nhanSuId'=>$intakeTeacher['id']],$staffToken)[0]===409,'duplicate proposal prevented');
    check(http($admissions.'/requests/'.$intakeId,'PUT',$intakeBody,$staffToken)[0]===409,'published proposal criteria cannot be silently changed');
    check(http($admissions.'/proposals/'.$intakeProposal['id'].'/respond','POST',['trangThai'=>'dong_y'],$staffToken)[0]===403,'staff cannot impersonate either response');
    check(http($admissions.'/proposals/'.$intakeProposal['id'].'/respond','POST',['trangThai'=>'dong_y'],$token)[0]===403,'admin cannot impersonate participant response');
    check(http($admissions.'/proposals/'.$intakeProposal['id'].'/respond','POST',['trangThai'=>'dong_y'],$parentToken)[0]===404,'unrelated parent cannot respond');
    check(http($admissions.'/proposals/'.$intakeProposal['id'].'/respond','POST',['trangThai'=>'dong_y'],$intakeTeacher2Token)[0]===404,'unrelated teacher cannot respond');
    check(http($admissions.'/proposals/'.$intakeProposal['id'].'/respond','POST',['trangThai'=>'dong_y','phuHuynhPhanHoiBoi'=>1],$intakeParentToken)[0]===400,'response actor cannot be forged');
    $intakeOwn=requestOk($admissions.'/proposals','GET',null,$intakeParentToken);
    check(count($intakeOwn['items'])===2 && array_unique(array_column($intakeOwn['items'],'yeuCauId'))===[$intakeId],'parent only lists own proposals');
    check(requestOk($admissions.'/proposals?trangThai=san_sang','GET',null,$staffToken)['total']===0,'no handoff before two acceptances');
    $intakeTeacherReply=requestOk($admissions.'/proposals/'.$intakeProposal['id'].'/respond','POST',['trangThai'=>'dong_y'],$intakeTeacherToken);
    check($intakeTeacherReply['nhanSuPhanHoiLuc']!==null && $intakeTeacherReply['ttPhuHuynh']==='cho','teacher consent does not supply parent consent');
    $intakeReply=requestOk($admissions.'/proposals/'.$intakeProposal['id'].'/respond','POST',['trangThai'=>'dong_y'],$intakeParentToken);
    check((int)$intakeReply['phuHuynhPhanHoiBoi']===(int)$intakeParent['userId'] && $intakeReply['phuHuynhPhanHoiLuc']!==null,'parent actor and server timestamp recorded');
    $intakeAuditCount=(int)Store::one("SELECT COUNT(*) AS n FROM audit_log WHERE bang='de_xuat_nhan_su' AND ban_ghi_id=?",[$intakeProposal['id']])['n'];
    requestOk($admissions.'/proposals/'.$intakeProposal['id'].'/respond','POST',['trangThai'=>'dong_y'],$intakeParentToken);
    check((int)Store::one("SELECT COUNT(*) AS n FROM audit_log WHERE bang='de_xuat_nhan_su' AND ban_ghi_id=?",[$intakeProposal['id']])['n']===$intakeAuditCount,'retry response creates no duplicate audit');
    check(requestOk($admissions.'/proposals?trangThai=san_sang','GET',null,$staffToken)['total']===1,'two acceptances appear in handoff list');
    check(requestOk($admissions.'/requests/'.$intakeId,'GET',null,$staffToken)['trangThai']==='da_chot','accepted request marked agreed without creating class');
    check(!Store::one('SELECT id FROM lop_hoc WHERE de_xuat_id=?',[$intakeProposal['id']]),'handoff never auto creates a class');
    check(http($admissions.'/proposals/'.$intakeProposal2['id'].'/respond','POST',['trangThai'=>'dong_y'],$intakeTeacher2Token)[0]===409,'closed request cannot acquire another accepted winner');
    check(http($admissions.'/requests/'.$intakeId.'/receive','POST',[],$staffToken)[0]===409,'closed intake cannot reopen implicitly');
    $intakeRejectedRequest=requestOk($admissions.'/requests','POST',$intakeBody,$staffToken,201);
    $intakeRejectedProposal=requestOk($admissions.'/requests/'.$intakeRejectedRequest['id'].'/proposals','POST',['nhanSuId'=>$intakeTeacher['id']],$staffToken,201);
    check(http($admissions.'/proposals/'.$intakeRejectedProposal['id'].'/respond','POST',['trangThai'=>'tu_choi'],$intakeParentToken)[0]===400,'decline requires explanation');
    requestOk($admissions.'/proposals/'.$intakeRejectedProposal['id'].'/respond','POST',['trangThai'=>'tu_choi','lyDo'=>'Chưa phù hợp nhu cầu'],$intakeParentToken);
    $intakeRejected=Store::one('SELECT * FROM de_xuat_nhan_su WHERE id=?',[$intakeRejectedProposal['id']]);
    check($intakeRejected['ly_do_tu_choi_phu_huynh']==='Chưa phù hợp nhu cầu' && $intakeRejected['ly_do_tu_choi_nhan_su']===null,'rejection reasons kept on the correct side');
    check(http($admissions.'/proposals/'.$intakeRejectedProposal['id'].'/respond','POST',['trangThai'=>'dong_y'],$intakeParentToken)[0]===409,'response history cannot be overwritten');
    $intakeList=requestOk($admissions.'/requests?q='.urlencode($intakeRequest['maYc']).'&perPage=1','GET',null,$staffToken);
    check($intakeList['total']===1 && count($intakeList['items'])===1,'intake search and pagination');
    check(http($admissions.'/requests?page=0','GET',null,$staffToken)[0]===400,'invalid page rejected');
    check(requestOk($admissions.'/registrations','GET',null,$staffToken)['total']>=1,'staff can read registrations without admin placement permission');
    check(count(requestOk($admissions.'/lookups?q='.urlencode('INT-'.$suffix),'GET',null,$staffToken)['students'])===1,'intake searches real student profiles');
    $intakeGuest=requestOk($admissions.'/requests','POST',array_replace($intakeBody,['hocVienId'=>null,'hoTenLienHe'=>'Khách tư vấn','sdtLienHe'=>'0900111222']),$staffToken,201);
    check(http($admissions.'/requests/'.$intakeGuest['id'].'/proposals','POST',['nhanSuId'=>$intakeTeacher['id']],$staffToken)[0]===409,'guest intake waits for a parent identity before sending actionable proposal');
    requestOk($admissions.'/requests/'.$intakeGuest['id'],'PUT',$intakeBody,$staffToken);
    requestOk($admissions.'/requests/'.$intakeGuest['id'].'/proposals','POST',['nhanSuId'=>$intakeTeacher['id']],$staffToken,201);
    check((int)Store::one('SELECT COUNT(*) AS n FROM thong_bao WHERE doi_tuong_loai=? AND doi_tuong_id=?',['de_xuat_nhan_su',$intakeProposal['id']])['n']>=4,'proposal and response notifications persisted');
    [$intakePageStatus,,$intakePage]=http('/staff');
    check($intakePageStatus===200 && str_contains($intakePage,'data-portal="admissions"') && !str_contains($intakePage,'data-screen="admin-payroll"'),'staff uses shared shell with correct menu');
    check(http('/admin-assets/admissions.html')[0]===200,'approved intake template available');

    $intakeBudgetRequest=requestOk($admissions.'/requests','POST',array_replace($intakeBody,
        ['nganSachMin'=>100000,'nganSachMax'=>170000,'donViNganSach'=>'theo_buoi']),$staffToken,201);
    check(!in_array($intakeTeacher['id'],array_column(requestOk($admissions.'/requests/'.$intakeBudgetRequest['id'].'/candidates','GET',null,$staffToken),'id'),true),'per-session budget compared with per-session quote');
    check(http($admissions.'/requests/'.$intakeBudgetRequest['id'].'/proposals','POST',['nhanSuId'=>$intakeTeacher['id']],$staffToken)[0]===409,'cannot bypass budget filtering with a direct proposal');
    requestOk($admissions.'/requests/'.$intakeBudgetRequest['id'],'PUT',array_replace($intakeBody,
        ['nganSachMin'=>100000,'nganSachMax'=>200000,'donViNganSach'=>'theo_buoi']),$staffToken);
    check(in_array($intakeTeacher['id'],array_column(requestOk($admissions.'/requests/'.$intakeBudgetRequest['id'].'/candidates','GET',null,$staffToken),'id'),true),'matching budget includes candidate');
    Store::execute("UPDATE nhan_su_chuyen_mon SET hinh_thuc_day='online' WHERE id=?",[$intakeTeacher['id']]);
    check(!in_array($intakeTeacher['id'],array_column(requestOk($admissions.'/requests/'.$intakeBudgetRequest['id'].'/candidates','GET',null,$staffToken),'id'),true),'offline requirement excludes online-only profile');
    Store::execute("UPDATE nhan_su_chuyen_mon SET hinh_thuc_day='online,offline' WHERE id=?",[$intakeTeacher['id']]);
    $intakeNext=(new DateTimeImmutable('next Tuesday'))->format('Y-m-d');
    requestOk('/api/academic/scheduling/admin-sessions','POST',['lopId'=>$intakeClass['id'],'ngay'=>$intakeNext,
        'gioBatDau'=>'19:00','gioKetThuc'=>'21:00','userId'=>$intakeTeacher['userId'],'troGiangIds'=>[],
        'repeatCount'=>1],$token,201);
    check(!in_array($intakeTeacher['id'],array_column(requestOk($admissions.'/requests/'.$intakeBudgetRequest['id'].'/candidates','GET',null,$staffToken),'id'),true),'actual future assignment excludes candidate even with weekly schedule inactive');
    $intakeCampusA=requestOk($catalog.'/campuses','POST',['maCoSo'=>'INT-A-'.$suffix,'tenCoSo'=>'Tuyển sinh A','diaChi'=>'A'],$token,201);
    $intakeCampusB=requestOk($catalog.'/campuses','POST',['maCoSo'=>'INT-B-'.$suffix,'tenCoSo'=>'Tuyển sinh B','diaChi'=>'B'],$token,201);
    Store::execute('UPDATE nhan_vien SET co_so_id=? WHERE user_id=?',[$intakeCampusA['id'],$staff]);
    $intakeRemote=requestOk($admissions.'/requests','POST',array_replace($intakeBody,['coSoMongMuonId'=>$intakeCampusB['id']]),$token,201);
    check(http($admissions.'/requests/'.$intakeRemote['id'],'GET',null,$staffToken)[0]===404,'staff cannot read another campus intake');
    check(http($admissions.'/requests/'.$intakeRemote['id'].'/receive','POST',[],$staffToken)[0]===404,'staff cannot claim another campus intake');
    check(http($admissions.'/requests','POST',array_replace($intakeBody,['coSoMongMuonId'=>$intakeCampusB['id']]),$staffToken)[0]===403,'staff cannot create intake for another campus');
    check(!in_array($intakeCampusB['id'],array_column(requestOk($admissions.'/lookups','GET',null,$staffToken)['campuses'],'id'),true),'campus picker respects scope');
    Store::execute('UPDATE lop_hoc SET co_so_id=? WHERE id=?',[$intakeCampusB['id'],$intakeClass['id']]);
    requestOk('/api/academic/classes/'.$intakeClass['id'].'/enrollments','POST',['hocVienId'=>$intakeStudent['id']],$token,201);
    check(!in_array($intakeStudent['id'],array_column(requestOk($admissions.'/lookups','GET',null,$staffToken)['students'],'id'),true),'student lookup excludes students assigned exclusively to another campus');
    check(http($admissions.'/requests/'.$intakeId,'GET',null,$staffToken)[0]===404,'unassigned-campus request cannot bypass student campus scope');
    check(http($admissions.'/requests','POST',$intakeBody,$staffToken)[0]===400,'student id cannot bypass campus scope in write payload');
    Store::execute('UPDATE nhan_vien SET co_so_id=NULL WHERE user_id=?',[$staff]);
    Store::execute('UPDATE role_group_function SET `view`=0 WHERE group_id=? AND function_id=?',[$groupIds['NhanVien'],$functionIds['TiepNhanYeuCau']]);
    check(http($admissions.'/requests','GET',null,$staffToken)[0]===403,'revoked intake permission takes effect on existing JWT');
    Store::execute('UPDATE role_group_function SET `view`=1 WHERE group_id=? AND function_id=?',[$groupIds['NhanVien'],$functionIds['TiepNhanYeuCau']]);
    Store::execute("UPDATE nhan_vien SET trang_thai='nghi_viec' WHERE user_id=?",[$staff]);
    check(http($admissions.'/requests','GET',null,$staffToken)[0]===403,'inactive employee cannot operate admissions');
    foreach (['/api/people/students','/api/people/parents','/api/academic/scheduling/sessions'] as $staffPath) {
        check(http($staffPath,'GET',null,$staffToken)[0]===403,'inactive employee cannot use staff people/calendar APIs');
    }
    Store::execute("UPDATE nhan_vien SET trang_thai='dang_lam' WHERE user_id=?",[$staff]);

    // Complete staff portal uses existing people, scheduling, leave and tuition features.
    $affairs='/api/academic/academic-affairs';
    check(http($affairs.'/dashboard','GET',null,$intakeParentToken)[0]===403,'parent cannot read staff dashboard');
    requestOk($affairs.'/dashboard','GET',null,$staffToken);
    $operatingPeople=requestOk($affairs.'/people','GET',null,$staffToken);
    check(!str_contains(json_encode($operatingPeople),'donGia')&&!str_contains(json_encode($operatingPeople),'nganHang'),'staff scheduling options never expose salary or banking');
    requestOk($affairs.'/rooms','GET',null,$staffToken);
    requestOk($affairs.'/courses','GET',null,$staffToken);
    $staffParent=requestOk('/api/people/parents','POST',['hoTen'=>'Phụ huynh do giáo vụ nhập','email'=>"staff.parent.$suffix@example.test",'sdt'=>'0900111333'],$staffToken,201);
    check($staffParent['username']===null,'staff creates contact profile without provisioning credentials');
    check(in_array($staffParent['userId'],array_column(requestOk('/api/people/parents','GET',null,$staffToken),'userId'),true),'parent profile visible before child/account creation');
    check(http('/api/people/parents/'.$admin['userId'],'PUT',['hoTen'=>'Sai quyền'],$staffToken)[0]===404,'parent editor cannot mutate an administrator profile');
    check(http('/api/people/parents','POST',['hoTen'=>'Không được cấp tài khoản','email'=>"forbidden.parent.$suffix@example.test",'username'=>'bad_parent_'.$suffix,'password'=>'Password@123'],$staffToken)[0]===403,'staff profile form cannot provision accounts');
    $staffStudentBody=['phuHuynhUserId'=>$staffParent['userId'],'maHv'=>'STAFF-HV-'.$suffix,'hoTen'=>'Học viên do giáo vụ nhập','capLopId'=>9,'khoaHocId'=>$activeCourse['id']];
    $beforeStudents=(int)Store::one('SELECT COUNT(*) AS n FROM hoc_vien')['n'];
    check(http($affairs.'/students','POST',array_replace($staffStudentBody,['khoaHocId'=>99999999]),$staffToken)[0]===400,'student and invalid course fail atomically');
    check((int)Store::one('SELECT COUNT(*) AS n FROM hoc_vien')['n']===$beforeStudents,'failed course registration leaves no partial student');
    $staffStudent=requestOk($affairs.'/students','POST',$staffStudentBody,$staffToken,201);
    $staffRegistration=requestOk($affairs.'/students/'.$staffStudent['id'].'/registrations','POST',['khoaHocId'=>$activeCourse['id']],$staffToken,201);
    check((int)$staffRegistration['hocPhiSnapshot']===2300000,'registration uses current course snapshot');
    check((int)Store::one('SELECT COUNT(*) AS n FROM dang_ky_khoa_hoc WHERE hoc_vien_id=?',[$staffStudent['id']])['n']===1,'repeated active course registration is idempotent');
    $staffInvoice=requestOk('/api/finance/tuition/invoices','POST',['dangKyKhoaHocId'=>$staffRegistration['id']],$staffToken,201);
    check($staffInvoice['trangThai']==='nhap'&&(int)$staffInvoice['tongTien']===2300000,'staff creates draft full-course invoice');
    check(requestOk('/api/finance/tuition/invoices','POST',['dangKyKhoaHocId'=>$staffRegistration['id']],$staffToken,201)['id']===$staffInvoice['id'],'duplicate invoice creation returns existing invoice');
    requestOk('/api/finance/tuition/invoices/'.$staffInvoice['id'].'/publish','POST',[],$staffToken);
    $publishedInvoice=requestOk('/api/finance/tuition/invoices/'.$staffInvoice['id'].'/publish','POST',[],$staffToken);
    check($publishedInvoice['phatHanhLuc']!==null&&(int)$publishedInvoice['phatHanhBoi']===$staff,'invoice publication records staff identity and server time');
    $receiptBody=['soTien'=>500000,'phuongThuc'=>'tien_mat','maGiaoDich'=>'STAFF-CASH-'.$suffix];
    check(http('/api/finance/tuition/invoices/'.$staffInvoice['id'].'/receipts','POST',$receiptBody,$staffToken)[0]===403,'only Admin confirms received money');
    requestOk('/api/finance/tuition/invoices/'.$staffInvoice['id'].'/receipts','POST',$receiptBody,$token);
    $receivedInvoice=requestOk('/api/finance/tuition/invoices/'.$staffInvoice['id'].'/receipts','POST',$receiptBody,$token);
    check((int)$receivedInvoice['daThu']===500000&&count($receivedInvoice['receipts'])===1,'repeated receipt creates exactly one payment');
    check(http('/api/finance/tuition/invoices/'.$staffInvoice['id'].'/receipts','POST',array_replace($receiptBody,['soTien'=>2300000,'maGiaoDich'=>'OVER-'.$suffix]),$token)[0]===409,'manual receipt cannot exceed remaining balance');
    $staffClass=requestOk('/api/academic/classes','POST',['maLop'=>'STAFF-L-'.$suffix,'tenLop'=>'Lớp giáo vụ vận hành','monHocId'=>1,'capLopId'=>9,
        'nguoiDayChinhId'=>$intakeTeacher2['id'],'khoaHocId'=>$activeCourse['id'],'cachThuPhi'=>'theo_khoa','cachTinhLuong'=>'theo_gio','ngayBatDau'=>date('Y-m-d')],$token,201);
    requestOk('/api/academic/classes/registrations/'.$staffRegistration['id'].'/assign','POST',['lopId'=>$staffClass['id']],$token);
    $staffSessionDate=(new DateTimeImmutable('next Thursday'))->modify('+7 days')->format('Y-m-d');
    $staffSessionBody=['lopId'=>$staffClass['id'],'ngay'=>$staffSessionDate,'gioBatDau'=>'18:00','gioKetThuc'=>'20:00',
        'userId'=>$intakeTeacher2['userId'],'troGiangIds'=>[],'repeatCount'=>1];
    $staffSession=requestOk('/api/academic/scheduling/operations-sessions','POST',$staffSessionBody,$staffToken,201)['ids'][0];
    check(http('/api/academic/scheduling/operations-sessions','POST',$staffSessionBody+['donGia'=>1],$staffToken)[0]===400,'staff cannot supply internal wage in schedule');
    requestOk('/api/academic/scheduling/operations-sessions/'.$staffSession,'PUT',array_replace($staffSessionBody,['gioBatDau'=>'18:30']),$staffToken);
    $staffLeave=Store::insert('don_xin_nghi',['buoi_hoc_id'=>$staffSession,'doi_tuong'=>'hoc_vien','hoc_vien_id'=>$staffStudent['id'],
        'nguoi_gui_id'=>$staffParent['userId'],'ly_do'=>'Gia đình đã liên hệ xin nghỉ']);
    check(http('/api/academic/leave-requests/personal/'.$staffLeave.'/decide','POST',['trangThai'=>'da_duyet'],$intakeParentToken)[0]===403,'parent cannot approve leave');
    requestOk('/api/academic/leave-requests/personal/'.$staffLeave.'/decide','POST',['trangThai'=>'da_duyet'],$staffToken);
    requestOk('/api/academic/leave-requests/personal/'.$staffLeave.'/decide','POST',['trangThai'=>'da_duyet'],$staffToken);
    check(Store::one('SELECT trang_thai FROM buoi_hoc WHERE id=?',[$staffSession])['trang_thai']!=='huy','individual absence never cancels the whole class');
    $staffMakeup=requestOk('/api/academic/scheduling/operations-sessions','POST',array_replace($staffSessionBody,[
        'ngay'=>(new DateTimeImmutable($staffSessionDate))->modify('+7 days')->format('Y-m-d'), 'loai'=>'bu','phamViBu'=>'ca_nhan','buoiGocId'=>$staffSession,
        'hocVienIds'=>[$staffStudent['id']],'ghiChuBu'=>'Phụ huynh đã thỏa thuận trực tiếp']),$staffToken,201)['ids'][0];
    check(Store::one('SELECT xep_boi,don_xin_nghi_id FROM hoc_bu_hoc_vien WHERE buoi_bu_id=?',[$staffMakeup])['don_xin_nghi_id']===null,'makeup may be scheduled without a leave request or fee');
    requestOk($affairs.'/makeups','GET',null,$staffToken);
    requestOk('/api/academic/leave-requests/class-overview','GET',null,$staffToken);
    $staffNotifications=requestOk($affairs.'/notifications','GET',null,$staffToken);
    check(count($staffNotifications)>0,'staff notification menu reads persisted notifications');
    requestOk($affairs.'/notifications/'.$staffNotifications[0]['id'].'/read','POST',[],$staffToken);
    check(http($affairs.'/notifications/'.$staffNotifications[0]['id'].'/read','POST',[],$token)[0]===404,'another account cannot mark staff notification read');
    [$staffHtmlStatus,,$staffHtml]=http('/staff');
    foreach(['staff-dash','staff-intake','staff-schedule','staff-classes','staff-students','staff-parents','staff-approve','staff-billing'] as $staffScreen) check(str_contains($staffHtml,'data-screen="'.$staffScreen.'"'),'staff menu includes '.$staffScreen);
    check($staffHtmlStatus===200&&str_contains($staffHtml,'id="modalSessionForm"')&&str_contains($staffHtml,'id="notifPanel"'),'staff uses shared calendar modals and notification panel');

    echo "PASS: $assertions administration HTTP/MySQL assertions.\n";
} catch(\Throwable $error) { fwrite(STDERR,$error->getMessage()."\n");$failed=true; }
finally { if(is_resource($server)) { proc_terminate($server);proc_close($server); } }
exit(isset($failed)?1:0);
