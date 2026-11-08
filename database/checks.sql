-- [THÊM] Báo cáo CHỈ ĐỌC sau triển khai. Mọi dòng VI_PHAM phải có số lượng bằng 0.
-- [THÊM] 004: Khóa cũ chưa gán sản phẩm được báo để đối chiếu, không tự tạo khóa hoặc giá giả.
SELECT 'CAN_XAC_NHAN' AS loai,'lop_chua_gan_khoa' AS noi_dung,COUNT(*) AS ket_qua FROM lop_hoc WHERE khoa_hoc_id IS NULL AND deleted_at IS NULL;
SELECT 'VI_PHAM' AS loai,'cong_004_thieu_snapshot_lich' AS noi_dung,COUNT(*) AS ket_qua FROM phan_cong_buoi_hoc
WHERE quy_tac_cong=4 AND checkin_luc IS NOT NULL AND (gio_lich_bat_dau IS NULL OR gio_lich_ket_thuc IS NULL);
SELECT 'VI_PHAM' AS loai,'quyen_luong_ngoai_admin' AS noi_dung,COUNT(*) AS ket_qua
FROM role_group_function p JOIN `function` f ON f.function_id=p.function_id JOIN role_group g ON g.group_id=p.group_id
WHERE f.name_function='ChamCongLuong' AND g.group_name<>'Admin' AND p.is_deleted=0
  AND (p.view<>0 OR p.`add`<>0 OR p.edit<>0 OR p.`delete`<>0 OR p.download<>0);
SELECT 'PHIEN_BAN' AS loai,version AS noi_dung,status AS ket_qua FROM schema_migrations;
SELECT 'CAU_TRUC' AS loai,'bang' AS noi_dung,COUNT(*) AS ket_qua FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE';
SELECT 'CAU_TRUC' AS loai,'trigger' AS noi_dung,COUNT(*) AS ket_qua FROM information_schema.triggers WHERE trigger_schema=DATABASE();
SELECT 'CAU_TRUC' AS loai,'routine' AS noi_dung,COUNT(*) AS ket_qua FROM information_schema.routines WHERE routine_schema=DATABASE();
SELECT 'VI_PHAM' AS loai,'hoa_don_lech_chung_tu' AS noi_dung,COUNT(*) AS ket_qua FROM hoa_don hd
WHERE hd.da_thu<>(SELECT COALESCE(SUM(IF(t.loai='thu',t.so_tien,-t.so_tien)),0) FROM thanh_toan t WHERE t.hoa_don_id=hd.id AND t.trang_thai='hop_le');
SELECT 'VI_PHAM' AS loai,'ca_sai_nguoi_ky_cong' AS noi_dung,COUNT(*) AS ket_qua FROM phan_cong_buoi_hoc pc
JOIN bang_cong_thang bc ON bc.id=pc.bang_cong_id JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
WHERE bc.user_id<>pc.user_id OR bc.thang<>MONTH(b.ngay) OR bc.nam<>YEAR(b.ngay);
-- [THÊM] Không để kiểm tra sau triển khai chỉ đếm object mà bỏ qua dữ liệu nghiệp vụ sai.
SELECT 'VI_PHAM' AS loai,'cong_hop_le_chua_khop_buoi' AS noi_dung,COUNT(*) AS ket_qua
FROM phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
WHERE pc.trang_thai_cong='hop_le' AND (pc.trang_thai<>'da_day' OR b.trang_thai<>'da_day');
SELECT 'VI_PHAM' AS loai,'lop_vuot_si_so' AS noi_dung,COUNT(*) AS ket_qua FROM lop_hoc l
WHERE l.si_so_toi_da<(SELECT COUNT(*) FROM lop_hoc_vien x WHERE x.lop_id=l.id AND x.trang_thai IN ('dang_hoc','hoc_thu'));
SELECT 'VI_PHAM' AS loai,'chi_tiet_sai_lop' AS noi_dung,COUNT(*) AS ket_qua FROM hoa_don_chi_tiet ct
JOIN hoa_don hd ON hd.id=ct.hoa_don_id JOIN lop_hoc_vien l ON l.id=hd.lop_hoc_vien_id LEFT JOIN buoi_hoc b ON b.id=ct.buoi_hoc_id
WHERE b.id IS NOT NULL AND b.lop_id<>l.lop_id;
SELECT 'CAN_XAC_NHAN' AS loai,'snapshot_luong_chua_xac_minh' AS noi_dung,COUNT(*) AS ket_qua FROM phan_cong_buoi_hoc WHERE cach_tinh_luong IS NULL;
SELECT 'CAN_XAC_NHAN' AS loai,'hoc_phi_thang_chua_cau_hinh' AS noi_dung,COUNT(*) AS ket_qua FROM lop_hoc WHERE cach_thu_phi='theo_thang' AND hoc_phi_thang IS NULL;
SELECT 'CAN_XAC_NHAN' AS loai,'co_so_buoi_hoc_chua_xac_minh' AS noi_dung,COUNT(*) AS ket_qua FROM buoi_hoc b
JOIN lop_hoc l ON l.id=b.lop_id WHERE b.co_so_id IS NULL AND l.co_so_id IS NOT NULL AND b.phong_hoc IS NOT NULL;
-- [THÊM] Giá và dấu vết lịch sử không rõ giữ NULL, báo cáo để đối chiếu thay vì tự điền.
SELECT 'CAN_XAC_NHAN','don_vi_ngan_sach_chua_ro',COUNT(*) FROM yeu_cau_hoc
WHERE (ngan_sach_min IS NOT NULL OR ngan_sach_max IS NOT NULL) AND don_vi_ngan_sach IS NULL;
SELECT 'CAN_XAC_NHAN','don_vi_phi_de_xuat_chua_ro',COUNT(*) FROM de_xuat_nhan_su
WHERE hoc_phi_de_xuat IS NOT NULL AND cach_thu_phi_de_xuat IS NULL;
SELECT 'CAN_XAC_NHAN','phan_hoi_thieu_thoi_diem',COUNT(*) FROM de_xuat_nhan_su
WHERE (tt_nhan_su<>'cho' AND nhan_su_phan_hoi_luc IS NULL)
   OR (tt_phu_huynh<>'cho' AND (phu_huynh_phan_hoi_luc IS NULL OR phu_huynh_phan_hoi_boi IS NULL));
SELECT 'CAN_XAC_NHAN','hoa_don_cu_thieu_phat_hanh',COUNT(*) FROM hoa_don
WHERE trang_thai NOT IN ('nhap','huy') AND phat_hanh_luc IS NULL;
SELECT 'CAN_XAC_NHAN','hoa_don_cu_thieu_dau_vet_huy',COUNT(*) FROM hoa_don WHERE trang_thai='huy' AND huy_luc IS NULL;
SELECT 'CAN_XAC_NHAN','phieu_cu_thieu_dau_vet_huy',COUNT(*) FROM thanh_toan WHERE trang_thai='da_huy' AND huy_luc IS NULL;
SELECT 'CAN_XAC_NHAN','buoi_cu_thieu_dau_vet_huy',COUNT(*) FROM buoi_hoc WHERE trang_thai='huy' AND huy_luc IS NULL;
SELECT 'CAN_XAC_NHAN','ky_luong_cu_thieu_dau_vet_tra',COUNT(*) FROM bang_cong_thang WHERE trang_thai='da_tra' AND tra_luc IS NULL;
