-- [THÊM] 003: chuẩn hóa thuộc tính và thêm dấu vết vận hành.
-- run.ps1 phải chạy 003_preflight.sql và dừng khi có VI_PHAM trước file này.
-- Sao lưu, dừng ghi ứng dụng. DDL tự COMMIT; lỗi giữa chừng giữ applying để kiểm tra.
INSERT INTO schema_migrations(version,status) VALUES('003_attribute_review','applying');
-- [SỬA] Gỡ trigger phụ thuộc cột sắp bỏ; toàn bộ modules được nạp lại sau ALTER.
DROP TRIGGER IF EXISTS trg_hdct_bi;
DROP TRIGGER IF EXISTS trg_hdct_bu;
ALTER TABLE hoa_don_chi_tiet DROP FOREIGN KEY fk_hdct_hv, DROP COLUMN hoc_vien_id;
-- [SỬA] Giữ doc_luc nguyên vẹn sau khi đã kiểm tra trạng thái đọc không mâu thuẫn.
ALTER TABLE thong_bao DROP INDEX idx_tb_user, DROP COLUMN da_doc;
ALTER TABLE thong_bao ADD COLUMN da_doc TINYINT(1) GENERATED ALWAYS AS (doc_luc IS NOT NULL) VIRTUAL,
  ADD KEY idx_tb_user (user_id,da_doc,created_at);
