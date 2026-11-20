<?php
// Included by integration.php after its disposable _test fixtures exist.
use App\Modules\Finance\Features\Tuition\{PayOsPayments,TuitionModel,PaymentQrImages};

final class PayosTestTransport implements \Psr\Http\Client\ClientInterface
{
    public array $orders=[];
    public bool $badSignature=false;
    public bool $wrongRecipient=false;
    public bool $payDuringCancel=false;
    public int $creates=0;
    public function sendRequest(\Psr\Http\Message\RequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $crypto=new \PayOS\Crypto\CryptoProvider();
        if ($request->getMethod()==='POST' && str_ends_with($request->getUri()->getPath(),'/cancel')) {
            $parts=explode('/',$request->getUri()->getPath());$order=$parts[count($parts)-2];
            $this->orders[$order]['status']=$this->payDuringCancel?'PAID':'CANCELLED';
            if($this->payDuringCancel)$this->orders[$order]['amountPaid']=$this->orders[$order]['amount'];
            $data=$this->orders[$order];
        } elseif ($request->getMethod()==='POST') {
            $body=json_decode((string)$request->getBody(),true);
            check(hash_equals($crypto->createSignatureOfPaymentRequest($body,'test-checksum'),$body['signature']),'official SDK signs payment creation');
            $order=$body['orderCode']; $this->creates++;
            $account=$this->wrongRecipient?'999999999':'1234567890';
            $data=['bin'=>'970436','accountNumber'=>$account,'accountName'=>'TEST ONLY','amount'=>$body['amount'],
                'description'=>$body['description'],'orderCode'=>$order,'currency'=>'VND','paymentLinkId'=>'link'.$order,
                'status'=>'PENDING','checkoutUrl'=>'https://pay.payos.vn/web/link'.$order,
                'qrCode'=>TuitionModel::vietQrPayload('970436',$account,$body['amount'],$body['description'])];
            $this->orders[$order]=['id'=>$data['paymentLinkId'],'orderCode'=>$order,'amount'=>$body['amount'],'amountPaid'=>0,
                'amountRemaining'=>$body['amount'],'status'=>'PENDING','createdAt'=>date('c'),'transactions'=>[]];
        } else {
            $order=basename($request->getUri()->getPath()); $data=$this->orders[$order];
        }
        $signature=$this->badSignature?str_repeat('0',64):$crypto->createSignatureFromObj($data,'test-checksum');
        return new \GuzzleHttp\Psr7\Response(200,['Content-Type'=>'application/json'],json_encode(['code'=>'00','desc'=>'success','data'=>$data,'signature'=>$signature]));
    }
}

