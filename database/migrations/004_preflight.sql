-- [THÊM] Báo cáo chỉ đọc trước 004; không thay đổi dữ liệu và không tính lại công cũ.
SELECT 'VI_PHAM' AS loai,'004_table_name_conflict' AS noi_dung,COUNT(*) AS ket_qua
FROM information_schema.tables WHERE table_schema=DATABASE()
 AND table_name IN ('khoa_hoc','dang_ky_khoa_hoc','lich_su_don_gia_gio','yeu_cau_nghi_lop','hoc_bu_hoc_vien');
SELECT 'VI_PHAM' AS loai,'004_column_name_conflict' AS noi_dung,COUNT(*) AS ket_qua
FROM information_schema.columns WHERE table_schema=DATABASE() AND
 ((table_name='lop_hoc' AND column_name='khoa_hoc_id')
 OR (table_name IN ('lop_hoc_vien','hoa_don') AND column_name='dang_ky_khoa_hoc_id')
 OR (table_name='phan_cong_buoi_hoc' AND column_name IN ('quy_tac_cong','gio_lich_bat_dau','gio_lich_ket_thuc','so_phut_tinh_cong','luong_sua_boi'))
 OR (table_name='buoi_hoc' AND column_name='pham_vi_bu')
 OR (table_name='bang_cong_thang' AND column_name IN ('mo_khoa_boi','mo_khoa_luc','ly_do_mo_khoa')));
SELECT 'VI_PHAM' AS loai,'004_legacy_invoice_period_incomplete' AS noi_dung,COUNT(*) AS ket_qua
FROM hoa_don WHERE lop_hoc_vien_id IS NULL OR thang IS NULL OR nam IS NULL;
