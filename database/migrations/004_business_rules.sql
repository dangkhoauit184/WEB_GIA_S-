-- [THÊM] 004: Nâng cấp tăng thêm từ 003. Không xóa bảng/cột hoặc sửa công/lương cũ.
-- [LƯU Ý] Dừng ghi, backup/restore thử và chạy 004_preflight.sql trước. DDL MySQL tự COMMIT.
SET NAMES utf8mb4;
INSERT INTO schema_migrations(version,status) VALUES('004_business_rules','applying');
-- [THÊM] 004: Khóa là sản phẩm đăng ký độc lập; không tạo kỳ/đợt thu hoặc hạn mức/phí bù.
CREATE TABLE khoa_hoc (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ma VARCHAR(40) NOT NULL,
  ten VARCHAR(150) NOT NULL,
  mo_ta TEXT NULL,
  hoc_phi DECIMAL(12,0) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_khoa_ma (ma),
  CONSTRAINT ck_khoa_phi CHECK (hoc_phi>=0)
) ENGINE=InnoDB;

-- [THÊM] Giá khóa được chụp lúc đăng ký, không đổi giá các đăng ký cũ khi đổi giá khóa.
CREATE TABLE dang_ky_khoa_hoc (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  khoa_hoc_id BIGINT UNSIGNED NOT NULL,
  hoc_vien_id BIGINT UNSIGNED NOT NULL,
  hoc_phi_snapshot DECIMAL(12,0) NOT NULL DEFAULT 0,
  trang_thai ENUM('cho_xep_lop','dang_hoc','hoan_thanh','thoi_hoc') NOT NULL DEFAULT 'cho_xep_lop',
  ngay_dang_ky DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dkk_hv (hoc_vien_id,khoa_hoc_id),
  CONSTRAINT fk_dkk_khoa FOREIGN KEY (khoa_hoc_id) REFERENCES khoa_hoc(id),
  CONSTRAINT fk_dkk_hv FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id),
  CONSTRAINT ck_dkk_phi CHECK (hoc_phi_snapshot>=0)
) ENGINE=InnoDB;

-- [THÊM] Mức lương tiền/giờ do Admin đặt, có mốc hiệu lực và audit; không sửa ca đã dạy.
CREATE TABLE lich_su_don_gia_gio (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nhan_su_id BIGINT UNSIGNED NOT NULL,
  don_gia_gio DECIMAL(12,0) NOT NULL,
  hieu_luc_tu DATETIME(6) NOT NULL,
  nguoi_dat_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dgg_hieu_luc (nhan_su_id,hieu_luc_tu),
  CONSTRAINT fk_dgg_ns FOREIGN KEY (nhan_su_id) REFERENCES nhan_su_chuyen_mon(id),
  CONSTRAINT fk_dgg_admin FOREIGN KEY (nguoi_dat_id) REFERENCES users(user_id),
  CONSTRAINT ck_dgg_gia CHECK (don_gia_gio>=0)
) ENGINE=InnoDB;

ALTER TABLE lop_hoc
  ADD khoa_hoc_id BIGINT UNSIGNED NULL,
  ADD CONSTRAINT fk_lop_khoa FOREIGN KEY (khoa_hoc_id) REFERENCES khoa_hoc(id);
ALTER TABLE lop_hoc_vien
  ADD dang_ky_khoa_hoc_id BIGINT UNSIGNED NULL,
  ADD CONSTRAINT fk_lhv_dkk FOREIGN KEY (dang_ky_khoa_hoc_id) REFERENCES dang_ky_khoa_hoc(id);
ALTER TABLE buoi_hoc
  ADD pham_vi_bu ENUM('ca_lop','ca_nhan') NOT NULL DEFAULT 'ca_lop',
  ADD CONSTRAINT ck_buoi_pham_vi CHECK (loai='bu' OR pham_vi_bu='ca_lop');
