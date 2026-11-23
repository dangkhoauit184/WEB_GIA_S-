<?php
namespace App\Modules\Communication\Features\Notifications;

use App\Shared\{Access, Store};

final class EmailWorkerService
{
    public const DEFAULT_BATCH_LIMIT = 20;
    public const DEFAULT_MAX_ATTEMPTS = 5;
    public const DEFAULT_REMINDER_HOURS = 24;
    public const CLAIM_LEASE_SECONDS = 300;
    private const BACKOFF_MINUTES = [2, 10, 30, 120, 360];

    public static function sanitizeError(string $raw): string
    {
        $clean = preg_replace('/[\r\n\t]+/', ' ', trim($raw)) ?? '';
        // Strip potential SMTP auth / credential / token leakage
        $clean = preg_replace('/(password|pass|secret|token|authorization|auth\s+plain|auth\s+login)\s*[:=]?\s*\S+/i', '$1=[REDACTED]', $clean) ?? $clean;
        $clean = preg_replace('/\s{2,}/', ' ', trim($clean)) ?? '';
        if ($clean === '') $clean = 'Lỗi gửi email tạm thời qua SMTP.';
        return mb_substr($clean, 0, 250);
    }

    public static function reminderLeadHours(?int $override = null): int
    {
        if ($override !== null && $override >= 1 && $override <= 168) {
            return $override;
        }
        $env = getenv('MAIL_REMINDER_HOURS');
        if ($env !== false && is_numeric($env)) {
            $val = (int)$env;
            if ($val >= 1 && $val <= 168) return $val;
        }
        $cfg = Store::one("SELECT gia_tri FROM cau_hinh WHERE khoa='nhac_lich_truoc_gio'");
        if ($cfg && is_numeric($cfg['gia_tri'])) {
            $val = (int)$cfg['gia_tri'];
            if ($val >= 1 && $val <= 168) return $val;
        }
        return self::DEFAULT_REMINDER_HOURS;
    }

