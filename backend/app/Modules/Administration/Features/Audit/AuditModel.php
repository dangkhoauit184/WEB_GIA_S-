<?php
namespace App\Modules\Administration\Features\Audit;

final class AuditModel
{
    public static function sanitize(mixed $value): mixed
    {
        if(!is_array($value)) return $value;
        foreach($value as $key=>&$item) {
            if(is_string($key) && preg_match('/password|token|secret|authorization/i',$key)) {
                $item='[đã ẩn]';
            } else {
                $item=self::sanitize($item);
            }
        }
        return $value;
    }

    public static function map(array $row): array
    {
        foreach(['duLieuCu','duLieuMoi'] as $field) {
            $row[$field]=self::sanitize($row[$field]===null?null:json_decode($row[$field],true,512,JSON_THROW_ON_ERROR));
        }
        return $row;
    }
}
