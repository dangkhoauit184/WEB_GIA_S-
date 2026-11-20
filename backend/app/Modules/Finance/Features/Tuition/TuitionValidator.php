<?php
namespace App\Modules\Finance\Features\Tuition;

use App\Shared\Input;

final class TuitionValidator
{
    public static function bank(array $body): array
    {
        if (array_diff(array_keys($body),['bankBin','bankName','accountNo','accountName','active','isDemo','qrImageHash'])) throw new \App\Shared\ApiException(400,'Thông tin tài khoản nhận tiền không hợp lệ.');
        $data=Input::validate($body,['bankBin'=>['string',true],'bankName'=>['string',true],'accountNo'=>['string',true],
            'accountName'=>['string',true],'active'=>['bool',true],'isDemo'=>['bool',true]]);
        if (!preg_match('/^\d{6}$/D',$data['bankBin'])) throw new \App\Shared\ApiException(400,'Mã BIN ngân hàng phải gồm 6 chữ số.');
        if (!preg_match('/^[A-Za-z0-9]{1,19}$/D',$data['accountNo'])) throw new \App\Shared\ApiException(400,'Số tài khoản gồm tối đa 19 chữ hoặc số.');
        if (!$data['isDemo'] && in_array(strtoupper($data['accountNo']),['DEMOEDU01','DEMOEDU02'],true)) throw new \App\Shared\ApiException(400,'Hãy nhập tài khoản ngân hàng thật thay cho số tài khoản demo.');
        if (mb_strlen($data['bankName'])>50 || mb_strlen($data['accountName'])>70) throw new \App\Shared\ApiException(400,'Tên ngân hàng tối đa 50 ký tự, tên chủ tài khoản tối đa 70 ký tự.');
        return ['bin'=>$data['bankBin'],'bank'=>$data['bankName'],'number'=>$data['accountNo'],'holder'=>$data['accountName'],'active'=>(bool)$data['active'],'demo'=>(bool)$data['isDemo']];
    }
    public static function invoice(array $body): array
    {
        if (array_diff(array_keys($body), ['dangKyKhoaHocId', 'hanThanhToan'])) Input::fail('fields');
        return Input::validate($body, ['dangKyKhoaHocId'=>['id', true], 'hanThanhToan'=>['date', false]]);
    }

    public static function receipt(array $body): array
    {
        if (array_diff(array_keys($body), ['soTien', 'phuongThuc', 'maGiaoDich', 'ghiChu'])) Input::fail('fields');
        $data=Input::validate($body, ['soTien'=>['integer', true], 'phuongThuc'=>[['tien_mat','chuyen_khoan'], true],
            'maGiaoDich'=>['string', true], 'ghiChu'=>['string', false]]);
        if (!$data['soTien'] || $data['soTien']>999999999999 || mb_strlen($data['maGiaoDich'])>60 || mb_strlen($data['ghiChu']??'')>255) Input::fail('receipt');
        return $data;
    }
}
