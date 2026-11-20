<?php
namespace App\Modules\Finance\Features\Tuition;

use App\Shared\ApiException;

final class PaymentQrImages
{
    public static function read(string $path): array
    {
        $info=@getimagesize($path);
        if (!$info || !in_array($info[2],[IMAGETYPE_PNG,IMAGETYPE_JPEG],true) || filesize($path)>750000
            || $info[0]>4096 || $info[1]>4096 || $info[0]*$info[1]>8000000) {
            throw new ApiException(400,'Chọn ảnh PNG/JPG dưới 750 KB, kích thước tối đa 4096 px và 8 triệu điểm ảnh.');
        }
        try { $text=(new \Zxing\QrReader($path))->text(); }
        catch (\Throwable) { $text=false; }
        if (!$text) throw new ApiException(400,'Không đọc được mã QR. Hãy chọn ảnh gốc rõ nét, có đủ viền mã QR.');
        return TuitionModel::readVietQr($text);
    }
    public static function upload(\CodeIgniter\HTTP\Files\UploadedFile $file): array
    {
        if (!$file->isValid() || $file->hasMoved()) throw new ApiException(400,'Không tải được ảnh. Vui lòng chọn lại ảnh PNG/JPG.');
        $parsed=self::read($file->getTempName());
        $source=imagecreatefromstring(file_get_contents($file->getTempName()));
        if (!$source) throw new ApiException(400,'Ảnh QR bị hỏng.');
        $ratio=min(1,1200/max(imagesx($source),imagesy($source)));
        $width=(int)round(imagesx($source)*$ratio); $height=(int)round(imagesy($source)*$ratio);
        $canvas=imagecreatetruecolor($width,$height);
        imagefill($canvas,0,0,imagecolorallocate($canvas,255,255,255));
        imagecopyresampled($canvas,$source,0,0,0,0,$width,$height,imagesx($source),imagesy($source));
        ob_start(); imagepng($canvas,null,9); $bytes=ob_get_clean();
        imagedestroy($source); imagedestroy($canvas);
        $hash=hash('sha256',$bytes); $path=self::path($hash);
        if (!is_dir(dirname($path))) mkdir(dirname($path),0700,true);
        if (!is_file($path) && file_put_contents($path,$bytes,LOCK_EX)===false) throw new ApiException(503,'Chưa lưu được ảnh QR. Vui lòng thử lại.');
        self::read($path);
        return $parsed+['imageHash'=>$hash,'imageUrl'=>'data:image/png;base64,'.base64_encode($bytes)];
    }
    public static function path(string $hash): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$hash)) throw new ApiException(400,'Ảnh QR chưa hợp lệ. Hãy tải ảnh lên lại.');
        return WRITEPATH.'uploads/payment-qr/'.$hash.'.png';
    }
    public static function reference(string $hash,array $bank): void
    {
        $path=self::path($hash);
        if (!is_file($path)) throw new ApiException(400,'Ảnh QR không còn trên máy chủ. Hãy tải ảnh lên lại.');
        $parsed=self::read($path);
        if ($parsed['bankBin']!==$bank['bin'] || $parsed['accountNo']!==$bank['number']) throw new ApiException(400,'Ngân hàng hoặc số tài khoản không khớp ảnh QR đã tải lên.');
    }
    public static function preview(string $bankId): ?string
    {
        $hash=\App\Shared\Store::one('SELECT gia_tri FROM cau_hinh WHERE khoa=?',['payment.qr.'.$bankId])['gia_tri']??'';
        if (!preg_match('/^[a-f0-9]{64}$/D',$hash) || !is_file(self::path($hash))) return null;
        return 'data:image/png;base64,'.base64_encode(file_get_contents(self::path($hash)));
    }
}
