<?php
namespace App\Modules\Finance\Features\Tuition;

use App\Shared\{ApiException, Store};
use App\Modules\Administration\Features\Permissions\{PermissionsFilter, PermissionsRepository};

/**
 * SePay automatic bank-transfer webhook & VietQR payment reconciliation.
 * Only API-key authenticated SePay webhooks matching exact invoice code, bank account,
 * and remaining balance can automatically create a receipt.
 */
final class SePayPayments
{
    private const MIN_API_KEY_LENGTH = 16;

    private static function config(): array
    {
        $publicUrl = rtrim(getenv('SEPAY_PUBLIC_URL') ?: (getenv('APP_BASE_URL') ?: ''), '/');
        $adminId = (int)(getenv('SEPAY_RECONCILE_USER_ID') ?: (getenv('PAYOS_RECONCILE_USER_ID') ?: 0));
        return [
            'enabled' => filter_var(getenv('SEPAY_ENABLED') ?: 'false', FILTER_VALIDATE_BOOL),
            'apiKey' => trim(getenv('SEPAY_API_KEY') ?: ''),
            'url' => $publicUrl,
            'admin' => $adminId,
            'accountNo' => preg_replace('/\D+/', '', getenv('SEPAY_ACCOUNT_NO') ?: ''),
        ];
    }

    private static function operator(): ?array
    {
        return Store::one(
            "SELECT a.account_id AS accountId, a.user_id AS userId
             FROM account a
             JOIN users u ON u.user_id = a.user_id
             JOIN role_group g ON g.group_id = a.group_id
             WHERE a.user_id = ? AND a.status = 'active' AND a.is_deleted = 0
               AND u.is_deleted = 0 AND g.is_deleted = 0 AND g.group_name = 'Admin'",
            [self::config()['admin']]
        );
    }

    private static function validPublicUrl(string $url): bool
    {
        return $url !== ''
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && !parse_url($url, PHP_URL_QUERY)
            && !parse_url($url, PHP_URL_FRAGMENT)
            && !parse_url($url, PHP_URL_USER)
            && in_array(parse_url($url, PHP_URL_PATH), ['', null], true);
    }

    public static function status(): array
    {
        $c = self::config();
        $missing = [];
        if (strlen($c['apiKey']) < self::MIN_API_KEY_LENGTH) {
            $missing[] = 'SEPAY_API_KEY (tối thiểu 16 ký tự)';
        }
        if (!self::validPublicUrl($c['url'])) {
            $missing[] = 'Địa chỉ HTTPS công khai (SEPAY_PUBLIC_URL)';
        }
        if (!self::operator()) {
            $missing[] = 'Admin đối soát (SEPAY_RECONCILE_USER_ID)';
        }
        $configured = $c['enabled'] && $missing === [];
        return [
            'provider' => 'SePay',
            'enabled' => $c['enabled'],
            'configured' => $configured,
            'ready' => $configured,
            'missing' => $missing,
            'webhookUrl' => $c['url'] !== '' ? $c['url'] . '/api/finance/tuition/sepay/webhook' : null,
        ];
    }

    private static function invoice(int $id, array $actor, bool $lock = false): array
    {
        $row = Store::one(
            'SELECT h.* FROM hoa_don h WHERE h.id = ? AND fn_phu_huynh_hoa_don(h.id) = ?' . ($lock ? ' FOR UPDATE' : ''),
            [$id, $actor['userId']]
        );
        if (!$row) {
            throw new ApiException(404, 'Không tìm thấy hóa đơn của bạn.');
        }
        return $row;
    }

    private static function metadata(int $id, string $action): ?array
    {
        $gd = Store::one('SELECT metadata_json FROM giao_dich_truc_tuyen WHERE id = ?', [$id]);
        if ($gd && !empty($gd['metadata_json'])) {
            $decoded = json_decode((string)$gd['metadata_json'], true);
            if (is_array($decoded) && isset($decoded[$action]) && is_array($decoded[$action])) {
                return $decoded[$action];
            }
        }
        return null;
    }

