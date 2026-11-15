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
    Store::insert('nhan_vien',['user_id'=>$staffUser,'ma_nv'=>'NV-'.$suffix,'trang_thai'=>'dang_lam']);
    $staffAccount=Store::insert('account',['user_id'=>$staffUser,'group_id'=>2,'username'=>'staff_'.$suffix,'password_hash'=>password_hash('Staff@123',PASSWORD_BCRYPT),'status'=>'active']);
    $staffToken=requestOk('/api/auth/login','POST',['username'=>'staff_'.$suffix,'password'=>'Staff@123'])['token'];
    requestOk('/api/academic/classes','GET',null,$staffToken);
    // All roles share /login and /app. Reloads use a cookie, never a replayed POST.
    $assistantGroup=Store::one("SELECT group_id FROM role_group WHERE group_name='TroGiang'")['group_id'];
    Store::insert('account',['user_id'=>$taUser,'group_id'=>$assistantGroup,'username'=>'ta_'.$suffix,
        'password_hash'=>password_hash('Assistant@123',PASSWORD_BCRYPT),'status'=>'active']);
    $portalAccounts=[
        [$username,$password,'ADMIN','data-portal="admin"'],
        ['staff_'.$suffix,'Staff@123','GIAO_VU','data-portal="admissions"'],
        ['ph_'.$suffix,'Parent@123','PHU_HUYNH','studentSelector'],
        ['gv_'.$suffix,'Teacher@123','GIAO_VIEN','portalTitle'],
        ['gs_'.$suffix,'Tutor@123','GIA_SU','portalTitle'],
        ['ta_'.$suffix,'Assistant@123','TRO_GIANG','portalTitle'],
    ];
    foreach($portalAccounts as [$portalUsername,$portalPassword,$role,$marker]) {
        [$status,$body,,$headers]=http('/api/auth/login','POST',['username'=>$portalUsername,'password'=>$portalPassword]);
        check($status===200 && $body['data']['user']['role']===$role,'shared login '.$role);
        $setCookie=implode("\n",array_filter($headers,static fn($header)=>str_starts_with(strtolower($header),'set-cookie:')));
        check(str_contains(strtolower($setCookie),'httponly') && str_contains(strtolower($setCookie),'samesite=lax'),'session cookie flags '.$role);
        preg_match('/educenter_session=([^;]+)/',$setCookie,$cookieMatch);
        check(isset($cookieMatch[1]),'browser session cookie '.$role);
        $cookie='Cookie: educenter_session='.$cookieMatch[1];
        foreach([1,2] as $load) {
            [$status,,$html]=http('/app','GET',null,null,null,[$cookie]);
            check($status===200 && str_contains($html,$marker) && str_contains($html,'id="portal-session"'),'role page and refresh '.$role.' '.$load);
        }
        [$status,,,$headers]=http('/api/auth/logout','POST',[],$body['data']['token'],null,[$cookie]);
        check($status===200 && str_contains(implode("\n",$headers),'educenter_session='),'logout clears cookie '.$role);
        [$status,,,$headers]=http('/app','GET',null,null,null,[$cookie]);
        check($status===303 && str_contains(implode("\n",$headers),'/login'),'refresh after logout returns shared login '.$role);
    }
    check(str_contains(http('/login')[2],'loginForm'),'original shared login UI');
    check(http('/app')[0]===303,'anonymous app returns login');
    check(http('/staff','POST',null,null,'accessToken=revoked')[0]===303,'old POST redirects instead of JSON');
    check(http('/frontend/src/config/app.config.js')[0]===200,'shared frontend assets served');
    check(http('/frontend/package.json')[0]===404,'nonpublic file extension refused');
    check(http('/api/people/students/'.$student2['id'],'DELETE',null,$staffToken)[0]===403,'database permission matrix delete denied');
    Store::execute("UPDATE account SET status='locked' WHERE account_id=?",[$staffAccount]);
    check(http('/api/auth/me','GET',null,$staffToken)[0]===401,'account lock revokes access');
    Store::execute("UPDATE account SET status='active',group_id=5 WHERE account_id=?",[$staffAccount]);
    check(requestOk('/api/academic/classes','GET',null,$staffToken)===[],'current DB role overrides stale JWT');

    // Parent UI reads real coursework and keeps actions scoped to the selected child.
    $homeworkId=Store::insert('bai_tap',['buoi_hoc_id'=>$sessions[0]['id'],'tieu_de'=>'Phiếu hệ phương trình',
        'file_de_bai_url'=>'/parent-ui-test.pdf','nguoi_tao_id'=>$teacher['userId']]);
    Store::insert('so_theo_doi_buoi_hoc',['buoi_hoc_id'=>$sessions[0]['id'],'hoc_vien_id'=>$student['id'],
        'trang_thai_diem_danh'=>'co_mat','bai_tap_id'=>$homeworkId,'diem_bai_tap_giay'=>8.5,'nguoi_nhap_id'=>$teacher['userId']]);
    $portal=requestOk('/api/people/parents/portal','GET',null,$parentToken);
    check(count($portal['students'])===2 && isset($portal['serverTime']),'parent child selector and server date');
    check($portal['learningNotes'][$student['id']][0]['fileUrl']==='/parent-ui-test.pdf','coursework has the stored PDF link');
    check(!str_contains(json_encode($portal),'donGia') && !str_contains(json_encode($portal),'mucTra'),'parent portal excludes payroll');
    check(requestOk('/api/people/parents/portal','GET',null,$otherToken)['students']===[],'another parent has no sample children');
    $futureDay=(new DateTimeImmutable('tomorrow'))->format('Y-m-d');
    $futureSession=Store::insert('buoi_hoc',['lop_id'=>$classId,'ngay'=>$futureDay,'gio_bat_dau'=>'09:00','gio_ket_thuc'=>'11:00']);
    requestOk('/api/people/parents/leave-requests','POST',['hocVienId'=>$student['id'],'buoiHocId'=>$futureSession,'lyDo'=>'Nghỉ kiểm thử'],$parentToken,201);
    check(http('/api/people/parents/leave-requests','POST',['hocVienId'=>$student['id'],'buoiHocId'=>$futureSession,'lyDo'=>'Gửi trùng'],$parentToken)[0]===409,'duplicate leave refused');
    check(http('/api/people/parents/leave-requests','POST',['hocVienId'=>$student['id'],'buoiHocId'=>$futureSession,'lyDo'=>'Sai phụ huynh'],$otherToken)[0]===403,'leave cannot target another parent child');
    Store::execute("UPDATE lop_hoc SET loai_lop='kem_1_2',si_so_toi_da=2 WHERE id=?",[$classId]);
    Store::insert('lop_hoc_vien',['lop_id'=>$classId,'hoc_vien_id'=>$student2['id'],'ngay_vao'=>date('Y-m-d'),
        'trang_thai'=>'dang_hoc','xep_boi'=>$login['user']['userId'],'xep_luc'=>date('Y-m-d H:i:s')]);
    $privateMakeup=Store::insert('buoi_hoc',['lop_id'=>$classId,'ngay'=>(new DateTimeImmutable('+2 days'))->format('Y-m-d'),
        'gio_bat_dau'=>'09:00','gio_ket_thuc'=>'11:00','loai'=>'bu','pham_vi_bu'=>'ca_nhan','buoi_goc_id'=>$sessions[0]['id']]);
    Store::insert('hoc_bu_hoc_vien',['buoi_bu_id'=>$privateMakeup,'hoc_vien_id'=>$student2['id'],'xep_boi'=>$login['user']['userId']]);
    check(http('/api/people/parents/leave-requests','POST',['hocVienId'=>$student['id'],'buoiHocId'=>$privateMakeup,'lyDo'=>'Sai lịch bù'],$parentToken)[0]===404,'private makeup belongs only to its linked student');
    $courseId=Store::insert('khoa_hoc',['ma'=>'PARENT-'.$suffix,'ten'=>'Khóa cơ bản học kỳ 1','hoc_phi'=>2500000]);
    $registrationId=Store::insert('dang_ky_khoa_hoc',['khoa_hoc_id'=>$courseId,'hoc_vien_id'=>$student['id']]);
    $parentInvoice=requestOk('/api/finance/tuition/invoices','POST',['dangKyKhoaHocId'=>$registrationId],$token,201);
    requestOk('/api/finance/tuition/invoices/'.$parentInvoice['id'].'/publish','POST',[],$token);
    $paymentBody=['hoaDonId'=>$parentInvoice['id'],'nhaCungCap'=>'VIETQR_NAPAS','soTien'=>2500000];
    $firstPayment=requestOk('/api/people/parents/payments','POST',$paymentBody,$parentToken,201);
    $retryPayment=requestOk('/api/people/parents/payments','POST',$paymentBody,$parentToken,201);
    check($firstPayment['id']===$retryPayment['id'],'payment retry reuses the pending notification');
    check(http('/api/people/parents/payments','POST',$paymentBody,$otherToken)[0]===403,'another parent cannot report payment for this invoice');
    $portal=requestOk('/api/people/parents/portal','GET',null,$parentToken);
    check($portal['transactions'][0]['studentId']===(string)$student['id'],'transactions follow the selected child');
    check((int)$portal['invoices'][$student['id']][0]['paid']===0,'reporting a transfer never confirms receipt');
    requestOk('/api/auth/logout','POST',[],$parentToken);
    check(http('/api/auth/me','GET',null,$parentToken)[0]===401,'logout revokes token');
    requestOk('/api/auth/change-password','PUT',['currentPassword'=>$password,'newPassword'=>'Changed@456'],$token);
    check(http('/api/auth/me','GET',null,$token)[0]===401,'password change revokes old token');
    $token=requestOk('/api/auth/login','POST',['username'=>$username,'password'=>'Changed@456'])['token'];
    requestOk('/api/people/students/'.$student2['id'],'DELETE',null,$token);
    check(http('/api/people/students/'.$student2['id'],'GET',null,$token)[0]===404,'soft-delete student');
    requestOk('/api/auth/logout','POST',[],$token);
    check(http('/api/auth/me','GET',null,$token)[0]===401,'admin logout revocation');
    // Recovery codes travel only by email and are consumed under an account row lock.
    $mailer = new class extends \CodeIgniter\Email\Email {
        public string $sentBody = '';
        public array $sentTo = [];
        public bool $deliver = true;
        public function send($autoClear = true) {
            $this->sentBody = $this->body;
            $this->sentTo = $this->recipients;
            return $this->deliver;
        }
    };
    \Config\Services::injectMock('email', $mailer);
    $recovery = AuthService::sendOtp(['identifier'=>$username]);
    preg_match('/\b[0-9]{6}\b/', $mailer->sentBody, $otpMatch);
    check(isset($otpMatch[0]) && $mailer->sentTo === ["admin.$suffix@example.test"], 'OTP delivered to registered email');
    check(!isset($recovery['otpDev']) && !str_contains(json_encode($recovery), $otpMatch[0]), 'OTP never returned in API data');
    $accountId = (int)$login['user']['accountId'];
    $otpRecord = \App\Modules\Administration\Features\Auth\AuthRepository::latestOtp($accountId);
    check($otpRecord['otpHash'] !== $otpMatch[0] && $otpRecord['version'] === 2, 'only keyed OTP hash is stored');
    try {
        AuthService::sendOtp(['identifier'=>$username]);
        check(false, 'OTP resend rate limit');
    } catch (\App\Shared\ApiException $error) { check($error->status === 429, 'OTP resend rate limit'); }
    $wrongOtp = $otpMatch[0] === '000000' ? '000001' : '000000';
    check(http('/api/auth/forgot-password/reset','POST',['identifier'=>$username,'otp'=>$wrongOtp,'newPassword'=>'Recovered@789','confirmPassword'=>'Recovered@789'])[0]===400, 'wrong OTP refused');
    check(\App\Modules\Administration\Features\Auth\AuthRepository::latestOtp($accountId)['attempts']===1, 'failed OTP attempt persists');
    $recoveryToken = requestOk('/api/auth/login','POST',['username'=>$username,'password'=>'Changed@456'])['token'];
    requestOk('/api/auth/forgot-password/reset','POST',['identifier'=>$username,'otp'=>$otpMatch[0],'newPassword'=>'Recovered@789','confirmPassword'=>'Recovered@789']);
    check(http('/api/auth/me','GET',null,$recoveryToken)[0]===401, 'password recovery revokes previous sessions');
    check(http('/api/auth/forgot-password/reset','POST',['identifier'=>$username,'otp'=>$otpMatch[0],'newPassword'=>'Another@789','confirmPassword'=>'Another@789'])[0]===400, 'OTP cannot be replayed');
    requestOk('/api/auth/login','POST',['username'=>$username,'password'=>'Recovered@789']);
    $otpRecord['sentAt'] = time()-61;
    unset($otpRecord['auditId']);
    Store::audit((int)$login['user']['userId'], 'khac', $accountId, $otpRecord);
    $beforeFailedSend = \App\Modules\Administration\Features\Auth\AuthRepository::latestOtp($accountId);
    $mailer->deliver = false;
    try {
        AuthService::sendOtp(['identifier'=>$username]);
        check(false, 'SMTP failure reported');
    } catch (\App\Shared\ApiException $error) { check($error->status===503, 'SMTP failure reported'); }
    check(\App\Modules\Administration\Features\Auth\AuthRepository::latestOtp($accountId)===$beforeFailedSend, 'failed email delivery rolls back OTP');
    $mailer->deliver = true;
    AuthService::sendOtp(['identifier'=>$username]);
    preg_match('/\b[0-9]{6}\b/', $mailer->sentBody, $otpMatch);
    for ($attempt=0; $attempt<5; $attempt++) {
        check(http('/api/auth/forgot-password/reset','POST',['identifier'=>$username,'otp'=>'000000','newPassword'=>'Another@789','confirmPassword'=>'Another@789'])[0]===400, 'wrong OTP attempt counted');
    }
    check(http('/api/auth/forgot-password/reset','POST',['identifier'=>$username,'otp'=>$otpMatch[0],'newPassword'=>'Another@789','confirmPassword'=>'Another@789'])[0]===429, 'guessing lock also blocks the correct OTP');
    \Config\Services::resetSingle('email');
    echo "PASS: $assertions HTTP/MySQL integration assertions on isolated test database.\n";
} catch(\Throwable $error) { fwrite(STDERR,$error->getMessage()."\n"); $failed=true; }
finally { if(is_resource($server)) { proc_terminate($server);proc_close($server); } }
exit(isset($failed)?1:0);
