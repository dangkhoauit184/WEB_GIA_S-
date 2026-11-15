<?php
namespace App\Modules\Administration\Features\Auth;

use App\Shared\{ApiException, Input};

final class AuthValidator
{
    public static function login(array $body): array
    {
        $data = Input::validate($body, ['username'=>['string',true], 'password'=>['string',true]]);
        // Password bytes are significant, including spaces.
        $data['password'] = $body['password'];
        return $data;
    }

    public static function password(array $body): array
    {
        $data = Input::validate($body, ['currentPassword'=>['string',true], 'newPassword'=>['password',true]]);
        $data['currentPassword'] = $body['currentPassword']; $data['newPassword'] = $body['newPassword'];
        return $data;
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
