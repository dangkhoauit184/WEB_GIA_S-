-- [THÊM] Nâng cấp cộng dồn 005 → 006: bổ sung đề xuất và ứng tuyển ca học cho Giáo viên / Trợ giảng, không sửa dữ liệu cũ.
SET NAMES utf8mb4;
INSERT INTO schema_migrations(version,status) VALUES('006_shift_recruitment','applying');

CREATE TABLE de_xuat_tuyen_ca (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ma_de_xuat VARCHAR(30) NOT NULL,
  buoi_hoc_id BIGINT UNSIGNED NOT NULL,
  vai_tro ENUM('day_chinh','tro_giang') NOT NULL,
  so_luong TINYINT UNSIGNED NOT NULL DEFAULT 1,
  yeu_cau VARCHAR(500) NULL,
  trang_thai ENUM('dang_mo','da_du_nguoi','da_dong') NOT NULL DEFAULT 'dang_mo',
  nguoi_tao_id BIGINT UNSIGNED NOT NULL,
  dong_boi BIGINT UNSIGNED NULL,
  dong_luc DATETIME NULL,
  mo_key VARCHAR(40) GENERATED ALWAYS AS (IF(trang_thai='dang_mo',CONCAT(buoi_hoc_id,':',vai_tro),NULL)) VIRTUAL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dxtc_ma (ma_de_xuat),
  UNIQUE KEY uq_dxtc_mo (mo_key),
  KEY idx_dxtc_buoi (buoi_hoc_id,vai_tro,trang_thai),
  KEY idx_dxtc_tt (trang_thai,created_at),
  CONSTRAINT fk_dxtc_buoi FOREIGN KEY (buoi_hoc_id) REFERENCES buoi_hoc(id),
  CONSTRAINT fk_dxtc_tao FOREIGN KEY (nguoi_tao_id) REFERENCES users(user_id),
  CONSTRAINT fk_dxtc_dong FOREIGN KEY (dong_boi) REFERENCES users(user_id),
  CONSTRAINT ck_dxtc_sl CHECK (so_luong>=1 AND (vai_tro<>'day_chinh' OR so_luong=1)),
  CONSTRAINT ck_dxtc_dong CHECK ((dong_boi IS NULL)=(dong_luc IS NULL))
) ENGINE=InnoDB;

CREATE TABLE ung_tuyen_ca (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  de_xuat_id BIGINT UNSIGNED NOT NULL,
  nhan_su_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  ghi_chu VARCHAR(500) NULL,
  trang_thai ENUM('cho_duyet','da_duyet','tu_choi','da_huy') NOT NULL DEFAULT 'cho_duyet',
  nguoi_duyet_id BIGINT UNSIGNED NULL,
  duyet_luc DATETIME NULL,
  ly_do_tu_choi VARCHAR(255) NULL,
  phan_cong_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_utc_dx_ns (de_xuat_id,nhan_su_id),
  UNIQUE KEY uq_utc_dx_user (de_xuat_id,user_id),
  KEY idx_utc_user (user_id,trang_thai,created_at),
  KEY idx_utc_dx (de_xuat_id,trang_thai,created_at),
  CONSTRAINT fk_utc_dx FOREIGN KEY (de_xuat_id) REFERENCES de_xuat_tuyen_ca(id),
  CONSTRAINT fk_utc_ns FOREIGN KEY (nhan_su_id) REFERENCES nhan_su_chuyen_mon(id),
  CONSTRAINT fk_utc_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT fk_utc_duyet FOREIGN KEY (nguoi_duyet_id) REFERENCES users(user_id),
  CONSTRAINT fk_utc_pc FOREIGN KEY (phan_cong_id) REFERENCES phan_cong_buoi_hoc(id),
  CONSTRAINT ck_utc_duyet CHECK (
    (trang_thai IN ('da_duyet','tu_choi') AND nguoi_duyet_id IS NOT NULL AND duyet_luc IS NOT NULL)
    OR (trang_thai IN ('cho_duyet','da_huy') AND nguoi_duyet_id IS NULL AND duyet_luc IS NULL)),
  CONSTRAINT ck_utc_tuchoi CHECK (ly_do_tu_choi IS NULL OR trang_thai='tu_choi'),
  CONSTRAINT ck_utc_pc CHECK ((trang_thai='da_duyet' AND phan_cong_id IS NOT NULL) OR (trang_thai<>'da_duyet' AND phan_cong_id IS NULL))
) ENGINE=InnoDB;