ALTER TABLE yeu_cau_hoc MODIFY COLUMN nhan_vien_id BIGINT UNSIGNED NULL;
ALTER TABLE users ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE account ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE yeu_cau_hoc ADD COLUMN nguoi_gui_id BIGINT UNSIGNED NULL;
ALTER TABLE yeu_cau_hoc ADD COLUMN don_vi_ngan_sach ENUM('theo_buoi','theo_gio','theo_thang','theo_khoa') NULL;
ALTER TABLE yeu_cau_hoc ADD CONSTRAINT fk_yc_gui FOREIGN KEY (nguoi_gui_id) REFERENCES users(user_id);
ALTER TABLE de_xuat_nhan_su ADD COLUMN cach_thu_phi_de_xuat ENUM('theo_buoi','theo_thang','theo_khoa') NULL;
ALTER TABLE de_xuat_nhan_su ADD COLUMN nhan_su_phan_hoi_luc DATETIME NULL;
ALTER TABLE de_xuat_nhan_su ADD COLUMN phu_huynh_phan_hoi_luc DATETIME NULL;
ALTER TABLE de_xuat_nhan_su ADD COLUMN phu_huynh_phan_hoi_boi BIGINT UNSIGNED NULL;
ALTER TABLE de_xuat_nhan_su ADD COLUMN ly_do_tu_choi_nhan_su VARCHAR(255) NULL;
ALTER TABLE de_xuat_nhan_su ADD COLUMN ly_do_tu_choi_phu_huynh VARCHAR(255) NULL;
ALTER TABLE de_xuat_nhan_su ADD CONSTRAINT fk_dx_ph_phan_hoi FOREIGN KEY (phu_huynh_phan_hoi_boi) REFERENCES users(user_id);
ALTER TABLE de_xuat_nhan_su ADD CONSTRAINT ck_dx_ly_do CHECK ((ly_do_tu_choi_nhan_su IS NULL OR tt_nhan_su='tu_choi') AND (ly_do_tu_choi_phu_huynh IS NULL OR tt_phu_huynh='tu_choi'));
ALTER TABLE buoi_hoc ADD COLUMN nguoi_huy_id BIGINT UNSIGNED NULL;
ALTER TABLE buoi_hoc ADD COLUMN huy_luc DATETIME NULL;
ALTER TABLE buoi_hoc ADD CONSTRAINT fk_buoi_nguoi_huy FOREIGN KEY (nguoi_huy_id) REFERENCES users(user_id);
ALTER TABLE buoi_hoc ADD CONSTRAINT ck_buoi_huy_meta CHECK ((nguoi_huy_id IS NULL) = (huy_luc IS NULL));
ALTER TABLE lop_hoc_vien ADD COLUMN created_at DATETIME NULL DEFAULT NULL;
ALTER TABLE lop_hoc_vien MODIFY COLUMN created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE lop_hoc_vien ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE bai_tap ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE ket_qua_hoc_tap ADD COLUMN created_at DATETIME NULL DEFAULT NULL;
ALTER TABLE ket_qua_hoc_tap MODIFY COLUMN created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE ket_qua_hoc_tap ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE hoa_don ADD COLUMN phat_hanh_luc DATETIME NULL;
ALTER TABLE hoa_don ADD COLUMN phat_hanh_boi BIGINT UNSIGNED NULL;
ALTER TABLE hoa_don ADD COLUMN huy_luc DATETIME NULL;
ALTER TABLE hoa_don ADD COLUMN huy_boi BIGINT UNSIGNED NULL;
ALTER TABLE hoa_don ADD COLUMN ly_do_huy VARCHAR(255) NULL;
ALTER TABLE hoa_don ADD CONSTRAINT fk_hd_phat_hanh FOREIGN KEY (phat_hanh_boi) REFERENCES users(user_id);
ALTER TABLE hoa_don ADD CONSTRAINT fk_hd_huy FOREIGN KEY (huy_boi) REFERENCES users(user_id);
ALTER TABLE hoa_don ADD CONSTRAINT ck_hd_phat_hanh_meta CHECK ((phat_hanh_luc IS NULL) = (phat_hanh_boi IS NULL));
ALTER TABLE hoa_don ADD CONSTRAINT ck_hd_huy_meta CHECK ((huy_luc IS NULL AND huy_boi IS NULL AND ly_do_huy IS NULL) OR (huy_luc IS NOT NULL AND huy_boi IS NOT NULL AND ly_do_huy IS NOT NULL AND CHAR_LENGTH(TRIM(ly_do_huy))>0));
ALTER TABLE thanh_toan ADD COLUMN huy_luc DATETIME NULL;
ALTER TABLE thanh_toan ADD COLUMN huy_boi BIGINT UNSIGNED NULL;
ALTER TABLE thanh_toan ADD COLUMN ly_do_huy VARCHAR(255) NULL;
ALTER TABLE thanh_toan ADD CONSTRAINT fk_tt_huy FOREIGN KEY (huy_boi) REFERENCES users(user_id);
ALTER TABLE thanh_toan ADD CONSTRAINT ck_tt_huy_meta CHECK ((huy_luc IS NULL AND huy_boi IS NULL AND ly_do_huy IS NULL) OR (huy_luc IS NOT NULL AND huy_boi IS NOT NULL AND ly_do_huy IS NOT NULL AND CHAR_LENGTH(TRIM(ly_do_huy))>0));
ALTER TABLE bang_cong_thang ADD COLUMN tra_boi BIGINT UNSIGNED NULL;
ALTER TABLE bang_cong_thang ADD COLUMN tra_luc DATETIME NULL;
ALTER TABLE bang_cong_thang ADD COLUMN ma_giao_dich_chi VARCHAR(60) NULL;
ALTER TABLE bang_cong_thang ADD CONSTRAINT fk_bc_tra FOREIGN KEY (tra_boi) REFERENCES users(user_id);
ALTER TABLE bang_cong_thang ADD CONSTRAINT ck_bc_tra_meta CHECK ((tra_boi IS NULL) = (tra_luc IS NULL));
ALTER TABLE thong_bao ADD COLUMN email_gui_luc DATETIME NULL;
ALTER TABLE thong_bao ADD COLUMN email_thu_gan_nhat_luc DATETIME NULL;
ALTER TABLE thong_bao ADD COLUMN email_thu_tiep_luc DATETIME NULL;
-- [SỬA] Chỉ chuyển lý do khi xác định được đúng một bên từ chối; preflight chặn trường hợp mơ hồ.
UPDATE de_xuat_nhan_su SET
  ly_do_tu_choi_nhan_su=IF(tt_nhan_su='tu_choi',ly_do_tu_choi,NULL),
  ly_do_tu_choi_phu_huynh=IF(tt_phu_huynh='tu_choi',ly_do_tu_choi,NULL),
  updated_at=updated_at;
ALTER TABLE de_xuat_nhan_su DROP COLUMN ly_do_tu_choi;
-- Đơn vị giá, người thao tác và các mốc thời gian cũ giữ NULL; không suy đoán lịch sử.
