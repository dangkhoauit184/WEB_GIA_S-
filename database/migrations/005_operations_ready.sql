-- [THÊM] Nâng cấp cộng dồn 004 → 005, không xóa hoặc sửa chứng từ cũ.
SET NAMES utf8mb4;
INSERT INTO schema_migrations(version,status) VALUES('005_operations_ready','applying');
ALTER TABLE lop_hoc_vien
  ADD COLUMN xep_boi BIGINT UNSIGNED NULL,
  ADD COLUMN xep_luc DATETIME NULL,
  ADD CONSTRAINT fk_lhv_admin FOREIGN KEY (xep_boi) REFERENCES users(user_id),
  ADD CONSTRAINT ck_lhv_xep CHECK ((xep_boi IS NULL)=(xep_luc IS NULL));

-- [THÊM] 005: Yêu cầu thanh toán không đồng nghĩa đã nhận tiền. Không lưu dữ liệu thẻ hay secret.
CREATE TABLE giao_dich_truc_tuyen (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ma_yeu_cau VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
  hoa_don_id BIGINT UNSIGNED NOT NULL,
  phu_huynh_id BIGINT UNSIGNED NOT NULL,
  nha_cung_cap VARCHAR(40) COLLATE utf8mb4_bin NOT NULL,
  ma_giao_dich_cong VARCHAR(120) COLLATE utf8mb4_bin NULL,
  so_tien DECIMAL(12,0) NOT NULL,
  tien_te VARCHAR(3) NOT NULL DEFAULT 'VND',
  trang_thai ENUM('khoi_tao','cho_xac_nhan','thanh_cong','that_bai','het_han','huy') NOT NULL DEFAULT 'khoi_tao',
  thanh_toan_id BIGINT UNSIGNED NULL,
  xac_nhan_boi BIGINT UNSIGNED NULL,
  xac_nhan_luc DATETIME NULL,
  het_han_luc DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gd_yeu_cau (ma_yeu_cau),
  UNIQUE KEY uq_gd_cong (nha_cung_cap,ma_giao_dich_cong),
  UNIQUE KEY uq_gd_phieu (thanh_toan_id),
  UNIQUE KEY uq_gd_id_cong (id,nha_cung_cap),
  KEY idx_gd_hd (hoa_don_id,trang_thai),
  KEY idx_gd_ph (phu_huynh_id,created_at),
  KEY idx_gd_han (trang_thai,het_han_luc),
  CONSTRAINT fk_gd_hd FOREIGN KEY (hoa_don_id) REFERENCES hoa_don(id),
  CONSTRAINT fk_gd_ph FOREIGN KEY (phu_huynh_id) REFERENCES users(user_id),
  CONSTRAINT fk_gd_phieu FOREIGN KEY (thanh_toan_id) REFERENCES thanh_toan(id),
  CONSTRAINT fk_gd_admin FOREIGN KEY (xac_nhan_boi) REFERENCES users(user_id),
  CONSTRAINT ck_gd_tien CHECK (so_tien>0 AND tien_te='VND'),
  CONSTRAINT ck_gd_xac_nhan CHECK (
    (trang_thai='thanh_cong' AND thanh_toan_id IS NOT NULL AND xac_nhan_boi IS NOT NULL AND xac_nhan_luc IS NOT NULL)
    OR (trang_thai<>'thanh_cong' AND thanh_toan_id IS NULL AND xac_nhan_boi IS NULL AND xac_nhan_luc IS NULL)),
  CONSTRAINT ck_gd_ma CHECK (CHAR_LENGTH(TRIM(ma_yeu_cau))>0 AND CHAR_LENGTH(TRIM(nha_cung_cap))>0)
) ENGINE=InnoDB;

-- [THÊM] Dedupe webhook. Chỉ lưu hash payload để truy vết, không xem một event là bằng chứng tiền đã nhận.
CREATE TABLE su_kien_thanh_toan (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  giao_dich_id BIGINT UNSIGNED NOT NULL,
  nha_cung_cap VARCHAR(40) COLLATE utf8mb4_bin NOT NULL,
  ma_su_kien VARCHAR(120) COLLATE utf8mb4_bin NOT NULL,
  payload_sha256 VARCHAR(64) NOT NULL,
  trang_thai ENUM('cho_xu_ly','da_xu_ly','bo_qua','loi') NOT NULL DEFAULT 'cho_xu_ly',
  loi VARCHAR(255) NULL,
  nhan_luc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  xu_ly_luc DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sk_cong (nha_cung_cap,ma_su_kien),
  KEY idx_sk_hang_doi (trang_thai,nhan_luc),
  CONSTRAINT fk_sk_gd FOREIGN KEY (giao_dich_id,nha_cung_cap) REFERENCES giao_dich_truc_tuyen(id,nha_cung_cap),
  CONSTRAINT ck_sk_hash CHECK (REGEXP_LIKE(payload_sha256,'^[a-fA-F0-9]{64}$','c')),
  CONSTRAINT ck_sk_xu_ly CHECK ((trang_thai='cho_xu_ly' AND xu_ly_luc IS NULL) OR (trang_thai<>'cho_xu_ly' AND xu_ly_luc IS NOT NULL))
) ENGINE=InnoDB;
