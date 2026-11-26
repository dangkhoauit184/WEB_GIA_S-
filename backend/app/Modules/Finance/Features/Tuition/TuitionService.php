<?php
namespace App\Modules\Finance\Features\Tuition;

use App\Modules\Administration\Features\Permissions\PermissionsFilter;
use App\Shared\{ApiException, ApiModel, Input, Store};

final class TuitionService
{
    private const BANK_DEFAULT = 'payment.bank.default';
    public static function banks(): array
    {
        PermissionsFilter::administer();
        $default=Store::one('SELECT gia_tri FROM cau_hinh WHERE khoa=?',[self::BANK_DEFAULT])['gia_tri']??'';
        $rows=[];
        foreach(Store::rows("SELECT khoa,gia_tri FROM cau_hinh WHERE khoa LIKE 'payment.bank.%' AND khoa<>? ORDER BY khoa",[self::BANK_DEFAULT]) as $row) {
            $value=json_decode($row['gia_tri'],true);
            if (is_array($value)) $rows[]=self::mapBank(substr($row['khoa'],13),$value,$default);
        }
        return $rows;
    }
    private static function mapBank(string $id,array $row,string $default): array
    {
        return ['id'=>$id,'bankBin'=>$row['bin'],'bankName'=>$row['bank'],'accountNo'=>$row['number'],'accountName'=>$row['holder'],
            'active'=>(bool)$row['active'],'isDemo'=>(bool)$row['demo'],'isDefault'=>$id===$default,
            'qrImageHash'=>Store::one('SELECT gia_tri FROM cau_hinh WHERE khoa=?',['payment.qr.'.$id])['gia_tri']??null];
    }
    public static function saveBank(array $body,?string $id=null): array
    {
        $actor=PermissionsFilter::administer(); $data=TuitionValidator::bank($body);
        $id ??= bin2hex(random_bytes(8));
        if ($id==='default' || !preg_match('/^[a-zA-Z0-9_-]{1,20}$/D',$id)) throw new ApiException(400,'Mã tài khoản không hợp lệ.');
        $encoded=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        if (mb_strlen($encoded)>255) throw new ApiException(400,'Thông tin tài khoản quá dài. Vui lòng rút gọn tên.');
        return Store::transaction(function() use($actor,$id,$data,$encoded,$body) {
            \App\Modules\Administration\Features\Permissions\PermissionsRepository::lockAdministrator($actor);
            Store::execute('INSERT IGNORE INTO cau_hinh(khoa,gia_tri,mo_ta) VALUES (?, ?, ?)',[self::BANK_DEFAULT,'','Tài khoản nhận học phí mặc định']);
            $default=Store::one('SELECT gia_tri FROM cau_hinh WHERE khoa=? FOR UPDATE',[self::BANK_DEFAULT])['gia_tri'];
            $key='payment.bank.'.$id;
            $before=Store::one('SELECT * FROM cau_hinh WHERE khoa=? FOR UPDATE',[$key]);
            $image=$body['qrImageHash']??Store::one('SELECT gia_tri FROM cau_hinh WHERE khoa=?',['payment.qr.'.$id])['gia_tri']??null;
            if ($image!==null) {
                if (!is_string($image)) throw new ApiException(400,'Ảnh QR không hợp lệ.');
                PaymentQrImages::reference($image,$data);
                Store::execute('INSERT INTO cau_hinh(khoa,gia_tri,mo_ta) VALUES (?,?,?) ON DUPLICATE KEY UPDATE gia_tri=VALUES(gia_tri)',['payment.qr.'.$id,$image,'Ảnh VietQR đã kiểm tra tài khoản']);
            }
            if ($default===$id && !$data['active']) throw new ApiException(409,'Hãy chọn tài khoản mặc định khác trước khi ngừng sử dụng tài khoản này.');
            Store::execute('INSERT INTO cau_hinh(khoa,gia_tri,mo_ta) VALUES (?,?,?) ON DUPLICATE KEY UPDATE gia_tri=VALUES(gia_tri)',[$key,$encoded,'Tài khoản ngân hàng nhận học phí']);
            Store::recordAudit($actor['userId'],$before?'sua':'them','cau_hinh',null,$before,['khoa'=>$key,'gia_tri'=>$encoded,'qrImageHash'=>$image]);
            return self::mapBank($id,$data,$default);
        });
    }
    public static function defaultBank(string $id): array
    {
        $actor=PermissionsFilter::administer();
        if ($id==='default' || !preg_match('/^[a-zA-Z0-9_-]{1,20}$/D',$id)) throw new ApiException(400,'Mã tài khoản không hợp lệ.');
        return Store::transaction(function() use($actor,$id) {
            \App\Modules\Administration\Features\Permissions\PermissionsRepository::lockAdministrator($actor);
            Store::execute('INSERT IGNORE INTO cau_hinh(khoa,gia_tri) VALUES (?,?)',[self::BANK_DEFAULT,'']);
            $before=Store::one('SELECT * FROM cau_hinh WHERE khoa=? FOR UPDATE',[self::BANK_DEFAULT]);
            $row=Store::one('SELECT gia_tri FROM cau_hinh WHERE khoa=?',['payment.bank.'.$id]);
            $bank=$row?json_decode($row['gia_tri'],true):null;
            if (!$bank || !$bank['active']) throw new ApiException(400,'Chọn tài khoản đang sử dụng để nhận học phí.');
            Store::execute('UPDATE cau_hinh SET gia_tri=? WHERE khoa=?',[$id,self::BANK_DEFAULT]);
            Store::recordAudit($actor['userId'],'sua','cau_hinh',null,$before,['khoa'=>self::BANK_DEFAULT,'gia_tri'=>$id]);
            return self::mapBank($id,$bank,$id);
        });
    }
    public static function paymentQr(int $invoiceId,array $actor): array
    {
        $row=Store::one('SELECT h.* FROM hoa_don h LEFT JOIN dang_ky_khoa_hoc d ON d.id=h.dang_ky_khoa_hoc_id
            LEFT JOIN lop_hoc_vien l ON l.id=h.lop_hoc_vien_id JOIN hoc_vien v ON v.id=COALESCE(d.hoc_vien_id,l.hoc_vien_id)
            WHERE h.id=? AND v.phu_huynh_user_id=? AND v.deleted_at IS NULL',[$invoiceId,$actor['userId']]);
        if (!$row) throw new ApiException(404,'Không tìm thấy hóa đơn của bạn.');
        $amount=(int)$row['phai_thu']-(int)$row['da_thu'];
        if (!in_array($row['trang_thai'],['chua_thanh_toan','mot_phan'],true) || $amount<=0) throw new ApiException(409,'Hóa đơn chưa phát hành hoặc đã thu đủ.');
        $setting=Store::one('SELECT b.khoa,b.gia_tri FROM cau_hinh d JOIN cau_hinh b ON b.khoa=CONCAT(\'payment.bank.\',d.gia_tri) WHERE d.khoa=?',[self::BANK_DEFAULT]);
        $bank=$setting?json_decode($setting['gia_tri'],true):null;
        if (!$bank || !$bank['active']) throw new ApiException(409,'Trung tâm chưa chọn tài khoản nhận học phí. Vui lòng liên hệ Giáo vụ.','BANK_NOT_CONFIGURED');
        $bank=self::mapBank(substr($setting['khoa'],13),$bank,substr($setting['khoa'],13));
        $quote=['invoiceId'=>$invoiceId,'parentId'=>(int)$actor['userId'],'amount'=>$amount,'memo'=>'EDUHD'.$invoiceId,'bank'=>$bank];
        $json=base64_encode(json_encode($quote,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $quote['paymentToken']=$json.'.'.hash_hmac('sha256',$json,\App\Shared\Access::secret());
        $quote['qrDataUrl']=null;
        $sepayReady = !$bank['isDemo'] && SePayPayments::status()['ready'];
        $payosReady = !$bank['isDemo'] && PayOsPayments::status()['ready'];
        $quote['automaticProvider'] = $sepayReady ? 'SEPAY' : ($payosReady ? 'PAYOS' : null);
        $quote['automaticAvailable'] = $quote['automaticProvider'] !== null;
        $quote['originalQrImage']=PaymentQrImages::preview($bank['id']);
        if (!$bank['isDemo']) {
            $payload=TuitionModel::vietQrPayload($bank['bankBin'],$bank['accountNo'],$amount,$quote['memo']);
            $qr=new \Endroid\QrCode\QrCode(data:$payload,encoding:new \Endroid\QrCode\Encoding\Encoding('ISO-8859-1'),size:360,margin:24);
            $quote['qrDataUrl']=(new \Endroid\QrCode\Writer\SvgWriter())->write($qr)->getDataUri();
        }
        return $quote;
    }
    public static function verifyPaymentToken(string $token,int $invoice,int $parent,int $amount): array
    {
        $parts=explode('.',$token);
        if (count($parts)!==2 || !hash_equals(hash_hmac('sha256',$parts[0],\App\Shared\Access::secret()),$parts[1])) throw new ApiException(400,'Thông tin chuyển khoản đã thay đổi. Vui lòng mở lại mã QR.');
        $quote=json_decode(base64_decode($parts[0],true)?:'',true);
        if (!$quote || $quote['invoiceId']!==$invoice || $quote['parentId']!==$parent || $quote['amount']!==$amount) throw new ApiException(400,'Thông tin chuyển khoản không khớp hóa đơn. Vui lòng mở lại mã QR.');
        return $quote;
    }
    public static function transactions(): array
    {
        PermissionsFilter::administer();
        return TuitionRepository::transactions();
    }
    public static function confirm(int $id): array
    {
        $actor=PermissionsFilter::administer();
        $row=TuitionRepository::find($id);
        if ($row['nha_cung_cap']==='PAYOS') throw new ApiException(409,'Giao dịch payOS phải được xác minh với cổng thanh toán. Hãy bấm Kiểm tra payOS.');
        \App\Shared\Store::call('CALL sp_xac_nhan_giao_dich(?,?)',[$id,$actor['userId']]);
        return TuitionRepository::find($id);
    }

    public static function invoices(): array
    {
        return ApiModel::map(TuitionRepository::invoices(TuitionFilter::check()));
    }

    public static function invoice(int $id): array
    {
        $row=TuitionRepository::invoices(TuitionFilter::check(),$id)[0]??null;
        if (!$row) throw new ApiException(404,'Không tìm thấy hóa đơn.');
        return ApiModel::map($row+['receipts'=>TuitionRepository::receipts($id)]);
    }

    public static function createInvoice(array $body): array
    {
        $actor=TuitionFilter::check('add'); $data=TuitionValidator::invoice($body);
        $id=Store::transaction(function() use($actor,$data) {
            $registration=Store::one('SELECT * FROM dang_ky_khoa_hoc WHERE id=? FOR UPDATE',[$data['dangKyKhoaHocId']]);
            if (!$registration) throw new ApiException(404,'Không tìm thấy đăng ký khóa học.');
            \App\Modules\People\Features\Students\StudentsService::detail((int)$registration['hoc_vien_id']);
            if ($registration['trang_thai']==='thoi_hoc') throw new ApiException(409,'Đăng ký đã thôi học.');
            $existing=Store::one('SELECT id FROM hoa_don WHERE dang_ky_khoa_hoc_id=?',[$registration['id']]);
            if ($existing) return (int)$existing['id'];
            $id=Store::insert('hoa_don',['ma_hd'=>'HD-'.strtoupper(bin2hex(random_bytes(8))), 'dang_ky_khoa_hoc_id'=>$registration['id'],
                'tong_tien'=>$registration['hoc_phi_snapshot'],'nguoi_lap_id'=>$actor['userId'],'han_thanh_toan'=>$data['hanThanhToan']]);
            Store::insert('hoa_don_chi_tiet',['hoa_don_id'=>$id,'loai_khoan'=>'khoa_hoc','mo_ta'=>'Học phí trọn khóa','don_gia'=>$registration['hoc_phi_snapshot']]);
            Store::recordAudit((int)$actor['userId'],'them','hoa_don',$id,null,Store::one('SELECT * FROM hoa_don WHERE id=?',[$id]));
            return $id;
        });
        return self::invoice($id);
    }

    public static function publish(int $id,array $body): array
    {
        $actor=TuitionFilter::check('edit');
        if($body!==[]) Input::fail('fields');
        self::invoice($id);
        Store::transaction(function() use($actor,$id) {
            $before=Store::one('SELECT * FROM hoa_don WHERE id=? FOR UPDATE',[$id]);
            if ($before['phat_hanh_luc']!==null && $before['trang_thai']!=='huy') return;
            if ($before['trang_thai']!=='nhap') throw new ApiException(409,'Hóa đơn không ở trạng thái nháp.');
            Store::execute("UPDATE hoa_don SET trang_thai='chua_thanh_toan',phat_hanh_luc=NOW(),phat_hanh_boi=? WHERE id=?",[$actor['userId'],$id]);
            Store::recordAudit((int)$actor['userId'],'sua','hoa_don',$id,$before,Store::one('SELECT * FROM hoa_don WHERE id=?',[$id]));
        });
        return self::invoice($id);
    }

    public static function receive(int $id,array $body): array
    {
        $actor=PermissionsFilter::administer(); $data=TuitionValidator::receipt($body);
        self::invoice($id);
        Store::transaction(function() use($id,$data,$actor) {
            \App\Modules\Administration\Features\Permissions\PermissionsRepository::lockAdministrator($actor);
            $invoice=Store::one('SELECT * FROM hoa_don WHERE id=? FOR UPDATE',[$id]);
            $previous=Store::one("SELECT * FROM thanh_toan WHERE hoa_don_id=? AND ma_giao_dich=? AND loai='thu'",[$id,$data['maGiaoDich']]);
            if($previous) {
                if((int)$previous['so_tien']===$data['soTien'] && $previous['phuong_thuc']===$data['phuongThuc'] && $previous['trang_thai']==='hop_le') return;
                throw new ApiException(409,'Mã chứng từ đã được dùng với nội dung khác.');
            }
            if (!in_array($invoice['trang_thai'],['chua_thanh_toan','mot_phan'],true) || $data['soTien']>(int)$invoice['phai_thu']-(int)$invoice['da_thu']) throw new ApiException(409,'Hóa đơn chưa phát hành, đã thu đủ hoặc số tiền vượt công nợ.');
            $receipt=Store::insert('thanh_toan',['hoa_don_id'=>$id,'loai'=>'thu','so_tien'=>$data['soTien'],
                'phuong_thuc'=>$data['phuongThuc'],'ma_giao_dich'=>$data['maGiaoDich'],'ghi_chu'=>$data['ghiChu'],'nguoi_thu_id'=>$actor['userId']]);
            Store::recordAudit((int)$actor['userId'],'them','thanh_toan',$receipt,null,Store::one('SELECT * FROM thanh_toan WHERE id=?',[$receipt]));
        });
        return self::invoice($id);
    }
}
