<?php
require __DIR__.'/bootstrap.php';

use App\Shared\{Access,Input,ApiException,ApiController};
use App\Modules\People\Features\Students\StudentsValidator;
use App\Modules\People\Features\Parents\ParentsValidator;
use App\Modules\People\Features\Teachers\TeachersValidator;
use App\Modules\Academic\Features\Scheduling\SchedulingValidator;
use App\Modules\Academic\Features\Classes\ClassesValidator;
use App\Modules\Academic\Features\Attendance\AttendanceValidator;
use App\Modules\Administration\Features\Auth\AuthValidator;

try {
    foreach(['Admin'=>'ADMIN','NhanVien'=>'GIAO_VU','TroGiang'=>'TRO_GIANG','PhuHuynh'=>'PHU_HUYNH'] as $group=>$role) check(Access::role($group)===$role,'role '.$group);
    check(Access::role('GiangVien','giao_vien,gia_su')==='GIAO_VIEN','teacher role');
    check(Access::role('GiangVien','gia_su')==='GIA_SU','tutor role');
    check(Access::role('unknown')===null,'unknown role denied');
    check(\App\Modules\Finance\Features\Tuition\TuitionModel::vietQrPayload('970416','257678859',1000,'Chuyen tien') === '00020101021238530010A0000007270123000697041601092576788590208QRIBFTTA5303704540410005802VN62150811Chuyen tien6304BBB8','NAPAS QR matches independent published vector including CRC');
    rejects(fn()=>\App\Modules\Finance\Features\Tuition\TuitionValidator::bank(['bankBin'=>'970436','bankName'=>'Demo','accountNo'=>'DEMOEDU01','accountName'=>'Demo','active'=>true,'isDemo'=>false]),'demo account cannot be marked as a real payment recipient');
    $parsed=\App\Modules\Finance\Features\Tuition\TuitionModel::readVietQr('00020101021238530010A0000007270123000697041601092576788590208QRIBFTTA5303704540410005802VN62150811Chuyen tien6304BBB8');
    check($parsed===['bankBin'=>'970416','accountNo'=>'257678859','amount'=>'1000','memo'=>'Chuyen tien'],'uploaded QR parser reads independent NAPAS vector');
    rejects(fn()=>\App\Modules\Finance\Features\Tuition\TuitionModel::readVietQr('https://malicious.example/'),'URL QR is not a bank transfer QR');
    rejects(fn()=>\App\Modules\Finance\Features\Tuition\TuitionModel::readVietQr(substr(\App\Modules\Finance\Features\Tuition\TuitionModel::vietQrPayload('970436','1234567890',1000,'TEST'),0,-4).'0000'),'QR checksum required');
    rejects(fn()=>StudentsValidator::validate(['maHv'=>'HV','hoTen'=>'A','phuHuynhUserId'=>-1]),'invalid parent id');
    rejects(fn()=>StudentsValidator::validate(['maHv'=>'HV','hoTen'=>'A','phuHuynhUserId'=>1,'ngaySinh'=>'2026-02-30']),'invalid calendar date');
    rejects(fn()=>StudentsValidator::validate(['hoTen'=>'  '],false),'blank update');
    rejects(fn()=>ParentsValidator::validate(['hoTen'=>'A','email'=>'bad']),'invalid email');
    rejects(fn()=>ParentsValidator::validate(['hoTen'=>'A','email'=>'a@example.com','username'=>'foo']),'unpaired credentials');
    rejects(fn()=>TeachersValidator::validate(['maNs'=>'NS','hoTen'=>'A','email'=>'a@example.com','monDays'=>[null]]),'invalid nested subjects');
    rejects(fn()=>TeachersValidator::match(['monHocId'=>1,'capLopId'=>9,'thu'=>3]),'incomplete availability');
    try {
        TeachersValidator::validate(['lichRanhs'=>[['thu'=>6,'gioBatDau'=>'11:00','gioKetThuc'=>'10:00']]],false);
        check(false,'invalid availability range must fail');
    } catch(ApiException $error) {
        check($error->status===400 && str_contains($error->getMessage(),'Khung giờ 1') && str_contains($error->getMessage(),'kết thúc'),
            'availability range error identifies the row in Vietnamese');
    }
    rejects(fn()=>SchedulingValidator::weekly(['lopId'=>1,'thu'=>9,'gioBatDau'=>'18:00','gioKetThuc'=>'20:00','hieuLucTu'=>'2026-11-01']),'invalid weekday');
    rejects(fn()=>SchedulingValidator::weekly(['lopId'=>1,'thu'=>3,'gioBatDau'=>'21:00','gioKetThuc'=>'20:00','hieuLucTu'=>'2026-11-01']),'inverted time');
    rejects(fn()=>SchedulingValidator::generate(['lopId'=>1,'tuNgay'=>'2026-12-01','denNgay'=>'2026-11-01']),'inverted generation range');
    rejects(fn()=>ClassesValidator::enrollment(['hocVienId'=>0]),'invalid enrollment');
    check(StudentsValidator::validate(['ghiChu'=>null],false)===['ghiChu'=>null],'explicit null update');
    check(StudentsValidator::validate([],false)===[],'missing fields not overwritten');
    check(Input::validate(['password'=>'  Abcd123  '],['password'=>['password',true]])['password']==='  Abcd123  ','password spaces preserved');
    check(AuthValidator::login(['username'=>'demo','password'=>'      '])['password']==='      ','login preserves all password bytes');
    check(AuthValidator::password(['currentPassword'=>'      ','newPassword'=>'  Abcd123  ','confirmPassword'=>'  Abcd123  '])===['currentPassword'=>'      ','newPassword'=>'  Abcd123  '],'change-password preserves password bytes');
    rejects(fn()=>AuthValidator::password(['currentPassword'=>'old','newPassword'=>'abcdef','confirmPassword'=>'abcdef ']),'confirmation compares exact bytes');
    rejects(fn()=>AuthValidator::password(['currentPassword'=>'old','newPassword'=>str_repeat('á',37)]),'password byte length validated');
    rejects(fn()=>AuthValidator::password(['currentPassword'=>'old','newPassword'=>"abc\0def"]),'password null byte validated');
    check(AttendanceValidator::url('/uploads/de-bai.pdf')==='/uploads/de-bai.pdf','legacy local coursework links preserved');
    rejects(fn()=>AttendanceValidator::url('//example.test/file.pdf'),'protocol-relative coursework links refused');
    rejects(fn()=>AttendanceValidator::url('javascript:alert(1)'),'script coursework links refused');
    rejects(fn()=>AttendanceValidator::url('/\\example.test/file.pdf'),'backslash coursework links refused');
    check(AttendanceValidator::dateTime('2028-02-29T18:10')==='2028-02-29 18:10:00','local datetime normalized');
    $noon=Input::validate(['gioBatDau'=>'11:00','gioKetThuc'=>'12:00'],['gioBatDau'=>['time',true],'gioKetThuc'=>['time',true]]);
    Input::pair($noon,'gioBatDau','gioKetThuc');
    check($noon['gioKetThuc']==='12:00:00','24 hour noon remains noon');
    try {
        Input::pair(['gioBatDau'=>'11:00:00','gioKetThuc'=>'00:00:00'],'gioBatDau','gioKetThuc');
        check(false,'midnight before start must fail');
    } catch(ApiException $error) {
        check(str_contains($error->getMessage(),'Giờ kết thúc phải sau giờ bắt đầu') && !str_contains($error->getMessage(),'gioKetThuc'),'time range error uses the visible field name');
    }
    try {
        Input::validate(['gioKetThuc'=>'25:00'],['gioKetThuc'=>['time',true]]);
        check(false,'invalid hour must fail');
    } catch(ApiException $error) {
        check(str_starts_with($error->getMessage(),'Giờ kết thúc'),'invalid hour error uses Vietnamese');
    }
    rejects(fn()=>AttendanceValidator::dateTime('2026-02-30T18:10'),'impossible correction date refused');
    rejects(fn()=>AttendanceValidator::fields(['donGia'=>1],[]),'unapproved teacher fields refused');
    $logInput=['revision'=>'version','chuDe'=>'Bài học','hocViens'=>[['hocVienId'=>1,'trangThaiDiemDanh'=>'co_mat','diemBaiTapGiay'=>11]]];
    rejects(fn()=>AttendanceValidator::log($logInput),'teacher score above ten refused');
    $logInput['hocViens'][0]['diemBaiTapGiay']=8;
    $logInput['hocViens'][]=$logInput['hocViens'][0];
    rejects(fn()=>AttendanceValidator::log($logInput),'duplicate student in teacher log refused');
    foreach([1644=>[400,'BUSINESS_RULE_VIOLATION'],1062=>[409,'DUPLICATE_ENTRY'],1452=>[400,'DATA_CONSTRAINT_ERROR']] as $number=>[$status,$code]) {
        $response=ApiController::error(new \RuntimeException('test',$number));
        check($response->getStatusCode()===$status && json_decode($response->getBody(),true)['code']===$code,'SQL error mapping '.$number);
    }
    // Google Identity Services RS256 ID token & validator checks (pure local crypto, no network).
    rejects(fn()=>AuthValidator::googleLogin(['credential'=>'abc','email'=>'spoof@example.com']),'google login rejects client email field');
    rejects(fn()=>AuthValidator::googleLogin(['credential'=>'abc','role'=>'ADMIN']),'google login rejects client role field');
    check(AuthValidator::googleLogin(['credential'=>'a.b.c','rememberMe'=>true])===['credential'=>'a.b.c','rememberMe'=>true],'google login accepts credential + rememberMe');
    check(AuthValidator::login(['username'=>'demo','password'=>'secret','rememberMe'=>true])['rememberMe']===true,'password login accepts rememberMe');
    $rejectsStatus=static function(callable $work,int $status,string $label): void {
        try { $work(); } catch(\App\Shared\ApiException $error) {
            check($error->status===$status,$label); return;
        }
        check(false,$label);
    };
    $prevGoogleClient=getenv('GOOGLE_CLIENT_ID');
    putenv('GOOGLE_CLIENT_ID=');
    check(\App\Modules\Administration\Features\Auth\GoogleIdentity::config()===['enabled'=>false,'clientId'=>null],'google disabled when GOOGLE_CLIENT_ID empty');
    $rejectsStatus(fn()=>\App\Modules\Administration\Features\Auth\GoogleIdentity::verify('a.b.c'),503,'google verify fails cleanly with 503 when unconfigured');
    $rsa=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($rsa,$privatePem);
    $rsaDetails=openssl_pkey_get_details($rsa)['rsa'];
    $b64u=static fn(string $bin)=>rtrim(strtr(base64_encode($bin),'+/','-_'),'=');
    $testJwks=['keys'=>[['kty'=>'RSA','alg'=>'RS256','use'=>'sig','kid'=>'test-kid-1','n'=>$b64u($rsaDetails['n']),'e'=>$b64u($rsaDetails['e'])]]];
    $loaderProp=new ReflectionProperty(\App\Modules\Administration\Features\Auth\GoogleIdentity::class,'jwksLoader');
    $loaderProp->setValue(null,static fn(bool $refresh)=>$testJwks);
    putenv('GOOGLE_CLIENT_ID=test-google-client-id.apps.googleusercontent.com');
    try {
        $signGoogle=static fn(array $claims)=>\Firebase\JWT\JWT::encode($claims,$privatePem,'RS256','test-kid-1');
        $validToken=$signGoogle(['iss'=>'https://accounts.google.com','aud'=>'test-google-client-id.apps.googleusercontent.com','sub'=>'g-sub-1','email'=>'User.Test@Gmail.com','email_verified'=>true,'name'=>'Test User','iat'=>time(),'exp'=>time()+600]);
        $verified=\App\Modules\Administration\Features\Auth\GoogleIdentity::verify($validToken);
        check($verified['email']==='user.test@gmail.com' && $verified['sub']==='g-sub-1' && $verified['emailVerified']===true,'valid RS256 Google token verified');
        $wrongAud=$signGoogle(['iss'=>'https://accounts.google.com','aud'=>'other-client.apps.googleusercontent.com','sub'=>'g-sub-1','email'=>'a@gmail.com','email_verified'=>true,'iat'=>time(),'exp'=>time()+600]);
        $rejectsStatus(fn()=>\App\Modules\Administration\Features\Auth\GoogleIdentity::verify($wrongAud),401,'wrong Google audience rejected');
        $wrongIss=$signGoogle(['iss'=>'https://evil.example','aud'=>'test-google-client-id.apps.googleusercontent.com','sub'=>'g-sub-1','email'=>'a@gmail.com','email_verified'=>true,'iat'=>time(),'exp'=>time()+600]);
        $rejectsStatus(fn()=>\App\Modules\Administration\Features\Auth\GoogleIdentity::verify($wrongIss),401,'wrong Google issuer rejected');
        $expired=$signGoogle(['iss'=>'https://accounts.google.com','aud'=>'test-google-client-id.apps.googleusercontent.com','sub'=>'g-sub-1','email'=>'a@gmail.com','email_verified'=>true,'iat'=>time()-900,'exp'=>time()-120]);
        $rejectsStatus(fn()=>\App\Modules\Administration\Features\Auth\GoogleIdentity::verify($expired),401,'expired Google token rejected');
        $unverified=$signGoogle(['iss'=>'https://accounts.google.com','aud'=>'test-google-client-id.apps.googleusercontent.com','sub'=>'g-sub-1','email'=>'a@gmail.com','email_verified'=>false,'iat'=>time(),'exp'=>time()+600]);
        $rejectsStatus(fn()=>\App\Modules\Administration\Features\Auth\GoogleIdentity::verify($unverified),403,'unverified Google email rejected');
        $hs256Fake=\Firebase\JWT\JWT::encode(['iss'=>'https://accounts.google.com','aud'=>'test-google-client-id.apps.googleusercontent.com','sub'=>'g-sub-1','email'=>'a@gmail.com','email_verified'=>true,'iat'=>time(),'exp'=>time()+600],'secret-key-for-hs256-attack-123456','HS256');
        $rejectsStatus(fn()=>\App\Modules\Administration\Features\Auth\GoogleIdentity::verify($hs256Fake),401,'HS256 algorithm confusion rejected');
    } finally {
        $loaderProp->setValue(null,null);
        putenv($prevGoogleClient===false?'GOOGLE_CLIENT_ID':'GOOGLE_CLIENT_ID='.$prevGoogleClient);
    }
    [$server]=startServer();
    try {
        $modules=json_decode(file_get_contents(ROOTPATH.'../shared/modules.json'),true);
        foreach($modules as $module=>$features) foreach($features as $feature) {
            $data=requestOk("/api/$module/$feature/status");
            check($data['module']===$module && $data['feature']===$feature,'feature route');
        }
        $googleCfg=requestOk('/api/auth/google/config');
        check(is_bool($googleCfg['enabled']) && array_key_exists('clientId',$googleCfg),'google config endpoint');
        check(http('/api/not-found')[0]===404,'API 404');
        check(http('/api/auth/me')[0]===401,'missing bearer');
        check(http('/api/auth/me','GET',null,'invalid.jwt.token')[0]===401,'invalid bearer');
        check(http('/api/auth/login','POST',null,null,'{broken')[0]===400,'malformed JSON');
        [$status,,$html]=http('/');
        check($status===200 && str_contains($html,'EduCenter'),'public MVC page');
        [$status,,$html]=http('/login');
        check($status===200 && str_contains($html,'id="loginForm"') && str_contains($html,'id="googleBtnContainer"') && str_contains($html,'id="rememberMe"'),'shared login page includes Google and rememberMe');
    } finally { proc_terminate($server); proc_close($server); }
    echo "PASS: $assertions PHP core assertions (no database writes).\n";
} catch(\Throwable $error) { fwrite(STDERR,$error->getMessage()."\n"); exit(1); }