    /**
     * Enqueue upcoming class/session reminders idempotently using (user_id, khoa_chong_trung).
     * Never creates duplicate rows when run repeatedly.
     */
    public static function enqueueUpcomingReminders(?int $leadHours = null): array
    {
        $hours = self::reminderLeadHours($leadHours);
        $sessions = Store::rows("SELECT b.id, b.lop_id, b.ngay, b.gio_bat_dau, b.gio_ket_thuc, b.loai, b.pham_vi_bu,
                l.ma_lop, l.ten_lop, l.hinh_thuc, COALESCE(b.phong_hoc, l.phong_hoc) AS phong_hoc, cs.ten_co_so
            FROM buoi_hoc b
            JOIN lop_hoc l ON l.id=b.lop_id
            LEFT JOIN co_so cs ON cs.id=COALESCE(b.co_so_id, l.co_so_id)
            WHERE l.deleted_at IS NULL
              AND l.trang_thai IN ('sap_bat_dau','dang_hoc')
              AND b.trang_thai='du_kien'
              AND TIMESTAMP(b.ngay, b.gio_bat_dau) > NOW()
              AND TIMESTAMP(b.ngay, b.gio_bat_dau) <= DATE_ADD(NOW(), INTERVAL ? HOUR)
            ORDER BY b.ngay ASC, b.gio_bat_dau ASC", [$hours]);

        $enqueuedStaff = 0;
        $enqueuedParents = 0;

        foreach ($sessions as $s) {
            $dayFormatted = substr($s['ngay'], 8, 2).'/'.substr($s['ngay'], 5, 2).'/'.substr($s['ngay'], 0, 4);
            $timeRange = substr($s['gio_bat_dau'], 0, 5).'–'.substr($s['gio_ket_thuc'], 0, 5);
            $place = $s['hinh_thuc'] === 'online'
                ? 'Trực tuyến (Online)'
                : trim(($s['ten_co_so'] ?: 'Tại trung tâm').($s['phong_hoc'] ? ' · '.$s['phong_hoc'] : ''));

            // 1. Assigned teachers / tutors / teaching assistants
            $staffRows = Store::rows("SELECT DISTINCT pc.user_id, pc.vai_tro
                FROM phan_cong_buoi_hoc pc
                JOIN users u ON u.user_id=pc.user_id AND u.is_deleted=0
                JOIN account a ON a.user_id=pc.user_id AND a.status='active' AND a.is_deleted=0
                WHERE pc.buoi_hoc_id=? AND pc.trang_thai<>'da_go'", [$s['id']]);
            foreach ($staffRows as $st) {
                $roleLabel = $st['vai_tro'] === 'tro_giang' ? 'Trợ giảng' : 'Dạy chính';
                Store::execute(
                    "INSERT IGNORE INTO thong_bao(user_id, loai, tieu_de, noi_dung, doi_tuong_loai, doi_tuong_id, khoa_chong_trung, co_gui_email)
                     VALUES (?, 'nhac_checkin', ?, ?, 'buoi_hoc', ?, ?, 1)",
                    [
                        (int)$st['user_id'],
                        'Nhắc lịch dạy ('.$roleLabel.') · '.$s['ma_lop'],
                        'Bạn có ca '.$s['ma_lop'].' — '.$s['ten_lop'].' vào ngày '.$dayFormatted.' lúc '.$timeRange.' ('.$place.'). Vui lòng bấm Check-in trên thời khóa biểu khi vào ca.',
                        (int)$s['id'],
                        'reminder:staff:'.$s['id'].':'.$st['vai_tro'],
                    ]
                );
                if (Store::db()->affectedRows() > 0) $enqueuedStaff++;
            }

            // 2. Parents of enrolled students (respecting individual makeup scope)
            $parentSql = "SELECT DISTINCT hv.phu_huynh_user_id AS user_id
                FROM lop_hoc_vien x
                JOIN hoc_vien hv ON hv.id=x.hoc_vien_id AND hv.deleted_at IS NULL
                JOIN users u ON u.user_id=hv.phu_huynh_user_id AND u.is_deleted=0
                WHERE x.lop_id=?
                  AND x.trang_thai IN ('dang_hoc','hoc_thu')
                  AND x.ngay_vao <= ?
                  AND (x.ngay_roi IS NULL OR x.ngay_roi >= ?)";
            $parentParams = [(int)$s['lop_id'], $s['ngay'], $s['ngay']];
            if ($s['loai'] === 'bu' && $s['pham_vi_bu'] === 'ca_nhan') {
                $parentSql .= ' AND EXISTS (SELECT 1 FROM hoc_bu_hoc_vien hb WHERE hb.buoi_bu_id=? AND hb.hoc_vien_id=hv.id)';
                $parentParams[] = (int)$s['id'];
            }
            $parents = Store::rows($parentSql, $parentParams);
            foreach ($parents as $p) {
                Store::execute(
                    "INSERT IGNORE INTO thong_bao(user_id, loai, tieu_de, noi_dung, doi_tuong_loai, doi_tuong_id, khoa_chong_trung, co_gui_email)
                     VALUES (?, 'nhac_checkin', ?, ?, 'buoi_hoc', ?, ?, 1)",
                    [
                        (int)$p['user_id'],
                        'Nhắc lịch học · '.$s['ma_lop'],
                        'Lớp '.$s['ma_lop'].' — '.$s['ten_lop'].' sẽ diễn ra vào ngày '.$dayFormatted.' lúc '.$timeRange.' ('.$place.').',
                        (int)$s['id'],
                        'reminder:parent:'.$s['id'],
                    ]
                );
                if (Store::db()->affectedRows() > 0) $enqueuedParents++;
            }
        }

        return [
            'leadHours' => $hours,
            'sessionsScanned' => count($sessions),
            'enqueuedStaff' => $enqueuedStaff,
            'enqueuedParents' => $enqueuedParents,
            'enqueuedTotal' => $enqueuedStaff + $enqueuedParents,
        ];
    }

    /**
     * Atomically claim ready queued notifications using InnoDB SKIP LOCKED + lease.
     * Prevents duplicate sends across concurrent worker processes without holding DB locks during SMTP I/O.
     */
    public static function claimBatch(int $limit, int $maxAttempts, int $leaseSeconds = self::CLAIM_LEASE_SECONDS): array
    {
        $limit = max(1, min(100, $limit));
        $maxAttempts = max(1, min(10, $maxAttempts));
        $leaseSeconds = max(30, min(1800, $leaseSeconds));

        return Store::transaction(function () use ($limit, $maxAttempts, $leaseSeconds): array {
            $rows = Store::rows(
                "SELECT tb.id
                 FROM thong_bao tb
                 WHERE tb.co_gui_email = 1
                   AND tb.trang_thai_email IN ('chua_gui', 'loi')
                   AND tb.so_lan_thu_email < ?
                   AND (tb.email_thu_tiep_luc IS NULL OR tb.email_thu_tiep_luc <= NOW())
                 ORDER BY COALESCE(tb.email_thu_tiep_luc, tb.created_at) ASC, tb.id ASC
                 LIMIT ?
                 FOR UPDATE SKIP LOCKED",
                [$maxAttempts, $limit]
            );
            if (!$rows) return [];

            $ids = array_map(static fn(array $r) => (int)$r['id'], $rows);
            $in = implode(',', array_fill(0, count($ids), '?'));
            Store::execute(
                "UPDATE thong_bao
                 SET email_thu_gan_nhat_luc = NOW(),
                     email_thu_tiep_luc = DATE_ADD(NOW(), INTERVAL ? SECOND)
                 WHERE id IN ($in)",
                [$leaseSeconds, ...$ids]
            );

            return Store::rows(
                "SELECT tb.id, tb.user_id, tb.loai, tb.tieu_de, tb.noi_dung, tb.doi_tuong_loai, tb.doi_tuong_id,
                        tb.so_lan_thu_email, tb.trang_thai_email, tb.created_at,
                        u.hoten, u.email, u.is_deleted AS user_deleted,
                        a.status AS account_status, g.group_name, ns.phan_loai
                 FROM thong_bao tb
                 LEFT JOIN users u ON u.user_id = tb.user_id
                 LEFT JOIN account a ON a.user_id = tb.user_id AND a.is_deleted = 0
                 LEFT JOIN role_group g ON g.group_id = a.group_id AND g.is_deleted = 0
                 LEFT JOIN nhan_su_chuyen_mon ns ON ns.user_id = tb.user_id AND ns.deleted_at IS NULL
                 WHERE tb.id IN ($in)
                 ORDER BY tb.id ASC",
                $ids
            );
        });
    }

    private static function markPermanentFailure(int $id, int $maxAttempts, string $reason): void
    {
        $sanitized = self::sanitizeError($reason);
        Store::execute(
            "UPDATE thong_bao
             SET trang_thai_email = 'loi',
                 so_lan_thu_email = ?,
                 email_thu_gan_nhat_luc = NOW(),
                 email_thu_tiep_luc = NULL,
                 loi_email = ?
             WHERE id = ? AND trang_thai_email <> 'da_gui'",
            [$maxAttempts, $sanitized, $id]
        );
    }

    private static function markTransientFailure(int $id, int $previousAttempts, int $maxAttempts, string $reason): bool
    {
        $nextAttempt = min(255, $previousAttempts + 1);
        $exhausted = $nextAttempt >= $maxAttempts;
        $sanitized = self::sanitizeError($reason);

        if ($exhausted) {
            Store::execute(
                "UPDATE thong_bao
                 SET trang_thai_email = 'loi',
                     so_lan_thu_email = ?,
                     email_thu_gan_nhat_luc = NOW(),
                     email_thu_tiep_luc = NULL,
                     loi_email = ?
                 WHERE id = ? AND trang_thai_email <> 'da_gui'",
                [$nextAttempt, $sanitized, $id]
            );
            return false;
        }

        $backoffIndex = min($nextAttempt - 1, count(self::BACKOFF_MINUTES) - 1);
        $delayMinutes = self::BACKOFF_MINUTES[$backoffIndex];
        Store::execute(
            "UPDATE thong_bao
             SET trang_thai_email = 'loi',
                 so_lan_thu_email = ?,
                 email_thu_gan_nhat_luc = NOW(),
                 email_thu_tiep_luc = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                 loi_email = ?
             WHERE id = ? AND trang_thai_email <> 'da_gui'",
            [$nextAttempt, $delayMinutes, $sanitized, $id]
        );
        return true;
    }

    private static function markSent(int $id): void
    {
        Store::execute(
            "UPDATE thong_bao
             SET trang_thai_email = 'da_gui',
                 so_lan_thu_email = LEAST(255, so_lan_thu_email + 1),
                 email_gui_luc = NOW(),
                 email_thu_gan_nhat_luc = NOW(),
                 email_thu_tiep_luc = NULL,
                 loi_email = NULL
             WHERE id = ? AND trang_thai_email <> 'da_gui'",
            [$id]
        );
    }

    private static function resolveRecipientRole(array $row): string
    {
        if (!empty($row['group_name'])) {
            return Access::role((string)$row['group_name'], (string)($row['phan_loai'] ?? '')) ?? 'PHU_HUYNH';
        }
        return 'PHU_HUYNH';
    }

    /**
     * Run one complete worker pass: optional reminder enqueue + atomic batch claim + per-row SMTP send.
     */
    public static function processBatch(array $options = []): array
    {
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $startedAt = microtime(true);

        $limit = max(1, min(100, (int)($options['limit'] ?? self::DEFAULT_BATCH_LIMIT)));
        $maxAttempts = max(1, min(10, (int)($options['maxAttempts'] ?? self::DEFAULT_MAX_ATTEMPTS)));
        $skipReminders = (bool)($options['skipReminders'] ?? false);
        $dryRun = (bool)($options['dryRun'] ?? false);
        $reminderHours = isset($options['reminderHours']) ? (int)$options['reminderHours'] : null;

        $reminders = $skipReminders
            ? ['leadHours' => self::reminderLeadHours($reminderHours), 'sessionsScanned' => 0, 'enqueuedStaff' => 0, 'enqueuedParents' => 0, 'enqueuedTotal' => 0]
            : self::enqueueUpcomingReminders($reminderHours);

        if ($dryRun) {
            $preview = Store::one(
                "SELECT COUNT(*) AS n
                 FROM thong_bao
                 WHERE co_gui_email = 1
                   AND trang_thai_email IN ('chua_gui', 'loi')
                   AND so_lan_thu_email < ?
                   AND (email_thu_tiep_luc IS NULL OR email_thu_tiep_luc <= NOW())",
                [$maxAttempts]
            );
            return [
                'dryRun' => true,
                'reminders' => $reminders,
                'readyCount' => (int)($preview['n'] ?? 0),
                'claimed' => 0,
                'sent' => 0,
                'retried' => 0,
                'permanentFailed' => 0,
                'durationMs' => (int)round((microtime(true) - $startedAt) * 1000),
            ];
        }

        $claimed = self::claimBatch($limit, $maxAttempts);
        $sent = 0;
        $retried = 0;
        $permanentFailed = 0;
        $errors = [];

        $config = config('Email');

        foreach ($claimed as $row) {
            $id = (int)$row['id'];
            $attempts = (int)$row['so_lan_thu_email'];
            try {
                if ((int)($row['user_deleted'] ?? 1) !== 0) {
                    throw new \DomainException('Người nhận không tồn tại hoặc đã bị xóa.');
                }
                if ($row['account_status'] !== null && $row['account_status'] !== 'active') {
                    throw new \DomainException('Tài khoản người nhận đang bị khóa hoặc ngừng hoạt động.');
                }
                $email = trim((string)($row['email'] ?? ''));
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \DomainException('Địa chỉ email người nhận không hợp lệ hoặc chưa được cấu hình.');
                }

                $recipient = [
                    'user_id' => (int)$row['user_id'],
                    'hoten' => (string)($row['hoten'] ?? ''),
                    'email' => $email,
                    'role' => self::resolveRecipientRole($row),
                ];

                $payload = EmailTemplate::build($row, $recipient);

                $mail = service('email');
                $mail->clear(true);
                $mail->setFrom($config->fromEmail, $config->fromName);
                $mail->setTo($email);
                $mail->setSubject($payload['subject']);
                $mail->setMailType('html');
                $mail->setMessage($payload['html']);
                $mail->setAltMessage($payload['text']);

                $ok = $mail->send(false);
                if (!$ok) {
                    $debugger = '';
                    if (method_exists($mail, 'printDebugger')) {
                        $debugger = strip_tags((string)$mail->printDebugger(['headers']));
                    }
                    throw new \RuntimeException($debugger !== '' ? 'SMTP từ chối gửi: '.$debugger : 'Không gửi được email qua SMTP.');
                }

                self::markSent($id);
                $sent++;
                log_message('info', '[EmailWorker] Sent notification #{id} (type={type}, attempt={attempt})', [
                    'id' => $id,
                    'type' => $row['loai'],
                    'attempt' => $attempts + 1,
                ]);
            } catch (\DomainException $permanent) {
                $msg = self::sanitizeError($permanent->getMessage());
                self::markPermanentFailure($id, $maxAttempts, $msg);
                $permanentFailed++;
                $errors[] = ['id' => $id, 'kind' => 'permanent', 'reason' => $msg];
                log_message('warning', '[EmailWorker] Permanent failure #{id} (type={type}): {reason}', [
                    'id' => $id,
                    'type' => $row['loai'],
                    'reason' => $msg,
                ]);
            } catch (\Throwable $transient) {
                $msg = self::sanitizeError($transient->getMessage());
                $willRetry = self::markTransientFailure($id, $attempts, $maxAttempts, $msg);
                if ($willRetry) {
                    $retried++;
                } else {
                    $permanentFailed++;
                }
                $errors[] = ['id' => $id, 'kind' => $willRetry ? 'retry' : 'exhausted', 'reason' => $msg];
                log_message('error', '[EmailWorker] Delivery error #{id} (type={type}, attempt={attempt}/{max}): {reason}', [
                    'id' => $id,
                    'type' => $row['loai'],
                    'attempt' => $attempts + 1,
                    'max' => $maxAttempts,
                    'reason' => $msg,
                ]);
            }
        }

        return [
            'dryRun' => false,
            'reminders' => $reminders,
            'claimed' => count($claimed),
            'sent' => $sent,
            'retried' => $retried,
            'permanentFailed' => $permanentFailed,
            'errors' => $errors,
            'durationMs' => (int)round((microtime(true) - $startedAt) * 1000),
        ];
    }

    public static function stats(int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS): array
    {
        $summary = Store::one(
            "SELECT
                COUNT(*) AS totalQueued,
                SUM(CASE WHEN trang_thai_email = 'chua_gui' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN trang_thai_email = 'da_gui' THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN trang_thai_email = 'loi' AND so_lan_thu_email < ? AND email_thu_tiep_luc IS NOT NULL THEN 1 ELSE 0 END) AS retrying,
                SUM(CASE WHEN trang_thai_email = 'loi' AND (so_lan_thu_email >= ? OR email_thu_tiep_luc IS NULL) THEN 1 ELSE 0 END) AS failedPermanent
             FROM thong_bao
             WHERE co_gui_email = 1",
            [$maxAttempts, $maxAttempts]
        );
        $recentFailures = Store::rows(
            "SELECT tb.id, tb.loai, tb.tieu_de, tb.so_lan_thu_email, tb.trang_thai_email,
                    tb.loi_email, tb.email_thu_gan_nhat_luc, tb.email_thu_tiep_luc, tb.created_at
             FROM thong_bao tb
             WHERE tb.co_gui_email = 1 AND tb.trang_thai_email = 'loi'
             ORDER BY COALESCE(tb.email_thu_gan_nhat_luc, tb.created_at) DESC, tb.id DESC
             LIMIT 20"
        );
        return [
            'summary' => [
                'totalQueued' => (int)($summary['totalQueued'] ?? 0),
                'pending' => (int)($summary['pending'] ?? 0),
                'sent' => (int)($summary['sent'] ?? 0),
                'retrying' => (int)($summary['retrying'] ?? 0),
                'failedPermanent' => (int)($summary['failedPermanent'] ?? 0),
            ],
            'recentFailures' => array_map(static fn(array $r) => [
                'id' => (int)$r['id'],
                'loai' => $r['loai'],
                'tieuDe' => $r['tieu_de'],
                'soLanThuEmail' => (int)$r['so_lan_thu_email'],
                'trangThaiEmail' => $r['trang_thai_email'],
                'loiEmail' => $r['loi_email'],
                'thuGanNhatLuc' => $r['email_thu_gan_nhat_luc'],
                'thuTiepLuc' => $r['email_thu_tiep_luc'],
                'createdAt' => $r['created_at'],
            ], $recentFailures),
        ];
    }
}
