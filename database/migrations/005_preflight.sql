-- [THÊM] Chỉ đọc trên schema 004 trước nâng cấp 005.
SELECT 'VI_PHAM' AS loai,'trung_bang_005' AS noi_dung,COUNT(*) AS ket_qua FROM information_schema.tables
 WHERE table_schema=DATABASE() AND table_name IN ('giao_dich_truc_tuyen','su_kien_thanh_toan');
SELECT 'VI_PHAM','trung_cot_005',COUNT(*) FROM information_schema.columns
 WHERE table_schema=DATABASE() AND table_name='lop_hoc_vien' AND column_name IN ('xep_boi','xep_luc');
SELECT 'VI_PHAM','migration_dang_do',COUNT(*) FROM schema_migrations WHERE status<>'applied';
