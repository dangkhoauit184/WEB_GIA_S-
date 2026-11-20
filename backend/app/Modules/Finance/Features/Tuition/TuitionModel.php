<?php
namespace App\Modules\Finance\Features\Tuition;

final class TuitionModel
{
    public static function readVietQr(string $payload): array
    {
        $invalid=static fn()=>new \App\Shared\ApiException(400,'Ảnh phải chứa mã VietQR chuyển khoản ngân hàng hợp lệ (VND), rõ nét và không bị cắt.');
        if (strlen($payload)>1500 || !preg_match('/6304[A-Fa-f0-9]{4}$/D',$payload)) throw $invalid();
        $crc=0xFFFF;
        foreach(str_split(substr($payload,0,-4)) as $char) {
            $crc^=ord($char)<<8;
            for($i=0;$i<8;$i++) $crc=(($crc&0x8000)?($crc<<1)^0x1021:$crc<<1)&0xFFFF;
        }
        if (strtoupper(substr($payload,-4))!==sprintf('%04X',$crc)) throw $invalid();
        $tlv=static function(string $text) use($invalid): array {
            $fields=[];
            for($p=0;$p<strlen($text);) {
                if (!preg_match('/^\d{4}$/D',substr($text,$p,4))) throw $invalid();
                $id=substr($text,$p,2); $length=(int)substr($text,$p+2,2); $p+=4;
                if ($p+$length>strlen($text) || isset($fields[$id])) throw $invalid();
                $fields[$id]=substr($text,$p,$length); $p+=$length;
            }
            return $fields;
        };
        $root=$tlv($payload); $merchant=$tlv($root['38']??''); $bank=$tlv($merchant['01']??'');
        if (($root['00']??'')!=='01' || ($root['53']??'')!=='704' || ($root['58']??'')!=='VN'
            || ($merchant['00']??'')!=='A000000727' || ($merchant['02']??'')!=='QRIBFTTA'
            || !preg_match('/^\d{6}$/D',$bank['00']??'') || !preg_match('/^[a-zA-Z0-9]{1,19}$/D',$bank['01']??'')) throw $invalid();
        return ['bankBin'=>$bank['00'],'accountNo'=>$bank['01'],'amount'=>$root['54']??null,'memo'=>$tlv($root['62']??'')['08']??null];
    }
    // NAPAS account-transfer payload, EMV TLV and CRC-16/CCITT-FALSE.
    public static function vietQrPayload(string $bin, string $account, int $amount, string $memo): string
    {
        $field = static fn(string $id, string $value): string => $id.str_pad((string)strlen($value),2,'0',STR_PAD_LEFT).$value;
        $bank = $field('00',$bin).$field('01',$account);
        $provider = $field('00','A000000727').$field('01',$bank).$field('02','QRIBFTTA');
        $payload = '000201010212'.$field('38',$provider).'5303704'.$field('54',(string)$amount).'5802VN'.$field('62',$field('08',$memo)).'6304';
        $crc = 0xFFFF;
        foreach (str_split($payload) as $char) {
            $crc ^= ord($char) << 8;
            for ($bit=0;$bit<8;$bit++) $crc = (($crc & 0x8000) ? ($crc << 1) ^ 0x1021 : $crc << 1) & 0xFFFF;
        }
        return $payload.strtoupper(str_pad(dechex($crc),4,'0',STR_PAD_LEFT));
    }
}