ALTER TABLE phan_cong_buoi_hoc
  ADD quy_tac_cong TINYINT UNSIGNED NOT NULL DEFAULT 3,
  ADD gio_lich_bat_dau DATETIME NULL,
  ADD gio_lich_ket_thuc DATETIME NULL,
  ADD so_phut_tinh_cong INT GENERATED ALWAYS AS (IF(quy_tac_cong=3,so_phut_thuc_te,
    IF(checkin_luc IS NULL OR checkout_luc IS NULL OR gio_lich_bat_dau IS NULL OR gio_lich_ket_thuc IS NULL,NULL,
      GREATEST(0,TIMESTAMPDIFF(MINUTE,GREATEST(checkin_luc,gio_lich_bat_dau),LEAST(checkout_luc,gio_lich_ket_thuc)))))) STORED,
  ADD luong_sua_boi BIGINT UNSIGNED NULL,
  ADD CONSTRAINT fk_pc_luong_sua FOREIGN KEY (luong_sua_boi) REFERENCES users(user_id),
  ADD CONSTRAINT ck_pc_quy_tac CHECK (quy_tac_cong IN (3,4));
ALTER TABLE phan_cong_buoi_hoc ALTER COLUMN quy_tac_cong SET DEFAULT 4;
ALTER TABLE bang_cong_thang
  ADD mo_khoa_boi BIGINT UNSIGNED NULL,
  ADD mo_khoa_luc DATETIME NULL,
  ADD ly_do_mo_khoa VARCHAR(255) NULL,
  ADD CONSTRAINT fk_bc_mo FOREIGN KEY (mo_khoa_boi) REFERENCES users(user_id);
ALTER TABLE hoa_don
  MODIFY lop_hoc_vien_id BIGINT UNSIGNED NULL,
  MODIFY thang TINYINT UNSIGNED NULL,
  MODIFY nam SMALLINT UNSIGNED NULL;

ALTER TABLE hoa_don
  ADD dang_ky_khoa_hoc_id BIGINT UNSIGNED NULL,
  ADD CONSTRAINT fk_hd_dkk FOREIGN KEY (dang_ky_khoa_hoc_id) REFERENCES dang_ky_khoa_hoc(id),
  ADD UNIQUE KEY uq_hd_dkk (dang_ky_khoa_hoc_id),
  ADD CONSTRAINT ck_hd_nguon CHECK ((dang_ky_khoa_hoc_id IS NOT NULL AND lop_hoc_vien_id IS NULL AND thang IS NULL AND nam IS NULL)
    OR (dang_ky_khoa_hoc_id IS NULL AND lop_hoc_vien_id IS NOT NULL AND thang IS NOT NULL AND nam IS NOT NULL));
-- [THÊM] Nghỉ nguyên buổi của lớp cần Admin duyệt; không tự sinh tiền hoàn hoặc hạn mức bù.
CREATE TABLE yeu_cau_nghi_lop (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  buoi_hoc_id BIGINT UNSIGNED NOT NULL,
  nguoi_gui_id BIGINT UNSIGNED NOT NULL,
  ly_do VARCHAR(500) NOT NULL,
  trang_thai ENUM('cho_duyet','da_duyet','tu_choi') NOT NULL DEFAULT 'cho_duyet',
  nguoi_duyet_id BIGINT UNSIGNED NULL,
  duyet_luc DATETIME NULL,
  ghi_chu VARCHAR(500) NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_nl_buoi FOREIGN KEY (buoi_hoc_id) REFERENCES buoi_hoc(id),
  CONSTRAINT fk_nl_gui FOREIGN KEY (nguoi_gui_id) REFERENCES users(user_id),
  CONSTRAINT fk_nl_duyet FOREIGN KEY (nguoi_duyet_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- [THÊM] Giáo vụ chỉ xếp lịch bù theo thỏa thuận. Không có hạn mức hoặc khoản tiền học bù.
CREATE TABLE hoc_bu_hoc_vien (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  buoi_bu_id BIGINT UNSIGNED NOT NULL,
  hoc_vien_id BIGINT UNSIGNED NOT NULL,
  don_xin_nghi_id BIGINT UNSIGNED NULL,
  xep_boi BIGINT UNSIGNED NOT NULL,
  ghi_chu VARCHAR(500) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hb_buoi_hv (buoi_bu_id,hoc_vien_id),
  CONSTRAINT fk_hb_buoi FOREIGN KEY (buoi_bu_id) REFERENCES buoi_hoc(id),
  CONSTRAINT fk_hb_hv FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id),
  CONSTRAINT fk_hb_don FOREIGN KEY (don_xin_nghi_id) REFERENCES don_xin_nghi(id),
  CONSTRAINT fk_hb_xep FOREIGN KEY (xep_boi) REFERENCES users(user_id)
) ENGINE=InnoDB;
