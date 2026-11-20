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
    // Teacher portal operations stay scoped and never accept client clock/pay data.
    $teacherToken=requestOk('/api/auth/login','POST',['username'=>'gv_'.$suffix,'password'=>'Teacher@123'])['token'];
    $work=requestOk('/api/academic/attendance/workspace','GET',null,$teacherToken);
    check(count($work['classes'])===1 && count($work['sessions'])===count($sessions),'teacher workspace reads assigned classes and sessions');
    check(!preg_match('/donGia|don_gia|mucTra|muc_tra|tongLuong|tong_luong/',json_encode($work)),'teacher workspace excludes salary');
    check(http('/api/academic/attendance/workspace','GET',null,$parentToken)[0]===403,'parent cannot use teacher APIs');
    check(http('/api/academic/attendance/workspace','GET',null,$tutorToken)[0]===403,'tutor portal remains separate');
    $teacherSession=(int)$sessions[1]['id'];
    $teacherDetail=requestOk('/api/academic/attendance/sessions/'.$teacherSession,'GET',null,$teacherToken);
    check(http('/api/academic/attendance/sessions/'.$teacherSession,'GET',null,$tutorToken)[0]===403,'another role cannot inspect teacher log');
    $teacherLog=['revision'=>$teacherDetail['revision'],'chuDe'=>'Bài giảng kiểm thử','noiDung'=>'Nội dung đã dạy',
        'baiTap'=>['tieuDe'=>'Bài tập giấy','fileDeBaiUrl'=>'https://example.test/de-bai.pdf'],
        'hocViens'=>[['hocVienId'=>$student['id'],'trangThaiDiemDanh'=>'co_mat','diemBaiTapGiay'=>8.5]]];
    check(http('/api/academic/attendance/sessions/'.$teacherSession.'/log','PUT',$teacherLog,$teacherToken)[0]===409,'checkin required for log');
    check(http('/api/academic/attendance/sessions/'.$teacherSession.'/checkin','POST',['checkinLuc'=>'2000-01-01'],$teacherToken)[0]===400,'server rejects client clock');
    $clock=requestOk('/api/academic/attendance/sessions/'.$teacherSession.'/checkin','POST',[],$teacherToken);
    check($clock['checkinLuc']!==null && $clock['trangThai']==='dang_day','teacher checkin persists');
    $calendarIds=array_map('intval',array_column(requestOk('/api/academic/scheduling/sessions','GET',null,$teacherToken),'id'));
    $workspaceIds=array_map('intval',array_column(requestOk('/api/academic/attendance/workspace','GET',null,$teacherToken)['sessions'],'id'));
    check(in_array($teacherSession,$calendarIds,true) && in_array($teacherSession,$workspaceIds,true),'checked-in session stays in both calendar sources');
    check(requestOk('/api/academic/attendance/sessions/'.$teacherSession.'/checkin','POST',[],$teacherToken)['checkinLuc']===$clock['checkinLuc'],'checkin replay keeps first timestamp');
    $teacherLog['revision']=$clock['revision'];
    $saved=requestOk('/api/academic/attendance/sessions/'.$teacherSession.'/log','PUT',$teacherLog,$teacherToken);
    check((float)$saved['hocViens'][0]['diemBaiTapGiay']===8.5 && $saved['baiTap']['tieuDe']==='Bài tập giấy','teacher log and grade persist');
    check(http('/api/academic/attendance/sessions/'.$teacherSession.'/log','PUT',$teacherLog,$teacherToken)[0]===409,'stale log cannot overwrite newer data');
    $teacherLog['revision']=$saved['revision'];
    $teacherLog['hocViens'][0]['hocVienId']=$student2['id'];
    check(http('/api/academic/attendance/sessions/'.$teacherSession.'/log','PUT',$teacherLog,$teacherToken)[0]===409,'teacher cannot grade a student outside roster');
    Store::execute('UPDATE phan_cong_buoi_hoc SET checkin_luc=DATE_SUB(NOW(),INTERVAL 5 MINUTE) WHERE id=?',[$clock['phanCongId']]);
    $clock=requestOk('/api/academic/attendance/sessions/'.$teacherSession.'/checkout','POST',[],$teacherToken);
    check($clock['checkoutLuc']!==null && $clock['trangThai']==='da_day','checkout persists and ends session');
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
    echo "PASS: $assertions HTTP/MySQL integration assertions on isolated test database.\n";
} catch(\Throwable $error) { fwrite(STDERR,$error->getMessage()."\n"); $failed=true; }
finally { if(is_resource($server)) { proc_terminate($server);proc_close($server); } }
exit(isset($failed)?1:0);
