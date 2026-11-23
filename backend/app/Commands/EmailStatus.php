<?php
namespace App\Commands;

use App\Modules\Communication\Features\Notifications\EmailWorkerService;
use CodeIgniter\CLI\{BaseCommand, CLI};

class EmailStatus extends BaseCommand
{
    protected $group = 'EduCenter';
    protected $name = 'educenter:email-status';
    protected $description = 'Kiểm tra trạng thái hàng đợi email thông báo và danh sách lỗi gần nhất.';

    public function run(array $params)
    {
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        try {
            $data = EmailWorkerService::stats();
            $s = $data['summary'];
            CLI::write(sprintf(
                '[Email Queue] Tổng xếp hàng: %d | Chờ gửi: %d | Đã gửi: %d | Đang retry: %d | Lỗi dừng: %d',
                $s['totalQueued'],
                $s['pending'],
                $s['sent'],
                $s['retrying'],
                $s['failedPermanent']
            ), 'cyan');
            if (!$data['recentFailures']) {
                CLI::write('Không có lỗi gửi email nào gần đây.', 'green');
                return;
            }
            foreach ($data['recentFailures'] as $item) {
                CLI::write(sprintf(
                    '  - #%d [%s] thử=%d | lần cuối=%s | thử tiếp=%s | %s',
                    $item['id'],
                    $item['loai'],
                    $item['soLanThuEmail'],
                    $item['thuGanNhatLuc'] ?? '—',
                    $item['thuTiepLuc'] ?? 'dừng',
                    $item['loiEmail'] ?? '—'
                ), 'yellow');
            }
        } catch (\Throwable $error) {
            CLI::error('[Email Status] Không đọc được trạng thái hàng đợi: '.EmailWorkerService::sanitizeError($error->getMessage()));
            exit(1);
        }
    }
}
