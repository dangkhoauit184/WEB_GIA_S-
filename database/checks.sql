-- [THÊM] Báo cáo CHỈ ĐỌC sau triển khai. Mọi dòng VI_PHAM phải có số lượng bằng 0.
SELECT 'PHIEN_BAN' AS loai,version AS noi_dung,status AS ket_qua FROM schema_migrations;
SELECT 'CAU_TRUC' AS loai,'bang' AS noi_dung,COUNT(*) AS ket_qua FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE';
SELECT 'CAU_TRUC' AS loai,'trigger' AS noi_dung,COUNT(*) AS ket_qua FROM information_schema.triggers WHERE trigger_schema=DATABASE();
SELECT 'CAU_TRUC' AS loai,'routine' AS noi_dung,COUNT(*) AS ket_qua FROM information_schema.routines WHERE routine_schema=DATABASE();
SELECT 'VI_PHAM' AS loai,'hoa_don_lech_chung_tu' AS noi_dung,COUNT(*) AS ket_qua FROM hoa_don hd
WHERE hd.da_thu<>(SELECT COALESCE(SUM(IF(t.loai='thu',t.so_tien,-t.so_tien)),0) FROM thanh_toan t WHERE t.hoa_don_id=hd.id AND t.trang_thai='hop_le');
SELECT 'VI_PHAM' AS loai,'ca_sai_nguoi_ky_cong' AS noi_dung,COUNT(*) AS ket_qua FROM phan_cong_buoi_hoc pc
JOIN bang_cong_thang bc ON bc.id=pc.bang_cong_id JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
WHERE bc.user_id<>pc.user_id OR bc.thang<>MONTH(b.ngay) OR bc.nam<>YEAR(b.ngay);
SELECT 'CAN_XAC_NHAN' AS loai,'snapshot_luong_chua_xac_minh' AS noi_dung,COUNT(*) AS ket_qua FROM phan_cong_buoi_hoc WHERE cach_tinh_luong IS NULL;
SELECT 'CAN_XAC_NHAN' AS loai,'hoc_phi_thang_chua_cau_hinh' AS noi_dung,COUNT(*) AS ket_qua FROM lop_hoc WHERE cach_thu_phi='theo_thang' AND hoc_phi_thang IS NULL;
SELECT 'CAN_XAC_NHAN' AS loai,'co_so_buoi_hoc_chua_xac_minh' AS noi_dung,COUNT(*) AS ket_qua FROM buoi_hoc b
JOIN lop_hoc l ON l.id=b.lop_id WHERE b.co_so_id IS NULL AND l.co_so_id IS NOT NULL AND b.phong_hoc IS NOT NULL;
