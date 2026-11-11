<?php
namespace App\Modules\Administration\Features\Permissions;

use App\Shared\Input;

final class PermissionsValidator
{
    public static function account(array $body,bool $create=true): array
    {
        $rules=['username'=>['string',true],'status'=>[['active','locked','pending'],true]];
        if($create) {
            $rules+=['userId'=>['id',true],'groupId'=>['id',true],'password'=>['password',true]];
            $body+=['status'=>'active'];
        }
        if(!$body || array_diff(array_keys($body),array_keys($rules))) Input::fail('fields');
        $data=Input::validate($body,$rules,$create);
        if(isset($data['username']) && mb_strlen($data['username'])>100) Input::fail('username');
        return $data;
    }

    public static function password(array $body): string
    {
        if(array_keys($body)!==['password']) Input::fail('fields');
        return Input::validate($body,['password'=>['password',true]])['password'];
    }

    public static function matrix(array $body): array
    {
        if(array_diff(array_keys($body),['version','permissions'])) Input::fail('fields');
        $data=Input::validate($body,['version'=>['string',true],'permissions'=>['array',true]]);
        if(!preg_match('/^[a-f0-9]{64}$/D',$data['version'])) Input::fail('version');
        $rules=['functionId'=>['id',true]];
        foreach(PermissionsModel::ACTIONS as $flag) $rules[$flag]=['bool',true];
        $ids=[];
        foreach($data['permissions'] as &$row) {
            if(!is_array($row) || array_diff(array_keys($row),array_keys($rules))) Input::fail('permissions');
            $row=Input::validate($row,$rules);
            if(isset($ids[$row['functionId']])) Input::fail('functionId trùng');
            $ids[$row['functionId']]=true;
            if(!$row['view'] && array_sum(array_intersect_key($row,array_flip(['add','edit','delete','download'])))>0) Input::fail('view');
        }
        return $data;
    }
    public static function description(array $body): ?string
    {
        if(array_diff(array_keys($body),['description']) || !array_key_exists('description',$body)) Input::fail('description');
        $data=Input::validate($body,['description'=>['string',false]]);
        if(isset($data['description']) && mb_strlen($data['description'])>255) Input::fail('description');
        return $data['description'];
    }
    public static function accountGroup(array $body): int
    {
        if(array_diff(array_keys($body),['groupId'])) Input::fail('fields');
        return Input::validate($body,['groupId'=>['id',true]])['groupId'];
    }
    public static function filters(array $query): array
    {
        $data=Input::validate($query,['q'=>['string',false],'groupId'=>['id',false],
            'status'=>[['active','locked','pending'],false],'page'=>['id',false,1],'perPage'=>['id',false,25]]);
        if($data['page']===null || $data['page']>1000000 || $data['perPage']===null || $data['perPage']>100) Input::fail('page/perPage');
        if(isset($data['q']) && mb_strlen($data['q'])>100) Input::fail('q');
        return $data;
    }
}
