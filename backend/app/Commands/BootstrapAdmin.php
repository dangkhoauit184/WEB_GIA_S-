<?php
namespace App\Commands;

use CodeIgniter\CLI\{BaseCommand, CLI};
use App\Modules\Administration\Features\Auth\AuthService;

class BootstrapAdmin extends BaseCommand
{
    protected $group = 'EduCenter';
    protected $name = 'educenter:bootstrap-admin';
    protected $description = 'Tạo admin PHP; không tự ghi đè tài khoản đã tồn tại.';
    protected $options = ['--reset'=>'Chủ động đặt lại tài khoản admin đã có.'];

    public function run(array $params)
    {
        try {
            $password = getenv('INITIAL_ADMIN_PASSWORD') ?: CLI::prompt('Mật khẩu admin (ít nhất 6 ký tự; ký tự sẽ hiển thị)', null, 'required');
            $result = AuthService::bootstrap([
                'username'=>getenv('ADMIN_USERNAME') ?: 'admin', 'password'=>$password,
                'hoTen'=>getenv('ADMIN_NAME') ?: 'Quản trị viên',
                'email'=>getenv('ADMIN_EMAIL') ?: 'admin@educenter.local',
                'sdt'=>getenv('ADMIN_PHONE') ?: null,
            ], CLI::getOption('reset') !== null);
            CLI::write(($result['created'] ? 'Đã tạo ' : 'Đã cập nhật ') . $result['username'], 'green');
        } catch (\Throwable $error) {
            CLI::error($error instanceof \App\Shared\ApiException ? $error->getMessage() : 'Không tạo được admin. Kiểm tra cấu hình DB và nhật ký.');
            exit(1);
        }
    }
}
