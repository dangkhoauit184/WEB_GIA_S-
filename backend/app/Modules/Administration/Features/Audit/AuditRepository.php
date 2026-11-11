<?php
namespace App\Modules\Administration\Features\Audit;

use App\Shared\{ApiException,Store};

final class AuditRepository
{
    private const FROM=' FROM audit_log a LEFT JOIN users u ON u.user_id=a.user_id';
    private const SELECT='SELECT a.id,a.user_id AS userId,u.hoten AS hoTen,a.hanh_dong AS hanhDong,
        a.bang,a.ban_ghi_id AS banGhiId,a.du_lieu_cu AS duLieuCu,a.du_lieu_moi AS duLieuMoi,a.ip,a.created_at AS createdAt';

    public static function all(array $filters): array
    {
        $where=' WHERE 1=1'; $params=[];
        foreach(['bang'=>'a.bang','userId'=>'a.user_id','hanhDong'=>'a.hanh_dong'] as $key=>$column) {
            if(isset($filters[$key]) && $filters[$key]!=='') { $where.=" AND $column=?"; $params[]=$filters[$key]; }
        }
        if(!empty($filters['tuNgay'])) { $where.=' AND a.created_at>=?'; $params[]=$filters['tuNgay'].' 00:00:00'; }
        if(!empty($filters['denNgay'])) { $where.=' AND a.created_at<DATE_ADD(?,INTERVAL 1 DAY)'; $params[]=$filters['denNgay']; }
        if(!empty($filters['q'])) {
            $where.=' AND (u.hoten LIKE ? OR a.bang LIKE ? OR a.hanh_dong LIKE ?)';
            array_push($params,...array_fill(0,3,'%'.$filters['q'].'%'));
        }
        $total=Store::one('SELECT COUNT(*) AS total'.self::FROM.$where,$params)['total'];
        $limit=$filters['perPage']; $offset=($filters['page']-1)*$limit;
        $rows=Store::rows(self::SELECT.self::FROM.$where." ORDER BY a.id DESC LIMIT $limit OFFSET $offset",$params);
        return ['items'=>array_map([AuditModel::class,'map'],$rows),'total'=>(int)$total,
            'page'=>$filters['page'],'perPage'=>$limit];
    }

    public static function find(int $id): array
    {
        $row=Store::one(self::SELECT.self::FROM.' WHERE a.id=?',[$id]);
        if(!$row) throw new ApiException(404,'Không tìm thấy nhật ký.','AUDIT_NOT_FOUND');
        return AuditModel::map($row);
    }
}
