-- [THÊM] 007: Chỉ mục hàng đợi email trên thong_bao (cộng thêm, không xóa hay sửa dữ liệu cũ).
SET NAMES utf8mb4;

SET @has_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'thong_bao'
    AND index_name = 'idx_tb_email_queue'
);
SET @sql_idx := IF(
  @has_idx = 0,
  'ALTER TABLE thong_bao ADD INDEX idx_tb_email_queue (co_gui_email, trang_thai_email, email_thu_tiep_luc)',
  'SELECT ''idx_tb_email_queue already exists'' AS info'
);
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;
