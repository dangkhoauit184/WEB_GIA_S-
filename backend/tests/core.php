<?php
require __DIR__.'/bootstrap.php';

use App\Shared\{Access,Input,ApiException,ApiController};
use App\Modules\People\Features\Students\StudentsValidator;
use App\Modules\People\Features\Parents\ParentsValidator;
use App\Modules\People\Features\Teachers\TeachersValidator;
use App\Modules\Academic\Features\Scheduling\SchedulingValidator;
use App\Modules\Academic\Features\Classes\ClassesValidator;

try {
    foreach(['Admin'=>'ADMIN','NhanVien'=>'GIAO_VU','TroGiang'=>'TRO_GIANG','PhuHuynh'=>'PHU_HUYNH'] as $group=>$role) check(Access::role($group)===$role,'role '.$group);
    check(Access::role('GiangVien','giao_vien,gia_su')==='GIAO_VIEN','teacher role');
    check(Access::role('GiangVien','gia_su')==='GIA_SU','tutor role');
    check(Access::role('unknown')===null,'unknown role denied');
    rejects(fn()=>StudentsValidator::validate(['maHv'=>'HV','hoTen'=>'A','phuHuynhUserId'=>-1]),'invalid parent id');
    rejects(fn()=>StudentsValidator::validate(['maHv'=>'HV','hoTen'=>'A','phuHuynhUserId'=>1,'ngaySinh'=>'2026-02-30']),'invalid calendar date');
    rejects(fn()=>StudentsValidator::validate(['hoTen'=>'  '],false),'blank update');
    rejects(fn()=>ParentsValidator::validate(['hoTen'=>'A','email'=>'bad']),'invalid email');
    rejects(fn()=>ParentsValidator::validate(['hoTen'=>'A','email'=>'a@example.com','username'=>'foo']),'unpaired credentials');
    rejects(fn()=>TeachersValidator::validate(['maNs'=>'NS','hoTen'=>'A','email'=>'a@example.com','monDays'=>[null]]),'invalid nested subjects');
    rejects(fn()=>TeachersValidator::match(['monHocId'=>1,'capLopId'=>9,'thu'=>3]),'incomplete availability');
    rejects(fn()=>SchedulingValidator::weekly(['lopId'=>1,'thu'=>9,'gioBatDau'=>'18:00','gioKetThuc'=>'20:00','hieuLucTu'=>'2026-11-01']),'invalid weekday');
    rejects(fn()=>SchedulingValidator::weekly(['lopId'=>1,'thu'=>3,'gioBatDau'=>'21:00','gioKetThuc'=>'20:00','hieuLucTu'=>'2026-11-01']),'inverted time');
    rejects(fn()=>SchedulingValidator::generate(['lopId'=>1,'tuNgay'=>'2026-12-01','denNgay'=>'2026-11-01']),'inverted generation range');
    rejects(fn()=>ClassesValidator::enrollment(['hocVienId'=>0]),'invalid enrollment');
    check(StudentsValidator::validate(['ghiChu'=>null],false)===['ghiChu'=>null],'explicit null update');
    check(StudentsValidator::validate([],false)===[],'missing fields not overwritten');
    check(Input::validate(['password'=>'  Abcd123  '],['password'=>['password',true]])['password']==='  Abcd123  ','password spaces preserved');
    foreach([1644=>[400,'BUSINESS_RULE_VIOLATION'],1062=>[409,'DUPLICATE_ENTRY'],1452=>[400,'DATA_CONSTRAINT_ERROR']] as $number=>[$status,$code]) {
        $response=ApiController::error(new \RuntimeException('test',$number));
        check($response->getStatusCode()===$status && json_decode($response->getBody(),true)['code']===$code,'SQL error mapping '.$number);
    }
    [$server]=startServer();
    try {
        $modules=json_decode(file_get_contents(ROOTPATH.'../shared/modules.json'),true);
        foreach($modules as $module=>$features) foreach($features as $feature) {
            $data=requestOk("/api/$module/$feature/status");
            check($data['module']===$module && $data['feature']===$feature,'feature route');
        }
        check(http('/api/not-found')[0]===404,'API 404');
        check(http('/api/auth/me')[0]===401,'missing bearer');
        check(http('/api/auth/me','GET',null,'invalid.jwt.token')[0]===401,'invalid bearer');
        check(http('/api/auth/login','POST',null,null,'{broken')[0]===400,'malformed JSON');
        [$status,,$html]=http('/');
        check($status===200 && str_contains($html,'id="login-form"'),'MVC view');
    } finally { proc_terminate($server); proc_close($server); }
    echo "PASS: $assertions PHP core assertions (no database writes).\n";
} catch(\Throwable $error) { fwrite(STDERR,$error->getMessage()."\n"); exit(1); }
