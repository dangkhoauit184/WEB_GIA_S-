<?php
namespace App\Commands;

use App\Modules\Communication\Features\Notifications\EmailWorkerService;
use CodeIgniter\CLI\{BaseCommand, CLI};

class EmailWorker extends BaseCommand
{
    protected $group = 'EduCenter';
    protected $name = 'educenter:email-worker';
    protected $description = 'Xử lý hàng đợi email thông báo nghiệp vụ (nhắc lịch, học phí, phân công, kết quả duyệt đơn).';
    protected $options = [
        '--limit' => 'Số email tối đa xử lý trong một lượt (mặc định 20, tối đa 100).',
        '--max-attempts' => 'Số lần thử tối đa cho lỗi tạm thời (mặc định 5, tối đa 10).',
        '--reminder-hours' => 'Số giờ trước giờ học để tạo thông báo nhắc lịch (mặc định cấu hình hoặc 24 giờ).',
        '--skip-reminders' => 'Bỏ qua bước quét sinh thông báo nhắc lịch học.',
        '--dry-run' => 'Chỉ quét nhắc lịch và đếm số email sẵn sàng gửi, không gửi SMTP.',
    ];

    private function optionValue(array $params, string $name): ?string
    {
        $val = CLI::getOption($name);
        if (is_string($val) && $val !== '') return $val;
        foreach ($params as $key => $v) {
            if (is_string($key) && str_starts_with($key, $name.'=')) {
                return substr($key, strlen($name) + 1);
            }
            if (is_string($v) && str_starts_with($v, '--'.$name.'=')) {
                return substr($v, strlen($name) + 3);
            }
        }
        return null;
    }

    public function run(array $params)
    {
        date_default_timezone_set('Asia/Ho_Chi_Minh');

        $limitOpt = $this->optionValue($params, 'limit');
        $maxOpt = $this->optionValue($params, 'max-attempts');
        $remHoursOpt = $this->optionValue($params, 'reminder-hours');
        $skipReminders = CLI::getOption('skip-reminders') !== null;
        $dryRun = CLI::getOption('dry-run') !== null;

        try {
            $result = EmailWorkerService::processBatch([
                'limit' => $limitOpt !== null ? (int)$limitOpt : EmailWorkerService::DEFAULT_BATCH_LIMIT,
                'maxAttempts' => $maxOpt !== null ? (int)$maxOpt : EmailWorkerService::DEFAULT_MAX_ATTEMPTS,
                'reminderHours' => $remHoursOpt !== null ? (int)$remHoursOpt : null,
                'skipReminders' => $skipReminders,
                'dryRun' => $dryRun,
            ]);

            $rem = $result['reminders'];
            CLI::write(sprintf(
                '[EduCenter EmailWorker] Nhắc lịch (%dh): %d ca, tạo mới %d thông báo (nhân sự %d, phụ huynh %d).',
                $rem['leadHours'],
                $rem['sessionsScanned'],
                $rem['enqueuedTotal'],
                $rem['enqueuedStaff'],
                $rem['enqueuedParents']
            ), 'cyan');

            if ($result['dryRun']) {
                CLI::write(sprintf('[Dry-run] Có %d email đang sẵn sàng gửi (%d ms).', $result['readyCount'], $result['durationMs']), 'yellow');
                return;
            }

            CLI::write(sprintf(
                '[EduCenter EmailWorker] Nhận xử lý: %d | Đã gửi: %d | Chờ thử lại: %d | Lỗi dừng: %d | %d ms',
                $result['claimed'],
                $result['sent'],
                $result['retried'],
                $result['permanentFailed'],
                $result['durationMs']
            ), $result['permanentFailed'] > 0 ? 'yellow' : 'green');

            foreach ($result['errors'] as $err) {
                CLI::write(sprintf('  - #%d [%s]: %s', $err['id'], $err['kind'], $err['reason']), 'red');
            }
        } catch (\Throwable $error) {
            CLI::error('[EduCenter EmailWorker] Lỗi hệ thống: '.EmailWorkerService::sanitizeError($error->getMessage()));
            exit(1);
        }
    }
}
