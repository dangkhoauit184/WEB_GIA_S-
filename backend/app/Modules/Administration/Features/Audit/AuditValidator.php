<?php
namespace App\Modules\Administration\Features\Audit;

use App\Shared\Input;

final class AuditValidator
{
    public static function filters(array $query): array
    {
        $data=Input::validate($query,['q'=>['string',false],'bang'=>['string',false],'userId'=>['id',false],
            'hanhDong'=>[['them','sua','xoa','dang_nhap','chot_cong','mo_khoa_cong','duyet','khac'],false],
            'tuNgay'=>['date',false],'denNgay'=>['date',false],
            'page'=>['id',false,1],'perPage'=>['id',false,25]]);
        if(!$data['page'] || $data['page']>1000000 || !$data['perPage'] || $data['perPage']>100) Input::fail('page/perPage');
        if(isset($data['q']) && mb_strlen($data['q'])>100) Input::fail('q');
        if(isset($data['bang']) && !preg_match('/^[a-z_]{1,60}$/D',$data['bang'])) Input::fail('bang');
        if(isset($data['tuNgay'],$data['denNgay']) && $data['tuNgay']>$data['denNgay']) Input::fail('denNgay');
        return $data;
    }
}
