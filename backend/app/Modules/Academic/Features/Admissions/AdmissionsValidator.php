<?php
namespace App\Modules\Academic\Features\Admissions;

use App\Shared\Input;

final class AdmissionsValidator
{
    public static function fields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) Input::fail('fields');
    }

    public static function listing(array $input): array
    {
        self::fields($input, ['q', 'trangThai', 'page', 'perPage']);
        $data = Input::validate($input, ['q'=>['string', false], 'trangThai'=>['string', false],
            'page'=>['id', false, 1], 'perPage'=>['id', false, 20]]);
        if ($data['page'] === null || $data['perPage'] === null || $data['perPage'] > 100 || $data['page'] > 1000000) Input::fail('page');
        if (mb_strlen($data['q'] ?? '') > 150) Input::fail('q');
        return $data;
    }

    public static function request(array $input): array
    {
        $rules = ['hocVienId'=>['id', false], 'hoTenLienHe'=>['string', false], 'sdtLienHe'=>['string', false],
            'emailLienHe'=>['email', false], 'monHocId'=>['id', true], 'capLopId'=>['id', true],
            'loaiLopMongMuon'=>[['kem_1_1', 'kem_1_2', 'nhom'], false, 'kem_1_1'],
            'coSoMongMuonId'=>['id', false], 'hinhThuc'=>[['online', 'offline', 'ca_hai'], false, 'offline'],
            'khuVucId'=>['id', false], 'diaChiHoc'=>['string', false], 'nganSachMin'=>['integer', false],
            'nganSachMax'=>['integer', false], 'donViNganSach'=>[['theo_buoi', 'theo_gio', 'theo_khoa'], false],
            'mucTieu'=>['string', false], 'ghiChu'=>['string', false],
            'nguon'=>[['website', 'dien_thoai', 'truc_tiep', 'gioi_thieu'], false, 'truc_tiep'], 'lichYeuCau'=>['array', true]];
        self::fields($input, array_keys($rules));
        $data = Input::validate($input, $rules);
        foreach (['hoTenLienHe'=>150, 'sdtLienHe'=>20, 'emailLienHe'=>150, 'diaChiHoc'=>255, 'mucTieu'=>5000, 'ghiChu'=>5000] as $key=>$max) {
            if (mb_strlen($data[$key] ?? '') > $max) Input::fail($key);
        }
        if (!$data['hocVienId'] && (!$data['hoTenLienHe'] || !$data['sdtLienHe'])) Input::fail('hoTenLienHe/sdtLienHe');
        foreach (['nganSachMin', 'nganSachMax'] as $key) {
            if (isset($data[$key]) && ($data[$key] > 999999999999 || !$data['donViNganSach'])) Input::fail($key);
        }
        if (isset($data['nganSachMin'], $data['nganSachMax']) && $data['nganSachMin'] > $data['nganSachMax']) Input::fail('nganSachMax');
        if (!$data['lichYeuCau'] || count($data['lichYeuCau']) > 21) Input::fail('lichYeuCau');
        $slots = [];
        foreach ($data['lichYeuCau'] as $slot) {
            if (!is_array($slot)) Input::fail('lichYeuCau');
            self::fields($slot, ['thu', 'gioBatDau', 'gioKetThuc']);
            $slot = Input::validate($slot, ['thu'=>['id', true], 'gioBatDau'=>['time', true], 'gioKetThuc'=>['time', true]]);
            if ($slot['thu'] < 2 || $slot['thu'] > 8) Input::fail('thu');
            Input::pair($slot, 'gioBatDau', 'gioKetThuc');
            foreach ($slots as $previous) {
                if ($slot['thu'] === $previous['thu'] && $slot['gioBatDau'] < $previous['gioKetThuc'] && $slot['gioKetThuc'] > $previous['gioBatDau']) Input::fail('lichYeuCau');
            }
            $slots[] = $slot;
        }
        $data['lichYeuCau'] = $slots;
        return $data;
    }

    public static function proposal(array $input): array
    {
        self::fields($input, ['nhanSuId', 'hocPhiDeXuat', 'ghiChu']);
        $data = Input::validate($input, ['nhanSuId'=>['id', true], 'hocPhiDeXuat'=>['integer', false], 'ghiChu'=>['string', false]]);
        if (($data['hocPhiDeXuat'] ?? 0) > 999999999999 || mb_strlen($data['ghiChu'] ?? '') > 255) Input::fail('hocPhiDeXuat/ghiChu');
        return $data;
    }

    public static function response(array $input): array
    {
        self::fields($input, ['trangThai', 'lyDo']);
        $data = Input::validate($input, ['trangThai'=>[['dong_y', 'tu_choi'], true], 'lyDo'=>['string', false]]);
        $data['lyDo'] = $data['lyDo'] ?: null;
        if (mb_strlen($data['lyDo'] ?? '') > 255 || ($data['trangThai'] === 'tu_choi' && !$data['lyDo']) || ($data['trangThai'] === 'dong_y' && $data['lyDo'])) Input::fail('lyDo');
        return $data;
    }
}
