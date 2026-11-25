-- [THÊM] 008: Tách trạng thái bảo mật phiên/metadata thanh toán khỏi audit_log và bổ sung chỉ mục báo cáo tài chính.
-- An toàn chạy lại nhiều lần (idempotent), hoàn toàn cộng thêm, không xóa hay sửa dữ liệu nghiệp vụ cũ.
SET NAMES utf8mb4;

INSERT INTO schema_migrations(version, status)
VALUES ('008_security_perf', 'applying')
ON DUPLICATE KEY UPDATE status = IF(status = 'applied', 'applied', 'applying');

-- 1. account.session_version & account.revoked_tokens_json
SET @has_col := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'account' AND column_name = 'session_version');
SET @sql := IF(@has_col = 0, 'ALTER TABLE account ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER status', 'SELECT ''account.session_version exists'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_col := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'account' AND column_name = 'revoked_tokens_json');
SET @sql := IF(@has_col = 0, 'ALTER TABLE account ADD COLUMN revoked_tokens_json JSON NULL AFTER session_version', 'SELECT ''account.revoked_tokens_json exists'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. giao_dich_truc_tuyen.metadata_json
SET @has_col := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'giao_dich_truc_tuyen' AND column_name = 'metadata_json');
SET @sql := IF(@has_col = 0, 'ALTER TABLE giao_dich_truc_tuyen ADD COLUMN metadata_json JSON NULL AFTER het_han_luc', 'SELECT ''giao_dich_truc_tuyen.metadata_json exists'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. Chỉ mục hiệu năng báo cáo tài chính & truy vấn audit
SET @has_idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'hoa_don' AND index_name = 'idx_hd_phat_hanh');
SET @sql := IF(@has_idx = 0, 'ALTER TABLE hoa_don ADD INDEX idx_hd_phat_hanh (phat_hanh_luc, trang_thai)', 'SELECT ''idx_hd_phat_hanh exists'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'hoa_don' AND index_name = 'idx_hd_created');
SET @sql := IF(@has_idx = 0, 'ALTER TABLE hoa_don ADD INDEX idx_hd_created (created_at, trang_thai)', 'SELECT ''idx_hd_created exists'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'thanh_toan' AND index_name = 'idx_tt_tt_ngay');
SET @sql := IF(@has_idx = 0, 'ALTER TABLE thanh_toan ADD INDEX idx_tt_tt_ngay (trang_thai, ngay_thanh_toan)', 'SELECT ''idx_tt_tt_ngay exists'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_idx := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'audit_log' AND index_name = 'idx_al_bang_hd');
SET @sql := IF(@has_idx = 0, 'ALTER TABLE audit_log ADD INDEX idx_al_bang_hd (bang, ban_ghi_id, hanh_dong, id)', 'SELECT ''idx_al_bang_hd exists'' AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. Đồng bộ an toàn metadata_json cho các giao dịch đang mở hiện có (không đụng giao dịch đã kết thúc vì trg_gd_bu khóa, giữ nguyên updated_at).
UPDATE giao_dich_truc_tuyen gd
SET gd.metadata_json = JSON_OBJECT(
    'bank_transfer_reported',
    (SELECT al.du_lieu_moi FROM audit_log al
     WHERE al.bang = 'giao_dich_truc_tuyen' AND al.ban_ghi_id = gd.id
       AND JSON_UNQUOTE(JSON_EXTRACT(al.du_lieu_moi, '$.action')) = 'bank_transfer_reported'
     ORDER BY al.id DESC LIMIT 1)
),
    gd.updated_at = gd.updated_at
WHERE gd.metadata_json IS NULL
  AND gd.trang_thai IN ('khoi_tao', 'cho_xac_nhan')
  AND EXISTS (
      SELECT 1 FROM audit_log al
      WHERE al.bang = 'giao_dich_truc_tuyen' AND al.ban_ghi_id = gd.id
        AND JSON_UNQUOTE(JSON_EXTRACT(al.du_lieu_moi, '$.action')) = 'bank_transfer_reported'
  );

UPDATE schema_migrations
SET status = 'applied', applied_at = COALESCE(applied_at, NOW())
WHERE version = '008_security_perf' AND status = 'applying';
