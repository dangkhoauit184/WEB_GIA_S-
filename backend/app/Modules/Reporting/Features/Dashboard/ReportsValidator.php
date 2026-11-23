<?php
namespace App\Modules\Reporting\Features\Dashboard;

use App\Shared\{ApiException, Input};

final class ReportsValidator
{
    public const TYPES = ['attendance', 'revenue', 'class-performance'];

    public static function type(string $raw): string
    {
        if (!in_array($raw, self::TYPES, true)) {
            throw new ApiException(404, 'Loại báo cáo không hợp lệ.', 'REPORT_TYPE_INVALID');
        }
        return $raw;
    }

    public static function filters(array $query, string $type, bool $export = false): array
    {
        $allowed = ['tuNgay', 'denNgay', 'coSoId', 'khoaHocId', 'lopId', 'trangThai'];
        if ($export) $allowed[] = 'format';
        if (array_diff(array_keys($query), $allowed)) {
            Input::fail('fields');
        }

        $tz = new \DateTimeZone('Asia/Ho_Chi_Minh');
        $now = new \DateTimeImmutable('now', $tz);
        $defaultFrom = $now->format('Y-m-01');
        $defaultTo = $now->format('Y-m-t');

        $rules = [
            'tuNgay' => ['date', false, $defaultFrom],
            'denNgay' => ['date', false, $defaultTo],
            'coSoId' => ['id', false],
            'khoaHocId' => ['id', false],
            'lopId' => ['id', false],
            'trangThai' => ['string', false],
        ];
        if ($export) {
            $rules['format'] = [['csv', 'xlsx'], false, 'xlsx'];
        }

        $clean = [];
        foreach ($query as $k => $v) {
            if ($v === '' || $v === null) continue;
            $clean[$k] = $v;
        }
        $data = Input::validate($clean, $rules);

        if ($data['tuNgay'] > $data['denNgay']) {
            throw new ApiException(400, 'Ngày bắt đầu không được sau ngày kết thúc.', 'INVALID_DATE_RANGE');
        }
        $diffDays = (int)(new \DateTimeImmutable($data['tuNgay']))->diff(new \DateTimeImmutable($data['denNgay']))->days;
        if ($diffDays > 366) {
            throw new ApiException(400, 'Khoảng ngày báo cáo tối đa 366 ngày.', 'DATE_RANGE_TOO_LARGE');
        }

        $allowedStatuses = match ($type) {
            'attendance' => ['co_mat', 'di_tre', 'vang_co_phep', 'vang_khong_phep', 'chua_diem_danh', 'chua_dien_ra'],
            'revenue' => ['chua_thanh_toan', 'mot_phan', 'da_thanh_toan'],
            'class-performance' => ['sap_bat_dau', 'dang_hoc', 'tam_dung', 'hoan_thanh', 'huy'],
            default => [],
        };
        if (!empty($data['trangThai']) && !in_array($data['trangThai'], $allowedStatuses, true)) {
            throw new ApiException(400, 'Giá trị lọc trạng thái không hợp lệ.', 'INVALID_STATUS_FILTER');
        }

        return $data;
    }
}
