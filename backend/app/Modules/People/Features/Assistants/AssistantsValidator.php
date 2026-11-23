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

    public static function url(mixed $value): ?string
    {
        $url = self::text($value, 255);
        if ($url !== null && str_starts_with($url, '/') && !str_starts_with($url, '//') && !preg_match('/[\\\\\x00-\x20]/', $url)) {
            return $url;
        }
        if ($url !== null && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['https', 'http'], true))) {
            throw new ApiException(400, 'Liên kết cần bắt đầu bằng http hoặc https.');
        }
        return $url;
    }

    /**
     * Session log for Teaching Assistants:
     * records lesson topic/notes, attendance, and grades previous session's homework;
     * strictly forbids attaching/modifying lecture materials, PDFs, or creating new homework.
     */
    public static function log(array $body): array
    {
        if (array_key_exists('fileBaiGiangUrl', $body) && $body['fileBaiGiangUrl'] !== null && $body['fileBaiGiangUrl'] !== '') {
            throw new ApiException(403, 'Trợ giảng không có quyền gắn hoặc chỉnh sửa tài liệu bài giảng.', 'FORBIDDEN');
        }
        unset($body['fileBaiGiangUrl']);
        if (array_key_exists('baiTap', $body) && $body['baiTap'] !== null) {
            if (is_array($body['baiTap']) && (array_key_exists('fileDeBaiUrl', $body['baiTap']) || array_key_exists('file_de_bai_url', $body['baiTap']))) {
                throw new ApiException(403, 'Trợ giảng không có quyền gắn hoặc chỉnh sửa tệp đề bài tập PDF.', 'FORBIDDEN');
            }
            throw new ApiException(400, 'Sổ buổi học không dùng để giao bài tập về nhà mới.');
        }
        unset($body['baiTap']);

        self::fields($body, ['revision', 'chuDe', 'noiDung', 'baiTapChamId', 'hocViens']);
        $data = Input::validate($body, [
            'revision' => ['string', true],
            'baiTapChamId' => ['id', false],
            'hocViens' => ['array', true],
        ]);
        $data['chuDe'] = self::text($body['chuDe'] ?? null, 255, true);
        $data['noiDung'] = self::text($body['noiDung'] ?? null, 15000);

        if (count($data['hocViens']) > 300) {
            throw new ApiException(400, 'Danh sách học viên quá dài.');
        }
        $seen = [];
        foreach ($data['hocViens'] as &$student) {
            if (!is_array($student)) throw new ApiException(400, 'Học viên không hợp lệ.');
            self::fields($student, ['hocVienId', 'trangThaiDiemDanh', 'diemBaiTapGiay', 'nhanXetBaiTap', 'mucTiepThu', 'thaiDo', 'nhanXetBuoiHoc', 'huongCaiThien']);
            $student = Input::validate($student, [
                'hocVienId' => ['id', true],
                'trangThaiDiemDanh' => [['co_mat', 'di_tre', 'vang_co_phep', 'vang_khong_phep'], true],
                'diemBaiTapGiay' => ['number', false],
                'mucTiepThu' => ['id', false],
                'thaiDo' => [['tot', 'kha', 'trung_binh', 'can_cai_thien'], false],
                'nhanXetBaiTap' => ['string', false],
                'nhanXetBuoiHoc' => ['string', false],
                'huongCaiThien' => ['string', false],
            ]);
            if (isset($seen[$student['hocVienId']]) || ($student['diemBaiTapGiay'] ?? 0) > 10 || ($student['mucTiepThu'] ?? 1) > 5) {
                throw new ApiException(400, 'Học viên trùng hoặc điểm không hợp lệ.');
            }
            $seen[$student['hocVienId']] = true;
            foreach (['nhanXetBaiTap' => 255, 'nhanXetBuoiHoc' => 10000, 'huongCaiThien' => 10000] as $key => $max) {
                $student[$key] = self::text($student[$key] ?? null, $max);
            }
        }
        return $data;
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
