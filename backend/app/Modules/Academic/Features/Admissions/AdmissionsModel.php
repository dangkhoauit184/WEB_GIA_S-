<?php
namespace App\Modules\Academic\Features\Admissions;

use App\Shared\ApiModel;

final class AdmissionsModel extends \CodeIgniter\Model
{
    public static function map(array $row): array
    {
        $row = ApiModel::map($row);
        foreach (['nganSachMin', 'nganSachMax', 'hocPhiDeXuat', 'hocPhiSnapshot', 'hocPhiBuoiDeXuat'] as $key) {
            if (isset($row[$key])) $row[$key] = (float)$row[$key];
        }
        return $row;
    }
}
