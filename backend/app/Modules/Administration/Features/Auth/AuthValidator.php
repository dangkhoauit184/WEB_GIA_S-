<?php
namespace App\Modules\Administration\Features\Auth;

use App\Shared\Input;

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
}
