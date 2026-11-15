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

    public static function register(array $data): array
    {
        return Store::transaction(function () use ($data) {
            if (Store::one('SELECT account_id FROM account WHERE username=? FOR UPDATE', [$data['username']])) {
                throw new ApiException(409, 'Tên đăng nhập này đã được sử dụng. Vui lòng chọn tên khác.', 'USERNAME_EXISTS');
            }
            if (Store::one('SELECT user_id FROM users WHERE sdt=? FOR UPDATE', [$data['sdt']])) {
                throw new ApiException(409, 'Số điện thoại này đã được đăng ký trong hệ thống.', 'PHONE_EXISTS');
            }
            if (Store::one('SELECT user_id FROM users WHERE email=? FOR UPDATE', [$data['email']])) {
                throw new ApiException(409, 'Địa chỉ Email này đã được đăng ký trong hệ thống.', 'EMAIL_EXISTS');
            }
            $group = Store::one("SELECT group_id FROM role_group WHERE group_name='PhuHuynh' AND is_deleted=0");
            if (!$group) {
                throw new ApiException(400, 'Nhóm quyền Phụ huynh chưa được khởi tạo trong CSDL.');
            }
            $userId = Store::insert('users', [
                'hoten' => $data['hoTen'],
                'sdt' => $data['sdt'],
                'email' => $data['email'],
                'nghe_nghiep' => 'Phụ huynh',
            ]);
            $accountId = Store::insert('account', [
                'user_id' => $userId,
                'group_id' => $group['group_id'],
                'username' => $data['username'],
                'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
                'status' => 'active',
            ]);
            Store::recordAudit($userId, 'them', 'users', $userId, null, [
                'action' => 'parent_profile_created',
                'hoTen' => $data['hoTen'],
                'source' => 'self_register',
            ]);
            return [
                'registered' => true,
                'userId' => $userId,
                'accountId' => $accountId,
                'username' => $data['username'],
                'hoTen' => $data['hoTen'],
            ];
        });
    }

    private static function lockRecoveryAccount(string $identifier): array
    {
        $account = AuthRepository::username($identifier);
        if (!$account) throw new ApiException(404, 'Không tìm thấy tài khoản.', 'ACCOUNT_NOT_FOUND');
        Store::one('SELECT account_id FROM account WHERE account_id=? FOR UPDATE', [$account['account_id']]);
        $account = AuthRepository::account((int)$account['account_id']);
        if (!$account || $account['status'] !== 'active') {
            throw new ApiException(403, 'Tài khoản đang bị khóa hoặc chưa kích hoạt.', 'ACCOUNT_INACTIVE');
        }
        return $account;
    }

    public static function sendOtp(array $data): array
    {
        return Store::transaction(function () use ($data) {
            $account = self::lockRecoveryAccount($data['identifier']);
            if (!filter_var($account['email'], FILTER_VALIDATE_EMAIL)) {
                throw new ApiException(400, 'Tài khoản chưa có email hợp lệ. Vui lòng liên hệ quản trị viên.', 'EMAIL_REQUIRED');
            }
            $previous = AuthRepository::latestOtp((int)$account['account_id']);
            if ((int)($previous['sentAt'] ?? 0) > time() - 60) {
                throw new ApiException(429, 'Vui lòng chờ 60 giây trước khi gửi lại mã OTP.', 'OTP_RATE_LIMITED');
            }
            $otp = (string)random_int(100000, 999999);
            $record = [
                'action'=>'forgot_password_otp', 'version'=>2,
                'otpHash'=>hash_hmac('sha256', $otp, Access::secret()),
                'passwordVersion'=>Access::fingerprint($account),
                'sentAt'=>time(), 'expiresAt'=>time()+600, 'attempts'=>0, 'used'=>false,
            ];
            Store::audit((int)$account['user_id'], 'khac', (int)$account['account_id'], $record);
            $mail = service('email');
            $mail->clear(true);
            $config = config('Email');
            $mail->setFrom($config->fromEmail, $config->fromName);
            $mail->setTo($account['email']);
            $mail->setSubject('EduCenter - Mã xác nhận đặt lại mật khẩu');
            $mail->setMailType('text');
            $mail->setMessage("Mã OTP của bạn là {$otp}. Mã có hiệu lực 10 phút và chỉ dùng một lần. Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này.");
            if (!$mail->send()) {
                throw new ApiException(503, 'Chưa gửi được email OTP. Vui lòng thử lại sau hoặc liên hệ quản trị viên.', 'EMAIL_UNAVAILABLE');
            }
            return ['sent'=>true, 'expiresInSeconds'=>600,
                'message'=>'Đã gửi mã OTP đến email đã đăng ký. Vui lòng kiểm tra hộp thư.'];
        });
    }

    public static function resetPassword(array $data): array
    {
        $result = Store::transaction(function () use ($data) {
            $account = self::lockRecoveryAccount($data['identifier']);
            $record = AuthRepository::latestOtp((int)$account['account_id']);
            if (!$record || ($record['version'] ?? 0) !== 2 || !empty($record['used'])
                || !hash_equals((string)($record['passwordVersion'] ?? ''), Access::fingerprint($account))) {
                throw new ApiException(400, 'Vui lòng lấy mã OTP mới trước khi đặt lại mật khẩu.', 'OTP_NOT_FOUND');
            }
            if ((int)$record['expiresAt'] <= time()) {
                throw new ApiException(400, 'Mã OTP đã hết hạn. Vui lòng lấy mã mới.', 'OTP_EXPIRED');
            }
            if ((int)$record['attempts'] >= 5) {
                throw new ApiException(429, 'Mã OTP đã bị khóa do nhập sai nhiều lần. Vui lòng lấy mã mới.', 'OTP_ATTEMPTS_EXCEEDED');
            }
            unset($record['auditId']);
            if (!hash_equals($record['otpHash'], hash_hmac('sha256', trim($data['otp']), Access::secret()))) {
                $record['attempts']++;
                Store::audit((int)$account['user_id'], 'khac', (int)$account['account_id'], $record);
                // Commit the failed attempt before returning the validation error.
                return ['invalidOtp'=>true];
            }
            Store::execute('UPDATE account SET password_hash=? WHERE account_id=?', [
                password_hash($data['newPassword'], PASSWORD_BCRYPT), $account['account_id'],
            ]);
            $record['used'] = true;
            Store::audit((int)$account['user_id'], 'khac', (int)$account['account_id'], $record);
            Store::audit((int)$account['user_id'], 'sua', (int)$account['account_id'], [
                'action'=>'account_status_changed', 'reason'=>'reset_password_by_otp',
            ]);
            return ['reset'=>true, 'username'=>$account['username'],
                'message'=>'Đặt lại mật khẩu thành công! Bạn có thể đăng nhập bằng mật khẩu mới.'];
        });
        if (!empty($result['invalidOtp'])) throw new ApiException(400, 'Mã xác nhận OTP không chính xác.', 'INVALID_OTP');
        return $result;
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
