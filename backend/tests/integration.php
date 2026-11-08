<?php
// Refuse production before booting the framework or opening a DB connection.
if(getenv('TEST_WITH_DB')!=='1' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*_test$/D',getenv('DB_NAME')?:'')) {
    fwrite(STDERR,"Refusing: set TEST_WITH_DB=1 and an isolated DB_NAME ending in _test.\n"); exit(1);
}
require __DIR__.'/bootstrap.php';

use App\Shared\Store;
use App\Modules\Administration\Features\Auth\AuthService;

$server=null;
try {
    [$server]=startServer();
    $suffix=bin2hex(random_bytes(4));
    $username='admin_'.$suffix; $password='Initial@123';
    AuthService::bootstrap(['username'=>$username,'password'=>$password,'hoTen'=>'Kiểm thử PHP',
        'email'=>"admin.$suffix@example.test",'sdt'=>null]);
    $health=requestOk('/api/health');
    check($health['db']===['timeZone'=>'+07:00','isolationLevel'=>'READ-COMMITTED'],'MySQL session settings');
    $login=requestOk('/api/administration/auth/login','POST',['username'=>$username,'password'=>$password]);
    $token=$login['token']; check($login['user']['role']==='ADMIN','admin login');
    check(requestOk('/api/auth/me','GET',null,$token)['user']['username']===$username,'me');
    check(http('/api/auth/login','POST',['username'=>$username,'password'=>'wrong'])[0]===401,'bad credentials');

    $parentInput=['hoTen'=>'Phụ huynh '.$suffix,'email'=>"ph.$suffix@example.test",'username'=>'ph_'.$suffix,'password'=>'Parent@123','khuVucId'=>1];
    $parent=requestOk('/api/people/parents','POST',$parentInput,$token,201);
    $parent2=requestOk('/api/people/parents','POST',['hoTen'=>'Phụ huynh khác','email'=>"ph2.$suffix@example.test",'username'=>'ph2_'.$suffix,'password'=>'Parent@123'],$token,201);
    $studentInput=['phuHuynhUserId'=>$parent['userId'],'maHv'=>'HV-'.$suffix,'hoTen'=>'Học viên PHP','capLopId'=>9,'khuVucId'=>1];
    $student=requestOk('/api/people/students','POST',$studentInput,$token,201);
    $student2=requestOk('/api/people/students','POST',array_replace($studentInput,['maHv'=>'HV2-'.$suffix,'hoTen'=>'Học viên 2']),$token,201);
    check(http('/api/people/students','POST',$studentInput,$token)[0]===409,'duplicate student mapped');
    $updated=requestOk('/api/people/students/'.$student['id'],'PUT',['ghiChu'=>'Đã cập nhật'],$token);
    check($updated['ghiChu']==='Đã cập nhật' && $updated['hoTen']===$student['hoTen'],'partial update preserves fields');
    requestOk('/api/people/parents/'.$parent['userId'],'PUT',['diaChi'=>'Thủ Đức'],$token);
    check(count(requestOk('/api/people/students?q=HV-'.$suffix,'GET',null,$token))===1,'student search');

    $teacherInput=['maNs'=>'NS-'.$suffix,'hoTen'=>'Giáo viên PHP','email'=>"gv.$suffix@example.test",'username'=>'gv_'.$suffix,'password'=>'Teacher@123',
        'phanLoai'=>['giao_vien','gia_su'],'hinhThucDay'=>['online','offline'],'donGiaGioChuan'=>150000,'kinhNghiemNam'=>4,
        'monDays'=>[['monHocId'=>1,'capLopTu'=>6,'capLopDen'=>9,'hocPhiBuoiDeXuat'=>180000]],'khuVucIds'=>[1],
        'lichRanhs'=>[['thu'=>3,'gioBatDau'=>'18:00','gioKetThuc'=>'21:00']]];
    $teacher=requestOk('/api/people/teachers','POST',$teacherInput,$token,201);
    check(in_array('gia_su',$teacher['phanLoai'],true),'teacher SET mapping');
    $teacher2=requestOk('/api/people/tutors','POST',['maNs'=>'GS-'.$suffix,'hoTen'=>'Gia sư khác','email'=>"gs.$suffix@example.test",'username'=>'gs_'.$suffix,'password'=>'Tutor@123'],$token,201);
    check($teacher2['phanLoai']===['gia_su'],'tutor default type');
    $matched=requestOk('/api/people/teachers/match?monHocId=1&capLopId=9&khuVucId=1&nganSachMax=200000&thu=3&gioBatDau=18:00&gioKetThuc=20:00','GET',null,$token);
    check(in_array($teacher['id'],array_column($matched,'id'),true),'BR02 matching');
    $before=Store::one('SELECT COUNT(*) AS n FROM users')['n'];
    check(http('/api/people/teachers','POST',array_replace($teacherInput,['email'=>"rollback.$suffix@example.test",'username'=>'rollback_'.$suffix]),$token)[0]===409,'duplicate professional code');
    check(Store::one('SELECT COUNT(*) AS n FROM users')['n']===$before,'transaction rolls back user/account');
    requestOk('/api/people/teachers/'.$teacher['id'],'PUT',['gioiThieu'=>'Đã cập nhật'],$token);

    $classInput=['maLop'=>'LOP-'.$suffix,'tenLop'=>'Lớp Toán PHP','loaiLop'=>'kem_1_1','monHocId'=>1,'capLopId'=>9,
        'nguoiDayChinhId'=>$teacher['id'],'siSoToiThieu'=>1,'siSoToiDa'=>1,'hocPhiBuoi'=>180000,'mucTraChinh'=>140000,
        'soBuoiDuKien'=>8,'ngayBatDau'=>'2026-11-01','trangThai'=>'dang_hoc'];
    $class=requestOk('/api/academic/classes','POST',$classInput,$token,201); $classId=$class['id'];
    requestOk('/api/academic/classes/'.$classId,'PUT',['tenLop'=>'Lớp Toán đã cập nhật'],$token);
    requestOk("/api/academic/classes/$classId/enrollments",'POST',['hocVienId'=>$student['id'],'ngayVao'=>'2026-11-01'],$token,201);
    [$status,$error]=http("/api/academic/classes/$classId/enrollments",'POST',['hocVienId'=>$student2['id'],'ngayVao'=>'2026-11-01'],$token);
    check($status===400 && $error['code']==='BUSINESS_RULE_VIOLATION','MySQL capacity trigger');
    $enrollment=requestOk("/api/academic/classes/$classId/enrollments",'GET',null,$token)[0];
    requestOk("/api/academic/classes/$classId/enrollments/{$enrollment['id']}",'PUT',['trangThai'=>'hoc_thu'],$token);
    requestOk('/api/academic/scheduling/weekly','POST',['lopId'=>$classId,'thu'=>3,'gioBatDau'=>'18:00','gioKetThuc'=>'20:00','hieuLucTu'=>'2026-11-01'],$token,201);
    $sessions=requestOk('/api/academic/scheduling/generate-sessions','POST',['lopId'=>$classId,'tuNgay'=>'2026-11-01','denNgay'=>'2026-11-30'],$token,201);
    check(count($sessions)>=4 && $sessions[0]['nguoiDayChinhUserId']===$teacher['userId'],'BR03 procedure and assignment');
    $repeated=requestOk('/api/academic/scheduling/generate-sessions','POST',['lopId'=>$classId,'tuNgay'=>'2026-11-01','denNgay'=>'2026-11-30'],$token,201);
    check(count($sessions)===count($repeated),'procedure idempotence and drained results');
    requestOk('/api/academic/scheduling/sessions/'.$sessions[0]['id'],'PUT',['chuDeBaiHoc'=>'Ôn tập'],$token);
    $taUser=Store::insert('users',['hoten'=>'Trợ giảng PHP','email'=>"ta.$suffix@example.test"]);
    Store::insert('nhan_su_chuyen_mon',['user_id'=>$taUser,'ma_ns'=>'TA-'.$suffix,'phan_loai'=>'tro_giang']);
    requestOk('/api/academic/scheduling/sessions/'.$sessions[0]['id'].'/assign','POST',['userId'=>$taUser,'vaiTro'=>'tro_giang','donGia'=>50000],$token,201);

    $parentToken=requestOk('/api/auth/login','POST',['username'=>'ph_'.$suffix,'password'=>'Parent@123'])['token'];
    $otherToken=requestOk('/api/auth/login','POST',['username'=>'ph2_'.$suffix,'password'=>'Parent@123'])['token'];
    $teacherToken=requestOk('/api/auth/login','POST',['username'=>'gv_'.$suffix,'password'=>'Teacher@123'])['token'];
    $tutorToken=requestOk('/api/auth/login','POST',['username'=>'gs_'.$suffix,'password'=>'Tutor@123'])['token'];
    check(count(requestOk('/api/people/students','GET',null,$parentToken))===2,'parent sees own children');
    check(requestOk('/api/people/students','GET',null,$otherToken)===[],'other parent cannot enumerate children');
    check(http('/api/people/students/'.$student['id'],'GET',null,$otherToken)[0]===404,'student ID scope');
    check(http('/api/people/parents/'.$parent['userId'],'GET',null,$otherToken)[0]===404,'parent ID scope');
    check(requestOk('/api/academic/classes','GET',null,$otherToken)===[],'class list scope');
    check(http('/api/academic/classes/'.$classId,'GET',null,$otherToken)[0]===404,'class detail scope');
    check(http("/api/academic/classes/$classId/enrollments",'GET',null,$otherToken)[0]===404,'enrollment scope');
    check(requestOk('/api/academic/scheduling/weekly','GET',null,$otherToken)===[],'weekly schedule scope');
    check(requestOk('/api/academic/scheduling/sessions','GET',null,$otherToken)===[],'session scope');
    check(count(requestOk('/api/academic/classes','GET',null,$teacherToken))===1,'teacher own classes');
    check(requestOk('/api/academic/classes','GET',null,$tutorToken)===[],'other tutor cannot enumerate classes');
    check(count(requestOk('/api/people/teachers','GET',null,$teacherToken))===1,'teacher own profile');
    check(http('/api/people/teachers/'.$teacher2['id'],'GET',null,$teacherToken)[0]===404,'teacher profile scope');
    check(http('/api/people/students','POST',$studentInput,$parentToken)[0]===403,'parent cannot write');
    check(http('/api/people/teachers/match?monHocId=1&capLopId=9','GET',null,$teacherToken)[0]===403,'matching staff only');
    $staffUser=Store::insert('users',['hoten'=>'Giáo vụ PHP','email'=>"staff.$suffix@example.test"]);
    $staffAccount=Store::insert('account',['user_id'=>$staffUser,'group_id'=>2,'username'=>'staff_'.$suffix,'password_hash'=>password_hash('Staff@123',PASSWORD_BCRYPT),'status'=>'active']);
    $staffToken=requestOk('/api/auth/login','POST',['username'=>'staff_'.$suffix,'password'=>'Staff@123'])['token'];
    requestOk('/api/academic/classes','GET',null,$staffToken);
    check(http('/api/people/students/'.$student2['id'],'DELETE',null,$staffToken)[0]===403,'database permission matrix delete denied');
    Store::execute("UPDATE account SET status='locked' WHERE account_id=?",[$staffAccount]);
    check(http('/api/auth/me','GET',null,$staffToken)[0]===401,'account lock revokes access');
    Store::execute("UPDATE account SET status='active',group_id=5 WHERE account_id=?",[$staffAccount]);
    check(requestOk('/api/academic/classes','GET',null,$staffToken)===[],'current DB role overrides stale JWT');

    requestOk('/api/auth/logout','POST',[],$parentToken);
    check(http('/api/auth/me','GET',null,$parentToken)[0]===401,'logout revokes token');
    requestOk('/api/auth/change-password','PUT',['currentPassword'=>$password,'newPassword'=>'Changed@456'],$token);
    check(http('/api/auth/me','GET',null,$token)[0]===401,'password change revokes old token');
    $token=requestOk('/api/auth/login','POST',['username'=>$username,'password'=>'Changed@456'])['token'];
    requestOk('/api/people/students/'.$student2['id'],'DELETE',null,$token);
    check(http('/api/people/students/'.$student2['id'],'GET',null,$token)[0]===404,'soft-delete student');
    requestOk('/api/auth/logout','POST',[],$token);
    check(http('/api/auth/me','GET',null,$token)[0]===401,'admin logout revocation');
    echo "PASS: $assertions HTTP/MySQL integration assertions on isolated test database.\n";
} catch(\Throwable $error) { fwrite(STDERR,$error->getMessage()."\n"); $failed=true; }
finally { if(is_resource($server)) { proc_terminate($server);proc_close($server); } }
exit(isset($failed)?1:0);
