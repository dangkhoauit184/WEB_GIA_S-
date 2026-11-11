<?php
namespace App\Shared;

/** Serialize new operational records using the project's camelCase API contract. */
final class ApiModel
{
    public static function map(array $records): array
    {
        $result=[];
        foreach($records as $key=>$value) {
            if(is_string($key)) {
                $key=$key==='hoten'?'hoTen':preg_replace_callback('/_([a-z])/',fn($part)=>strtoupper($part[1]),$key);
            }
            $result[$key]=is_array($value)?self::map($value):$value;
        }
        return $result;
    }
}
