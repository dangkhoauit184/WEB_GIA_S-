<?php
namespace App\Modules\People\Features\Assistants;

use App\Shared\{ApiException, Input};

final class AssistantsValidator
{
    public static function fields(array $body, array $allowed): void
    {
        if (array_diff(array_keys($body), $allowed)) {
            throw new ApiException(400, 'Có trường dữ liệu không được phép.');
        }
    }

    public static function text(mixed $value, int $max, bool $required = false): ?string
    {
        if ($value === null && !$required) return null;
        if (!is_string($value) || mb_strlen(trim($value)) > $max || ($required && trim($value) === '')) {
            throw new ApiException(400, 'Nội dung trống hoặc vượt độ dài cho phép.');
        }
        return trim($value) ?: null;
    }

    public static function dateTime(mixed $value, string $label = 'Thời gian'): ?string
    {
        if ($value === null || $value === '') return null;
        if (!is_string($value)) throw new ApiException(400, $label.' không hợp lệ.');
        $value = str_replace('T', ' ', $value);
        if (strlen($value) === 16) $value .= ':00';
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        if (!$date || $date->format('Y-m-d H:i:s') !== $value) {
            throw new ApiException(400, $label.' không hợp lệ. Vui lòng kiểm tra ngày và giờ 24 giờ.');
        }
        return $value;
    }

    public static function attendance(array $body): array
    {
        self::fields($body, ['revision', 'hocViens']);
        $data = Input::validate($body, [
            'revision' => ['string', true],
            'hocViens' => ['array', true],
        ]);
        if (count($data['hocViens']) > 300) {
            throw new ApiException(400, 'Danh sách học viên quá dài.');
        }
        $seen = [];
        foreach ($data['hocViens'] as &$student) {
            if (!is_array($student)) throw new ApiException(400, 'Học viên không hợp lệ.');
            self::fields($student, ['hocVienId', 'trangThaiDiemDanh']);
            $student = Input::validate($student, [
                'hocVienId' => ['id', true],
                'trangThaiDiemDanh' => [['co_mat', 'di_tre', 'vang_co_phep', 'vang_khong_phep'], true],
            ]);
            if (isset($seen[$student['hocVienId']])) {
                throw new ApiException(400, 'Học viên trùng trong danh sách.');
            }
            $seen[$student['hocVienId']] = true;
        }
        return $data;
    }
}
