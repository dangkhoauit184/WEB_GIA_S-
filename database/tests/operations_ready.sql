-- [THÊM] 005: Chỉ kiểm tra hạ tầng dữ liệu trên demo tạm; không gọi ngân hàng/gửi thông báo ra ngoài.
SET SESSION transaction_isolation='READ-COMMITTED';
DELIMITER $$
DROP PROCEDURE IF EXISTS test_operations_ready$$
CREATE PROCEDURE test_operations_ready()
BEGIN
  DECLARE v_n INT DEFAULT 0;
  DECLARE v_failed BOOL DEFAULT FALSE;
  DECLARE v_k BIGINT UNSIGNED;
  DECLARE v_d BIGINT UNSIGNED;
  DECLARE v_h BIGINT UNSIGNED;
  DECLARE v_g BIGINT UNSIGNED;
  DECLARE v_p BIGINT UNSIGNED;
  DECLARE v_b BIGINT UNSIGNED;
  DECLARE v_pc BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  IF DATABASE() NOT LIKE '%\_test' OR (SELECT COUNT(*) FROM users)<>8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chỉ chạy suite 005 trên demo _test.';
  END IF;
  UPDATE account SET status='active' WHERE user_id IN (1,2,7,8);
  INSERT INTO khoa_hoc(ma,ten,hoc_phi) VALUES('005-TEST','Khóa thử 005',1200000); SET v_k=LAST_INSERT_ID();
  INSERT INTO dang_ky_khoa_hoc(khoa_hoc_id,hoc_vien_id) VALUES(v_k,1); SET v_d=LAST_INSERT_ID();
  UPDATE lop_hoc SET khoa_hoc_id=v_k WHERE id=1;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE lop_hoc_vien SET dang_ky_khoa_hoc_id=v_d,xep_boi=2,xep_luc=NOW() WHERE id=1;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Giáo vụ xếp lớp khóa'; END IF; SET v_n=v_n+1;
  UPDATE lop_hoc_vien SET dang_ky_khoa_hoc_id=v_d,xep_boi=1,xep_luc=NOW() WHERE id=1;
  IF EXISTS(SELECT 1 FROM hoa_don WHERE dang_ky_khoa_hoc_id=v_d) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Xếp lớp phụ thuộc tiền'; END IF; SET v_n=v_n+1;
  INSERT INTO hoa_don(ma_hd,dang_ky_khoa_hoc_id,tong_tien,nguoi_lap_id) VALUES('HD-005',v_d,1200000,2); SET v_h=LAST_INSERT_ID();
  IF EXISTS(SELECT 1 FROM thong_bao WHERE khoa_chong_trung=CONCAT('hocphi:',v_h)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Nháp phát thông báo'; END IF; SET v_n=v_n+1;
  UPDATE hoa_don SET trang_thai='chua_thanh_toan',phat_hanh_boi=2,phat_hanh_luc=NOW() WHERE id=v_h;
  IF (SELECT COUNT(*) FROM thong_bao WHERE user_id=7 AND khoa_chong_trung=CONCAT('hocphi:',v_h))<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Thiếu thông báo phụ huynh'; END IF; SET v_n=v_n+1;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    INSERT INTO giao_dich_truc_tuyen(ma_yeu_cau,hoa_don_id,phu_huynh_id,nha_cung_cap,so_tien) VALUES('005-SAI-PH',v_h,8,'bank',1200000);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Sai phụ huynh thanh toán'; END IF; SET v_n=v_n+1;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    INSERT INTO giao_dich_truc_tuyen(ma_yeu_cau,hoa_don_id,phu_huynh_id,nha_cung_cap,so_tien) VALUES('005-SAI-TIEN',v_h,7,'bank',1);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Số tiền thanh toán sai'; END IF; SET v_n=v_n+1;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    INSERT INTO giao_dich_truc_tuyen(ma_yeu_cau,hoa_don_id,phu_huynh_id,nha_cung_cap,so_tien,trang_thai) VALUES('005-TU-DUYET',v_h,7,'bank',1200000,'thanh_cong');
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Tự tạo trạng thái thành công'; END IF; SET v_n=v_n+1;
  INSERT INTO giao_dich_truc_tuyen(ma_yeu_cau,hoa_don_id,phu_huynh_id,nha_cung_cap,so_tien) VALUES('005-OK',v_h,7,'bank',1200000); SET v_g=LAST_INSERT_ID();
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET v_failed=TRUE;
    INSERT INTO giao_dich_truc_tuyen(ma_yeu_cau,hoa_don_id,phu_huynh_id,nha_cung_cap,so_tien) VALUES('005-OK',v_h,7,'bank',1200000);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Trùng mã yêu cầu'; END IF; SET v_n=v_n+1;
  INSERT INTO su_kien_thanh_toan(giao_dich_id,nha_cung_cap,ma_su_kien,payload_sha256) VALUES(v_g,'bank','EV-005',REPEAT('a',64));
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET v_failed=TRUE;
    INSERT INTO su_kien_thanh_toan(giao_dich_id,nha_cung_cap,ma_su_kien,payload_sha256) VALUES(v_g,'bank','EV-005',REPEAT('a',64));
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Webhook trùng không bị chặn'; END IF; SET v_n=v_n+1;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET v_failed=TRUE;
    INSERT INTO su_kien_thanh_toan(giao_dich_id,nha_cung_cap,ma_su_kien,payload_sha256) VALUES(v_g,'other','EV-005',REPEAT('a',64));
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Event sai nhà cung cấp'; END IF; SET v_n=v_n+1;
  IF (SELECT da_thu FROM hoa_don WHERE id=v_h)<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Callback tự ghi đã nhận tiền'; END IF; SET v_n=v_n+1;
  UPDATE giao_dich_truc_tuyen SET trang_thai='cho_xac_nhan',ma_giao_dich_cong='BANK-005' WHERE id=v_g;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    CALL sp_xac_nhan_giao_dich(v_g,2);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Giáo vụ đối soát nhận tiền'; END IF; SET v_n=v_n+1;
  CALL sp_xac_nhan_giao_dich(v_g,1);
  SELECT thanh_toan_id INTO v_p FROM giao_dich_truc_tuyen WHERE id=v_g;
  CALL sp_xac_nhan_giao_dich(v_g,1);
  IF (SELECT COUNT(*) FROM thanh_toan WHERE hoa_don_id=v_h)<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Gửi lại tạo thu tiền trùng'; END IF; SET v_n=v_n+1;
  IF NOT EXISTS(SELECT 1 FROM hoa_don WHERE id=v_h AND da_thu=1200000 AND trang_thai='da_thanh_toan') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Lệch số dư sau xác nhận'; END IF; SET v_n=v_n+1;
  IF (SELECT COUNT(*) FROM thong_bao WHERE user_id IN (1,2,7) AND khoa_chong_trung=CONCAT('thu:',v_p))<>3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Thiếu thông báo Admin/giáo vụ/phụ huynh'; END IF; SET v_n=v_n+1;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE giao_dich_truc_tuyen SET trang_thai='khoi_tao' WHERE id=v_g;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Sửa giao dịch đã hoàn tất'; END IF; SET v_n=v_n+1;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    INSERT INTO giao_dich_truc_tuyen(ma_yeu_cau,hoa_don_id,phu_huynh_id,nha_cung_cap,so_tien) VALUES('005-PAID',v_h,7,'bank',1200000);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Thanh toán lại hóa đơn đủ tiền'; END IF; SET v_n=v_n+1;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE su_kien_thanh_toan SET payload_sha256=REPEAT('b',64) WHERE giao_dich_id=v_g;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Sửa bằng chứng sự kiện'; END IF; SET v_n=v_n+1;
  INSERT INTO buoi_hoc(lop_id,ngay,gio_bat_dau,gio_ket_thuc) VALUES(2,'2027-04-01','18:00','20:00'); SET v_b=LAST_INSERT_ID();
  INSERT INTO phan_cong_buoi_hoc(buoi_hoc_id,user_id,vai_tro,checkin_luc,checkout_luc)
    VALUES(v_b,5,'day_chinh','2027-04-01 06:00','2027-04-02 03:00'); SET v_pc=LAST_INSERT_ID();
  IF NOT EXISTS(SELECT 1 FROM phan_cong_buoi_hoc WHERE id=v_pc AND so_phut_tinh_cong=120 AND checkin_tre=0 AND checkin_lat IS NULL AND checkin_lng IS NULL) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Không cho check-in sớm/out muộn không GPS'; END IF; SET v_n=v_n+1;
  INSERT INTO buoi_hoc(lop_id,loai,buoi_goc_id,pham_vi_bu,ngay,gio_bat_dau,gio_ket_thuc)
    VALUES(2,'bu',v_b,'ca_nhan','2027-04-03','18:00','20:00'); SET v_b=LAST_INSERT_ID();
  INSERT INTO hoc_bu_hoc_vien(buoi_bu_id,hoc_vien_id,xep_boi) VALUES(v_b,2,2);
  IF (SELECT don_xin_nghi_id FROM hoc_bu_hoc_vien WHERE buoi_bu_id=v_b) IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Bắt buộc đơn xin bù'; END IF; SET v_n=v_n+1;
  UPDATE account SET status='locked' WHERE user_id IN (1,2,7,8);
  SELECT CONCAT('PASS: ',v_n,' operations-ready cases for database 005.') AS ket_qua;
END$$
DELIMITER ;
CALL test_operations_ready();
DROP PROCEDURE test_operations_ready;
