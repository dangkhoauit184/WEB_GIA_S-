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
        ['gv_'.$suffix,'Teacher@123','GIAO_VIEN','teacher-content'],
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
        $ownToken=$body['data']['token'];
        $profile=requestOk('/api/auth/me/profile','GET',null,$ownToken);
        check($profile['role']===$role && $profile['username']===$portalUsername,'self profile follows authenticated identity '.$role);
        check(!preg_match('/password|donGia|nganHang|soTaiKhoan|hocPhiBuoiDeXuat/i',json_encode($profile)),'self profile excludes secrets and rates '.$role);
        $oldOther=Store::one('SELECT hoten,diachi FROM users WHERE user_id=?',[$parent2['userId']]);
        check(http('/api/auth/me/profile','PUT',['userId'=>$parent2['userId'],'hoTen'=>'Giả danh'],$ownToken)[0]===400,'self profile rejects another identity '.$role);
        check(http('/api/auth/me/profile','PUT',['role'=>'ADMIN'],$ownToken)[0]===400,'self profile rejects privilege changes '.$role);
        check(http('/api/auth/me/profile','PUT',['email'=>'invalid'],$ownToken)[0]===400,'self profile validates email '.$role);
        check(http('/api/auth/me/profile','PUT',['email'=>$parent2['email']],$ownToken)[0]===409,'self profile identifies duplicate email '.$role);
        check(http('/api/auth/me/profile','PUT',['ngaySinh'=>'2099-01-01'],$ownToken)[0]===400,'self profile rejects future birth date '.$role);
        $savedProfile=requestOk('/api/auth/me/profile','PUT',['diaChi'=>'Hồ sơ cá nhân '.$role],$ownToken);
        check($savedProfile['diaChi']==='Hồ sơ cá nhân '.$role,'self profile saves and reloads '.$role);
        check(Store::one('SELECT hoten,diachi FROM users WHERE user_id=?',[$parent2['userId']])===$oldOther,'self edit leaves other users intact '.$role);
        if ($role === 'GIAO_VU') {
            check(isset($profile['staff']['maNv']),'staff profile includes employment details');
            check(http('/api/auth/me/profile','PUT',['coSoId'=>1],$ownToken)[0]===400,'staff cannot reassign own campus');
        }
        if (in_array($role,['GIAO_VIEN','GIA_SU','TRO_GIANG'],true)) {
            check(isset($profile['professional']['maNs']),'professional profile for '.$role);
            $savedProfile=requestOk('/api/auth/me/profile','PUT',['gioiThieu'=>'Chuyên môn '.$role,'donViCongTac'=>'EduCenter'], $ownToken);
            check($savedProfile['professional']['gioiThieu']==='Chuyên môn '.$role,'professional self edit '.$role);
            check(http('/api/auth/me/profile','PUT',['donGiaGioChuan'=>1],$ownToken)[0]===400,'professional rate protected '.$role);
            check(http('/api/auth/me/profile','PUT',['phanLoai'=>['giao_vien']],$ownToken)[0]===400,'professional classification protected '.$role);
        }
        if ($role === 'PHU_HUYNH') {
            check(count($profile['children'])===2,'parent profile lists only own children');
            $savedProfile=requestOk('/api/auth/me/profile','PUT',['ngheNghiep'=>'Kỹ sư'], $ownToken);
            check($savedProfile['ngheNghiep']==='Kỹ sư','parent can edit occupation');
        }
        foreach([1,2] as $load) {
            [$status,,$html]=http('/app','GET',null,null,null,[$cookie]);
            check($status===200 && str_contains($html,$marker) && str_contains($html,'id="portal-session"') && !str_contains($html,$ownToken),'role page and refresh without token in HTML '.$role.' '.$load);
        }
        check(http('/api/auth/me','GET',null,null,null,[$cookie])[0]===200,'cookie-only auth works for API GET '.$role);
        check(http('/api/auth/logout','POST',null,null,'',[$cookie,'Origin: https://evil.example'])[0]===403,'cookie auth blocks cross-site CSRF without XHR header '.$role);
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
    $enrolled=requestOk('/api/people/parents/enrollments','POST',['khoaHocId'=>$courseId,'hocVienId'=>$student['id'],'hocPhi'=>1],$parentToken,201);
    check($enrolled['hoaDon']['id']===(int)$parentInvoice['id'] && $enrolled['hoaDon']['must']===2500000,'parent enrollment reuses invoice and authoritative course price');
    check(http('/api/people/parents/enrollments','POST',['khoaHocId'=>$courseId,'hocVienId'=>$student['id']],$otherToken)[0]===403,'parent enrollment cannot target another family');
    $courseCount=Store::one('SELECT COUNT(*) AS n FROM khoa_hoc')['n'];
    check(http('/api/people/parents/enrollments','POST',['maKhoa'=>'KHOA-GS-1KEM1','hocVienId'=>$student['id']],$parentToken)[0]===404,'parent cannot create a hardcoded catalog course');
    check(Store::one('SELECT COUNT(*) AS n FROM khoa_hoc')['n']===$courseCount,'rejected enrollment leaves catalog unchanged');
    $paymentBody=['hoaDonId'=>$parentInvoice['id'],'nhaCungCap'=>'VIETQR_NAPAS','soTien'=>2500000];
    check(http('/api/people/parents/invoices/'.$parentInvoice['id'].'/payment-qr','GET',null,$parentToken)[0]===409,'QR requires configured bank');
    $bankBody=['bankBin'=>'970436','bankName'=>'Vietcombank','accountNo'=>'1234567890','accountName'=>'TEST ONLY','active'=>true,'isDemo'=>false];
    check(http('/api/finance/tuition/bank-accounts','POST',$bankBody,$parentToken)[0]===403,'parent cannot change receiving account');
    check(http('/api/finance/tuition/bank-accounts','POST',$bankBody,$staffToken)[0]===403,'staff cannot change receiving account');
    $bank=requestOk('/api/finance/tuition/bank-accounts','POST',$bankBody,$token);
    requestOk('/api/finance/tuition/bank-accounts/'.$bank['id'].'/default','POST',[],$token);
    check(http('/api/finance/tuition/bank-accounts/'.$bank['id'],'PUT',array_replace($bankBody,['active'=>false]),$token)[0]===409,'default must stay active');
    check(http('/api/finance/tuition/bank-accounts/default','PUT',$bankBody,$token)[0]===400,'reserved configuration key protected');
    check(http('/api/people/parents/invoices/'.$parentInvoice['id'].'/payment-qr','GET',null,$otherToken)[0]===404,'QR invoice ownership');
    $qr=requestOk('/api/people/parents/invoices/'.$parentInvoice['id'].'/payment-qr','GET',null,$parentToken);
    check($qr['amount']===2500000 && $qr['bank']['id']===$bank['id'] && str_starts_with($qr['qrDataUrl'],'data:image/svg+xml;base64,'),'QR carries canonical amount and default bank');
    $paymentBody['paymentToken']=$qr['paymentToken'];
    check(http('/api/people/parents/payments','POST',$paymentBody+['xacNhanQr'=>true],$parentToken)[0]===400,'parent cannot impersonate Admin confirmation');
    check(http('/api/people/parents/payments','POST',array_replace($paymentBody,['paymentToken'=>'forged']),$parentToken)[0]===400,'tampered QR quote refused');
    check(http('/api/people/parents/payments','POST',array_replace($paymentBody,['soTien'=>1]),$parentToken)[0]===400,'QR amount tampering refused');
    $secondBank=requestOk('/api/finance/tuition/bank-accounts','POST',array_replace($bankBody,['accountNo'=>'0987654321','isDemo'=>true]),$token);
    requestOk('/api/finance/tuition/bank-accounts/'.$secondBank['id'].'/default','POST',[],$token);
    $newQr=requestOk('/api/people/parents/invoices/'.$parentInvoice['id'].'/payment-qr','GET',null,$parentToken);
    check($newQr['bank']['id']===$secondBank['id'] && $newQr['qrDataUrl']===null,'changing default updates new QR and demo never renders bank-transfer QR');
    $firstPayment=requestOk('/api/people/parents/payments','POST',$paymentBody,$parentToken,201);
    $retryPayment=requestOk('/api/people/parents/payments','POST',$paymentBody,$parentToken,201);
    check($firstPayment['id']===$retryPayment['id'],'payment retry reuses the pending notification');
    $tx=requestOk('/api/finance/tuition/transactions','GET',null,$token);
    $tx=array_values(array_filter($tx,fn($item)=>(int)$item['id']===(int)$firstPayment['id']))[0];
    check(json_decode($tx['transferDetails'],true)['receivingAccount']['id']===$bank['id'],'transfer retains quoted bank after default changes');
    check(http('/api/people/parents/payments','POST',$paymentBody,$otherToken)[0]===403,'another parent cannot report payment for this invoice');
    $portal=requestOk('/api/people/parents/portal','GET',null,$parentToken);
    check($portal['transactions'][0]['studentId']===(string)$student['id'],'transactions follow the selected child');
    check((int)$portal['invoices'][$student['id']][0]['paid']===0,'reporting a transfer never confirms receipt');
    require __DIR__.'/payos.php';
    testPayos(compact('parent','parent2','student','login','token','parentToken','otherToken','bank','secondBank','bankBody','suffix'));
    testSepay(compact('parent','parent2','student','login','token','parentToken','otherToken','bank','secondBank','bankBody','suffix'));
    // Teacher portal operations stay scoped and never accept client clock/pay data.
    $teacherToken=requestOk('/api/auth/login','POST',['username'=>'gv_'.$suffix,'password'=>'Teacher@123'])['token'];
    $work=requestOk('/api/academic/attendance/workspace','GET',null,$teacherToken);
    check(count($work['classes'])===1 && count($work['sessions'])===count($sessions),'teacher workspace reads assigned classes and sessions');
    check(!preg_match('/donGia|don_gia|mucTra|muc_tra|tongLuong|tong_luong/',json_encode($work)),'teacher workspace excludes salary');
    check(http('/api/academic/attendance/workspace','GET',null,$parentToken)[0]===403,'parent cannot use teacher APIs');
    check(http('/api/academic/attendance/workspace','GET',null,$tutorToken)[0]===403,'tutor portal remains separate');
    // Pre-session material upload (before check-in) on a clean prior session + strict download authorization
    $prevSessionId=Store::insert('buoi_hoc',['lop_id'=>$classId,'ngay'=>'2026-11-09','gio_bat_dau'=>'18:00','gio_ket_thuc'=>'20:00','trang_thai'=>'du_kien']);
    Store::insert('phan_cong_buoi_hoc',['buoi_hoc_id'=>$prevSessionId,'user_id'=>$teacher['userId'],'vai_tro'=>'day_chinh','don_gia'=>140000,'trang_thai'=>'da_phan_cong']);
    $minimalPdf="%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";
    check(httpMultipart('/api/academic/attendance/sessions/'.$prevSessionId.'/material', [], ['file'=>['shell.php', '<?php echo 1;', 'application/x-php']], $teacherToken)[0]===400,'material upload blocks disallowed extension');
    check(httpMultipart('/api/academic/attendance/sessions/'.$prevSessionId.'/material', [], ['file'=>['spoof.pdf', '<?php echo 1;', 'application/pdf']], $teacherToken)[0]===400,'material upload blocks MIME spoofing');
    [$matUpStatus,$matUpBody]=httpMultipart('/api/academic/attendance/sessions/'.$prevSessionId.'/material',
        ['tieuDe'=>'Tài liệu & BTVN buổi 1','ghiChu'=>'Đọc trước phần lý thuyết và làm bài 1-3'],
        ['file'=>['bai-giang-buoi-1.pdf', $minimalPdf, 'application/pdf']], $teacherToken);
    check($matUpStatus===200 && ($matUpBody['data']['taiLieu']['laTepTaiLen']??false) && ($matUpBody['data']['taiLieu']['tenTep']??'')==='bai-giang-buoi-1.pdf','teacher uploads real PDF before check-in');
    $matDownloadPath=$matUpBody['data']['taiLieu']['url'];
    check(str_starts_with($matDownloadPath,'/api/academic/attendance/sessions/'.$prevSessionId.'/material/download'),'material download path canonical');
    check(http($matDownloadPath,'GET',null,null)[0]===401,'unauthenticated material download blocked');
    check(http($matDownloadPath,'GET',null,$otherToken)[0]===403,'parent outside class cannot download session material');
    check(http($matDownloadPath,'GET',null,$tutorToken)[0]===403,'unassigned tutor cannot download session material');
    [$dlTeacherStatus,,$dlTeacherBytes]=http($matDownloadPath,'GET',null,$teacherToken);
    check($dlTeacherStatus===200 && str_starts_with($dlTeacherBytes,'%PDF-1.4'),'assigned teacher downloads uploaded material');
    [$dlParentStatus,,$dlParentBytes]=http($matDownloadPath,'GET',null,$parentToken);
    check($dlParentStatus===200 && str_starts_with($dlParentBytes,'%PDF-1.4'),'enrolled parent downloads pre-session material');
    $prevHwId=(int)$matUpBody['data']['baiTap']['id'];

    $teacherSession=(int)$sessions[1]['id'];
    $teacherDetail=requestOk('/api/academic/attendance/sessions/'.$teacherSession,'GET',null,$teacherToken);
    check(http('/api/academic/attendance/sessions/'.$teacherSession,'GET',null,$tutorToken)[0]===403,'another role cannot inspect teacher log');
    check((int)($teacherDetail['baiTapBuoiTruoc']['id']??0)===$prevHwId,'next session log exposes previous session homework for grading');
    // Session logbook does NOT create new homework; grades previous session's homework
    $teacherLog=['revision'=>$teacherDetail['revision'],'chuDe'=>'Bài giảng kiểm thử','noiDung'=>'Nội dung đã dạy',
        'baiTapChamId'=>$prevHwId,
        'hocViens'=>[['hocVienId'=>$student['id'],'trangThaiDiemDanh'=>'co_mat','diemBaiTapGiay'=>8.5,'nhanXetBaiTap'=>'Làm tốt BTVN buổi trước']]];
    check(http('/api/academic/attendance/sessions/'.$teacherSession.'/log','PUT',$teacherLog,$teacherToken)[0]===409,'checkin required for log');
    check(http('/api/academic/attendance/sessions/'.$teacherSession.'/log','PUT',$teacherLog+['baiTap'=>['tieuDe'=>'Không được giao mới']],$teacherToken)[0]===400,'session log rejects creating new homework');
    check(http('/api/academic/attendance/sessions/'.$teacherSession.'/checkin','POST',['checkinLuc'=>'2000-01-01'],$teacherToken)[0]===400,'server rejects client clock');
    $clock=requestOk('/api/academic/attendance/sessions/'.$teacherSession.'/checkin','POST',[],$teacherToken);
    check($clock['checkinLuc']!==null && $clock['trangThai']==='dang_day','teacher checkin persists');
    check(($clock['quyenYeuCau']['coTheDoiLich']??true)===false && ($clock['quyenYeuCau']['coTheChinhCong']??true)===false,'in-progress teacher shift disables both reschedule and time adjustment');
    check(http('/api/academic/attendance/requests','POST',['loai'=>'doi_lich','buoiHocId'=>$teacherSession,'lyDo'=>'Thử đổi lịch lúc đang dạy','ngayMoi'=>'2098-01-09','gioBatDau'=>'18:00','gioKetThuc'=>'20:00'],$teacherToken)[0]===409,'in-progress shift blocks reschedule request');
    check(http('/api/academic/attendance/requests','POST',['loai'=>'dieu_chinh_cong','buoiHocId'=>$teacherSession,'lyDo'=>'Thử chỉnh công lúc đang dạy','checkinMoi'=>$sessions[1]['ngay'].'T18:00','checkoutMoi'=>$sessions[1]['ngay'].'T20:00'],$teacherToken)[0]===409,'in-progress shift blocks time adjustment request');
    $calendarIds=array_map('intval',array_column(requestOk('/api/academic/scheduling/sessions','GET',null,$teacherToken),'id'));
    $workspaceIds=array_map('intval',array_column(requestOk('/api/academic/attendance/workspace','GET',null,$teacherToken)['sessions'],'id'));
    check(in_array($teacherSession,$calendarIds,true) && in_array($teacherSession,$workspaceIds,true),'checked-in session stays in both calendar sources');
    check(requestOk('/api/academic/attendance/sessions/'.$teacherSession.'/checkin','POST',[],$teacherToken)['checkinLuc']===$clock['checkinLuc'],'checkin replay keeps first timestamp');
    $teacherLog['revision']=$clock['revision'];
    $saved=requestOk('/api/academic/attendance/sessions/'.$teacherSession.'/log','PUT',$teacherLog,$teacherToken);
    check((float)$saved['hocViens'][0]['diemBaiTapGiay']===8.5 && (int)$saved['hocViens'][0]['baiTapId']===$prevHwId,'teacher log grades previous session homework cleanly');
    check(http('/api/academic/attendance/sessions/'.$teacherSession.'/log','PUT',$teacherLog,$teacherToken)[0]===409,'stale log cannot overwrite newer data');
    $teacherLog['revision']=$saved['revision'];
    $teacherLog['hocViens'][0]['hocVienId']=$student2['id'];
    check(http('/api/academic/attendance/sessions/'.$teacherSession.'/log','PUT',$teacherLog,$teacherToken)[0]===409,'teacher cannot grade a student outside roster');
    // Anti-duplicate grading across sessions: grading same (student, prevHwId) in sessions[3] must be rejected (409)
    $thirdSessionId=(int)$sessions[3]['id'];
    $thirdClock=requestOk('/api/academic/attendance/sessions/'.$thirdSessionId.'/checkin','POST',[],$teacherToken);
    check(http('/api/academic/attendance/sessions/'.$thirdSessionId.'/log','PUT',[
        'revision'=>$thirdClock['revision'],'chuDe'=>'Buổi 3','noiDung'=>'Kiểm tra chống chấm trùng','baiTapChamId'=>$prevHwId,
        'hocViens'=>[['hocVienId'=>$student['id'],'trangThaiDiemDanh'=>'co_mat','diemBaiTapGiay'=>9.0]]
    ],$teacherToken)[0]===409,'duplicate grading of same previous homework in another session rejected');
    Store::execute('UPDATE phan_cong_buoi_hoc SET checkin_luc=DATE_SUB(NOW(),INTERVAL 5 MINUTE) WHERE id=?',[$clock['phanCongId']]);
    $clock=requestOk('/api/academic/attendance/sessions/'.$teacherSession.'/checkout','POST',[],$teacherToken);
    check($clock['checkoutLuc']!==null && $clock['trangThai']==='da_day','checkout persists and ends session');
    check(($clock['quyenYeuCau']['coTheDoiLich']??true)===false && ($clock['quyenYeuCau']['coTheChinhCong']??false)===true,'completed teacher shift allows only time adjustment');
    check(http('/api/academic/attendance/requests','POST',['loai'=>'doi_lich','buoiHocId'=>$teacherSession,'lyDo'=>'Thử đổi lịch sau khi dạy xong','ngayMoi'=>'2098-01-10','gioBatDau'=>'18:00','gioKetThuc'=>'20:00'],$teacherToken)[0]===409,'completed shift blocks reschedule request');
    check((int)$clock['soPhutTinhCong']===0 && $clock['gioLichBatDau']!==null && $clock['gioLichKetThuc']!==null,
        'checkout outside scheduled hours returns zero minutes and schedule snapshot');
    check(in_array($teacherSession,array_map('intval',array_column(requestOk('/api/academic/scheduling/sessions','GET',null,$teacherToken),'id')),true),'completed session remains on timetable');
    check(requestOk('/api/academic/attendance/sessions/'.$teacherSession.'/checkout','POST',[],$teacherToken)['checkoutLuc']===$clock['checkoutLuc'],'checkout replay stable');
    check(http('/api/people/teachers/me/profile','PUT',['donGiaGioChuan'=>1],$teacherToken)[0]===400,'self profile rejects rate changes');
    check(http('/api/people/teachers/me/profile','PUT',['trangThai'=>'ngung_hop_tac'],$teacherToken)[0]===400,'self profile rejects status changes');
    requestOk('/api/people/teachers/me/profile','PUT',['gioiThieu'=>'Chuyên môn tự cập nhật'],$teacherToken);
    $selfProfile=requestOk('/api/people/teachers/me/profile','PUT',['hoTen'=>'Giáo viên cập nhật','email'=>"updated.gv.$suffix@example.test",
        'ngaySinh'=>'1995-02-15','gioiTinh'=>'nu','sdt'=>'0900000000','diaChi'=>'Địa chỉ mới'],$teacherToken);
    check($selfProfile['hoTen']==='Giáo viên cập nhật' && $selfProfile['ngaySinh']==='1995-02-15' && $selfProfile['gioiTinh']==='nu','teacher personal profile fields persist');
    check(http('/api/people/teachers/me/profile','PUT',['lichRanhs'=>[['thu'=>3,'gioBatDau'=>'18:00','gioKetThuc'=>'20:00'],['thu'=>3,'gioBatDau'=>'19:00','gioKetThuc'=>'21:00']]],$teacherToken)[0]===400,'overlapping free slots refused');
    $availabilityPath='/api/people/teachers/me/availability';
    $freeSlot=requestOk($availabilityPath,'POST',['thu'=>8,'gioBatDau'=>'06:00','gioKetThuc'=>'07:00'],$teacherToken,201);
    $freeId=(int)$freeSlot['id'];
    $secondSlot=requestOk($availabilityPath,'POST',['thu'=>8,'gioBatDau'=>'07:00','gioKetThuc'=>'08:00'],$teacherToken,201);
    check(http($availabilityPath,'POST',['thu'=>8,'gioBatDau'=>'06:30','gioKetThuc'=>'07:30'],$teacherToken)[0]===409,'individual availability refuses overlap and allows adjacent slots');
    check(http($availabilityPath.'/'.$freeId,'PUT',['thu'=>8,'gioBatDau'=>'08:00','gioKetThuc'=>'07:00'],$teacherToken)[0]===400,'individual availability refuses reversed hours');
    check(http($availabilityPath,'POST',['thu'=>8,'gioBatDau'=>'04:00'],$teacherToken)[0]===400,'individual availability requires both hours');
    check(http($availabilityPath,'POST',['thu'=>9,'gioBatDau'=>'04:00','gioKetThuc'=>'05:00'],$teacherToken)[0]===400,'individual availability requires a valid weekday');
    check(http($availabilityPath,'POST',['thu'=>8,'gioBatDau'=>'04:00','gioKetThuc'=>'05:00','nhanSuId'=>1],$teacherToken)[0]===400,'availability rejects another owner field');
    check(http($availabilityPath,'POST',['thu'=>8,'gioBatDau'=>'04:00','gioKetThuc'=>'05:00'],$parentToken)[0]===403,'parent cannot add teacher availability');
    check(http($availabilityPath.'/'.$freeId,'DELETE',null,$parentToken)[0]===403,'parent cannot delete teacher availability');
    $changedSlot=requestOk($availabilityPath.'/'.$freeId,'PUT',['thu'=>8,'gioBatDau'=>'05:30','gioKetThuc'=>'06:30'],$teacherToken);
    check((int)$changedSlot['id']===$freeId && $changedSlot['gioBatDau']==='05:30:00','availability edit preserves slot identity');
    check(http($availabilityPath.'/'.$freeId,'PUT',['thu'=>8,'gioBatDau'=>'07:15','gioKetThuc'=>'08:15'],$teacherToken)[0]===409,'availability edit excludes itself and checks other slots');
    check(Store::one('SELECT gio_bat_dau FROM nhan_su_lich_ranh WHERE id=?',[$freeId])['gio_bat_dau']==='05:30:00','failed edit preserves saved availability');
    requestOk($availabilityPath.'/'.$freeId,'DELETE',null,$teacherToken);
    check(Store::one('SELECT id FROM nhan_su_lich_ranh WHERE id=?',[$secondSlot['id']])!==null,'deleting one slot preserves other slots');
    check(http($availabilityPath.'/'.$freeId,'DELETE',null,$teacherToken)[0]===404,'deleted availability returns not found on retry');
    check((int)Store::one("SELECT COUNT(*) AS n FROM audit_log WHERE bang='nhan_su_lich_ranh' AND ban_ghi_id=? AND user_id=?",[$freeId,$teacher['userId']])['n']===3,'availability create edit delete are audited');
    requestOk($availabilityPath.'/'.$secondSlot['id'],'DELETE',null,$teacherToken);
    $noonSlot=requestOk($availabilityPath,'POST',['thu'=>8,'gioBatDau'=>'11:00','gioKetThuc'=>'12:00'],$teacherToken,201);
    check($noonSlot['gioBatDau']==='11:00:00' && $noonSlot['gioKetThuc']==='12:00:00','availability accepts 11 to noon in 24 hour format');
    requestOk($availabilityPath.'/'.$noonSlot['id'],'DELETE',null,$teacherToken);
    $requestSession=Store::insert('buoi_hoc',['lop_id'=>$classId,'ngay'=>'2098-01-07','gio_bat_dau'=>'18:00','gio_ket_thuc'=>'20:00']);
    Store::insert('phan_cong_buoi_hoc',['buoi_hoc_id'=>$requestSession,'user_id'=>$teacher['userId'],'vai_tro'=>'day_chinh']);
    $reqSessionDetail=requestOk('/api/academic/attendance/sessions/'.$requestSession,'GET',null,$teacherToken);
    check(($reqSessionDetail['quyenYeuCau']['coTheDoiLich']??false)===true && ($reqSessionDetail['quyenYeuCau']['coTheChinhCong']??true)===false,'upcoming teacher shift allows only reschedule');
    check(http('/api/academic/attendance/requests','POST',['loai'=>'dieu_chinh_cong','buoiHocId'=>$requestSession,'lyDo'=>'Chỉnh công ca chưa diễn ra','checkinMoi'=>'2098-01-07T18:00','checkoutMoi'=>'2098-01-07T20:00'],$teacherToken)[0]===409,'upcoming teacher shift blocks time adjustment');
    $requestBody=['loai'=>'doi_lich','buoiHocId'=>$requestSession,'lyDo'=>'Có việc cá nhân','ngayMoi'=>'2098-01-08','gioBatDau'=>'18:00','gioKetThuc'=>'20:00'];
    [$badStatus,$badTime]=http('/api/academic/attendance/requests','POST',array_replace($requestBody,['gioKetThuc'=>'00:00']),$teacherToken);
    check($badStatus===400 && str_contains($badTime['message'],'Giờ kết thúc phải sau giờ bắt đầu') && !str_contains($badTime['message'],'gioKetThuc'),'reschedule reversed hours give a readable error');
    [$badStatus,$badTime]=http('/api/academic/attendance/requests','POST',array_replace($requestBody,['gioKetThuc'=>'invalid']),$teacherToken);
    check($badStatus===400 && str_contains($badTime['message'],'Giờ kết thúc'),'reschedule malformed hour identifies the field');
    [$badStatus,$badTime]=http('/api/academic/attendance/requests','POST',array_replace($requestBody,['ngayMoi'=>'2000-01-01']),$teacherToken);
    check($badStatus===400 && str_contains($badTime['message'],'thời điểm hiện tại'),'reschedule past proposal refused clearly');
    [$badStatus,$badTime]=http('/api/academic/attendance/requests','POST',['loai'=>'dieu_chinh_cong','buoiHocId'=>$teacherSession,'lyDo'=>'Sai giờ',
        'checkinMoi'=>'2026-11-17T18:00','checkoutMoi'=>'2026-11-17T00:00'],$teacherToken);
    check($badStatus===400 && str_contains($badTime['message'],'Check-out đề nghị phải sau Check-in'),'correction reversed datetime gives a readable error');
    $reschedule=requestOk('/api/academic/attendance/requests','POST',$requestBody,$teacherToken,201);
    check(Store::one('SELECT trang_thai FROM don_xin_nghi WHERE id=?',[$reschedule['id']])['trang_thai']==='cho_duyet','reschedule awaits staff approval');
    check(http('/api/academic/attendance/requests','POST',$requestBody,$teacherToken)[0]===409,'duplicate pending request refused');
    $wholeLeave=requestOk('/api/academic/attendance/requests','POST',['loai'=>'nghi_day','buoiHocId'=>$requestSession,'lyDo'=>'Lớp cần nghỉ'],$teacherToken,201);
    check(Store::one('SELECT trang_thai FROM yeu_cau_nghi_lop WHERE id=?',[$wholeLeave['id']])['trang_thai']==='cho_duyet','whole class request awaits admin');
    requestOk('/api/academic/attendance/requests','POST',['loai'=>'dieu_chinh_cong','buoiHocId'=>$teacherSession,
        'lyDo'=>'Xin kiểm tra giờ dạy','checkinMoi'=>$sessions[1]['ngay'].'T18:10','checkoutMoi'=>$sessions[1]['ngay'].'T19:30'],$teacherToken,201);
    $teacherRequests=requestOk('/api/academic/attendance/workspace','GET',null,$teacherToken)['requests'];
    check(count($teacherRequests)===3,'teacher request history includes all three workflows');
    check((int)Store::one("SELECT COUNT(*) AS n FROM thong_bao WHERE khoa_chong_trung LIKE 'teacher-request:%'")['n']>=3,'requests notify reviewers');
    $unassignedTeacher=requestOk('/api/people/teachers','POST',['maNs'=>'GV2-'.$suffix,'hoTen'=>'Giáo viên chưa phân công',
        'email'=>"gv2.$suffix@example.test",'username'=>'gv2_'.$suffix,'password'=>'Teacher@123'],$token,201);
    $unassignedToken=requestOk('/api/auth/login','POST',['username'=>'gv2_'.$suffix,'password'=>'Teacher@123'])['token'];
    $ownedSlot=requestOk($availabilityPath,'POST',['thu'=>8,'gioBatDau'=>'06:00','gioKetThuc'=>'07:00'],$teacherToken,201);
    check(http($availabilityPath.'/'.$ownedSlot['id'],'PUT',['thu'=>8,'gioBatDau'=>'04:00','gioKetThuc'=>'05:00'],$unassignedToken)[0]===404,'teacher cannot edit another teacher availability');
    check(http($availabilityPath.'/'.$ownedSlot['id'],'DELETE',null,$unassignedToken)[0]===404,'teacher cannot delete another teacher availability');
    requestOk($availabilityPath.'/'.$ownedSlot['id'],'DELETE',null,$teacherToken);
    check(requestOk('/api/academic/attendance/workspace','GET',null,$unassignedToken)['sessions']===[],'unassigned teacher sees no other sessions');
    check(http('/api/academic/attendance/sessions/'.$teacherSession,'GET',null,$unassignedToken)[0]===404,'teacher cannot read another assignment');
    check(http('/api/academic/attendance/sessions/'.$teacherSession.'/checkin','POST',[],$unassignedToken)[0]===404,'teacher cannot checkin for another person');
    Store::execute('UPDATE account SET group_id=2 WHERE account_id=?',[$staffAccount]);
    $reviewerToken=requestOk('/api/auth/login','POST',['username'=>'staff_'.$suffix,'password'=>'Staff@123'])['token'];
    requestOk('/api/academic/leave-requests/personal/'.$reschedule['id'].'/decide','POST',['trangThai'=>'da_duyet'],$reviewerToken);
    check(http('/api/academic/leave-requests/classes/'.$wholeLeave['id'].'/decision','POST',['trangThai'=>'da_duyet'],$reviewerToken)[0]===403,'staff cannot approve whole class request');
    requestOk('/api/academic/leave-requests/classes/'.$wholeLeave['id'].'/decision','POST',['trangThai'=>'tu_choi','ghiChu'=>'Giữ lịch cũ'],$token);
    $correctionId=Store::one('SELECT id FROM dieu_chinh_cong WHERE phan_cong_id=?',[$clock['phanCongId']])['id'];
    requestOk('/api/finance/timekeeping/corrections/'.$correctionId.'/decision','POST',['trangThai'=>'da_duyet'],$token);
    $adjusted=requestOk('/api/academic/attendance/sessions/'.$teacherSession,'GET',null,$teacherToken);
    check((int)$adjusted['soPhutTinhCong']===80 && (int)$adjusted['checkinTre']===1,'approved teacher correction gives 80 minutes and late flag');
    Store::insert('bang_cong_thang',['user_id'=>$teacher['userId'],'nam'=>2026,'thang'=>11,'trang_thai'=>'da_chot',
        'chot_boi'=>$login['user']['userId'],'chot_luc'=>date('Y-m-d H:i:s')]);
    check(http('/api/academic/attendance/sessions/'.$sessions[2]['id'].'/checkin','POST',[],$teacherToken)[0]===409,'closed period blocks new teacher clock');
    $teacherLog['revision']=$adjusted['revision'];
    $teacherLog['hocViens'][0]['hocVienId']=$student['id'];
    check(http('/api/academic/attendance/sessions/'.$teacherSession.'/log','PUT',$teacherLog,$teacherToken)[0]===409,'closed period blocks teacher log edits');
    requestOk('/api/people/teachers/me/profile','PUT',['lichRanhs'=>[['thu'=>3,'gioBatDau'=>'18:00','gioKetThuc'=>'21:00'],
        ['thu'=>2,'gioBatDau'=>'08:00','gioKetThuc'=>'12:00']]],$teacherToken);
    $employeeId=Store::one('SELECT id FROM nhan_vien WHERE user_id=?',[$staffUser])['id'];
    $opportunityId=Store::insert('yeu_cau_hoc',['ma_yc'=>'APP-'.$suffix,'hoc_vien_id'=>$student['id'],'mon_hoc_id'=>1,
        'cap_lop_id'=>9,'hinh_thuc'=>'online','nhan_vien_id'=>$employeeId,'nguoi_gui_id'=>$parent['userId']]);
    Store::insert('yeu_cau_lich',['yeu_cau_id'=>$opportunityId,'thu'=>2,'gio_bat_dau'=>'09:00','gio_ket_thuc'=>'11:00']);
    $opportunities=requestOk('/api/people/teachers/me/opportunities','GET',null,$teacherToken);
    check(in_array($opportunityId,array_map('intval',array_column($opportunities,'id')),true),'teacher sees matching opportunities');
    check(!str_contains(json_encode($opportunities),'sdt') && !str_contains(json_encode($opportunities),'phuHuynh'),'opportunities exclude family contact');
    $application=requestOk('/api/people/teachers/me/opportunities/'.$opportunityId.'/apply','POST',[],$teacherToken,201);
    check($application['ttNhanSu']==='dong_y','application records teacher acceptance');
    $replay=requestOk('/api/people/teachers/me/opportunities/'.$opportunityId.'/apply','POST',[],$teacherToken,201);
    check((int)$replay['id']===(int)$application['id'],'application replay does not duplicate proposal');
    requestOk('/api/academic/admissions/proposals/'.$application['id'].'/respond','POST',['trangThai'=>'dong_y','lyDo'=>null],$parentToken);
    check(Store::one('SELECT trang_thai FROM yeu_cau_hoc WHERE id=?',[$opportunityId])['trang_thai']==='da_chot','application follows normal parent confirmation workflow');

    // ==========================================
    // Assistants (TRO_GIANG) Integration Suite
    // ==========================================
    $assistantToken = requestOk('/api/auth/login', 'POST', ['username'=>'ta_'.$suffix, 'password'=>'Assistant@123'])['token'];

    // 1. Quyền (Permissions)
    check(http('/api/academic/attendance/workspace', 'GET', null, $assistantToken)[0] === 403, 'assistant cannot access teacher attendance workspace');
    check(http('/api/academic/attendance/sessions/'.$sessions[0]['id'].'/log', 'PUT', ['revision'=>'test'], $assistantToken)[0] === 403, 'assistant cannot call teacher attendance log');
    check(http('/api/people/assistants/workspace', 'GET', null, $teacherToken)[0] === 403, 'teacher cannot call assistant workspace');
    check(http('/api/people/assistants/workspace', 'GET', null, $parentToken)[0] === 403, 'parent cannot call assistant workspace');
    check(http('/api/people/assistants/workspace', 'GET', null, $staffToken)[0] === 403, 'staff cannot call assistant workspace');

    $taWork = requestOk('/api/people/assistants/workspace', 'GET', null, $assistantToken);
    check(isset($taWork['sessions'], $taWork['classes'], $taWork['monthSummary'], $taWork['periods']), 'assistant workspace structure');

    // 5. Không rò rỉ tiền/lương/đơn giá/tài khoản ngân hàng trong toàn bộ JSON response của feature
    $taWorkJson = json_encode($taWork);
    check(!preg_match('/don_?gia|muc_?tra|tong_?luong|thu_?lao|hoc_?phi|ngan_?hang|so_?tai_?khoan|chu_?tai_?khoan/i', $taWorkJson), 'assistant workspace excludes all salary, rate, and bank keys');

    // Scoping check: Assistant 2
    $taUser2 = Store::insert('users', ['hoten'=>'Trợ giảng 2 '.$suffix, 'email'=>'ta2.'.$suffix.'@example.test']);
    Store::insert('account', ['user_id'=>$taUser2, 'group_id'=>$assistantGroup, 'username'=>'ta2_'.$suffix, 'password_hash'=>password_hash('Assistant@123', PASSWORD_BCRYPT), 'status'=>'active']);
    Store::insert('nhan_su_chuyen_mon', ['user_id'=>$taUser2, 'ma_ns'=>'TA2-'.$suffix, 'phan_loai'=>'tro_giang', 'trang_thai'=>'hoat_dong']);
    $assistant2Token = requestOk('/api/auth/login', 'POST', ['username'=>'ta2_'.$suffix, 'password'=>'Assistant@123'])['token'];

    $taSession = (int)$sessions[0]['id'];
    check(http('/api/people/assistants/sessions/'.$taSession, 'GET', null, $assistant2Token)[0] === 404, 'assistant 2 cannot see session of assistant 1');
    check(http('/api/people/assistants/sessions/'.$taSession.'/checkin', 'POST', [], $assistant2Token)[0] === 404, 'assistant 2 cannot checkin session of assistant 1');

    // 2. Check-in / Check-out
    check(http('/api/people/assistants/sessions/'.$taSession.'/checkin', 'POST', ['time'=>'now'], $assistantToken)[0] === 400, 'assistant checkin rejects client time');
    check(http('/api/people/assistants/sessions/'.$taSession.'/checkout', 'POST', [], $assistantToken)[0] === 409, 'checkout before checkin rejected');

    $taSessionOldStatus = Store::one('SELECT trang_thai FROM buoi_hoc WHERE id=?', [$taSession])['trang_thai'];
    $taClockIn = requestOk('/api/people/assistants/sessions/'.$taSession.'/checkin', 'POST', [], $assistantToken);
    check($taClockIn['checkinLuc'] !== null, 'assistant checkin persists timestamp');
    $taSessionNewStatus = Store::one('SELECT trang_thai FROM buoi_hoc WHERE id=?', [$taSession])['trang_thai'];
    check($taSessionOldStatus === $taSessionNewStatus, 'assistant checkin does NOT alter buoi_hoc.trang_thai');

    $taReplay = requestOk('/api/people/assistants/sessions/'.$taSession.'/checkin', 'POST', [], $assistantToken);
    check($taReplay['checkinLuc'] === $taClockIn['checkinLuc'], 'assistant checkin replay is idempotent');

    // 3. Điểm danh hỗ trợ (legacy /attendance) & Sổ buổi học đầy đủ (/log, CHẶN gắn/sửa file bài tập PDF)
    if (!Store::one('SELECT id FROM so_theo_doi_buoi_hoc WHERE buoi_hoc_id=? AND hoc_vien_id=?', [$taSession, $student['id']])) {
        Store::insert('so_theo_doi_buoi_hoc', [
            'buoi_hoc_id' => $taSession,
            'hoc_vien_id' => $student['id'],
            'trang_thai_diem_danh' => 'co_mat',
            'diem_bai_tap_giay' => 9.0,
            'nhan_xet_buoi_hoc' => 'Giáo viên nhận xét mẫu',
            'nguoi_nhap_id' => $teacher['userId'],
        ]);
    }
    $taDetail = requestOk('/api/people/assistants/sessions/'.$taSession, 'GET', null, $assistantToken);

    // Legacy attendance-only endpoint still rejects extra fields and preserves grades/notes
    check(http('/api/people/assistants/sessions/'.$taSession.'/attendance', 'PUT', [
        'revision' => $taDetail['revision'],
        'hocViens' => [['hocVienId'=>$student['id'], 'trangThaiDiemDanh'=>'co_mat', 'diemBaiTapGiay'=>9]]
    ], $assistantToken)[0] === 400, 'assistant attendance-only endpoint rejects extra fields');

    check(http('/api/people/assistants/sessions/'.$taSession.'/attendance', 'PUT', [
        'revision' => 'wrong_revision',
        'hocViens' => [['hocVienId'=>$student['id'], 'trangThaiDiemDanh'=>'co_mat']]
    ], $assistantToken)[0] === 409, 'stale revision rejected');

    check(http('/api/people/assistants/sessions/'.$taSession.'/attendance', 'PUT', [
        'revision' => $taDetail['revision'],
        'hocViens' => [['hocVienId'=>$student2['id'], 'trangThaiDiemDanh'=>'co_mat']]
    ], $assistantToken)[0] === 409, 'mismatched student roster rejected');

    $studentLogBefore = Store::one('SELECT diem_bai_tap_giay, nhan_xet_buoi_hoc FROM so_theo_doi_buoi_hoc WHERE buoi_hoc_id=? AND hoc_vien_id=?', [$taSession, $student['id']]);
    requestOk('/api/people/assistants/sessions/'.$taSession.'/attendance', 'PUT', [
        'revision' => $taDetail['revision'],
        'hocViens' => [['hocVienId'=>$student['id'], 'trangThaiDiemDanh'=>'di_tre']]
    ], $assistantToken);
    $studentLogAfter = Store::one('SELECT trang_thai_diem_danh, diem_bai_tap_giay, nhan_xet_buoi_hoc FROM so_theo_doi_buoi_hoc WHERE buoi_hoc_id=? AND hoc_vien_id=?', [$taSession, $student['id']]);
    check($studentLogAfter['trang_thai_diem_danh'] === 'di_tre', 'assistant attendance updated to di_tre');
    check($studentLogBefore !== null && $studentLogAfter['diem_bai_tap_giay'] === $studentLogBefore['diem_bai_tap_giay'] && $studentLogAfter['nhan_xet_buoi_hoc'] === $studentLogBefore['nhan_xet_buoi_hoc'], 'assistant attendance-only endpoint preserves grade and note');

    // Dedicated previous + current session for TA full log, grading previous homework & strict material/PDF guard tests
    $taPrevSession = Store::insert('buoi_hoc', ['lop_id'=>$classId, 'ngay'=>'2098-01-24', 'gio_bat_dau'=>'18:00', 'gio_ket_thuc'=>'20:00', 'trang_thai'=>'da_day']);
    $teacherPdfUrl = 'https://example.test/de-bai-gv.pdf';
    $taPrevHwId = Store::insert('bai_tap', [
        'buoi_hoc_id' => $taPrevSession,
        'tieu_de' => 'Bài tập buổi ôn tập trước',
        'noi_dung' => 'Làm bài 1-5',
        'file_de_bai_url' => $teacherPdfUrl,
        'nguoi_tao_id' => $teacher['userId'],
    ]);
    $taLogSession = Store::insert('buoi_hoc', ['lop_id'=>$classId, 'ngay'=>'2098-01-25', 'gio_bat_dau'=>'18:00', 'gio_ket_thuc'=>'20:00']);
    Store::insert('phan_cong_buoi_hoc', ['buoi_hoc_id'=>$taLogSession, 'user_id'=>$taUser, 'vai_tro'=>'tro_giang', 'don_gia'=>80000, 'trang_thai'=>'da_phan_cong']);
    // Upcoming TA shift: only reschedule/leave allowed, time adjustment blocked (409); cannot upload material (403)
    $taUpDetail = requestOk('/api/people/assistants/sessions/'.$taLogSession, 'GET', null, $assistantToken);
    check(($taUpDetail['quyenYeuCau']['coTheDoiLich'] ?? false) === true && ($taUpDetail['quyenYeuCau']['coTheChinhCong'] ?? true) === false, 'upcoming TA shift allows only reschedule');
    check(http('/api/people/assistants/requests', 'POST', ['loai'=>'dieu_chinh_cong','buoiHocId'=>$taLogSession,'lyDo'=>'Chỉnh công ca chưa học','checkinMoi'=>'2098-01-25T18:00','checkoutMoi'=>'2098-01-25T20:00'], $assistantToken)[0] === 409, 'upcoming TA shift blocks time adjustment');
    check(httpMultipart('/api/academic/attendance/sessions/'.$taLogSession.'/material', [], ['file'=>['ta.pdf', $minimalPdf, 'application/pdf']], $assistantToken)[0] === 403, 'assistant forbidden from uploading session material');

    requestOk('/api/people/assistants/sessions/'.$taLogSession.'/checkin', 'POST', [], $assistantToken);
    $taLogDetail = requestOk('/api/people/assistants/sessions/'.$taLogSession, 'GET', null, $assistantToken);
    check(($taLogDetail['quyenYeuCau']['coTheDoiLich'] ?? true) === false && ($taLogDetail['quyenYeuCau']['coTheChinhCong'] ?? true) === false, 'in-progress TA shift hides both requests');
    check(http('/api/people/assistants/requests', 'POST', ['loai'=>'doi_gio','buoiHocId'=>$taLogSession,'lyDo'=>'Đổi giờ lúc đang dạy','ngayMoi'=>'2098-01-26','gioBatDau'=>'18:00','gioKetThuc'=>'20:00'], $assistantToken)[0] === 409, 'in-progress TA shift blocks reschedule');
    check(http('/api/people/assistants/requests', 'POST', ['loai'=>'dieu_chinh_cong','buoiHocId'=>$taLogSession,'lyDo'=>'Chỉnh công lúc đang dạy','checkinMoi'=>'2098-01-25T18:00','checkoutMoi'=>'2098-01-25T20:00'], $assistantToken)[0] === 409, 'in-progress TA shift blocks time adjustment');
    check((int)($taLogDetail['baiTapBuoiTruoc']['id'] ?? 0) === $taPrevHwId && $taLogDetail['baiTapBuoiTruoc']['fileDeBaiUrl'] === $teacherPdfUrl, 'assistant session reads previous session homework metadata');

    $taLogStudents = array_map(static fn(array $hv): array => [
        'hocVienId' => (int)$hv['hocVienId'],
        'trangThaiDiemDanh' => 'co_mat',
        'diemBaiTapGiay' => (int)$hv['hocVienId'] === (int)$student['id'] ? 8.5 : 7.5,
        'nhanXetBaiTap' => 'Làm bài cẩn thận',
        'mucTiepThu' => 4,
        'thaiDo' => 'tot',
        'nhanXetBuoiHoc' => 'Tích cực phát biểu',
        'huongCaiThien' => 'Ôn thêm phần hình học',
    ], $taLogDetail['hocViens']);

    // Full log endpoint: strictly FORBIDS attaching/modifying homework PDF (403), lecture URL (403), or creating new homework (400)
    $taLogPayload = [
        'revision' => $taLogDetail['revision'],
        'chuDe' => 'Ôn tập trợ giảng',
        'noiDung' => 'Hỗ trợ giải bài tập trên lớp',
        'baiTapChamId' => $taPrevHwId,
        'hocViens' => $taLogStudents,
    ];
    check(http('/api/people/assistants/sessions/'.$taLogSession.'/log', 'PUT', $taLogPayload + ['baiTap' => ['tieuDe' => 'Hack', 'fileDeBaiUrl' => 'https://example.test/hack.pdf']], $assistantToken)[0] === 403, 'assistant log forbids attaching or editing homework PDF');
    check(http('/api/people/assistants/sessions/'.$taLogSession.'/log', 'PUT', $taLogPayload + ['fileBaiGiangUrl' => 'https://example.test/hack.pdf'], $assistantToken)[0] === 403, 'assistant log forbids modifying lecture material url');
    check(http('/api/people/assistants/sessions/'.$taLogSession.'/log', 'PUT', $taLogPayload + ['baiTap' => ['tieuDe' => 'Tạo mới trong sổ']], $assistantToken)[0] === 400, 'assistant log forbids creating new homework in logbook');

    // Full log grading previous session's homework succeeds
    $taLogSaved = requestOk('/api/people/assistants/sessions/'.$taLogSession.'/log', 'PUT', $taLogPayload, $assistantToken);
    $savedStudentRow = null;
    foreach ($taLogSaved['hocViens'] as $hvRow) {
        if ((int)$hvRow['hocVienId'] === (int)$student['id']) $savedStudentRow = $hvRow;
    }
    check($savedStudentRow !== null && (float)$savedStudentRow['diemBaiTapGiay'] === 8.5 && (int)$savedStudentRow['baiTapId'] === $taPrevHwId && $savedStudentRow['nhanXetBuoiHoc'] === 'Tích cực phát biểu', 'assistant full log saves previous homework grade and comments');
    check($taLogSaved['chuDeBaiHoc'] === 'Ôn tập trợ giảng', 'assistant full log saves session topic');
    check($taLogSaved['baiTapBuoiTruoc']['fileDeBaiUrl'] === $teacherPdfUrl, 'assistant log preserves previous teacher homework PDF url');
    check(http('/api/people/assistants/sessions/'.$taLogSession.'/log', 'PUT', $taLogPayload, $assistantToken)[0] === 409, 'stale assistant log revision rejected');
    check(http('/api/people/assistants/sessions/'.$taLogSession.'/log', 'PUT', array_merge($taLogPayload, [
        'revision' => $taLogSaved['revision'],
        'hocViens' => [['hocVienId' => 999999, 'trangThaiDiemDanh' => 'co_mat']],
    ]), $assistantToken)[0] === 409, 'assistant log rejects out-of-roster student');

    // 3b. Assistant personal availability CRUD (same rules as teacher, strictly scoped)
    $taAvailPath = '/api/people/assistants/availability';
    $taSlot = requestOk($taAvailPath, 'POST', ['thu' => 8, 'gioBatDau' => '08:00', 'gioKetThuc' => '10:00'], $assistantToken, 201);
    $taSlotId = (int)$taSlot['id'];
    check(http($taAvailPath, 'POST', ['thu' => 8, 'gioBatDau' => '09:00', 'gioKetThuc' => '11:00'], $assistantToken)[0] === 409, 'assistant availability refuses overlap');
    check(http($taAvailPath.'/'.$taSlotId, 'PUT', ['thu' => 8, 'gioBatDau' => '10:00', 'gioKetThuc' => '08:00'], $assistantToken)[0] === 400, 'assistant availability refuses reversed hours');
    check(http($taAvailPath.'/'.$taSlotId, 'PUT', ['thu' => 8, 'gioBatDau' => '07:00', 'gioKetThuc' => '09:00'], $assistant2Token)[0] === 404, 'assistant 2 cannot edit assistant 1 availability');
    check(http($taAvailPath, 'POST', ['thu' => 8, 'gioBatDau' => '06:00', 'gioKetThuc' => '07:00'], $parentToken)[0] === 403, 'parent cannot add assistant availability');
    $taSlotEdited = requestOk($taAvailPath.'/'.$taSlotId, 'PUT', ['thu' => 8, 'gioBatDau' => '07:30', 'gioKetThuc' => '09:30'], $assistantToken);
    check($taSlotEdited['gioBatDau'] === '07:30:00', 'assistant availability edit persists');
    requestOk($taAvailPath.'/'.$taSlotId, 'DELETE', null, $assistantToken);
    check(http($taAvailPath.'/'.$taSlotId, 'DELETE', null, $assistantToken)[0] === 404, 'deleted assistant availability returns 404');

    // Check-out
    Store::execute('UPDATE phan_cong_buoi_hoc SET checkin_luc=DATE_SUB(NOW(),INTERVAL 5 MINUTE) WHERE buoi_hoc_id=? AND user_id=?', [$taSession, $taUser]);
    $taClockOut = requestOk('/api/people/assistants/sessions/'.$taSession.'/checkout', 'POST', [], $assistantToken);
    check($taClockOut['checkoutLuc'] !== null, 'assistant checkout persists');
    $taSessionFinalStatus = Store::one('SELECT trang_thai FROM buoi_hoc WHERE id=?', [$taSession])['trang_thai'];
    check($taSessionFinalStatus === $taSessionOldStatus, 'assistant checkout does NOT alter buoi_hoc.trang_thai');
    check(requestOk('/api/people/assistants/sessions/'.$taSession.'/checkout', 'POST', [], $assistantToken)['checkoutLuc'] === $taClockOut['checkoutLuc'], 'assistant checkout replay idempotent');

    // Regression: Assistant check-in & check-out MUST still work independently AFTER Main Teacher has checked out (buoi_hoc.trang_thai='da_day')
    $taAfterTeacherSess = Store::insert('buoi_hoc', ['lop_id'=>$classId, 'ngay'=>'2098-01-27', 'gio_bat_dau'=>'18:00', 'gio_ket_thuc'=>'20:00', 'trang_thai'=>'da_day']);
    Store::insert('phan_cong_buoi_hoc', ['buoi_hoc_id'=>$taAfterTeacherSess, 'user_id'=>$taUser, 'vai_tro'=>'tro_giang', 'don_gia'=>80000, 'trang_thai'=>'da_phan_cong']);
    $taIndepIn = requestOk('/api/people/assistants/sessions/'.$taAfterTeacherSess.'/checkin', 'POST', [], $assistantToken);
    check($taIndepIn['checkinLuc'] !== null && $taIndepIn['trangThai'] === 'da_day', 'assistant can check-in independently after teacher marked buoi_hoc da_day');
    Store::execute('UPDATE phan_cong_buoi_hoc SET checkin_luc=DATE_SUB(NOW(),INTERVAL 5 MINUTE) WHERE buoi_hoc_id=? AND user_id=?', [$taAfterTeacherSess, $taUser]);
    $taIndepOut = requestOk('/api/people/assistants/sessions/'.$taAfterTeacherSess.'/checkout', 'POST', [], $assistantToken);
    check($taIndepOut['checkoutLuc'] !== null && $taIndepOut['phanCongTrangThai'] === 'da_day', 'assistant can check-out independently after teacher marked buoi_hoc da_day');
    Store::execute('DELETE FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=?', [$taAfterTeacherSess]);
    Store::execute('DELETE FROM buoi_hoc WHERE id=?', [$taAfterTeacherSess]);

    // Timekeeping calculation: 18:10 -> 19:30 => 80 minutes + late; 17:50 -> 20:10 => 120 minutes + no late (distinct deterministic dates)
    $schedDay1 = '2098-02-01';
    $schedDay2 = '2098-02-02';
    $testBuoi1 = Store::insert('buoi_hoc', ['lop_id'=>$classId, 'ngay'=>$schedDay1, 'gio_bat_dau'=>'18:00', 'gio_ket_thuc'=>'20:00']);
    $pcTest1 = Store::insert('phan_cong_buoi_hoc', ['buoi_hoc_id'=>$testBuoi1, 'user_id'=>$taUser, 'vai_tro'=>'tro_giang', 'don_gia'=>80000, 'trang_thai'=>'da_phan_cong']);
    Store::execute("UPDATE phan_cong_buoi_hoc SET checkin_luc=?, checkout_luc=? WHERE id=?", [$schedDay1.' 18:10:00', $schedDay1.' 19:30:00', $pcTest1]);
    $pcRes1 = Store::one('SELECT so_phut_tinh_cong, checkin_tre FROM phan_cong_buoi_hoc WHERE id=?', [$pcTest1]);
    check((int)$pcRes1['so_phut_tinh_cong'] === 80 && (int)$pcRes1['checkin_tre'] === 1, '18:10 to 19:30 yields 80 minutes and late flag');

    $testBuoi2 = Store::insert('buoi_hoc', ['lop_id'=>$classId, 'ngay'=>$schedDay2, 'gio_bat_dau'=>'18:00', 'gio_ket_thuc'=>'20:00']);
    $pcTest2 = Store::insert('phan_cong_buoi_hoc', ['buoi_hoc_id'=>$testBuoi2, 'user_id'=>$taUser, 'vai_tro'=>'tro_giang', 'don_gia'=>80000, 'trang_thai'=>'da_phan_cong']);
    Store::execute("UPDATE phan_cong_buoi_hoc SET checkin_luc=?, checkout_luc=? WHERE id=?", [$schedDay2.' 17:50:00', $schedDay2.' 20:10:00', $pcTest2]);
    $pcRes2 = Store::one('SELECT so_phut_tinh_cong, checkin_tre FROM phan_cong_buoi_hoc WHERE id=?', [$pcTest2]);
    check((int)$pcRes2['so_phut_tinh_cong'] === 120 && (int)$pcRes2['checkin_tre'] === 0, '17:50 to 20:10 yields 120 minutes and not late');

    // 4. Requests (chỉ nghỉ cá nhân bao_ban, đổi giờ doi_gio, điều chỉnh công dieu_chinh_cong; chặn nghỉ cả lớp nghi_day)
    $futureAssignedSession = Store::insert('buoi_hoc', ['lop_id'=>$classId, 'ngay'=>'2098-02-03', 'gio_bat_dau'=>'18:00', 'gio_ket_thuc'=>'20:00']);
    Store::insert('phan_cong_buoi_hoc', ['buoi_hoc_id'=>$futureAssignedSession, 'user_id'=>$taUser, 'vai_tro'=>'tro_giang', 'don_gia'=>80000, 'trang_thai'=>'da_phan_cong']);

    check(http('/api/people/assistants/requests', 'POST', [
        'loai' => 'nghi_day',
        'buoiHocId' => $futureAssignedSession,
        'lyDo' => 'Thử xin nghỉ cả lớp'
    ], $assistantToken)[0] === 400, 'assistant cannot submit whole-class leave nghi_day');

    $bbReq = requestOk('/api/people/assistants/requests', 'POST', [
        'loai' => 'bao_ban',
        'buoiHocId' => $futureAssignedSession,
        'lyDo' => 'Bận thi học kỳ'
    ], $assistantToken, 201);
    check(str_starts_with($bbReq['ma'], 'BB-'), 'bao_ban returns BB- code');
    check(Store::one("SELECT doi_tuong FROM don_xin_nghi WHERE id=?", [$bbReq['id']])['doi_tuong'] === 'tro_giang', 'bao_ban stores doi_tuong tro_giang');

    check(http('/api/people/assistants/requests', 'POST', [
        'loai' => 'bao_ban',
        'buoiHocId' => $futureAssignedSession,
        'lyDo' => 'Gửi đơn trùng'
    ], $assistantToken)[0] === 409, 'duplicate pending bao_ban rejected');

    $futureAssignedSession2 = Store::insert('buoi_hoc', ['lop_id'=>$classId, 'ngay'=>'2098-02-04', 'gio_bat_dau'=>'18:00', 'gio_ket_thuc'=>'20:00']);
    Store::insert('phan_cong_buoi_hoc', ['buoi_hoc_id'=>$futureAssignedSession2, 'user_id'=>$taUser, 'vai_tro'=>'tro_giang', 'don_gia'=>80000, 'trang_thai'=>'da_phan_cong']);
    check(http('/api/people/assistants/requests', 'POST', [
        'loai' => 'doi_gio',
        'buoiHocId' => $futureAssignedSession2,
        'lyDo' => 'Đổi giờ',
        'ngayMoi' => '2098-02-05',
        'gioBatDau' => '20:00',
        'gioKetThuc' => '18:00'
    ], $assistantToken)[0] === 400, 'doi_gio end time before start time rejected');

    $dgReq = requestOk('/api/people/assistants/requests', 'POST', [
        'loai' => 'doi_gio',
        'buoiHocId' => $futureAssignedSession2,
        'lyDo' => 'Đổi giờ hỗ trợ',
        'ngayMoi' => '2098-02-05',
        'gioBatDau' => '18:00',
        'gioKetThuc' => '20:00'
    ], $assistantToken, 201);
    check(str_starts_with($dgReq['ma'], 'DG-'), 'doi_gio returns DG- code');

    $dcReq = requestOk('/api/people/assistants/requests', 'POST', [
        'loai' => 'dieu_chinh_cong',
        'buoiHocId' => $taSession,
        'lyDo' => 'Quên check-in đầu giờ',
        'checkinMoi' => $schedDay1.'T18:00',
        'checkoutMoi' => $schedDay1.'T20:00'
    ], $assistantToken, 201);
    check(str_starts_with($dcReq['ma'], 'DC-'), 'dieu_chinh_cong returns DC- code');

    $bbNotif = Store::one("SELECT user_id FROM thong_bao WHERE doi_tuong_loai='bao_ban' AND doi_tuong_id=?", [$bbReq['id']]);
    $dcNotif = Store::one("SELECT user_id FROM thong_bao WHERE doi_tuong_loai='dieu_chinh_cong' AND doi_tuong_id=?", [$dcReq['id']]);
    check(Store::one("SELECT group_name FROM role_group g JOIN account a ON a.group_id=g.group_id WHERE a.user_id=?", [$bbNotif['user_id']])['group_name'] === 'NhanVien', 'bao_ban notifies branch staff');
    check(Store::one("SELECT group_name FROM role_group g JOIN account a ON a.group_id=g.group_id WHERE a.user_id=?", [$dcNotif['user_id']])['group_name'] === 'Admin', 'dieu_chinh_cong notifies admin');

    // ==========================================
    // Shift Recruitment & Unified Profile Suite (006)
    // ==========================================
    // 1. Unified profile GET/PUT parity for GIAO_VIEN and TRO_GIANG (DB persisted, zero money/bank leakage)
    foreach ([['teacher', $teacherToken, 'GV Đơn vị cập nhật'], ['assistant', $assistantToken, 'TG Đơn vị cập nhật']] as [$roleTag, $roleTok, $orgText]) {
        $profBefore = requestOk('/api/auth/me/profile', 'GET', null, $roleTok);
        check(isset($profBefore['professional']['maNs'], $profBefore['professional']['lichRanhs']), $roleTag.' profile reads professional metadata');
        check(!preg_match('/donGia|nganHang|soTaiKhoan|chuTaiKhoan/i', json_encode($profBefore)), $roleTag.' profile excludes salary and bank info');
        $profSaved = requestOk('/api/auth/me/profile', 'PUT', [
            'hoTen' => $profBefore['hoTen'],
            'email' => $profBefore['email'],
            'donViCongTac' => $orgText,
            'gioiThieu' => 'Cập nhật hồ sơ '.$roleTag,
        ], $roleTok);
        check($profSaved['professional']['donViCongTac'] === $orgText && $profSaved['professional']['gioiThieu'] === 'Cập nhật hồ sơ '.$roleTag, $roleTag.' profile edit persists to DB');
    }

    // 2. Shift openings & applications (Staff creates/reviews; Teacher -> day_chinh ONLY; Assistant -> tro_giang ONLY)
    $recruitClass = requestOk('/api/academic/classes', 'POST', [
        'maLop' => 'REC-'.$suffix,
        'tenLop' => 'Lớp Tuyển Ca '.$suffix,
        'loaiLop' => 'nhom',
        'monHocId' => 1,
        'capLopId' => 9,
        'nguoiDayChinhId' => $teacher['id'],
        'hinhThuc' => 'offline',
        'khuVucId' => 1,
        'siSoToiThieu' => 1,
        'siSoToiDa' => 10,
        'hocPhiBuoi' => 180000,
        'mucTraChinh' => 150000,
        'mucTraTroGiang' => 80000,
        'soBuoiDuKien' => 8,
        'ngayBatDau' => '2026-11-01',
        'trangThai' => 'dang_hoc',
    ], $token, 201);
    $recruitDay = '2098-03-04';
    $recruitWeekday = (int)(new \DateTimeImmutable($recruitDay))->format('N') + 1;
    $recruitSession = Store::insert('buoi_hoc', ['lop_id' => $recruitClass['id'], 'ngay' => $recruitDay, 'gio_bat_dau' => '18:00', 'gio_ket_thuc' => '20:00']);
    $recruitSessionBefore = Store::one('SELECT ngay, gio_bat_dau, gio_ket_thuc, phong_hoc, trang_thai FROM buoi_hoc WHERE id=?', [$recruitSession]);

    // Permissions
    check(http('/api/academic/scheduling/openings', 'GET', null, $parentToken)[0] === 403, 'parent cannot read shift openings');
    check(http('/api/academic/scheduling/openings', 'POST', ['buoiHocId' => $recruitSession, 'vaiTro' => 'tro_giang'], $teacherToken)[0] === 403, 'teacher cannot create shift opening');
    check(http('/api/academic/scheduling/openings', 'POST', ['buoiHocId' => $recruitSession, 'vaiTro' => 'tro_giang'], $assistantToken)[0] === 403, 'assistant cannot create shift opening');
    check(http('/api/academic/scheduling/openings', 'POST', ['buoiHocId' => $recruitSession, 'vaiTro' => 'day_chinh', 'soLuong' => 2], $staffToken)[0] === 400, 'day_chinh opening rejects quantity > 1');

    // Staff creates TA opening & Teacher opening
    $taOpening = requestOk('/api/academic/scheduling/openings', 'POST', [
        'buoiHocId' => $recruitSession,
        'vaiTro' => 'tro_giang',
        'soLuong' => 1,
        'yeuCau' => 'Hỗ trợ chấm bài tập giấy',
    ], $staffToken, 201);
    check(str_starts_with($taOpening['maDeXuat'], 'TC-') && $taOpening['trangThai'] === 'dang_mo', 'staff creates tro_giang opening');
    check(http('/api/academic/scheduling/openings', 'POST', ['buoiHocId' => $recruitSession, 'vaiTro' => 'tro_giang'], $staffToken)[0] === 409, 'duplicate active opening rejected');

    $gvOpening = requestOk('/api/academic/scheduling/openings', 'POST', [
        'buoiHocId' => $recruitSession,
        'vaiTro' => 'day_chinh',
        'soLuong' => 1,
        'yeuCau' => 'Giáo viên đứng lớp Toán 9',
    ], $staffToken, 201);
    check($gvOpening['vaiTro'] === 'day_chinh', 'staff creates day_chinh opening');

    // Strict role visibility & cross-role apply block
    $gvOpeningsList = requestOk('/api/academic/scheduling/openings', 'GET', null, $teacherToken);
    $taOpeningsList = requestOk('/api/academic/scheduling/openings', 'GET', null, $assistantToken);
    check(!in_array('tro_giang', array_column($gvOpeningsList, 'vaiTro'), true), 'teacher only sees day_chinh openings');
    check(!in_array('day_chinh', array_column($taOpeningsList, 'vaiTro'), true), 'assistant only sees tro_giang openings');
    check(!preg_match('/donGia|mucTra|hocPhi|nganHang|soTaiKhoan/i', json_encode($taOpeningsList)), 'openings response excludes salary and bank info');
    check(http('/api/academic/scheduling/openings/'.$taOpening['id'].'/apply', 'POST', [], $teacherToken)[0] === 403, 'teacher cannot apply to tro_giang opening');
    check(http('/api/academic/scheduling/openings/'.$gvOpening['id'].'/apply', 'POST', [], $assistantToken)[0] === 403, 'assistant cannot apply to day_chinh opening');
    check(http('/api/academic/scheduling/openings/'.$taOpening['id'].'/apply', 'POST', [], $staffToken)[0] === 403, 'staff cannot apply to opening');

    // Unqualified assistant (missing subject/region/availability) is blocked on backend
    check(http('/api/academic/scheduling/openings/'.$taOpening['id'].'/apply', 'POST', ['ghiChu' => 'Thử ứng tuyển'], $assistantToken)[0] === 409, 'unqualified assistant cannot apply');

    // Equip assistant 1 & assistant 2 with matching subject, grade, region, and availability
    $ta1NsId = (int)Store::one('SELECT id FROM nhan_su_chuyen_mon WHERE user_id=?', [$taUser])['id'];
    $ta2NsId = (int)Store::one('SELECT id FROM nhan_su_chuyen_mon WHERE user_id=?', [$taUser2])['id'];
    foreach ([$ta1NsId, $ta2NsId] as $nsId) {
        Store::insert('nhan_su_mon', ['nhan_su_id' => $nsId, 'mon_hoc_id' => 1, 'cap_lop_tu' => 6, 'cap_lop_den' => 9, 'hoc_phi_buoi_de_xuat' => 80000]);
        Store::insert('nhan_su_khu_vuc', ['nhan_su_id' => $nsId, 'khu_vuc_id' => 1]);
        Store::insert('nhan_su_lich_ranh', ['nhan_su_id' => $nsId, 'thu' => $recruitWeekday, 'gio_bat_dau' => '17:30', 'gio_ket_thuc' => '21:00']);
    }

    // Assistant 1 applies -> pending, NOT on personal timetable yet, duplicate apply blocked
    $ta1App = requestOk('/api/academic/scheduling/openings/'.$taOpening['id'].'/apply', 'POST', ['ghiChu' => 'Rảnh đúng khung giờ'], $assistantToken, 201);
    check($ta1App['trangThai'] === 'cho_duyet' && $ta1App['phanCongId'] === null, 'pending application does not create assignment');
    $ta1SessionsPending = array_column(requestOk('/api/people/assistants/workspace', 'GET', null, $assistantToken)['sessions'], 'id');
    check(!in_array($recruitSession, $ta1SessionsPending, true), 'pending application is not shown on personal timetable');
    check(http('/api/academic/scheduling/openings/'.$taOpening['id'].'/apply', 'POST', [], $assistantToken)[0] === 409, 'duplicate application blocked');
    check(http('/api/academic/scheduling/applications/'.$ta1App['id'].'/review', 'POST', ['trangThai' => 'da_duyet'], $assistantToken)[0] === 403, 'assistant cannot review applications');

    // Staff rejects Assistant 1 with reason -> rejected, NOT on timetable, reason visible, double review blocked
    check(http('/api/academic/scheduling/applications/'.$ta1App['id'].'/review', 'POST', ['trangThai' => 'tu_choi'], $staffToken)[0] === 400, 'reject requires reason');
    $ta1Rejected = requestOk('/api/academic/scheduling/applications/'.$ta1App['id'].'/review', 'POST', ['trangThai' => 'tu_choi', 'lyDoTuChoi' => 'Ưu tiên trợ giảng chưa đủ ca trong tuần'], $staffToken);
    check($ta1Rejected['trangThai'] === 'tu_choi' && $ta1Rejected['phanCongId'] === null && str_contains($ta1Rejected['lyDoTuChoi'], 'Ưu tiên'), 'rejection saves reason and creates no assignment');
    check(http('/api/academic/scheduling/applications/'.$ta1App['id'].'/review', 'POST', ['trangThai' => 'da_duyet'], $staffToken)[0] === 409, 'double review rejected');
    $ta1SessionsRejected = array_column(requestOk('/api/people/assistants/workspace', 'GET', null, $assistantToken)['sessions'], 'id');
    check(!in_array($recruitSession, $ta1SessionsRejected, true), 'rejected application is not shown on personal timetable');

    // Assistant 2 applies & Staff approves -> creates official phan_cong_buoi_hoc, shows on Assistant 2 timetable, closes opening, preserves buoi_hoc
    $ta2App = requestOk('/api/academic/scheduling/openings/'.$taOpening['id'].'/apply', 'POST', ['ghiChu' => 'Sẵn sàng nhận ca'], $assistant2Token, 201);
    $ta2Approved = requestOk('/api/academic/scheduling/applications/'.$ta2App['id'].'/review', 'POST', ['trangThai' => 'da_duyet'], $staffToken);
    check($ta2Approved['trangThai'] === 'da_duyet' && $ta2Approved['phanCongId'] !== null, 'approved application creates official assignment');
    check(http('/api/academic/scheduling/applications/'.$ta2App['id'].'/review', 'POST', ['trangThai' => 'da_duyet'], $staffToken)[0] === 409, 'double approval rejected');
    $ta2SessionsAfter = array_column(requestOk('/api/people/assistants/workspace', 'GET', null, $assistant2Token)['sessions'], 'id');
    check(in_array($recruitSession, $ta2SessionsAfter, true), 'approved shift appears on assistant 2 personal timetable');
    check(Store::one('SELECT trang_thai FROM de_xuat_tuyen_ca WHERE id=?', [$taOpening['id']])['trang_thai'] === 'da_du_nguoi', 'filled opening closes automatically');
    $recruitSessionAfterTa = Store::one('SELECT ngay, gio_bat_dau, gio_ket_thuc, phong_hoc, trang_thai FROM buoi_hoc WHERE id=?', [$recruitSession]);
    check($recruitSessionAfterTa === $recruitSessionBefore, 'approving application never modifies session schedule');

    // Conflict guard: overlapping shift opening on same day/time blocks Assistant 2 from applying
    $conflictSession = Store::insert('buoi_hoc', ['lop_id' => $classId, 'ngay' => $recruitDay, 'gio_bat_dau' => '18:30', 'gio_ket_thuc' => '20:00']);
    $conflictOpening = requestOk('/api/academic/scheduling/openings', 'POST', ['buoiHocId' => $conflictSession, 'vaiTro' => 'tro_giang'], $staffToken, 201);
    check(http('/api/academic/scheduling/openings/'.$conflictOpening['id'].'/apply', 'POST', [], $assistant2Token)[0] === 409, 'overlapping shift blocked from applying');
    requestOk('/api/academic/scheduling/openings/'.$conflictOpening['id'].'/close', 'POST', [], $staffToken);

    // Teacher applies to day_chinh opening & Staff approves -> appears on Teacher timetable
    Store::insert('nhan_su_lich_ranh', ['nhan_su_id' => $teacher['id'], 'thu' => $recruitWeekday, 'gio_bat_dau' => '17:30', 'gio_ket_thuc' => '21:00']);
    $gvApp = requestOk('/api/academic/scheduling/openings/'.$gvOpening['id'].'/apply', 'POST', ['ghiChu' => 'Nhận dạy chính'], $teacherToken, 201);
    $gvApproved = requestOk('/api/academic/scheduling/applications/'.$gvApp['id'].'/review', 'POST', ['trangThai' => 'da_duyet'], $staffToken);
    check($gvApproved['trangThai'] === 'da_duyet' && $gvApproved['phanCongId'] !== null, 'approved teacher application creates day_chinh assignment');
    $gvSessionsAfter = array_column(requestOk('/api/academic/attendance/workspace', 'GET', null, $teacherToken)['sessions'], 'id');
    check(in_array($recruitSession, $gvSessionsAfter, true), 'approved teacher shift appears on teacher personal timetable');
    check(Store::one('SELECT ngay, gio_bat_dau, gio_ket_thuc, phong_hoc, trang_thai FROM buoi_hoc WHERE id=?', [$recruitSession]) === $recruitSessionBefore, 'teacher approval preserves session schedule');

    // Tutor (GIA_SU) portal parity with Teacher + strict tutoring-only class scheduling & recruitment rules
    $tutorToken = requestOk('/api/auth/login', 'POST', ['username' => 'gs_'.$suffix, 'password' => 'Tutor@123'])['token'];
    $tutorWorkInit = requestOk('/api/people/tutors/me/workspace', 'GET', null, $tutorToken);
    check(isset($tutorWorkInit['profile'], $tutorWorkInit['sessions'], $tutorWorkInit['classes'], $tutorWorkInit['requests'], $tutorWorkInit['periods']), 'tutor workspace returns full structure');
    check(!preg_match('/donGia|don_gia|mucTra|muc_tra|tongLuong|tong_luong|nganHang|soTaiKhoan/i', json_encode($tutorWorkInit)), 'tutor workspace excludes salary and bank info');
    check(http('/api/people/tutors/me/workspace', 'GET', null, $teacherToken)[0] === 403, 'teacher cannot access tutor workspace');
    check(http('/api/people/tutors/me/workspace', 'GET', null, $assistantToken)[0] === 403, 'assistant cannot access tutor workspace');
    check(http('/api/people/tutors/me/workspace', 'GET', null, $parentToken)[0] === 403, 'parent cannot access tutor workspace');
    check(http('/api/people/assistants/workspace', 'GET', null, $tutorToken)[0] === 403, 'tutor cannot access assistant workspace');
    check(http('/frontend/src/modules/people/features/tutors/tutors.html')[0] === 200 && http('/frontend/src/modules/people/features/tutors/tutors.js')[0] === 200, 'tutor frontend portal assets served');

    // Equip pure tutor ($teacher2, phan_loai='gia_su') with subject, grade, region, mode & availability via Tutor API
    Store::execute("UPDATE nhan_su_chuyen_mon SET hinh_thuc_day='online,offline' WHERE id=?", [$teacher2['id']]);
    Store::insert('nhan_su_mon', ['nhan_su_id' => $teacher2['id'], 'mon_hoc_id' => 1, 'cap_lop_tu' => 6, 'cap_lop_den' => 9, 'hoc_phi_buoi_de_xuat' => 180000]);
    Store::insert('nhan_su_khu_vuc', ['nhan_su_id' => $teacher2['id'], 'khu_vuc_id' => 1]);
    $tutorDay1 = '2098-03-11';
    $tutorDay2 = '2098-03-18';
    $tutorWeekday = (int)(new \DateTimeImmutable($tutorDay1))->format('N') + 1;
    $tutorSlot = requestOk('/api/people/tutors/me/availability', 'POST', ['thu' => $tutorWeekday, 'gioBatDau' => '17:00', 'gioKetThuc' => '21:00'], $tutorToken, 201);
    check(http('/api/people/tutors/me/availability', 'POST', ['thu' => $tutorWeekday, 'gioBatDau' => '18:00', 'gioKetThuc' => '19:00'], $tutorToken)[0] === 409, 'tutor duplicate availability rejected');
    $tutorSlotUpdated = requestOk('/api/people/tutors/me/availability/'.$tutorSlot['id'], 'PUT', ['thu' => $tutorWeekday, 'gioBatDau' => '17:30', 'gioKetThuc' => '21:30'], $tutorToken);
    check(str_starts_with($tutorSlotUpdated['gioBatDau'], '17:30'), 'tutor availability update persists');
    $tutorSlotTemp = requestOk('/api/people/tutors/me/availability', 'POST', ['thu' => ($tutorWeekday === 8 ? 2 : $tutorWeekday + 1), 'gioBatDau' => '08:00', 'gioKetThuc' => '10:00'], $tutorToken, 201);
    requestOk('/api/people/tutors/me/availability/'.$tutorSlotTemp['id'], 'DELETE', null, $tutorToken);

    // Core rule: Staff CANNOT schedule pure tutor into group (nhom) class via operations-sessions or direct assign
    check(http('/api/academic/scheduling/operations-sessions', 'POST', [
        'lopId' => $recruitClass['id'],
        'ngay' => $tutorDay1,
        'gioBatDau' => '18:00',
        'gioKetThuc' => '20:00',
        'userId' => $teacher2['userId'],
    ], $staffToken)[0] === 400, 'staff blocked from scheduling pure tutor into group class');
    $nhomSessionForTutorCheck = Store::insert('buoi_hoc', ['lop_id' => $recruitClass['id'], 'ngay' => $tutorDay1, 'gio_bat_dau' => '18:00', 'gio_ket_thuc' => '20:00']);
    check(http('/api/academic/scheduling/sessions/'.$nhomSessionForTutorCheck.'/assign', 'POST', [
        'userId' => $teacher2['userId'],
        'vaiTro' => 'day_chinh',
        'donGia' => 120000,
    ], $staffToken)[0] === 400, 'staff blocked from directly assigning pure tutor as main teacher of group class');

    // Create tutoring class (kem_1_1) and schedule pure tutor via Staff -> allowed
    $tutorClass = requestOk('/api/academic/classes', 'POST', [
        'maLop' => 'TUT-'.$suffix,
        'tenLop' => 'Kèm Toán 1:1',
        'loaiLop' => 'kem_1_1',
        'monHocId' => 1,
        'capLopId' => 9,
        'khuVucId' => 1,
        'hinhThuc' => 'offline',
        'nguoiDayChinhId' => $teacher2['id'],
        'siSoToiThieu' => 1,
        'siSoToiDa' => 1,
        'hocPhiBuoi' => 220000,
        'mucTraChinh' => 150000,
        'soBuoiDuKien' => 8,
        'ngayBatDau' => '2026-11-01',
        'trangThai' => 'dang_hoc',
    ], $token, 201);
    requestOk('/api/academic/classes/'.$tutorClass['id'].'/enrollments', 'POST', ['hocVienId' => $student['id'], 'ngayVao' => '2026-11-01'], $token, 201);
    $tutorSessionCreated = requestOk('/api/academic/scheduling/operations-sessions', 'POST', [
        'lopId' => $tutorClass['id'],
        'ngay' => $tutorDay1,
        'gioBatDau' => '18:00',
        'gioKetThuc' => '20:00',
        'userId' => $teacher2['userId'],
    ], $staffToken, 201);
    $tutorSessionId = (int)$tutorSessionCreated['ids'][0];

    // Shift recruitment for GIA_SU: hidden/blocked on nhom, visible & approvable on kem_1_1
    $nhomOpening = requestOk('/api/academic/scheduling/openings', 'POST', [
        'buoiHocId' => $nhomSessionForTutorCheck,
        'vaiTro' => 'day_chinh',
        'soLuong' => 1,
        'yeuCau' => 'Lớp nhóm chỉ tuyển giáo viên',
    ], $staffToken, 201);
    $tutorRecruitSession = Store::insert('buoi_hoc', ['lop_id' => $tutorClass['id'], 'ngay' => $tutorDay2, 'gio_bat_dau' => '18:00', 'gio_ket_thuc' => '20:00']);
    $tutorOpening = requestOk('/api/academic/scheduling/openings', 'POST', [
        'buoiHocId' => $tutorRecruitSession,
        'vaiTro' => 'day_chinh',
        'soLuong' => 1,
        'yeuCau' => 'Tuyển gia sư kèm 1:1 Toán 9',
    ], $staffToken, 201);
    $tutorOpeningsList = requestOk('/api/academic/scheduling/openings', 'GET', null, $tutorToken);
    $tutorOpeningIds = array_column($tutorOpeningsList, 'id');
    check(!in_array($nhomOpening['id'], $tutorOpeningIds, true), 'tutor does not see group class openings');
    check(in_array($tutorOpening['id'], $tutorOpeningIds, true), 'tutor sees kem_1_1 tutoring opening');
    check(http('/api/academic/scheduling/openings/'.$nhomOpening['id'].'/apply', 'POST', [], $tutorToken)[0] === 403, 'tutor blocked from applying to group class opening');
    $tutorApp = requestOk('/api/academic/scheduling/openings/'.$tutorOpening['id'].'/apply', 'POST', ['ghiChu' => 'Rảnh kèm 1:1'], $tutorToken, 201);
    check($tutorApp['trangThai'] === 'cho_duyet' && $tutorApp['phanCongId'] === null, 'tutor shift application starts pending');
    $tutorApproved = requestOk('/api/academic/scheduling/applications/'.$tutorApp['id'].'/review', 'POST', ['trangThai' => 'da_duyet'], $staffToken);
    check($tutorApproved['trangThai'] === 'da_duyet' && $tutorApproved['phanCongId'] !== null, 'staff approves tutor shift application');
    requestOk('/api/academic/scheduling/openings/'.$nhomOpening['id'].'/close', 'POST', [], $staffToken);

    // Tutor pre-session material upload, check-in, previous-homework grading, check-out & shift request policy
    $tutorPrevSession = Store::insert('buoi_hoc', ['lop_id' => $tutorClass['id'], 'ngay' => '2098-03-04', 'gio_bat_dau' => '18:00', 'gio_ket_thuc' => '20:00', 'trang_thai' => 'da_day']);
    Store::insert('phan_cong_buoi_hoc', ['buoi_hoc_id' => $tutorPrevSession, 'user_id' => $teacher2['userId'], 'vai_tro' => 'day_chinh', 'don_gia' => 150000, 'trang_thai' => 'da_day']);
    [$tutMatStatus, $tutMatBody] = httpMultipart('/api/people/tutors/me/sessions/'.$tutorPrevSession.'/material',
        ['tieuDe' => 'Phiếu tài liệu & BTVN kèm 1:1'],
        ['file' => ['tutor-hw.pdf', $minimalPdf, 'application/pdf']], $tutorToken);
    check($tutMatStatus === 200 && ($tutMatBody['data']['taiLieu']['laTepTaiLen'] ?? false), 'tutor uploads pre-session material PDF');
    $tutorPrevHwId = (int)$tutMatBody['data']['baiTap']['id'];

    $tutorWorkNow = requestOk('/api/people/tutors/me/workspace', 'GET', null, $tutorToken);
    $tutorSessionIds = array_map('intval', array_column($tutorWorkNow['sessions'], 'id'));
    check(in_array($tutorSessionId, $tutorSessionIds, true) && in_array($tutorRecruitSession, $tutorSessionIds, true), 'tutor timetable shows scheduled and approved tutoring shifts');
    $tutorDetail = requestOk('/api/people/tutors/me/sessions/'.$tutorSessionId, 'GET', null, $tutorToken);
    check($tutorDetail['loaiLop'] === 'kem_1_1', 'tutor session detail exposes loaiLop');
    check(($tutorDetail['quyenYeuCau']['coTheDoiLich'] ?? false) === true && ($tutorDetail['quyenYeuCau']['coTheChinhCong'] ?? true) === false, 'upcoming tutor shift allows only reschedule');
    $tutorLogBody = [
        'revision' => $tutorDetail['revision'],
        'chuDe' => 'Ôn tập chuyên đề kèm 1:1',
        'noiDung' => 'Giải phương trình bậc 2',
        'baiTapChamId' => $tutorPrevHwId,
        'hocViens' => [['hocVienId' => $student['id'], 'trangThaiDiemDanh' => 'co_mat', 'diemBaiTapGiay' => 9.0, 'nhanXetBuoiHoc' => 'Tiếp thu nhanh']],
    ];
    check(http('/api/people/tutors/me/sessions/'.$tutorSessionId.'/log', 'PUT', $tutorLogBody, $tutorToken)[0] === 409, 'tutor must check in before saving log');
    check(http('/api/people/tutors/me/sessions/'.$tutorSessionId.'/checkin', 'POST', ['checkinLuc' => '2000-01-01'], $tutorToken)[0] === 400, 'tutor checkin rejects client timestamp');
    $tutorClockIn = requestOk('/api/people/tutors/me/sessions/'.$tutorSessionId.'/checkin', 'POST', [], $tutorToken);
    check($tutorClockIn['checkinLuc'] !== null && $tutorClockIn['trangThai'] === 'dang_day', 'tutor checkin persists with server time');
    check(($tutorClockIn['quyenYeuCau']['coTheDoiLich'] ?? true) === false && ($tutorClockIn['quyenYeuCau']['coTheChinhCong'] ?? true) === false, 'in-progress tutor shift hides both requests');
    check(http('/api/people/tutors/me/requests', 'POST', ['loai' => 'dieu_chinh_cong', 'buoiHocId' => $tutorSessionId, 'lyDo' => 'Chỉnh công lúc đang kèm', 'checkinMoi' => $tutorDay1.'T18:00', 'checkoutMoi' => $tutorDay1.'T20:00'], $tutorToken)[0] === 409, 'in-progress tutor shift blocks time adjustment');
    $tutorLogBody['revision'] = $tutorClockIn['revision'];
    check(http('/api/people/tutors/me/sessions/'.$tutorSessionId.'/log', 'PUT', $tutorLogBody + ['baiTap' => ['tieuDe' => 'Không được tạo mới']], $tutorToken)[0] === 400, 'tutor log rejects creating new homework');
    $tutorSavedLog = requestOk('/api/people/tutors/me/sessions/'.$tutorSessionId.'/log', 'PUT', $tutorLogBody, $tutorToken);
    check((float)$tutorSavedLog['hocViens'][0]['diemBaiTapGiay'] === 9.0 && (int)$tutorSavedLog['hocViens'][0]['baiTapId'] === $tutorPrevHwId, 'tutor saves attendance and previous homework grade');
    Store::execute('UPDATE phan_cong_buoi_hoc SET checkin_luc=DATE_SUB(NOW(),INTERVAL 5 MINUTE) WHERE id=?', [$tutorClockIn['phanCongId']]);
    $tutorClockOut = requestOk('/api/people/tutors/me/sessions/'.$tutorSessionId.'/checkout', 'POST', [], $tutorToken);
    check($tutorClockOut['checkoutLuc'] !== null && $tutorClockOut['trangThai'] === 'da_day', 'tutor checkout persists');
    check(($tutorClockOut['quyenYeuCau']['coTheDoiLich'] ?? true) === false && ($tutorClockOut['quyenYeuCau']['coTheChinhCong'] ?? false) === true, 'completed tutor shift allows only time adjustment');

    // Tutor requests (nghi_day, doi_lich, dieu_chinh_cong)
    $tutorReqLeave = requestOk('/api/people/tutors/me/requests', 'POST', ['loai' => 'nghi_day', 'buoiHocId' => $tutorRecruitSession, 'lyDo' => 'Xin nghỉ ca kèm'], $tutorToken, 201);
    check($tutorReqLeave['trangThai'] === 'cho_duyet', 'tutor can submit class leave request');
    check(http('/api/people/tutors/me/requests', 'POST', ['loai' => 'nghi_day', 'buoiHocId' => $tutorRecruitSession, 'lyDo' => 'Gửi trùng'], $tutorToken)[0] === 409, 'tutor duplicate request rejected');
    $tutorReqShift = requestOk('/api/people/tutors/me/requests', 'POST', ['loai' => 'doi_lich', 'buoiHocId' => $tutorRecruitSession, 'lyDo' => 'Đề xuất đổi giờ', 'ngayMoi' => '2098-03-19', 'gioBatDau' => '18:00', 'gioKetThuc' => '20:00'], $tutorToken, 201);
    check($tutorReqShift['trangThai'] === 'cho_duyet', 'tutor can submit reschedule request');
    $tutorReqAdj = requestOk('/api/people/tutors/me/requests', 'POST', ['loai' => 'dieu_chinh_cong', 'buoiHocId' => $tutorSessionId, 'lyDo' => 'Quên bấm check-out đúng giờ', 'checkinMoi' => $tutorDay1.'T18:00', 'checkoutMoi' => $tutorDay1.'T20:00'], $tutorToken, 201);
    check($tutorReqAdj['trangThai'] === 'cho_duyet', 'tutor can submit timekeeping adjustment request');

    // Tutor parent opportunities strictly limited to kem_1_1 / kem_1_2 (group blocked 403)
    $staffEmpId = (int)Store::one('SELECT id FROM nhan_vien WHERE user_id=?', [$staffUser])['id'];
    $tutorOppId = Store::insert('yeu_cau_hoc', [
        'ma_yc' => 'YC-TUT-'.$suffix, 'nguoi_gui_id' => $parent['userId'], 'hoc_vien_id' => $student['id'],
        'mon_hoc_id' => 1, 'cap_lop_id' => 9, 'khu_vuc_id' => 1, 'hinh_thuc' => 'offline',
        'loai_lop_mong_muon' => 'kem_1_1', 'nhan_vien_id' => $staffEmpId, 'trang_thai' => 'dang_tim',
    ]);
    Store::insert('yeu_cau_lich', ['yeu_cau_id' => $tutorOppId, 'thu' => $tutorWeekday, 'gio_bat_dau' => '20:00', 'gio_ket_thuc' => '21:00']);
    $groupOppId = Store::insert('yeu_cau_hoc', [
        'ma_yc' => 'YC-GRP-'.$suffix, 'nguoi_gui_id' => $parent['userId'], 'hoc_vien_id' => $student['id'],
        'mon_hoc_id' => 1, 'cap_lop_id' => 9, 'khu_vuc_id' => 1, 'hinh_thuc' => 'offline',
        'loai_lop_mong_muon' => 'nhom', 'nhan_vien_id' => $staffEmpId, 'trang_thai' => 'dang_tim',
    ]);
    Store::insert('yeu_cau_lich', ['yeu_cau_id' => $groupOppId, 'thu' => $tutorWeekday, 'gio_bat_dau' => '20:00', 'gio_ket_thuc' => '21:00']);
    $tutorOppIds = array_map('intval', array_column(requestOk('/api/people/tutors/me/opportunities', 'GET', null, $tutorToken), 'id'));
    check(in_array($tutorOppId, $tutorOppIds, true) && !in_array($groupOppId, $tutorOppIds, true), 'tutor opportunities include kem_1_1 and exclude nhom');
    check(http('/api/people/tutors/me/opportunities/'.$groupOppId.'/apply', 'POST', [], $tutorToken)[0] === 403, 'tutor blocked from applying to group opportunity');
    $tutorOppApplied = requestOk('/api/people/tutors/me/opportunities/'.$tutorOppId.'/apply', 'POST', [], $tutorToken, 201);
    check($tutorOppApplied['ttNhanSu'] === 'dong_y', 'tutor applies to kem_1_1 opportunity');
    Store::execute('DELETE FROM de_xuat_nhan_su WHERE yeu_cau_id IN (?,?)', [$tutorOppId, $groupOppId]);
    Store::execute('DELETE FROM yeu_cau_lich WHERE yeu_cau_id IN (?,?)', [$tutorOppId, $groupOppId]);
    Store::execute('DELETE FROM yeu_cau_hoc WHERE id IN (?,?)', [$tutorOppId, $groupOppId]);

    // ==========================================
    // Email Notification Worker & Detailed Reports (CSV/XLSX) Suite
    // ==========================================
    $remStart = (new \DateTimeImmutable('+4 hours', new \DateTimeZone('Asia/Ho_Chi_Minh')));
    if ((int)$remStart->format('H') >= 22) {
        // Stay on one calendar day inside the 24h window so +90m never wraps past midnight and violates ck_buoi_gio.
        $remStart = $remStart->modify('tomorrow 09:00');
    }
    $remEnd = $remStart->modify('+90 minutes');
    $remSessionId = Store::insert('buoi_hoc', [
        'lop_id' => $tutorClass['id'],
        'ngay' => $remStart->format('Y-m-d'),
        'gio_bat_dau' => $remStart->format('H:i:00'),
        'gio_ket_thuc' => $remEnd->format('H:i:00'),
        'trang_thai' => 'du_kien',
    ]);
    Store::insert('phan_cong_buoi_hoc', [
        'buoi_hoc_id' => $remSessionId,
        'user_id' => $teacher2['userId'],
        'vai_tro' => 'day_chinh',
        'don_gia' => 150000,
        'cach_tinh_luong' => 'theo_gio',
        'phan_cong_boi' => (int)$login['user']['userId'],
    ]);
    $remRun1 = \App\Modules\Communication\Features\Notifications\EmailWorkerService::enqueueUpcomingReminders(24);
    check($remRun1['enqueuedStaff'] >= 1 && $remRun1['enqueuedParents'] >= 1, 'email worker enqueues staff and parent reminders');
    $remRun2 = \App\Modules\Communication\Features\Notifications\EmailWorkerService::enqueueUpcomingReminders(24);
    check($remRun2['enqueuedTotal'] === 0, 'reminder enqueue is idempotent with zero duplicates');

    // Lease / skip-locked concurrency claim prevents double claiming
    $claim1 = \App\Modules\Communication\Features\Notifications\EmailWorkerService::claimBatch(1, 5, 300);
    check(count($claim1) === 1, 'atomic claimBatch claims 1 ready notification');
    $claim2 = \App\Modules\Communication\Features\Notifications\EmailWorkerService::claimBatch(50, 5, 300);
    check(!in_array((int)$claim1[0]['id'], array_map('intval', array_column($claim2, 'id')), true), 'claimed row lease blocks duplicate concurrent claim');
    // Reset leases so full batch processes cleanly with mock mailer
    Store::execute("UPDATE thong_bao SET email_thu_tiep_luc=NULL WHERE co_gui_email=1 AND trang_thai_email='chua_gui'");

    // Sensitive content (OTP) & wrong-owner financial email are permanently blocked without retry
    $badOtpNotifId = Store::insert('thong_bao', [
        'user_id' => $teacher['userId'],
        'loai' => 'he_thong',
        'tieu_de' => 'OTP test',
        'noi_dung' => 'Mã OTP 123456 không được gửi qua hàng đợi',
        'co_gui_email' => 1,
    ]);
    $workerMailer = new class extends \CodeIgniter\Email\Email {
        public array $sent = [];
        public bool $deliver = true;
        public function send($autoClear = true) {
            if (!$this->deliver) {
                throw new \RuntimeException('SMTP timeout password=SuperSecret123');
            }
            $this->sent[] = ['to' => $this->recipients, 'subject' => $this->headers['Subject'] ?? '', 'body' => $this->body];
            return true;
        }
    };
    \Config\Services::injectMock('email', $workerMailer);
    try {
        $batchRes = \App\Modules\Communication\Features\Notifications\EmailWorkerService::processBatch(['limit' => 50, 'skipReminders' => true]);
        check($batchRes['sent'] >= 2 && $batchRes['permanentFailed'] >= 1, 'email worker sends valid queued emails and blocks sensitive OTP');
        $otpRow = Store::one('SELECT trang_thai_email, so_lan_thu_email, email_thu_tiep_luc FROM thong_bao WHERE id=?', [$badOtpNotifId]);
        check($otpRow['trang_thai_email'] === 'loi' && (int)$otpRow['so_lan_thu_email'] === 5 && $otpRow['email_thu_tiep_luc'] === null, 'permanent failure does not retry infinitely');
        $reRun = \App\Modules\Communication\Features\Notifications\EmailWorkerService::processBatch(['limit' => 50, 'skipReminders' => true]);
        check($reRun['claimed'] === 0 && $reRun['sent'] === 0, 're-running email worker never resends already sent notifications');

        // Transient SMTP failure schedules backoff and redacts secrets in loi_email
        $retryNotifId = Store::insert('thong_bao', [
            'user_id' => $parent['userId'],
            'loai' => 'he_thong',
            'tieu_de' => 'Kiểm thử retry tạm thời',
            'noi_dung' => 'Nội dung thông báo nghiệp vụ hợp lệ.',
            'co_gui_email' => 1,
        ]);
        $workerMailer->deliver = false;
        $retryRes = \App\Modules\Communication\Features\Notifications\EmailWorkerService::processBatch(['limit' => 10, 'skipReminders' => true]);
        check($retryRes['retried'] === 1, 'transient SMTP error schedules retry');
        $retryRow = Store::one('SELECT trang_thai_email, so_lan_thu_email, email_thu_tiep_luc, loi_email FROM thong_bao WHERE id=?', [$retryNotifId]);
        check($retryRow['trang_thai_email'] === 'loi' && (int)$retryRow['so_lan_thu_email'] === 1 && $retryRow['email_thu_tiep_luc'] !== null, 'backoff timestamp and attempt count recorded');
        check(!str_contains((string)$retryRow['loi_email'], 'SuperSecret123') && str_contains((string)$retryRow['loi_email'], '[REDACTED]'), 'sanitized email error never leaks SMTP secrets');
    } finally {
        \Config\Services::resetSingle('email');
    }
    $emailStats = requestOk('/api/communication/notifications/email-stats', 'GET', null, $token);
    check(isset($emailStats['summary']['sent']) && $emailStats['summary']['sent'] >= 2, 'admin reads email queue stats');
    check(http('/api/communication/notifications/email-stats', 'GET', null, $teacherToken)[0] === 403, 'teacher cannot read email queue stats');
    check(http('/api/communication/notifications/email-stats', 'GET', null, $staffToken)[0] === 403, 'staff cannot read email queue stats');

    // Detailed Reports & CSV/XLSX Export (same filters, strict RBAC, formula injection protection)
    $rptFrom = '2026-11-01';
    $rptTo = '2026-12-31';
    $rptMetaAdmin = requestOk('/api/reporting/dashboard/reports/meta', 'GET', null, $token);
    check(in_array('revenue', $rptMetaAdmin['allowedReports'], true)
        && in_array('payroll', $rptMetaAdmin['allowedReports'], true)
        && in_array('debt', $rptMetaAdmin['allowedReports'], true)
        && in_array('admissions', $rptMetaAdmin['allowedReports'], true), 'admin report meta includes all six reports');
    $rptMetaStaff = requestOk('/api/reporting/dashboard/reports/meta', 'GET', null, $staffToken);
    check(!in_array('revenue', $rptMetaStaff['allowedReports'], true)
        && !in_array('payroll', $rptMetaStaff['allowedReports'], true)
        && !in_array('debt', $rptMetaStaff['allowedReports'], true)
        && in_array('admissions', $rptMetaStaff['allowedReports'], true), 'staff report meta excludes revenue, payroll, and debt');
    foreach ([$teacherToken, $tutorToken, $assistantToken, $parentToken] as $nonMgrToken) {
        check(http('/api/reporting/dashboard/reports/meta', 'GET', null, $nonMgrToken)[0] === 403, 'non-manager cannot read report meta');
        check(http('/api/reporting/dashboard/reports/attendance?tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $nonMgrToken)[0] === 403, 'non-manager cannot read attendance report');
        check(http('/api/reporting/dashboard/reports/revenue?tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $nonMgrToken)[0] === 403, 'non-manager cannot read revenue report');
        check(http('/api/reporting/dashboard/reports/payroll?tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $nonMgrToken)[0] === 403, 'non-manager cannot read payroll report');
        check(http('/api/reporting/dashboard/reports/debt?tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $nonMgrToken)[0] === 403, 'non-manager cannot read debt report');
        check(http('/api/reporting/dashboard/reports/revenue/export?format=xlsx&tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $nonMgrToken)[0] === 403, 'non-manager cannot export revenue report');
        check(http('/api/reporting/dashboard/reports/payroll/export?format=xlsx&tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $nonMgrToken)[0] === 403, 'non-manager cannot export payroll report');
    }
    check(http('/api/reporting/dashboard/reports/revenue?tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $staffToken)[0] === 403, 'staff cannot access admin-only revenue report');
    check(http('/api/reporting/dashboard/reports/revenue/export?format=csv&tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $staffToken)[0] === 403, 'staff cannot export admin-only revenue report');
    check(http('/api/reporting/dashboard/reports/payroll?tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $staffToken)[0] === 403, 'staff cannot access admin-only payroll report');
    check(http('/api/reporting/dashboard/reports/payroll/export?format=xlsx&tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $staffToken)[0] === 403, 'staff cannot export admin-only payroll report');
    check(http('/api/reporting/dashboard/reports/debt?tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $staffToken)[0] === 403, 'staff cannot access admin-only debt report');
    check(http('/api/reporting/dashboard/reports/debt/export?format=csv&tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $staffToken)[0] === 403, 'staff cannot export admin-only debt report');
    requestOk('/api/reporting/dashboard/reports/attendance?tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $staffToken);
    requestOk('/api/reporting/dashboard/reports/class-performance?tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $staffToken);
    requestOk('/api/reporting/dashboard/reports/admissions?tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $staffToken);
    if (!empty($rptMetaStaff['scopedCampusId'])) {
        $otherCampusId = (int)$rptMetaStaff['scopedCampusId'] + 999;
        check(http('/api/reporting/dashboard/reports/attendance?tuNgay='.$rptFrom.'&denNgay='.$rptTo.'&coSoId='.$otherCampusId, 'GET', null, $staffToken)[0] === 403, 'campus-scoped staff blocked from querying another campus');
    }

    $attReport = requestOk('/api/reporting/dashboard/reports/attendance?tuNgay='.$rptFrom.'&denNgay='.$rptTo.'&lopId='.$classId, 'GET', null, $token);
    check($attReport['summary']['coMat'] >= 1 && $attReport['summary']['daDiemDanh'] >= 1, 'attendance report counts real marked attendance');
    check(isset($attReport['summary']['chuaDiemDanh'], $attReport['summary']['chuaDienRa']), 'attendance report separates unrecorded and upcoming slots');
    $revReport = requestOk('/api/reporting/dashboard/reports/revenue?tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $token);
    check(isset($revReport['summary']['thucThuRong'], $revReport['summary']['phaiThu'], $revReport['summary']['giaoDichChoDoiSoat'], $revReport['summary']['tongCongNoTonHienHanh']) && is_array($revReport['trend']), 'revenue report separates receivable, confirmed actuals, outstanding debt, pending intents, and trend');
    $perfReport = requestOk('/api/reporting/dashboard/reports/class-performance?tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $token);
    check($perfReport['summary']['soLop'] >= 1 && isset($perfReport['classes'][0]['tyLeLapDay']), 'class performance report returns real DB class KPIs');
    $payReport = requestOk('/api/reporting/dashboard/reports/payroll?tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $token);
    check(isset($payReport['summary']['tongTienCongCa'], $payReport['summary']['thucThuRong'], $payReport['summary']['chenhLechThuLuong']) && count($payReport['byRole']) === 3, 'payroll report returns teaching hours, role breakdown, and tuition comparison');
    // Regression: Closed payroll period with 0-minute / 0-VND shift must still report trangThaiKy = 'da_chot'
    $zeroSessId = Store::insert('buoi_hoc', ['lop_id'=>$classId, 'ngay'=>'2097-03-10', 'gio_bat_dau'=>'18:00', 'gio_ket_thuc'=>'20:00', 'trang_thai'=>'da_day']);
    $zeroPcId = Store::insert('phan_cong_buoi_hoc', [
        'buoi_hoc_id'=>$zeroSessId, 'user_id'=>$teacher['userId'], 'vai_tro'=>'day_chinh', 'don_gia'=>200000,
        'checkin_luc'=>'2097-03-10 21:00:00', 'checkout_luc'=>'2097-03-10 21:05:00',
        'trang_thai'=>'da_day', 'trang_thai_cong'=>'hop_le',
    ]);
    requestOk('/api/finance/payroll/close', 'POST', ['userId'=>$teacher['userId'], 'month'=>'2097-03'], $token);
    $zeroBcId = (int)Store::one('SELECT id FROM bang_cong_thang WHERE user_id=? AND nam=2097 AND thang=3', [$teacher['userId']])['id'];
    $zeroPayRpt = requestOk('/api/reporting/dashboard/reports/payroll?tuNgay=2097-03-01&denNgay=2097-03-31', 'GET', null, $token);
    $zeroPerson = $zeroPayRpt['byPerson'][0] ?? null;
    check($zeroPerson !== null && (int)$zeroPerson['tienCongCa'] === 0 && $zeroPerson['trangThaiKy'] === 'da_chot', 'payroll report detects da_chot period even when shift wage is 0 VND');
    requestOk('/api/finance/payroll/'.$zeroBcId.'/unlock', 'POST', ['lyDo'=>'Test cleanup'], $token);
    Store::execute('UPDATE phan_cong_buoi_hoc SET bang_cong_id=NULL WHERE id=?', [$zeroPcId]);
    Store::execute('DELETE FROM bang_cong_thang WHERE id=?', [$zeroBcId]);
    Store::execute('DELETE FROM phan_cong_buoi_hoc WHERE id=?', [$zeroPcId]);
    Store::execute('DELETE FROM buoi_hoc WHERE id=?', [$zeroSessId]);
    $debtReport = requestOk('/api/reporting/dashboard/reports/debt?tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $token);
    check(isset($debtReport['summary']['tongConNo'], $debtReport['summary']['tienQuaHan'], $debtReport['summary']['tienChuaDenHan']), 'debt report separates overdue and upcoming outstanding invoices');
    $admReport = requestOk('/api/reporting/dashboard/reports/admissions?tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $token);
    check(isset($admReport['summary']['tongYeuCau'], $admReport['summary']['daChot']) && is_array($admReport['bySource']), 'admissions report returns real request counts and sources');

    // Formula injection sanitization unit checks + live HTTP CSV & XLSX export checks
    check(\App\Modules\Reporting\Features\Dashboard\SpreadsheetExport::sanitizeCell('=CMD|calc') === "'=CMD|calc", 'CSV/XLSX sanitizes leading =');
    check(\App\Modules\Reporting\Features\Dashboard\SpreadsheetExport::sanitizeCell('+SUM(1,2)') === "'+SUM(1,2)", 'CSV/XLSX sanitizes leading +');
    check(\App\Modules\Reporting\Features\Dashboard\SpreadsheetExport::sanitizeCell('-2+3') === "'-2+3", 'CSV/XLSX sanitizes leading -');
    check(\App\Modules\Reporting\Features\Dashboard\SpreadsheetExport::sanitizeCell('@HYPERLINK') === "'@HYPERLINK", 'CSV/XLSX sanitizes leading @');
    [$csvStatus, , $csvRaw, $csvHeaders] = http('/api/reporting/dashboard/reports/attendance/export?format=csv&tuNgay='.$rptFrom.'&denNgay='.$rptTo.'&lopId='.$classId, 'GET', null, $token);
    check($csvStatus === 200 && str_starts_with($csvRaw, "\xEF\xBB\xBF") && str_contains(implode("\n", $csvHeaders), 'bao-cao-chuyen-can-2026-11-01-den-2026-12-31.csv'), 'CSV export returns UTF-8 BOM and structured filename');
    foreach ([
        'attendance' => 'chuyen-can',
        'revenue' => 'doanh-thu',
        'class-performance' => 'hieu-suat-lop',
        'payroll' => 'cong-day-quy-luong',
        'debt' => 'cong-no-ton',
        'admissions' => 'tuyen-sinh',
    ] as $rType => $rSlug) {
        [$xStatus, , $xRaw, $xHeaders] = http('/api/reporting/dashboard/reports/'.$rType.'/export?format=xlsx&tuNgay='.$rptFrom.'&denNgay='.$rptTo, 'GET', null, $token);
        check($xStatus === 200 && str_starts_with($xRaw, "PK\x03\x04") && str_contains(implode("\n", $xHeaders), "bao-cao-$rSlug-$rptFrom-den-$rptTo.xlsx"), 'XLSX export produces valid OpenSpout workbook for '.$rType);
    }

    // Clean temporary reminder session so administration suite counts stay intact
    Store::execute('DELETE FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=?', [$remSessionId]);
    Store::execute('DELETE FROM buoi_hoc WHERE id=?', [$remSessionId]);

    // The administration suite shares this disposable DB and asserts its own handoff totals.
    Store::execute('DELETE FROM yeu_cau_hoc WHERE id=?',[$opportunityId]);
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
    // Password tests use only the disposable database. Developer credentials stay unchanged.
    foreach (array_slice($portalAccounts,1) as [$portalUsername,$portalPassword,$role]) {
        $first=requestOk('/api/auth/login','POST',['username'=>$portalUsername,'password'=>$portalPassword]);
        $second=requestOk('/api/auth/login','POST',['username'=>$portalUsername,'password'=>$portalPassword]);
        $ownToken=$first['token'];
        check(http('/api/auth/change-password','PUT',['currentPassword'=>'wrong','newPassword'=>'Changed@789','confirmPassword'=>'Changed@789'],$ownToken)[1]['code']==='INVALID_CURRENT_PASSWORD','wrong current password identified '.$role);
        check(http('/api/auth/change-password','PUT',['currentPassword'=>$portalPassword,'newPassword'=>'Changed@789','confirmPassword'=>'Mismatch@789'],$ownToken)[1]['code']==='PASSWORD_MISMATCH','password confirmation enforced '.$role);
        check(http('/api/auth/change-password','PUT',['currentPassword'=>$portalPassword,'newPassword'=>str_repeat('á',37)],$ownToken)[0]===400,'bcrypt byte limit enforced '.$role);
        check(http('/api/auth/change-password','PUT',['currentPassword'=>$portalPassword,'newPassword'=>"Valid\0Password"],$ownToken)[0]===400,'null byte password refused '.$role);
        $newPassword='  MậtKhẩu@789  ';
        [$status,$result,,$headers]=http('/api/auth/change-password','PUT',['currentPassword'=>$portalPassword,'newPassword'=>$newPassword,'confirmPassword'=>$newPassword],$ownToken);
        check($status===200 && $result['data']['requiresLogin']===true,'password changes for '.$role);
        check(str_contains(implode("\n",$headers),'educenter_session='),'password change clears browser cookie '.$role);
        check(http('/api/auth/me','GET',null,$ownToken)[0]===401 && http('/api/auth/me','GET',null,$second['token'])[0]===401,'all prior sessions invalidated '.$role);
        check(http('/api/auth/login','POST',['username'=>$portalUsername,'password'=>$portalPassword])[0]===401,'old password refused '.$role);
        check(requestOk('/api/auth/login','POST',['username'=>$portalUsername,'password'=>$newPassword])['user']['role']===$role,'new password preserves spaces and role '.$role);
    }
    // Remember Me (7d max JWT + synchronized cookie) and Google login DB account-matching checks.
    [$remStatus,$remBody,,$remHeaders]=http('/api/auth/login','POST',['username'=>$username,'password'=>'Recovered@789','rememberMe'=>true]);
    check($remStatus===200 && $remBody['data']['rememberMe']===true && $remBody['data']['expiresIn']===604800,'rememberMe issues 7-day session');
    $remSetCookie=implode("\n",array_filter($remHeaders,static fn($h)=>str_starts_with(strtolower($h),'set-cookie:')));
    check(str_contains(strtolower($remSetCookie),'max-age=') || str_contains(strtolower($remSetCookie),'expires='),'rememberMe persists cookie across browser restarts');
    preg_match('/educenter_session=([^;]+)/',$remSetCookie,$remCookieMatch);
    $remCookie='Cookie: educenter_session='.$remCookieMatch[1];
    check(http('/login','GET',null,null,null,[$remCookie])[0]===303,'already-authenticated cookie on /login redirects to /app');
    check(http('/api/auth/logout','POST',[],null,null,[$remCookie,'X-Requested-With: XMLHttpRequest'])[0]===200,'cookie-only logout revokes remembered session');
    check(http('/api/auth/me','GET',null,null,null,[$remCookie])[0]===401,'revoked 7-day cookie immediately rejected');

    $rsa=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($rsa,$gPriv);
    $gPub=openssl_pkey_get_details($rsa)['rsa'];
    $b64u=static fn(string $bin)=>rtrim(strtr(base64_encode($bin),'+/','-_'),'=');
    $gJwks=['keys'=>[['kty'=>'RSA','alg'=>'RS256','use'=>'sig','kid'=>'int-kid','n'=>$b64u($gPub['n']),'e'=>$b64u($gPub['e'])]]];
    $gLoader=new ReflectionProperty(\App\Modules\Administration\Features\Auth\GoogleIdentity::class,'jwksLoader');
    $gLoader->setValue(null,static fn(bool $r)=>$gJwks);
    $prevGClient=getenv('GOOGLE_CLIENT_ID');
    putenv('GOOGLE_CLIENT_ID=int-client.apps.googleusercontent.com');
    try {
        $makeGToken=static fn(string $email,bool $verified=true)=>\Firebase\JWT\JWT::encode([
            'iss'=>'https://accounts.google.com','aud'=>'int-client.apps.googleusercontent.com',
            'sub'=>'google-sub-'.$suffix,'email'=>$email,'email_verified'=>$verified,
            'iat'=>time(),'exp'=>time()+600,
        ],$gPriv,'RS256','int-kid');
        $gAdmin=AuthService::googleLogin(['credential'=>$makeGToken($login['user']['email']),'rememberMe'=>true]);
        check($gAdmin['user']['role']==='ADMIN' && $gAdmin['rememberMe']===true && $gAdmin['expiresIn']===604800,'Google login matches active existing account and DB role');
        $usersBefore=(int)Store::one('SELECT COUNT(*) AS n FROM users')['n'];
        $accountsBefore=(int)Store::one('SELECT COUNT(*) AS n FROM account')['n'];
        try {
            AuthService::googleLogin(['credential'=>$makeGToken("unlinked.$suffix@gmail.com"),'rememberMe'=>false]);
            check(false,'unlinked Google email must be rejected');
        } catch (\App\Shared\ApiException $error) {
            check($error->status===403 && $error->errorCode==='GOOGLE_ACCOUNT_NOT_LINKED','unlinked Google email returns 403 guidance');
        }
        check((int)Store::one('SELECT COUNT(*) AS n FROM users')['n']===$usersBefore && (int)Store::one('SELECT COUNT(*) AS n FROM account')['n']===$accountsBefore,'Google login never auto-creates user or account');
        Store::execute("UPDATE account SET status='locked' WHERE account_id=?",[$staffAccount]);
        try {
            AuthService::googleLogin(['credential'=>$makeGToken("staff.$suffix@example.test"),'rememberMe'=>false]);
            check(false,'locked account must be rejected for Google login');
        } catch (\App\Shared\ApiException $error) {
            check($error->status===403 && $error->errorCode==='ACCOUNT_INACTIVE','locked account rejected on Google login');
        }
    } finally {
        $gLoader->setValue(null,null);
        putenv($prevGClient===false?'GOOGLE_CLIENT_ID':'GOOGLE_CLIENT_ID='.$prevGClient);
    }

    // P1.1 Nested Store::transaction() SAVEPOINT atomicity
    $spCode='SP_'.$suffix;
    $outerCode='OUT_'.$suffix;
    Store::transaction(function() use ($spCode,$outerCode) {
        Store::execute('INSERT INTO khu_vuc(ten,is_active) VALUES (?,1)',[$outerCode]);
        try {
            Store::transaction(function() use ($spCode) {
                Store::execute('INSERT INTO khu_vuc(ten,is_active) VALUES (?,1)',[$spCode]);
                throw new \RuntimeException('inner-savepoint-abort');
            });
        } catch (\RuntimeException) {}
    });
    check(Store::one('SELECT id FROM khu_vuc WHERE ten=?',[$outerCode])!==null,'nested transaction keeps outer write when inner savepoint caught');
    check(Store::one('SELECT id FROM khu_vuc WHERE ten=?',[$spCode])===null,'nested transaction rolls back inner savepoint on exception');
    Store::execute('DELETE FROM khu_vuc WHERE ten=?',[$outerCode]);

    // P1.2 Token revocation survives audit_log purge (decoupled via account.revoked_tokens_json & session_version)
    $p12Login=requestOk('/api/auth/login','POST',['username'=>$username,'password'=>'Recovered@789']);
    $p12Token=$p12Login['token'];
    requestOk('/api/auth/logout','POST',[],$p12Token);
    Store::execute("DELETE FROM audit_log WHERE bang='account' AND ban_ghi_id=?",[$accountId]);
    check(http('/api/auth/me','GET',null,$p12Token)[0]===401,'revoked token remains invalid even if audit_log is purged');

    // P2.4 Lightweight admin notification summary endpoint (Admin only)
    $p24Admin=requestOk('/api/auth/login','POST',['username'=>$username,'password'=>'Recovered@789']);
    $p24Summary=requestOk('/api/communication/notifications/admin-summary','GET',null,$p24Admin['token']);
    check(isset($p24Summary['total']) && is_array($p24Summary['items']),'admin-summary returns bounded summary payload for Admin');
    $p24Parent=requestOk('/api/auth/login','POST',['username'=>'ph_'.$suffix,'password'=>'  MậtKhẩu@789  ']);
    check(http('/api/communication/notifications/admin-summary','GET',null,$p24Parent['token'])[0]===403,'admin-summary rejects non-Admin roles');

    // P1.3 Non-enumerating sendOtp & login brute-force rate limiting
    [$neStatus,$neBody]=http('/api/auth/forgot-password/send-otp','POST',['identifier'=>"does_not_exist_$suffix@example.test"]);
    check($neStatus===200 && ($neBody['success']??false)===true,'sendOtp returns uniform 200 for nonexistent account');
    $rlUser="bruteforce_$suffix";
    try {
        for($i=0;$i<10;$i++) {
            [$bfSt]=http('/api/auth/login','POST',['username'=>$rlUser,'password'=>'WrongPass@1']);
            check($bfSt===401,'failed login before threshold returns 401');
        }
        [$bfBlocked,$bfBody]=http('/api/auth/login','POST',['username'=>$rlUser,'password'=>'WrongPass@1']);
        check($bfBlocked===429 && ($bfBody['code']??'')==='RATE_LIMITED','login rate limit returns 429 RATE_LIMITED after 10 failures');
    } finally {
        \App\Shared\RateLimiter::clear('login:127.0.0.1:'.strtolower($rlUser));
        \App\Shared\RateLimiter::clear('login_ip:127.0.0.1');
        \App\Shared\RateLimiter::clear('otp_ip:127.0.0.1');
    }

    echo "PASS: $assertions HTTP/MySQL integration assertions on isolated test database.\n";
} catch(\Throwable $error) { fwrite(STDERR,$error->getMessage()."\n"); $failed=true; }
finally { if(is_resource($server)) { proc_terminate($server);proc_close($server); } }
exit(isset($failed)?1:0);
