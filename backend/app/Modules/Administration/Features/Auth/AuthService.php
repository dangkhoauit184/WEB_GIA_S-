<?php
namespace App\Modules\Administration\Features\Auth;

use App\Shared\{Access, ApiException, Input, Store};
use Firebase\JWT\JWT;

final class AuthService
{
    public static function login(array $input): array
    {
        $account = AuthRepository::username($input['username']);
        if (!$account || !password_verify($input['password'], $account['password_hash'])) {
            throw new ApiException(401, 'Tên đăng nhập hoặc mật khẩu không chính xác.', 'INVALID_CREDENTIALS');
        }
        if ($account['status'] !== 'active') {
            throw new ApiException(403, 'Tài khoản đang bị khóa hoặc chưa kích hoạt.', 'ACCOUNT_INACTIVE');
        }
        $user = AuthModel::map($account, AuthRepository::permissions((int)$account['group_id']));
        $duration = getenv('JWT_EXPIRES_IN') ?: '8h';
        if (!preg_match('/^(\d+)([smhd])$/D', $duration, $match)) throw new ApiException(503, 'JWT_EXPIRES_IN không hợp lệ.', 'SERVER_NOT_CONFIGURED');
        $seconds = (int)$match[1] * ['s'=>1,'m'=>60,'h'=>3600,'d'=>86400][$match[2]];
        $claims = array_intersect_key($user, array_flip(['accountId','userId','groupId','groupName','role','username','nhanVienId','nhanSuId','phanLoai']));
        $claims += ['iss'=>'educenter-php', 'iat'=>time(), 'exp'=>time()+$seconds, 'jti'=>bin2hex(random_bytes(16)), 'pwd'=>Access::fingerprint($account)];
        $token = JWT::encode($claims, Access::secret(), 'HS256');
        Store::audit((int)$user['userId'], 'dang_nhap', (int)$user['accountId'], ['username'=>$user['username'], 'role'=>$user['role']]);
        return ['token'=>$token, 'user'=>$user];
    }

    public static function changePassword(array $input): array
    {
        $user = AuthFilter::user();
        $account = AuthRepository::account((int)$user['accountId']);
        if (!password_verify($input['currentPassword'], $account['password_hash'])) {
            throw new ApiException(400, 'Mật khẩu hiện tại không chính xác.', 'INVALID_CURRENT_PASSWORD');
        }
        Store::transaction(function () use ($account, $input, $user) {
            Store::execute('UPDATE account SET password_hash=? WHERE account_id=?', [password_hash($input['newPassword'], PASSWORD_BCRYPT), $account['account_id']]);
            Store::audit((int)$user['userId'], 'sua', (int)$user['accountId'], ['action'=>'doi_mat_khau']);
        });
        return ['updated'=>true, 'requiresLogin'=>true];
    }

    public static function logout(): array
    {
        $user = AuthFilter::user();
        Store::audit((int)$user['userId'], 'khac', (int)$user['accountId'], [
            'action'=>'dang_xuat', 'tokenHash'=>hash('sha256', Access::$token), 'expiresAt'=>Access::$claims->exp,
        ]);
        return ['loggedOut'=>true];
    }

    public static function bootstrap(array $input, bool $reset = false): array
    {
        $data = Input::validate($input, ['username'=>['string',true], 'password'=>['password',true],
            'hoTen'=>['string',true], 'email'=>['email',true], 'sdt'=>['string',false]]);
        return Store::transaction(function () use ($data, $reset) {
            $existing = Store::one('SELECT account_id, user_id FROM account WHERE username=? FOR UPDATE', [$data['username']]);
            if ($existing && !$reset) throw new ApiException(409, 'Tài khoản đã tồn tại. Chỉ dùng --reset nếu chủ động đổi mật khẩu.', 'ACCOUNT_EXISTS');
            $group = Store::one("SELECT group_id FROM role_group WHERE group_name='Admin' AND is_deleted=0");
            if (!$group) throw new ApiException(400, 'Cần chạy seed-reference trước.');
            $hash = password_hash($data['password'], PASSWORD_BCRYPT);
            if ($existing) {
                Store::execute("UPDATE account SET password_hash=?, group_id=?, status='active', is_deleted=0 WHERE account_id=?", [$hash,$group['group_id'],$existing['account_id']]);
                return ['created'=>false, 'username'=>$data['username']];
            }
            $userId = Store::insert('users', ['hoten'=>$data['hoTen'], 'email'=>$data['email'], 'sdt'=>$data['sdt'], 'nghe_nghiep'=>'Quản trị hệ thống']);
            Store::insert('account', ['user_id'=>$userId,'group_id'=>$group['group_id'],'username'=>$data['username'],'password_hash'=>$hash,'status'=>'active']);
            return ['created'=>true, 'username'=>$data['username']];
        });
    }
}