    private static function saveMetadata(int $id, string $action, array $payload): void
    {
        $row = Store::one('SELECT metadata_json FROM giao_dich_truc_tuyen WHERE id = ?', [$id]);
        $existing = ($row && !empty($row['metadata_json'])) ? json_decode((string)$row['metadata_json'], true) : [];
        if (!is_array($existing)) {
            $existing = [];
        }
        $existing[$action] = $payload;
        Store::execute('UPDATE giao_dich_truc_tuyen SET metadata_json = ? WHERE id = ?', [
            json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $id,
        ]);
    }

    private static function normalizeDigits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string)$value) ?? '';
    }

    public static function checkout(int $invoice, array $actor): array
    {
        if (!self::status()['ready']) {
            throw new ApiException(503, 'Trung tâm chưa hoàn tất cấu hình SePay. Vui lòng liên hệ Admin.', 'SEPAY_NOT_READY');
        }
        $intent = Store::transaction(function () use ($invoice, $actor) {
            $h = self::invoice($invoice, $actor, true);
            $amount = (int)$h['phai_thu'] - (int)$h['da_thu'];
            if (!in_array($h['trang_thai'], ['chua_thanh_toan', 'mot_phan'], true) || $amount <= 0) {
                throw new ApiException(409, 'Hóa đơn chưa phát hành hoặc đã thanh toán đủ.');
            }
            $pending = Store::one(
                "SELECT * FROM giao_dich_truc_tuyen WHERE hoa_don_id = ? AND trang_thai IN ('khoi_tao','cho_xac_nhan') ORDER BY id DESC LIMIT 1 FOR UPDATE",
                [$invoice]
            );
            if ($pending) {
                if ($pending['nha_cung_cap'] !== 'SEPAY' || (int)$pending['so_tien'] !== $amount) {
                    throw new ApiException(409, 'Hóa đơn có giao dịch chờ đối soát. Vui lòng liên hệ Admin, không chuyển thêm tiền.');
                }
                if ($pending['trang_thai'] === 'khoi_tao') {
                    Store::execute("UPDATE giao_dich_truc_tuyen SET trang_thai = 'cho_xac_nhan' WHERE id = ?", [$pending['id']]);
                    $pending['trang_thai'] = 'cho_xac_nhan';
                }
                return $pending;
            }
            $quote = TuitionService::paymentQr($invoice, $actor);
            if ($quote['bank']['isDemo']) {
                throw new ApiException(409, 'Tài khoản minh họa không nhận tiền thật. Admin cần chọn tài khoản ngân hàng thật.');
            }
            $code = 'EDUSP' . random_int(10000000, 99999999);
            $meta = [
                'action' => 'sepay_intent',
                'code' => $code,
                'memo' => $code,
                'bank' => $quote['bank'],
            ];
            $id = Store::insert('giao_dich_truc_tuyen', [
                'ma_yeu_cau' => 'SEPAY-' . $code,
                'hoa_don_id' => $invoice,
                'phu_huynh_id' => $actor['userId'],
                'nha_cung_cap' => 'SEPAY',
                'so_tien' => $amount,
                'tien_te' => 'VND',
                'trang_thai' => 'khoi_tao',
                'metadata_json' => json_encode(['sepay_intent' => $meta], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
            Store::execute("UPDATE giao_dich_truc_tuyen SET trang_thai = 'cho_xac_nhan' WHERE id = ?", [$id]);
            Store::recordAudit((int)$actor['userId'], 'them', 'giao_dich_truc_tuyen', $id, null, $meta);
            return Store::one('SELECT * FROM giao_dich_truc_tuyen WHERE id = ?', [$id]);
        });

        $id = (int)$intent['id'];
        $meta = self::metadata($id, 'sepay_intent');
        if (!$meta || empty($meta['code']) || empty($meta['bank'])) {
            throw new ApiException(409, 'Yêu cầu thanh toán SePay chưa đủ thông tin đối soát. Vui lòng liên hệ Admin.');
        }
        $bank = $meta['bank'];
        $amount = (int)$intent['so_tien'];
        $memo = (string)$meta['code'];
        $payload = TuitionModel::vietQrPayload($bank['bankBin'], $bank['accountNo'], $amount, $memo);
        $qr = new \Endroid\QrCode\QrCode(
            data: $payload,
            encoding: new \Endroid\QrCode\Encoding\Encoding('ISO-8859-1'),
            size: 360,
            margin: 24
        );
        return [
            'transactionId' => $id,
            'invoiceId' => $invoice,
            'amount' => $amount,
            'memo' => $memo,
            'bank' => $bank,
            'qrDataUrl' => (new \Endroid\QrCode\Writer\SvgWriter())->write($qr)->getDataUri(),
            'checkoutUrl' => null,
            'mode' => 'sepay',
        ];
    }

    public static function paymentStatus(int $invoice, array $actor): array
    {
        $h = self::invoice($invoice, $actor);
        $intent = Store::one(
            "SELECT id, trang_thai FROM giao_dich_truc_tuyen WHERE hoa_don_id = ? AND nha_cung_cap = 'SEPAY' ORDER BY id DESC LIMIT 1",
            [$invoice]
        );
        $review = $intent ? self::metadata((int)$intent['id'], 'sepay_review') : null;
        return [
            'invoiceId' => $invoice,
            'paid' => $h['trang_thai'] === 'da_thanh_toan',
            'remaining' => max(0, (int)$h['phai_thu'] - (int)$h['da_thu']),
            'status' => $intent['trang_thai'] ?? null,
            'review' => $review['reason'] ?? null,
            'provider' => 'SEPAY',
        ];
    }

    public static function cancel(int $id, array $actor): array
    {
        $intent = Store::one("SELECT * FROM giao_dich_truc_tuyen WHERE id = ? AND nha_cung_cap = 'SEPAY'", [$id]);
        if (!$intent) {
            throw new ApiException(404, 'Không tìm thấy yêu cầu SePay.');
        }
        if (!in_array($intent['trang_thai'], ['khoi_tao', 'cho_xac_nhan'], true)) {
            throw new ApiException(409, 'Yêu cầu đã kết thúc; không thể hủy lại.');
        }
        return Store::transaction(function () use ($actor, $intent, $id) {
            PermissionsRepository::lockAdministrator($actor);
            Store::one('SELECT id FROM hoa_don WHERE id = ? FOR UPDATE', [$intent['hoa_don_id']]);
            $current = Store::one('SELECT * FROM giao_dich_truc_tuyen WHERE id = ? FOR UPDATE', [$id]);
            if (!in_array($current['trang_thai'], ['khoi_tao', 'cho_xac_nhan'], true)) {
                throw new ApiException(409, 'Trạng thái vừa thay đổi. Vui lòng tải lại.');
            }
            Store::execute("UPDATE giao_dich_truc_tuyen SET trang_thai = 'huy' WHERE id = ?", [$id]);
            Store::recordAudit((int)$actor['userId'], 'khac', 'giao_dich_truc_tuyen', $id, null, ['action' => 'sepay_cancelled_unpaid']);
            return ['cancelled' => true];
        });
    }

    private static function verifyApiKey(string $authHeader): void
    {
        $c = self::config();
        if (!$c['enabled'] || strlen($c['apiKey']) < self::MIN_API_KEY_LENGTH) {
            throw new ApiException(503, 'SePay webhook chưa được kích hoạt trên máy chủ.', 'SEPAY_NOT_READY');
        }
        $header = trim($authHeader);
        if ($header === '') {
            $header = trim((string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        }
        if (!preg_match('/^Apikey\s+(\S+)$/iD', $header, $match) || !hash_equals($c['apiKey'], $match[1])) {
            throw new ApiException(401, 'Xác thực API Key SePay không hợp lệ.', 'INVALID_SEPAY_API_KEY');
        }
    }

    public static function webhook(array $body, string $authHeader = ''): array
    {
        self::verifyApiKey($authHeader);

        // Ignore outgoing transfers cleanly with HTTP 200 so SePay does not retry.
        if (strtolower(trim((string)($body['transferType'] ?? ''))) !== 'in') {
            return ['received' => true, 'ignored' => true, 'reason' => 'not_incoming'];
        }

        $sepayIdRaw = $body['id'] ?? null;
        if ((!is_int($sepayIdRaw) && !is_string($sepayIdRaw)) || trim((string)$sepayIdRaw) === '' || !preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', trim((string)$sepayIdRaw))) {
            throw new ApiException(400, 'Thiếu mã giao dịch SePay hợp lệ.', 'INVALID_SEPAY_PAYLOAD');
        }
        $sepayId = trim((string)$sepayIdRaw);

        $transferAmountRaw = $body['transferAmount'] ?? null;
        if (!is_numeric($transferAmountRaw) || (float)$transferAmountRaw <= 0) {
            return ['received' => true, 'ignored' => true, 'reason' => 'invalid_amount'];
        }
        $transferAmount = (int)round((float)$transferAmountRaw);

        $haystack = strtoupper(trim(
            (string)($body['code'] ?? '') . ' ' .
            (string)($body['content'] ?? '') . ' ' .
            (string)($body['description'] ?? '')
        ));
        if (!preg_match('/EDUSP\d{8}/', $haystack, $codeMatch)) {
            // Normal personal/unrelated bank transfer on the same account — acknowledge with 200 and ignore.
            return ['received' => true, 'ignored' => true, 'reason' => 'no_payment_code'];
        }
        $paymentCode = $codeMatch[0];

        $intent = Store::one(
            "SELECT * FROM giao_dich_truc_tuyen WHERE nha_cung_cap = 'SEPAY' AND ma_yeu_cau = ?",
            ['SEPAY-' . $paymentCode]
        );
        if (!$intent) {
            return ['received' => true, 'ignored' => true, 'reason' => 'unknown_payment_code'];
        }

        $id = (int)$intent['id'];
        $meta = self::metadata($id, 'sepay_intent');
        $incomingAccount = self::normalizeDigits((string)($body['accountNumber'] ?? ''));
        $incomingSubAccount = self::normalizeDigits((string)($body['subAccount'] ?? ''));
        $referenceCode = trim((string)($body['referenceCode'] ?? ''));
        $gateway = trim((string)($body['gateway'] ?? 'SePay'));
        $configAccount = self::config()['accountNo'];

        $reason = null;
        $expectedAccount = self::normalizeDigits((string)($meta['bank']['accountNo'] ?? ''));
        if ($configAccount !== '' && $incomingAccount !== $configAccount && $incomingSubAccount !== $configAccount) {
            $reason = 'Số tài khoản nhận SePay không khớp cấu hình máy chủ. Admin cần đối soát.';
        } elseif ($expectedAccount !== '' && $incomingAccount !== $expectedAccount && $incomingSubAccount !== $expectedAccount) {
            $reason = 'Số tài khoản nhận từ SePay chưa khớp tài khoản mặc định của hóa đơn. Admin cần đối soát.';
        } elseif ($transferAmount !== (int)$intent['so_tien']) {
            $reason = 'Số tiền chuyển qua SePay (' . number_format($transferAmount) . ' đ) không khớp số phải thu (' . number_format((int)$intent['so_tien']) . ' đ). Admin cần đối soát.';
        }

        return Store::transaction(function () use ($id, $intent, $paymentCode, $sepayId, $transferAmount, $referenceCode, $gateway, $body, $reason) {
            $operator = self::operator();
            if (!$operator) {
                throw new ApiException(503, 'Admin đối soát SePay chưa được cấu hình hoặc không còn hoạt động.');
            }
            PermissionsRepository::lockAdministrator($operator);
            $h = Store::one('SELECT * FROM hoa_don WHERE id = ? FOR UPDATE', [$intent['hoa_don_id']]);
            $current = Store::one('SELECT * FROM giao_dich_truc_tuyen WHERE id = ? FOR UPDATE', [$id]);
            if ($current['trang_thai'] === 'thanh_cong') {
                return ['received' => true, 'paid' => true, 'duplicate' => true];
            }

            $amount = (int)$current['so_tien'];
            if (!$reason && (
                $current['trang_thai'] !== 'cho_xac_nhan'
                || !in_array($h['trang_thai'], ['chua_thanh_toan', 'mot_phan'], true)
                || (int)$h['phai_thu'] - (int)$h['da_thu'] !== $amount
            )) {
                $reason = 'Công nợ đã thay đổi hoặc giao dịch không còn ở trạng thái chờ đối soát. Admin cần kiểm tra khoản tiền vào.';
            }

            $hash = hash('sha256', json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $eventKey = $reason ? 'review:' . $id . ':' . $sepayId . ':' . substr($hash, 0, 16) : 'sepay:' . $sepayId;

            Store::execute(
                "INSERT IGNORE INTO su_kien_thanh_toan(giao_dich_id, nha_cung_cap, ma_su_kien, payload_sha256) VALUES (?, 'SEPAY', ?, ?)",
                [$id, $eventKey, $hash]
            );
            $event = Store::one(
                "SELECT * FROM su_kien_thanh_toan WHERE nha_cung_cap = 'SEPAY' AND ma_su_kien = ? FOR UPDATE",
                [$eventKey]
            );
            if ((int)$event['giao_dich_id'] !== $id) {
                $reason = 'Mã giao dịch SePay đã gắn với hóa đơn khác. Admin cần đối soát.';
            }

            if ($reason) {
                $reviewMeta = [
                    'action' => 'sepay_review',
                    'reason' => $reason,
                    'sepayId' => $sepayId,
                    'transferAmount' => $transferAmount,
                    'referenceCode' => $referenceCode,
                ];
                if ((int)$event['giao_dich_id'] === $id && $event['trang_thai'] === 'cho_xu_ly') {
                    Store::execute(
                        "UPDATE su_kien_thanh_toan SET trang_thai = 'loi', loi = ?, xu_ly_luc = NOW() WHERE id = ?",
                        [mb_substr($reason, 0, 255), $event['id']]
                    );
                }
                if (in_array($current['trang_thai'], ['khoi_tao', 'cho_xac_nhan'], true)) {
                    self::saveMetadata($id, 'sepay_review', $reviewMeta);
                }
                Store::recordAudit((int)$operator['userId'], 'khac', 'giao_dich_truc_tuyen', $id, null, $reviewMeta);
                return ['received' => true, 'paid' => false, 'review' => $reason];
            }

            if ($event['trang_thai'] === 'da_xu_ly') {
                return ['received' => true, 'paid' => true, 'duplicate' => true];
            }

            $refSuffix = $referenceCode !== '' ? ' · ' . mb_substr($referenceCode, 0, 40) : '';
            $receipt = Store::insert('thanh_toan', [
                'hoa_don_id' => $h['id'],
                'loai' => 'thu',
                'so_tien' => $amount,
                'phuong_thuc' => 'chuyen_khoan',
                'ma_giao_dich' => 'SEPAY-' . substr(hash('sha256', $sepayId . ':' . $referenceCode), 0, 48),
                'nguoi_thu_id' => $operator['userId'],
                'ghi_chu' => mb_substr('SePay đối soát tự động · ' . $paymentCode . $refSuffix, 0, 255),
            ]);
            Store::execute(
                "UPDATE giao_dich_truc_tuyen SET trang_thai = 'thanh_cong', ma_giao_dich_cong = ?, thanh_toan_id = ?, xac_nhan_boi = ?, xac_nhan_luc = NOW() WHERE id = ?",
                ['SEPAY-' . $sepayId, $receipt, $operator['userId'], $id]
            );
            Store::execute(
                "UPDATE su_kien_thanh_toan SET trang_thai = 'da_xu_ly', loi = NULL, xu_ly_luc = NOW() WHERE id = ?",
                [$event['id']]
            );
            Store::recordAudit((int)$operator['userId'], 'khac', 'giao_dich_truc_tuyen', $id, null, [
                'action' => 'sepay_verified',
                'receiptId' => $receipt,
                'sepayId' => $sepayId,
                'gateway' => $gateway,
                'referenceCode' => $referenceCode,
                'amount' => $amount,
                'code' => $paymentCode,
            ]);
            return ['received' => true, 'paid' => true, 'receiptId' => $receipt];
        });
    }
}
