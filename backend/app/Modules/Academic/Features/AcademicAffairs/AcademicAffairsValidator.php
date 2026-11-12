<?php
namespace App\Modules\Academic\Features\AcademicAffairs;

use App\Shared\Input;

final class AcademicAffairsValidator
{
    public static function registration(array $body): int
    {
        if (array_diff(array_keys($body), ['khoaHocId'])) Input::fail('fields');
        return Input::validate($body, ['khoaHocId'=>['id', true]])['khoaHocId'];
    }
}
