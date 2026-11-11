<?php
namespace App\Modules\Academic\Features\Scheduling;

use App\Shared\{ApiException,Input,Store};
use App\Modules\Administration\Features\Permissions\{PermissionsFilter,PermissionsRepository};

final class AdminSchedule
{
    public static function save(array $body,?int $id=null,bool $operations=false): array
    {
        $actor=$operations ? \App\Modules\Academic\Features\AcademicAffairs\AcademicAffairsFilter::check('VanHanhBuoiHoc',$id===null?'add':'edit') : PermissionsFilter::administer();
        $rules=['lopId'=>['id',true],'ngay'=>['date',true],'gioBatDau'=>['time',true],
            'gioKetThuc'=>['time',true],'userId'=>['id',true],'troGiangId'=>['id',false],
            'coSoId'=>['id',false],'phongHoc'=>['string',false],'chuDeBaiHoc'=>['string',false],
            'linkHoc'=>['string',false],'isOnline'=>['bool',false,0],
            'repeatCount'=>['integer',false,1],'buoiGocId'=>['id',false],'troGiangIds'=>['array',false],
            'loai'=>[['thuong','bu'],false,'thuong'],'phamViBu'=>[['ca_lop','ca_nhan'],false,'ca_lop'],
            'hocVienIds'=>['array',false],'ghiChuBu'=>['string',false]];
        if(array_diff(array_keys($body),array_keys($rules))) Input::fail('fields');
        $data=Input::validate($body,$rules);
        Input::pair($data,'gioBatDau','gioKetThuc');
        if(!$data['repeatCount'] || $data['repeatCount']>24 || $id!==null && $data['repeatCount']!==1) Input::fail('repeatCount');
        foreach(['phongHoc'=>50,'chuDeBaiHoc'=>255,'linkHoc'=>255] as $field=>$max) if(isset($data[$field]) && mb_strlen($data[$field])>$max) Input::fail($field);
        if(isset($data['linkHoc']) && $data['linkHoc']!=='' && !preg_match('~^https?://~i',$data['linkHoc'])) Input::fail('linkHoc');
        if($data['troGiangId']===$data['userId']) Input::fail('troGiangId');
        $assistants=$data['troGiangIds']??($data['troGiangId']?[$data['troGiangId']]:[]);
        $data['troGiangIds']=[];
        foreach($assistants as $assistant) $data['troGiangIds'][]=Input::validate(['id'=>$assistant],['id'=>['id',true]])['id'];
        if(count(array_unique($data['troGiangIds']))!==count($data['troGiangIds']) || in_array($data['userId'],$data['troGiangIds'],true)) Input::fail('troGiangIds');
        $data['hocVienIds']=array_map(fn($value)=>Input::validate(['id'=>$value],['id'=>['id',true]])['id'],$data['hocVienIds']??[]);
        if($data['loai']==='bu' && (!$data['buoiGocId'] || $data['phamViBu']==='ca_nhan' && !$data['hocVienIds'])) Input::fail('buoiGocId/hocVienIds');
        if($data['loai']==='thuong' && ($data['buoiGocId'] || $data['phamViBu']!=='ca_lop' || $data['hocVienIds'])) Input::fail('loai');
        if($data['phamViBu']==='ca_lop' && $data['hocVienIds']) Input::fail('hocVienIds');
        if(isset($data['ghiChuBu']) && mb_strlen($data['ghiChuBu'])>500) Input::fail('ghiChuBu');
        return Store::transaction(function() use($id,$data,$actor) {
            if($actor['role']==='ADMIN') PermissionsRepository::lockAdministrator($actor);
            \App\Shared\Access::classAllowed($data['lopId'],$actor);
            $class=Store::one('SELECT * FROM lop_hoc WHERE id=? AND deleted_at IS NULL FOR UPDATE',[$data['lopId']]);
            if(!$class) throw new ApiException(404,'Không tìm thấy lớp học.');
            if(!in_array($class['trang_thai'],['sap_bat_dau','dang_hoc','tam_dung'],true)) throw new ApiException(409,'Lớp đã kết thúc hoặc hủy.');
            if($actor['role']==='GIAO_VU' && $actor['coSoId'] && $data['coSoId'] && (int)$data['coSoId']!==(int)$actor['coSoId']) throw new ApiException(403,'Cơ sở nằm ngoài phạm vi phụ trách.');
            foreach([$data['userId'],...$data['troGiangIds']] as $index=>$userId) {
                if(!$userId) continue;
                $person=Store::one("SELECT phan_loai FROM nhan_su_chuyen_mon WHERE user_id=? AND deleted_at IS NULL AND trang_thai='hoat_dong'",[$userId]);
                if(!$person || ($index===0 && !array_intersect(explode(',',$person['phan_loai']),['giao_vien','gia_su'])) || ($index>0 && !in_array('tro_giang',explode(',',$person['phan_loai']),true))) {
                    throw new ApiException(400,'Người được phân công cần hồ sơ đang hoạt động phù hợp vai trò.');
                }
            }
            if($actor['role']==='GIAO_VU') {
                $qualified=Store::one('SELECT sm.id FROM nhan_su_mon sm JOIN nhan_su_chuyen_mon ns ON ns.id=sm.nhan_su_id
                    WHERE ns.user_id=? AND sm.mon_hoc_id=? AND ? BETWEEN sm.cap_lop_tu AND sm.cap_lop_den',[$data['userId'],$class['mon_hoc_id'],$class['cap_lop_id']]);
                $weekday=(int)(new \DateTimeImmutable($data['ngay']))->format('N')+1;
                $available=Store::one('SELECT lr.id FROM nhan_su_lich_ranh lr JOIN nhan_su_chuyen_mon ns ON ns.id=lr.nhan_su_id
                    WHERE ns.user_id=? AND lr.thu=? AND lr.gio_bat_dau<=? AND lr.gio_ket_thuc>=?',[$data['userId'],$weekday,$data['gioBatDau'],$data['gioKetThuc']]);
                if(!$qualified || !$available) throw new ApiException(400,'Người dạy cần khớp chuyên môn và lịch rảnh đã đăng ký.');
            }
            if($data['coSoId'] && !Store::one('SELECT id FROM co_so WHERE id=? AND is_active=1',[$data['coSoId']])) throw new ApiException(400,'Cơ sở không hoạt động.');
            $before=$id?Store::one('SELECT * FROM buoi_hoc WHERE id=? FOR UPDATE',[$id]):null;
            if($id && (!$before || (int)$before['lop_id']!==$data['lopId'])) throw new ApiException(400,'Giữ lớp gốc khi chỉnh sửa ca.');
            if($before && in_array($before['trang_thai'],['da_day','huy','doi'],true)) throw new ApiException(409,'Buổi đã hoàn tất/hủy/dời cần xử lý theo nghiệp vụ tương ứng.');
            if($id && Store::one("SELECT id FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=? AND (checkin_luc IS NOT NULL OR bang_cong_id IS NOT NULL) LIMIT 1",[$id])) throw new ApiException(409,'Ca đã check-in hoặc chốt công, cần dùng điều chỉnh công.');
            $ids=[];
            for($offset=0;$offset<$data['repeatCount'];$offset++) {
                $date=(new \DateTimeImmutable($data['ngay']))->modify('+'.($offset*7).' days')->format('Y-m-d');
                $values=['lop_id'=>$data['lopId'],'ngay'=>$date,'gio_bat_dau'=>$data['gioBatDau'],
                    'gio_ket_thuc'=>$data['gioKetThuc'],'co_so_id'=>$data['isOnline']?null:($data['coSoId']??$class['co_so_id']),
                    'phong_hoc'=>$data['isOnline']?'Online':$data['phongHoc'],'chu_de_bai_hoc'=>$data['chuDeBaiHoc'],
                    'loai'=>$data['loai'],'buoi_goc_id'=>$data['buoiGocId'],'pham_vi_bu'=>$data['phamViBu']];
                if($id) {
                    Store::execute("UPDATE phan_cong_buoi_hoc SET trang_thai='da_go' WHERE buoi_hoc_id=? AND trang_thai<>'da_go'",[$id]);
                    Store::update('buoi_hoc','id',$id,$values,array_combine(array_keys($values),array_keys($values)));
                    $sessionId=$id;
                } else {
                    $sessionId=Store::insert('buoi_hoc',$values);
                }
                $oldTargets=Store::rows('SELECT hoc_vien_id FROM hoc_bu_hoc_vien WHERE buoi_bu_id=?',[$sessionId]);
                $oldIds=array_column($oldTargets,'hoc_vien_id'); $newIds=$data['hocVienIds']; sort($oldIds); sort($newIds);
                if($oldIds!==$newIds && Store::one('SELECT id FROM so_theo_doi_buoi_hoc WHERE buoi_hoc_id=? LIMIT 1',[$sessionId])) throw new ApiException(409,'Buổi đã có học vụ, cần giữ danh sách học viên bù.');
                if($oldIds!==$newIds) {
                    Store::execute('DELETE FROM hoc_bu_hoc_vien WHERE buoi_bu_id=?',[$sessionId]);
                    if($data['loai']==='bu' && $data['phamViBu']==='ca_nhan') foreach($newIds as $studentId) {
                        Store::insert('hoc_bu_hoc_vien',['buoi_bu_id'=>$sessionId,'hoc_vien_id'=>$studentId,
                            'xep_boi'=>$actor['userId'],'ghi_chu'=>$data['ghiChuBu']]);
                    }
                }
                if($data['loai']==='bu' && $data['phamViBu']==='ca_nhan') Store::execute('UPDATE hoc_bu_hoc_vien SET ghi_chu=? WHERE buoi_bu_id=?',[$data['ghiChuBu'],$sessionId]);
                $assignments=[['day_chinh',$data['userId']]];
                foreach($data['troGiangIds'] as $assistant) $assignments[]=['tro_giang',$assistant];
                foreach($assignments as [$role,$userId]) {
                    if(!$userId) continue;
                    $old=Store::one('SELECT id FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=? AND user_id=? AND vai_tro=? FOR UPDATE',[$sessionId,$userId,$role]);
                    if($old && $actor['role']==='ADMIN') Store::execute("UPDATE phan_cong_buoi_hoc SET trang_thai='da_phan_cong',phan_cong_boi=?,luong_sua_boi=?,
                        don_gia=(SELECT don_gia_gio_chuan FROM nhan_su_chuyen_mon WHERE user_id=? AND deleted_at IS NULL),cach_tinh_luong='theo_gio' WHERE id=?",[$actor['userId'],$actor['userId'],$userId,$old['id']]);
                    elseif($old) Store::execute("UPDATE phan_cong_buoi_hoc SET trang_thai='da_phan_cong',phan_cong_boi=? WHERE id=?",[$actor['userId'],$old['id']]);
                    else Store::insert('phan_cong_buoi_hoc',['buoi_hoc_id'=>$sessionId,'user_id'=>$userId,'vai_tro'=>$role,'don_gia'=>0,'cach_tinh_luong'=>'theo_gio','phan_cong_boi'=>$actor['userId']]);
                }
                $after=Store::one('SELECT * FROM buoi_hoc WHERE id=?',[$sessionId]);
                Store::recordAudit((int)$actor['userId'],$id?'sua':'them','buoi_hoc',$sessionId,$before,$after);
                $ids[]=$sessionId;
            }
            if($data['isOnline'] && $data['linkHoc']) Store::execute('UPDATE lop_hoc SET link_hoc=? WHERE id=?',[$data['linkHoc'],$data['lopId']]);
            return ['ids'=>$ids];
        });
    }
}
