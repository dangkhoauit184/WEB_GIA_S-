<?php
namespace App\Modules\Finance\Features\Tuition;

use App\Shared\{ApiException,Store};
use App\Modules\Administration\Features\Permissions\{PermissionsFilter,PermissionsRepository};

/** Gateway calls are outside DB transactions. Only signed provider evidence can create a receipt. */
final class PayOsPayments
{
    private static ?\PayOS\PayOS $client=null;

    private static function config(): array
    {
        return ['enabled'=>filter_var(getenv('PAYOS_ENABLED')?:'false',FILTER_VALIDATE_BOOL),
            'client'=>getenv('PAYOS_CLIENT_ID')?:'', 'key'=>getenv('PAYOS_API_KEY')?:'',
            'checksum'=>getenv('PAYOS_CHECKSUM_KEY')?:'', 'url'=>rtrim(getenv('PAYOS_PUBLIC_URL')?:'','/'),
            'admin'=>(int)(getenv('PAYOS_RECONCILE_USER_ID')?:0)];
    }
    private static function fingerprint(array $c): string
    {
        return hash('sha256',json_encode($c,JSON_THROW_ON_ERROR));
    }
    private static function operator(): ?array
    {
        return Store::one("SELECT a.account_id AS accountId,a.user_id AS userId FROM account a JOIN users u ON u.user_id=a.user_id
            JOIN role_group g ON g.group_id=a.group_id WHERE a.user_id=? AND a.status='active' AND a.is_deleted=0
            AND u.is_deleted=0 AND g.is_deleted=0 AND g.group_name='Admin'",[self::config()['admin']]);
    }
    public static function status(): array
    {
        $c=self::config(); $missing=[];
        foreach(['client'=>'Client ID','key'=>'API Key','checksum'=>'Checksum Key'] as $k=>$label) if (!$c[$k]) $missing[]=$label;
        if (!filter_var($c['url'],FILTER_VALIDATE_URL) || parse_url($c['url'],PHP_URL_SCHEME)!=='https' || parse_url($c['url'],PHP_URL_QUERY)
            || parse_url($c['url'],PHP_URL_FRAGMENT) || parse_url($c['url'],PHP_URL_USER) || !in_array(parse_url($c['url'],PHP_URL_PATH),['',null],true)) $missing[]='Địa chỉ HTTPS công khai (chỉ tên miền)';
        if (!self::operator()) $missing[]='Admin được giao đối soát';
        $registered=Store::one('SELECT gia_tri FROM cau_hinh WHERE khoa=?',['payment.payos.webhook'])['gia_tri']??'';
        $confirmed=hash_equals(self::fingerprint($c),$registered);
        return ['provider'=>'payOS','enabled'=>$c['enabled'],'configured'=>$c['enabled']&&!$missing,'webhookConfirmed'=>$confirmed,
            'ready'=>$c['enabled']&&!$missing&&$confirmed,'missing'=>$missing,
            'webhookUrl'=>$c['url']?$c['url'].'/api/finance/tuition/payos/webhook':null];
    }
    private static function client(): \PayOS\PayOS
    {
        $c=self::config();
        if (!$c['enabled'] || !$c['client'] || !$c['key'] || !$c['checksum']) throw new ApiException(503,'Trung tâm chưa bật kết nối payOS. Vui lòng liên hệ Admin.','PAYOS_NOT_READY');
        return self::$client??=new \PayOS\PayOS(clientId:$c['client'],apiKey:$c['key'],checksumKey:$c['checksum'],baseURL:'https://api-merchant.payos.vn',maxRetries:0,
            httpClient:new \PayOS\Core\HTTPClient(new \GuzzleHttp\Client(['timeout'=>10,'connect_timeout'=>3,'allow_redirects'=>false])));
    }
    private static function call(callable $work): array
    {
        try { return $work(self::client()); }
        catch(ApiException $e) { throw $e; }
        catch(\Throwable) { throw new ApiException(503,'Chưa xác minh được với payOS. Trạng thái học phí được giữ nguyên; hãy kiểm tra lại sau.','PAYOS_UNAVAILABLE'); }
    }
    public static function connect(): array
    {
        $actor=PermissionsFilter::administer(); $status=self::status();
        if (!$status['configured']) throw new ApiException(409,'Chưa đủ cấu hình payOS: '.implode(', ',$status['missing']).'.');
        self::call(fn($client)=>$client->webhooks->confirm($status['webhookUrl'],['asArray'=>true]));
        Store::transaction(function() use($actor) {
            PermissionsRepository::lockAdministrator($actor);
            Store::execute('INSERT INTO cau_hinh(khoa,gia_tri,mo_ta) VALUES (?,?,?) ON DUPLICATE KEY UPDATE gia_tri=VALUES(gia_tri)',
                ['payment.payos.webhook',self::fingerprint(self::config()),'payOS đã kiểm tra webhook; dấu cấu hình, không chứa khóa']);
            Store::recordAudit((int)$actor['userId'],'khac','cau_hinh',null,null,['action'=>'payos_webhook_confirmed']);
        });
        return self::status();
    }
    private static function invoice(int $id,array $actor,bool $lock=false): array
    {
        $row=Store::one('SELECT h.* FROM hoa_don h WHERE h.id=? AND fn_phu_huynh_hoa_don(h.id)=?'.($lock?' FOR UPDATE':''),[$id,$actor['userId']]);
        if (!$row) throw new ApiException(404,'Không tìm thấy hóa đơn của bạn.');
        return $row;
    }
    private static function metadata(int $id, string $action): ?array
    {
        $gd = Store::one('SELECT metadata_json FROM giao_dich_truc_tuyen WHERE id=?', [$id]);
        if ($gd && !empty($gd['metadata_json'])) {
            $decoded = json_decode((string)$gd['metadata_json'], true);
            if (is_array($decoded) && isset($decoded[$action]) && is_array($decoded[$action])) {
                return $decoded[$action];
            }
        }
        // Fallback for legacy pre-008 records only.
        $row = Store::one("SELECT du_lieu_moi FROM audit_log WHERE bang='giao_dich_truc_tuyen' AND ban_ghi_id=?
            AND JSON_UNQUOTE(JSON_EXTRACT(du_lieu_moi,'$.action'))=? ORDER BY id DESC LIMIT 1", [$id, $action]);
        return $row ? json_decode($row['du_lieu_moi'], true) : null;
    }

    private static function saveMetadata(int $id, string $action, array $payload): void
    {
        $row = Store::one('SELECT metadata_json FROM giao_dich_truc_tuyen WHERE id=?', [$id]);
        $existing = ($row && !empty($row['metadata_json'])) ? json_decode((string)$row['metadata_json'], true) : [];
        if (!is_array($existing)) {
            $existing = [];
        }
        $existing[$action] = $payload;
        Store::execute('UPDATE giao_dich_truc_tuyen SET metadata_json=? WHERE id=?', [
            json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $id,
        ]);
    }
    public static function checkout(int $invoice,array $actor): array
    {
        if (!self::status()['ready']) throw new ApiException(503,'Trung tâm chưa hoàn tất kết nối payOS. Vui lòng liên hệ Admin.','PAYOS_NOT_READY');
        $intent=Store::transaction(function() use($invoice,$actor) {
            $h=self::invoice($invoice,$actor,true); $amount=(int)$h['phai_thu']-(int)$h['da_thu'];
            if (!in_array($h['trang_thai'],['chua_thanh_toan','mot_phan'],true) || $amount<=0) throw new ApiException(409,'Hóa đơn chưa phát hành hoặc đã thanh toán đủ.');
            $pending=Store::one("SELECT * FROM giao_dich_truc_tuyen WHERE hoa_don_id=? AND trang_thai IN ('khoi_tao','cho_xac_nhan') ORDER BY id DESC LIMIT 1 FOR UPDATE",[$invoice]);
            if ($pending) {
                if ($pending['nha_cung_cap']!=='PAYOS' || (int)$pending['so_tien']!==$amount) throw new ApiException(409,'Hóa đơn có giao dịch chờ đối soát. Vui lòng liên hệ Admin, không chuyển thêm tiền.');
                return $pending;
            }
            $quote=TuitionService::paymentQr($invoice,$actor);
            if ($quote['bank']['isDemo']) throw new ApiException(409,'Tài khoản minh họa không nhận tiền thật. Admin cần chọn tài khoản ngân hàng thật.');
            $order=random_int(1000000000000,9999999999999);
            $reservedMeta=['action'=>'payos_reserved','bank'=>$quote['bank'],'memo'=>'EDU'.$order,'orderCode'=>$order];
            $id=Store::insert('giao_dich_truc_tuyen',['ma_yeu_cau'=>'PAYOS-'.$order,'hoa_don_id'=>$invoice,'phu_huynh_id'=>$actor['userId'],'nha_cung_cap'=>'PAYOS','so_tien'=>$amount,'metadata_json'=>json_encode(['payos_reserved'=>$reservedMeta],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            Store::recordAudit((int)$actor['userId'],'them','giao_dich_truc_tuyen',$id,null,$reservedMeta);
            return Store::one('SELECT * FROM giao_dich_truc_tuyen WHERE id=?',[$id]);
        });
        $id=(int)$intent['id']; $meta=self::metadata($id,'payos_link');
        if (!$meta) {
            $reserved=self::metadata($id,'payos_reserved');
            if (!$reserved || !isset($reserved['orderCode'],$reserved['bank'],$reserved['memo'])) {
                throw new ApiException(409,'Yêu cầu cũ chưa có thông tin đối soát payOS. Vui lòng liên hệ Admin kiểm tra trước khi chuyển tiền.');
            }
            $order=$reserved['orderCode']; $c=self::config();
            // The same order code is used after timeouts; payOS rejects duplicate creates safely.
            $link=self::call(fn($client)=>$client->paymentRequests->create(['orderCode'=>$order,'amount'=>(int)$intent['so_tien'],
                'description'=>$reserved['memo'],'returnUrl'=>$c['url'].'/app',
                'cancelUrl'=>$c['url'].'/app'],['asArray'=>true]));
            $qr=TuitionModel::readVietQr($link['qrCode']??'');
            if (($link['orderCode']??null)!==$order || ($link['amount']??null)!==(int)$intent['so_tien'] || ($link['currency']??'')!=='VND'
                || ($link['bin']??'')!==$reserved['bank']['bankBin'] || ($link['accountNumber']??'')!==$reserved['bank']['accountNo']
                || $qr['bankBin']!==$reserved['bank']['bankBin'] || $qr['accountNo']!==$reserved['bank']['accountNo']
                || (int)$qr['amount']!==(int)$intent['so_tien'] || $qr['memo']!==($link['description']??null)
                || !str_contains($link['description']??'',$reserved['memo']) || !preg_match('/^[a-zA-Z0-9_-]{1,120}$/D',$link['paymentLinkId']??'')
                || !is_string($link['accountName']??null) || trim($link['accountName'])===''
                || parse_url($link['checkoutUrl']??'',PHP_URL_SCHEME)!=='https' || parse_url($link['checkoutUrl']??'',PHP_URL_HOST)!=='pay.payos.vn') {
                throw new ApiException(409,'QR payOS không khớp tài khoản mặc định hoặc hóa đơn. Không chuyển tiền; hãy liên hệ Admin.','PAYOS_ACCOUNT_MISMATCH');
            }
            $meta=$reserved+['paymentLinkId'=>$link['paymentLinkId'],'checkoutUrl'=>$link['checkoutUrl'],'qrCode'=>$link['qrCode'],'description'=>$link['description']];
            $meta['action']='payos_link';
            $meta['bank']['accountName']=$link['accountName'];
            Store::transaction(function() use($id,$intent,$actor,$meta) {
                Store::one('SELECT id FROM hoa_don WHERE id=? FOR UPDATE',[$intent['hoa_don_id']]);
                $current=Store::one('SELECT * FROM giao_dich_truc_tuyen WHERE id=? FOR UPDATE',[$id]);
                if ($current['trang_thai']==='khoi_tao') {
                    Store::execute("UPDATE giao_dich_truc_tuyen SET trang_thai='cho_xac_nhan',ma_giao_dich_cong=? WHERE id=?",[$meta['paymentLinkId'],$id]);
                    self::saveMetadata($id,'payos_link',$meta);
                    Store::recordAudit((int)$actor['userId'],'khac','giao_dich_truc_tuyen',$id,null,$meta);
                }
            });
        }
        $current=Store::one('SELECT trang_thai FROM giao_dich_truc_tuyen WHERE id=?',[$id]);
        $h=self::invoice($invoice,$actor);
        if ($current['trang_thai']!=='cho_xac_nhan' || !in_array($h['trang_thai'],['chua_thanh_toan','mot_phan'],true)
            || (int)$h['phai_thu']-(int)$h['da_thu']!==(int)$intent['so_tien']) {
            throw new ApiException(409,'Yêu cầu vừa được hủy hoặc công nợ đã thay đổi. Vui lòng mở lại hóa đơn, không dùng mã QR cũ.');
        }
        $qr=new \Endroid\QrCode\QrCode(data:$meta['qrCode'],encoding:new \Endroid\QrCode\Encoding\Encoding('ISO-8859-1'),size:360,margin:24);
        return ['transactionId'=>$id,'invoiceId'=>$invoice,'amount'=>(int)$intent['so_tien'],'memo'=>$meta['description'],'bank'=>$meta['bank'],
            'qrDataUrl'=>(new \Endroid\QrCode\Writer\SvgWriter())->write($qr)->getDataUri(),'checkoutUrl'=>$meta['checkoutUrl'],'mode'=>'payos'];
    }
    public static function paymentStatus(int $invoice,array $actor): array
    {
        $h=self::invoice($invoice,$actor);
        $intent=Store::one("SELECT id,trang_thai FROM giao_dich_truc_tuyen WHERE hoa_don_id=? AND nha_cung_cap='PAYOS' ORDER BY id DESC LIMIT 1",[$invoice]);
        $review=$intent?self::metadata((int)$intent['id'],'payos_review'):null;
        return ['invoiceId'=>$invoice,'paid'=>$h['trang_thai']==='da_thanh_toan','remaining'=>max(0,(int)$h['phai_thu']-(int)$h['da_thu']),
            'status'=>$intent['trang_thai']??null,'review'=>$review['reason']??null];
    }
    public static function checkInvoice(int $invoice,array $actor): array
    {
        self::invoice($invoice,$actor);
        $intent=Store::one("SELECT id FROM giao_dich_truc_tuyen WHERE hoa_don_id=? AND nha_cung_cap='PAYOS' ORDER BY id DESC LIMIT 1",[$invoice]);
        if (!$intent) throw new ApiException(404,'Hóa đơn chưa có yêu cầu payOS.');
        self::reconcile((int)$intent['id']);
        return self::paymentStatus($invoice,$actor);
    }
    public static function cancel(int $id,array $actor): array
    {
        $intent=Store::one("SELECT * FROM giao_dich_truc_tuyen WHERE id=? AND nha_cung_cap='PAYOS'",[$id]);
        if (!$intent) throw new ApiException(404,'Không tìm thấy yêu cầu payOS.');
        if (!in_array($intent['trang_thai'],['khoi_tao','cho_xac_nhan'],true)) throw new ApiException(409,'Yêu cầu đã kết thúc; không thể hủy lại.');
        $order=(int)substr($intent['ma_yeu_cau'],6);
        $link=self::call(fn($client)=>$client->paymentRequests->get($order,['asArray'=>true]));
        $unpaid=static fn(array $r): bool=>($r['orderCode']??null)===$order && ($r['amount']??null)===(int)$intent['so_tien']
            && ($r['amountPaid']??null)===0 && empty($r['transactions']) && !empty($r['id'])
            && (!$intent['ma_giao_dich_cong'] || $r['id']===$intent['ma_giao_dich_cong']);
        if (!$unpaid($link)) throw new ApiException(409,'Yêu cầu đã nhận tiền hoặc thông tin không khớp. Hãy kiểm tra payOS và đối soát trước khi xử lý.');
        if (!in_array($link['status']??'',['CANCELLED','EXPIRED'],true)) {
            $link=self::call(fn($client)=>$client->paymentRequests->cancel($order,'Admin hủy yêu cầu chưa nhận tiền',['asArray'=>true]));
        }
        if (!$unpaid($link) || !in_array($link['status']??'',['CANCELLED','EXPIRED'],true)) throw new ApiException(409,'payOS chưa xác nhận hủy hoặc yêu cầu vừa nhận tiền. Không thay đổi công nợ.');
        return Store::transaction(function() use($actor,$intent,$id,$link) {
            PermissionsRepository::lockAdministrator($actor);
            Store::one('SELECT id FROM hoa_don WHERE id=? FOR UPDATE',[$intent['hoa_don_id']]);
            $current=Store::one('SELECT * FROM giao_dich_truc_tuyen WHERE id=? FOR UPDATE',[$id]);
            if (!in_array($current['trang_thai'],['khoi_tao','cho_xac_nhan'],true)) throw new ApiException(409,'Trạng thái vừa thay đổi. Vui lòng tải lại.');
            Store::execute("UPDATE giao_dich_truc_tuyen SET trang_thai='huy' WHERE id=?",[$id]);
            Store::recordAudit((int)$actor['userId'],'khac','giao_dich_truc_tuyen',$id,null,['action'=>'payos_cancelled_unpaid','paymentLinkId'=>$link['id']]);
            return ['cancelled'=>true];
        });
    }
    public static function webhook(array $body): array
    {
        try { $data=self::client()->webhooks->verify($body,['asArray'=>true]); }
        catch(ApiException $e) { throw $e; }
        catch(\Throwable) { throw new ApiException(400,'Chữ ký payOS không hợp lệ.','INVALID_PAYMENT_SIGNATURE'); }
        if (($body['success']??false)!==true || ($data['code']??'')!=='00') return ['received'=>true,'ignored'=>true];
        $intent=Store::one("SELECT id FROM giao_dich_truc_tuyen WHERE nha_cung_cap='PAYOS' AND ma_yeu_cau=?",['PAYOS-'.($data['orderCode']??'')]);
        // payOS sends a signed sample when verifying the endpoint. Never create invoices from a webhook.
        if (!$intent) return ['received'=>true,'ignored'=>true];
        self::reconcile((int)$intent['id'],$data);
        return ['received'=>true];
    }
    public static function reconcile(int $id,?array $webhook=null): array
    {
        $intent=Store::one("SELECT * FROM giao_dich_truc_tuyen WHERE id=? AND nha_cung_cap='PAYOS'",[$id]);
        if (!$intent) throw new ApiException(404,'Không tìm thấy giao dịch payOS.');
        if ($intent['trang_thai']==='thanh_cong') return ['paid'=>true];
        $meta=self::metadata($id,'payos_link');
        if (!$meta) throw new ApiException(503,'Yêu cầu thanh toán đang được tạo; vui lòng kiểm tra lại sau.');
        $link=self::call(fn($client)=>$client->paymentRequests->get($meta['orderCode'],['asArray'=>true]));
        $amount=(int)$intent['so_tien']; $reason=null; $transactions=$link['transactions']??[];
        if (($link['id']??'')!==$meta['paymentLinkId'] || ($link['orderCode']??null)!==$meta['orderCode'] || ($link['amount']??null)!==$amount) $reason='Thông tin payOS không khớp yêu cầu gốc.';
        elseif (($link['status']??'')!=='PAID') {
            if (($link['amountPaid']??0)>0) $reason='Đã nhận khoản chuyển chưa đủ hoặc chưa khớp học phí. Admin cần đối soát.';
            elseif (in_array($link['status']??'',['CANCELLED','EXPIRED'],true)) $reason='Yêu cầu payOS đã hủy hoặc hết hạn. Liên hệ Admin trước khi chuyển tiền.';
            else return ['paid'=>false,'pending'=>true];
        }
        elseif (($link['amountPaid']??null)!==$amount || ($link['amountRemaining']??null)!==0 || count($transactions)!==1) $reason='Số tiền hoặc số giao dịch chưa khớp. Admin cần đối soát khoản thiếu, thừa hoặc nhiều lần chuyển.';
        $tx=$transactions[0]??[];
        if (!$reason && (($tx['amount']??null)!==$amount || ($tx['accountNumber']??'')!==$meta['bank']['accountNo']
            || ($tx['description']??'')!==$meta['description'] || !is_string($tx['reference']??null) || !strlen($tx['reference']) || strlen($tx['reference'])>120)) $reason='Tài khoản, nội dung hoặc mã ngân hàng chưa khớp. Admin cần đối soát.';
        if (!$reason && $webhook && (($webhook['paymentLinkId']??'')!==$meta['paymentLinkId'] || ($webhook['orderCode']??null)!==$meta['orderCode']
            || ($webhook['amount']??null)!==$amount || ($webhook['currency']??'')!=='VND' || ($webhook['reference']??'')!==$tx['reference']
            || ($webhook['accountNumber']??'')!==$meta['bank']['accountNo'] || ($webhook['description']??'')!==$tx['description'])) $reason='Thông báo ngân hàng chưa khớp giao dịch payOS đã xác thực.';
        return Store::transaction(function() use($id,$intent,$link,$tx,$meta,$amount,$reason,$webhook) {
            $operator=self::operator();
            if (!$operator) throw new ApiException(503,'Admin đối soát chưa được cấu hình hoặc không còn hoạt động.');
            PermissionsRepository::lockAdministrator($operator);
            $h=Store::one('SELECT * FROM hoa_don WHERE id=? FOR UPDATE',[$intent['hoa_don_id']]);
            $current=Store::one('SELECT * FROM giao_dich_truc_tuyen WHERE id=? FOR UPDATE',[$id]);
            if ($current['trang_thai']==='thanh_cong') return ['paid'=>true];
            if (!$reason && ($current['trang_thai']!=='cho_xac_nhan' || !in_array($h['trang_thai'],['chua_thanh_toan','mot_phan'],true)
                || (int)$h['phai_thu']-(int)$h['da_thu']!==$amount)) $reason='Công nợ đã thay đổi hoặc đã được thu bằng cách khác. Admin cần kiểm tra khoản tiền vào.';
            $hash=hash('sha256',json_encode($webhook??$link,JSON_THROW_ON_ERROR));
            $eventKey=$reason?'review:'.$id.':'.$hash:'bank:'.hash('sha256',$tx['reference']);
            Store::execute('INSERT IGNORE INTO su_kien_thanh_toan(giao_dich_id,nha_cung_cap,ma_su_kien,payload_sha256) VALUES (?,\'PAYOS\',?,?)',[$id,$eventKey,$hash]);
            $event=Store::one("SELECT * FROM su_kien_thanh_toan WHERE nha_cung_cap='PAYOS' AND ma_su_kien=? FOR UPDATE",[$eventKey]);
            if ((int)$event['giao_dich_id']!==$id) $reason='Mã giao dịch ngân hàng đã gắn với hóa đơn khác. Admin cần đối soát.';
            if ($reason) {
                $reviewMeta=['action'=>'payos_review','reason'=>$reason];
                if ((int)$event['giao_dich_id']===$id && $event['trang_thai']==='cho_xu_ly') {
                    Store::execute("UPDATE su_kien_thanh_toan SET trang_thai='loi',loi=?,xu_ly_luc=NOW() WHERE id=?",[$reason,$event['id']]);
                    if (in_array($current['trang_thai'],['khoi_tao','cho_xac_nhan'],true)) self::saveMetadata($id,'payos_review',$reviewMeta);
                    Store::recordAudit((int)$operator['userId'],'khac','giao_dich_truc_tuyen',$id,null,$reviewMeta);
                }
                if ((int)$event['giao_dich_id']!==$id && (self::metadata($id,'payos_review')['reason']??null)!==$reason) {
                    if (in_array($current['trang_thai'],['khoi_tao','cho_xac_nhan'],true)) self::saveMetadata($id,'payos_review',$reviewMeta);
                    Store::recordAudit((int)$operator['userId'],'khac','giao_dich_truc_tuyen',$id,null,$reviewMeta);
                }
                return ['paid'=>false,'review'=>$reason];
            }
            if ($event['trang_thai']==='da_xu_ly') return ['paid'=>true];
            $receipt=Store::insert('thanh_toan',['hoa_don_id'=>$h['id'],'loai'=>'thu','so_tien'=>$amount,'phuong_thuc'=>'chuyen_khoan',
                'ma_giao_dich'=>'PAYOS-'.substr(hash('sha256',$tx['reference']),0,48),'nguoi_thu_id'=>$operator['userId'],'ghi_chu'=>'payOS đối soát tự động · '.$meta['orderCode']]);
            Store::execute("UPDATE giao_dich_truc_tuyen SET trang_thai='thanh_cong',thanh_toan_id=?,xac_nhan_boi=?,xac_nhan_luc=NOW() WHERE id=?",[$receipt,$operator['userId'],$id]);
            Store::execute("UPDATE su_kien_thanh_toan SET trang_thai='da_xu_ly',loi=NULL,xu_ly_luc=NOW() WHERE id=?",[$event['id']]);
            Store::recordAudit((int)$operator['userId'],'khac','giao_dich_truc_tuyen',$id,null,['action'=>'payos_verified','receiptId'=>$receipt,'reference'=>$tx['reference'],'amount'=>$amount,'source'=>$webhook?'signed_webhook_and_api':'signed_api']);
            return ['paid'=>true,'receiptId'=>$receipt];
        });
    }
}
