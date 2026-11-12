<?php
namespace App\Modules\Academic\Features\AcademicAffairs;

final class AcademicAffairsModel extends \CodeIgniter\Model
{
    public static function map(array $row): array
    {
        return \App\Shared\ApiModel::map($row);
    }
}
