<?php
namespace App\Modules\Finance\Features\Tuition;

use App\Modules\Administration\Features\Permissions\PermissionsFilter;
use App\Shared\{ApiException, ApiModel, Input, Store};

final class TuitionService
{
    public static function transactions(): array
    {
        PermissionsFilter::administer();
        return TuitionRepository::transactions();
    }
    public static function confirm(int $id): array
    {
        $actor=PermissionsFilter::administer();
        TuitionRepository::find($id);
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