function testPayos(array $ctx): void
{
    extract($ctx); // Named, trusted fixtures from integration.php only.
    $actor=['userId'=>$parent['userId']];
    $variables=['PAYOS_ENABLED'=>'true','PAYOS_CLIENT_ID'=>'test-client','PAYOS_API_KEY'=>'test-api','PAYOS_CHECKSUM_KEY'=>'test-checksum',
        'PAYOS_PUBLIC_URL'=>'https://educenter.example.test','PAYOS_RECONCILE_USER_ID'=>(string)$login['user']['userId']];
    $before=[]; foreach($variables as $k=>$v){$before[$k]=getenv($k);putenv($k.'='.$v);}
    $transport=new PayosTestTransport();
    $clientProperty=new ReflectionProperty(PayOsPayments::class,'client');
    $clientProperty->setValue(null,new \PayOS\PayOS(clientId:'test-client',apiKey:'test-api',checksumKey:'test-checksum',maxRetries:0,
        httpClient:new \PayOS\Core\HTTPClient($transport)));
    try {
        $config=(new ReflectionMethod(PayOsPayments::class,'config'))->invoke(null);
        $fingerprint=(new ReflectionMethod(PayOsPayments::class,'fingerprint'))->invoke(null,$config);
        \App\Shared\Store::execute('INSERT INTO cau_hinh(khoa,gia_tri) VALUES (?,?) ON DUPLICATE KEY UPDATE gia_tri=VALUES(gia_tri)',['payment.payos.webhook',$fingerprint]);
        check(PayOsPayments::status()['ready'],'payOS requires complete config and confirmed matching webhook');
        putenv('PAYOS_PUBLIC_URL=https://changed.example.test');
        check(!PayOsPayments::status()['ready'],'changing public URL invalidates webhook confirmation');
        putenv('PAYOS_PUBLIC_URL=https://educenter.example.test');
        requestOk('/api/finance/tuition/bank-accounts/'.$bank['id'].'/default','POST',[],$token);
        check(http('/api/finance/tuition/payos/status','GET',null,$parentToken)[0]===403,'gateway settings are Admin only');
        check(http('/api/finance/tuition/qr-images','POST',[],$parentToken)[0]===403,'parent cannot upload bank QR');
        $png=(new \Endroid\QrCode\Writer\PngWriter())->write(new \Endroid\QrCode\QrCode(data:TuitionModel::vietQrPayload('970436','1234567890',1000,'TEST'),size:320,margin:20))->getString();
        $http=new \GuzzleHttp\Client(['http_errors'=>false]);
        $upload=$http->post($GLOBALS['baseUrl'].'/api/finance/tuition/qr-images',['headers'=>['Authorization'=>'Bearer '.$token],
            'multipart'=>[['name'=>'qrImage','contents'=>$png,'filename'=>'../../unsafe.png']]]);
        $image=json_decode((string)$upload->getBody(),true);
        check($upload->getStatusCode()===201 && $image['data']['accountNo']==='1234567890','bank QR upload decodes bank account and ignores client path');
        $hash=$image['data']['imageHash'];
        check(is_file(PaymentQrImages::path($hash)) && str_starts_with($image['data']['imageUrl'],'data:image/png;base64,'),'QR image stored privately and re-encoded');
        requestOk('/api/finance/tuition/bank-accounts/'.$bank['id'],'PUT',$bankBody+['qrImageHash'=>$hash],$token);
        check(http('/api/finance/tuition/bank-accounts/'.$bank['id'],'PUT',array_replace($bankBody,['accountNo'=>'99999','qrImageHash'=>$hash]),$token)[0]===400,'QR bank identity cannot be changed independently of image');
        $invalid=$http->post($GLOBALS['baseUrl'].'/api/finance/tuition/qr-images',['headers'=>['Authorization'=>'Bearer '.$token],
            'multipart'=>[['name'=>'qrImage','contents'=>'<svg onload="alert(1)"></svg>','filename'=>'fake.png']]]);
        check($invalid->getStatusCode()===400,'fake image or SVG refused');

        $newInvoice=static function() use($student,$token,$suffix): int {
            $course=\App\Shared\Store::insert('khoa_hoc',['ma'=>'PAYOS-'.bin2hex(random_bytes(5)),'ten'=>'Khóa kiểm thử payOS','hoc_phi'=>100000]);
            $registration=\App\Shared\Store::insert('dang_ky_khoa_hoc',['khoa_hoc_id'=>$course,'hoc_vien_id'=>$student['id']]);
            $invoice=requestOk('/api/finance/tuition/invoices','POST',['dangKyKhoaHocId'=>$registration],$token,201);
            requestOk('/api/finance/tuition/invoices/'.$invoice['id'].'/publish','POST',[],$token);
            return (int)$invoice['id'];
        };
        $paid=static function(array $quote) use($transport): array {
            $order=(int)substr(\App\Shared\Store::one('SELECT ma_yeu_cau FROM giao_dich_truc_tuyen WHERE id=?',[$quote['transactionId']])['ma_yeu_cau'],6);
            $tx=['reference'=>'REF'.$order,'amount'=>100000,'accountNumber'=>'1234567890','description'=>$quote['memo'],'transactionDateTime'=>'2026-11-19 12:00:00'];
            $transport->orders[$order]=array_replace($transport->orders[$order],['amountPaid'=>100000,'amountRemaining'=>0,'status'=>'PAID','transactions'=>[$tx]]);
            return $tx+['orderCode'=>$order,'currency'=>'VND','paymentLinkId'=>'link'.$order,'code'=>'00','desc'=>'success'];
        };
        $webhook=static function(array $data): array {return ['success'=>true,'code'=>'00','data'=>$data,'signature'=>(new \PayOS\Crypto\CryptoProvider())->createSignatureFromObj($data,'test-checksum')];};
        $invoice=$newInvoice();
        try{PayOsPayments::checkout($invoice,['userId'=>$parent2['userId']]);check(false,'checkout rejects other family');}catch(\App\Shared\ApiException $e){check($e->status===404,'checkout ownership enforced');}
        $quote=PayOsPayments::checkout($invoice,$actor);$creates=$transport->creates;
        $repeat=PayOsPayments::checkout($invoice,$actor);
        check($repeat['transactionId']===$quote['transactionId'] && $transport->creates===$creates,'repeated checkout reuses provider order');
        check(!PayOsPayments::reconcile($quote['transactionId'])['paid'],'pending provider payment never creates receipt');
        check(http('/api/finance/tuition/transactions/'.$quote['transactionId'].'/confirm','POST',[],$token)[0]===409,'payOS cannot use unchecked manual confirm');
        $data=$paid($quote);$body=$webhook($data);$forged=$body;$forged['data']['amount']=1;
        rejects(fn()=>PayOsPayments::webhook($forged),'tampered webhook rejected by official SDK');
        $transport->badSignature=true;
        try{PayOsPayments::webhook($body);check(false,'invalid provider response denied');}catch(\App\Shared\ApiException $e){check($e->status===503,'signed webhook also requires authentic API response');}
        $transport->badSignature=false;
        PayOsPayments::webhook($body);PayOsPayments::webhook($body);
        $status=PayOsPayments::paymentStatus($invoice,$actor);
        check($status['paid'] && $status['remaining']===0,'verified payOS transfer updates invoice');
        check((int)\App\Shared\Store::one('SELECT COUNT(*) AS n FROM thanh_toan WHERE hoa_don_id=?',[$invoice])['n']===1,'webhook retry creates exactly one receipt');
        $sample=$data;$sample['orderCode']=123;
        check(PayOsPayments::webhook($webhook($sample))['ignored'],'signed verification sample creates no financial records');

        foreach(['short','over','account','memo','partial','multiple','webhook','duplicate','invoice_changed'] as $case) {
            $inv=$newInvoice();$q=PayOsPayments::checkout($inv,$actor);$d=$paid($q);$order=$d['orderCode'];
            if($case==='short')$transport->orders[$order]['amountPaid']=99999;
            if($case==='over')$transport->orders[$order]['amountPaid']=100001;
            if($case==='account')$transport->orders[$order]['transactions'][0]['accountNumber']='99999';
            if($case==='memo')$transport->orders[$order]['transactions'][0]['description']='wrong invoice';
            if($case==='partial'){$transport->orders[$order]['status']='PENDING';$transport->orders[$order]['amountPaid']=50000;}
            if($case==='multiple')$transport->orders[$order]['transactions'][]=$transport->orders[$order]['transactions'][0];
            if($case==='webhook')$d['paymentLinkId']='wrong-link';
            if($case==='duplicate'){$d['reference']=$data['reference'];$transport->orders[$order]['transactions'][0]['reference']=$data['reference'];}
            if($case==='invoice_changed')requestOk('/api/finance/tuition/invoices/'.$inv.'/receipts','POST',['soTien'=>100000,'phuongThuc'=>'tien_mat','maGiaoDich'=>'CASH-'.$order,'ghiChu'=>null],$token);
            $beforeCount=(int)\App\Shared\Store::one('SELECT COUNT(*) AS n FROM thanh_toan WHERE hoa_don_id=?',[$inv])['n'];
            PayOsPayments::webhook($webhook($d));
            check((int)\App\Shared\Store::one('SELECT COUNT(*) AS n FROM thanh_toan WHERE hoa_don_id=?',[$inv])['n']===$beforeCount,'no automatic receipt for '.$case);
            check(\App\Shared\Store::one('SELECT trang_thai FROM giao_dich_truc_tuyen WHERE id=?',[$q['transactionId']])['trang_thai']==='cho_xac_nhan','review keeps intent pending for '.$case);
        }
        $transport->wrongRecipient=true;
        try{PayOsPayments::checkout($newInvoice(),$actor);check(false,'wrong default recipient');}catch(\App\Shared\ApiException $e){check($e->errorCode==='PAYOS_ACCOUNT_MISMATCH','create response must match Admin default bank');}
        $transport->wrongRecipient=false;
        $inv=$newInvoice();$q=PayOsPayments::checkout($inv,$actor);
        requestOk('/api/finance/tuition/bank-accounts/'.$secondBank['id'].'/default','POST',[],$token);
        check(PayOsPayments::checkout($inv,$actor)['bank']['id']===$bank['id'],'existing order retains original bank after default switch');
        $paid($q);
        check(PayOsPayments::checkInvoice($inv,$actor)['paid'],'recheck reconciles real signed provider API without trusting return URL');
        try{PayOsPayments::paymentStatus($inv,['userId'=>$parent2['userId']]);check(false,'payment status ownership');}catch(\App\Shared\ApiException $e){check($e->status===404,'status cannot expose another family invoice');}
        requestOk('/api/finance/tuition/bank-accounts/'.$bank['id'].'/default','POST',[],$token);
        $adminActor=\App\Shared\Store::one('SELECT account_id AS accountId,user_id AS userId FROM account WHERE user_id=?',[$login['user']['userId']]);
        $inv=$newInvoice();$q=PayOsPayments::checkout($inv,$actor);
        check(http('/api/finance/tuition/transactions/'.$q['transactionId'].'/cancel-payos','POST',[],$parentToken)[0]===403,'parent cannot cancel receiving request as Admin');
        check(PayOsPayments::cancel($q['transactionId'],$adminActor)['cancelled'],'unpaid request requires verified provider cancellation');
        check(PayOsPayments::checkout($inv,$actor)['transactionId']!==$q['transactionId'],'confirmed unpaid cancellation allows a fresh request');
        $inv=$newInvoice();$q=PayOsPayments::checkout($inv,$actor);$transport->payDuringCancel=true;
        try{PayOsPayments::cancel($q['transactionId'],$adminActor);check(false,'late payment cannot cancel');}catch(\App\Shared\ApiException $e){check($e->status===409,'provider receiving money during cancellation keeps local intent pending');}
        $transport->payDuringCancel=false;
        echo "PASS: QR image uploads and signed payOS verification/reconciliation scenarios.\n";
    } finally {
        $clientProperty->setValue(null,null);
        foreach($before as $k=>$v)putenv($v===false?$k:$k.'='.$v);
    }
}
