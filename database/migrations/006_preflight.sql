-- [THÊM] Chỉ đọc trên schema 005 trước nâng cấp 006 (đề xuất và ứng tuyển ca học).
SELECT 'VI_PHAM' AS loai,'trung_bang_006' AS noi_dung,COUNT(*) AS ket_qua FROM information_schema.tables
 WHERE table_schema=DATABASE() AND table_name IN ('de_xuat_tuyen_ca','ung_tuyen_ca');
SELECT 'VI_PHAM' AS loai,'migration_dang_do' AS noi_dung,COUNT(*) AS ket_qua FROM schema_migrations WHERE status<>'applied';
