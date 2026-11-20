<?php
namespace App\Modules\Administration\Features\Auth;

use App\Shared\{ApiException, Input};

final class AuthValidator
{
    public static function login(array $body): array
    {
        $data = Input::validate($body, ['username'=>['string',true], 'rememberMe'=>['bool',false,false]]);
        if (!is_string($body['password'] ?? null) || $body['password'] === '') {
            throw new ApiException(400, 'Vui lòng nhập mật khẩu.');
        }
        // Password bytes are significant, including spaces.
        $data['password'] = $body['password'];
        $data['rememberMe'] = (bool)($data['rememberMe'] ?? false);
        return $data;
    }

    public static function googleLogin(array $body): array
    {
        if (array_diff(array_keys($body), ['credential', 'rememberMe'])) {
            throw new ApiException(400, 'Chỉ chấp nhận mã xác thực Google (credential) và tùy chọn ghi nhớ đăng nhập.');
        }
        $credential = $body['credential'] ?? null;
        if (!is_string($credential) || trim($credential) === '' || strlen($credential) > 16384) {
            throw new ApiException(400, 'Thiếu mã xác thực Google hợp lệ.', 'INVALID_GOOGLE_TOKEN');
        }
        $rememberMe = false;
        if (array_key_exists('rememberMe', $body)) {
            if (!is_bool($body['rememberMe']) && $body['rememberMe'] !== 0 && $body['rememberMe'] !== 1) {
                throw new ApiException(400, 'Tùy chọn ghi nhớ đăng nhập không hợp lệ.');
            }
            $rememberMe = (bool)$body['rememberMe'];
        }
        return ['credential' => trim($credential), 'rememberMe' => $rememberMe];
    }

    public static function password(array $body): array
    {
        if (!is_string($body['currentPassword'] ?? null) || $body['currentPassword'] === '') {
            throw new ApiException(400, 'Vui lòng nhập mật khẩu hiện tại.');
        }
        if (!is_string($body['newPassword'] ?? null) || strlen($body['newPassword']) < 6 || strlen($body['newPassword']) > 72 || str_contains($body['newPassword'], "\0")) {
            throw new ApiException(400, 'Mật khẩu mới cần từ 6 đến 72 byte, không chứa ký tự rỗng.');
        }
        if (array_key_exists('confirmPassword', $body) && $body['confirmPassword'] !== $body['newPassword']) {
            throw new ApiException(400, 'Xác nhận mật khẩu mới chưa khớp.', 'PASSWORD_MISMATCH');
        }
        return ['currentPassword'=>$body['currentPassword'], 'newPassword'=>$body['newPassword']];
    }

    public static function profile(array $body, string $role): array
    {
        $fields = ['hoTen'=>150,'email'=>150,'sdt'=>20,'ngaySinh'=>10,'diaChi'=>255];
        if ($role === 'PHU_HUYNH') $fields['ngheNghiep'] = 100;
        if (in_array($role, ['GIAO_VIEN','GIA_SU','TRO_GIANG'], true)) $fields += ['gioiTinh'=>4,'donViCongTac'=>150,'gioiThieu'=>10000];
        if (!$body || array_diff(array_keys($body), array_keys($fields))) throw new ApiException(400, 'Chỉ được cập nhật thông tin cá nhân trong biểu mẫu.', 'INVALID_PROFILE_FIELDS');
        $labels = ['hoTen'=>'Họ và tên','email'=>'Email','sdt'=>'Số điện thoại','ngaySinh'=>'Ngày sinh','diaChi'=>'Địa chỉ','ngheNghiep'=>'Nghề nghiệp','gioiTinh'=>'Giới tính','donViCongTac'=>'Đơn vị công tác','gioiThieu'=>'Giới thiệu chuyên môn'];
        foreach ($body as $key=>&$value) {
            if ($value !== null && !is_string($value)) throw new ApiException(400, $labels[$key].' không hợp lệ.');
            $value = $value === null ? null : trim($value);
            if (in_array($key, ['hoTen','email'], true) && !$value) throw new ApiException(400, 'Vui lòng nhập '.$labels[$key].'.');
            if ($value !== null && mb_strlen($value) > $fields[$key]) throw new ApiException(400, $labels[$key].' tối đa '.$fields[$key].' ký tự.');
            if ($value === '') $value = null;
        }
        unset($value);
        if (isset($body['email']) && !filter_var($body['email'], FILTER_VALIDATE_EMAIL)) throw new ApiException(400, 'Email không đúng định dạng.');
        if (isset($body['sdt']) && !preg_match('/^\+?[0-9][0-9 .()-]{5,19}$/D', $body['sdt'])) throw new ApiException(400, 'Số điện thoại không đúng định dạng.');
        if (isset($body['ngaySinh']) && (!Input::date($body['ngaySinh']) || $body['ngaySinh'] > date('Y-m-d'))) throw new ApiException(400, 'Ngày sinh phải là ngày hợp lệ và không ở tương lai.');
        if (isset($body['gioiTinh']) && !in_array($body['gioiTinh'], ['nam','nu','khac'], true)) throw new ApiException(400, 'Giới tính không hợp lệ.');
        foreach (['email'=>'email','sdt'=>'sdt'] as $key=>$column) {
            if (isset($body[$key]) && \App\Shared\Store::one("SELECT user_id FROM users WHERE $column=? AND user_id<>?", [$body[$key], AuthFilter::user()['userId']])) {
                throw new ApiException(409, $labels[$key].' đã được sử dụng bởi hồ sơ khác.', $key === 'email' ? 'PROFILE_EMAIL_EXISTS' : 'PROFILE_PHONE_EXISTS');
            }
        }
        return $body;
    }

    public static function register(array $body): array
    {
        $data = Input::validate($body, [
            'hoTen' => ['string', true],
            'sdt' => ['string', true],
            'email' => ['email', false],
            'username' => ['string', true],
            'password' => ['password', true],
            'confirmPassword' => ['string', false],
        ]);
        $data['password'] = $body['password'];
        if (array_key_exists('confirmPassword', $body) && $body['confirmPassword'] !== $body['password']) {
            throw new ApiException(400, 'Mật khẩu xác nhận không khớp với mật khẩu đã nhập.', 'PASSWORD_MISMATCH');
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,100}$/', $data['username'])) {
            throw new ApiException(400, 'Tên đăng nhập chỉ gồm chữ, số, dấu gạch dưới hoặc dấu chấm (3-100 ký tự).');
        }
        if (empty($data['email'])) {
            $data['email'] = strtolower($data['username']) . '@phuhuynh.educenter.local';
        }
        return $data;
    }

    public static function sendOtp(array $body): array
    {
        return Input::validate($body, [
            'identifier' => ['string', true],
        ]);
    }

    public static function resetPassword(array $body): array
    {
        $data = Input::validate($body, [
            'identifier' => ['string', true],
            'otp' => ['string', true],
            'newPassword' => ['password', true],
            'confirmPassword' => ['string', true],
        ]);
        $data['newPassword'] = $body['newPassword'];
        if ($body['newPassword'] !== $body['confirmPassword']) {
            throw new ApiException(400, 'Mật khẩu xác nhận không khớp với mật khẩu mới.', 'PASSWORD_MISMATCH');
        }
        return $data;
    }
}
