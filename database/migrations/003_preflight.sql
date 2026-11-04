-- [THÊM] Chạy trên cấu trúc gốc/001/002 trước bất kỳ DDL của 003; chỉ đọc.
-- Không tự chọn giữa hai giá trị mâu thuẫn hoặc đoán thời điểm đã đọc.
SELECT 'VI_PHAM','003_chi_tiet_sai_hoc_vien_lop',COUNT(*)
FROM hoa_don_chi_tiet ct JOIN hoa_don hd ON hd.id=ct.hoa_don_id
JOIN lop_hoc_vien l ON l.id=hd.lop_hoc_vien_id LEFT JOIN buoi_hoc b ON b.id=ct.buoi_hoc_id
WHERE ct.hoc_vien_id<>l.hoc_vien_id OR (b.id IS NOT NULL AND b.lop_id<>l.lop_id);
SELECT 'VI_PHAM','003_trang_thai_doc_mau_thuan',COUNT(*) FROM thong_bao
WHERE da_doc<>(doc_luc IS NOT NULL);
SELECT 'VI_PHAM','003_ly_do_tu_choi_khong_ro_ben',COUNT(*) FROM de_xuat_nhan_su
WHERE ly_do_tu_choi IS NOT NULL AND ((tt_nhan_su='tu_choi') + (tt_phu_huynh='tu_choi'))<>1;
SELECT 'VI_PHAM','003_hoa_don_lech_chung_tu',COUNT(*) FROM hoa_don hd
WHERE hd.da_thu<>(SELECT COALESCE(SUM(IF(t.loai='thu',t.so_tien,-t.so_tien)),0)
FROM thanh_toan t WHERE t.hoa_don_id=hd.id AND t.trang_thai='hop_le');
