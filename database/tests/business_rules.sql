-- [THÊM] Nghiệm thu 004 theo quyết định người dùng; chỉ chạy trên demo _test dùng một lần.
SET SESSION transaction_isolation='READ-COMMITTED';
DELIMITER $$
DROP PROCEDURE IF EXISTS test_business_rules$$
CREATE PROCEDURE test_business_rules()
BEGIN
  DECLARE v_n INT DEFAULT 0;
  DECLARE v_failed BOOL DEFAULT FALSE;
  DECLARE v_pc BIGINT UNSIGNED;
  DECLARE v_b BIGINT UNSIGNED;
  DECLARE v_k BIGINT UNSIGNED;
  DECLARE v_d BIGINT UNSIGNED;
  DECLARE v_hd BIGINT UNSIGNED;
  DECLARE v_don BIGINT UNSIGNED;
  DECLARE v_bu BIGINT UNSIGNED;
  DECLARE v_request BIGINT UNSIGNED;
  DECLARE v_i INT DEFAULT 0;
  DECLARE v_count INT;
  DECLARE v_old DECIMAL(12,0);
  DECLARE v_future BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  IF DATABASE() NOT LIKE '%\_test' OR (SELECT COUNT(*) FROM users)<>8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chỉ chạy suite 004 trên demo _test.';
  END IF;
  START TRANSACTION;
  UPDATE account SET status='active' WHERE user_id IN (1,2);
  INSERT INTO buoi_hoc(lop_id,ngay,gio_bat_dau,gio_ket_thuc) VALUES(1,'2026-12-20','18:00','20:00');
  SET v_b=LAST_INSERT_ID();
  INSERT INTO phan_cong_buoi_hoc(buoi_hoc_id,user_id,vai_tro) VALUES(v_b,3,'day_chinh');
  SET v_pc=LAST_INSERT_ID();
  UPDATE phan_cong_buoi_hoc SET checkin_luc='2026-12-20 17:50',checkout_luc='2026-12-20 20:10' WHERE id=v_pc;
  IF NOT EXISTS(SELECT 1 FROM phan_cong_buoi_hoc WHERE id=v_pc AND so_phut_thuc_te=140 AND so_phut_tinh_cong=120 AND checkin_tre=0) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Đến sớm/về muộn chỉ tính 120 phút';
  END IF; SET v_n=v_n+1;
  UPDATE phan_cong_buoi_hoc SET checkin_luc='2026-12-20 18:10',checkout_luc='2026-12-20 20:05',checkin_tre=0 WHERE id=v_pc;
  IF NOT EXISTS(SELECT 1 FROM phan_cong_buoi_hoc WHERE id=v_pc AND so_phut_tinh_cong=110 AND checkin_tre=1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Đến trễ phải tính 110 phút và đánh trễ';
  END IF; SET v_n=v_n+1;
  UPDATE phan_cong_buoi_hoc SET checkout_luc='2026-12-20 19:30' WHERE id=v_pc;
  IF NOT EXISTS(SELECT 1 FROM phan_cong_buoi_hoc WHERE id=v_pc AND so_phut_tinh_cong=80 AND checkin_tre=1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Đến trễ/về sớm phải tính 80 phút';
  END IF; SET v_n=v_n+1;
  UPDATE phan_cong_buoi_hoc SET checkin_luc='2026-12-20 17:50' WHERE id=v_pc;
  IF NOT EXISTS(SELECT 1 FROM phan_cong_buoi_hoc WHERE id=v_pc AND so_phut_tinh_cong=90 AND checkin_tre=0) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Về sớm phải tính 90 phút';
  END IF; SET v_n=v_n+1;
  IF fn_tien_cong('theo_gio',60000,110)<>110000 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Đơn giá tiền/giờ'; END IF;
  SET v_n=v_n+1;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE phan_cong_buoi_hoc SET quy_tac_cong=3 WHERE id=v_pc;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Hạ quy tắc công'; END IF; SET v_n=v_n+1;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE phan_cong_buoi_hoc SET don_gia=1,luong_sua_boi=2 WHERE id=v_pc;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Giáo vụ sửa đơn giá'; END IF; SET v_n=v_n+1;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE buoi_hoc b JOIN phan_cong_buoi_hoc p ON p.buoi_hoc_id=b.id SET b.gio_bat_dau='18:15' WHERE p.id=v_pc;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Sửa lịch sau check-in'; END IF; SET v_n=v_n+1;

  INSERT INTO khoa_hoc(ma,ten,hoc_phi) VALUES('004-CB','Học kỳ 1 cơ bản',1000000); SET v_k=LAST_INSERT_ID();
  INSERT INTO dang_ky_khoa_hoc(khoa_hoc_id,hoc_vien_id,hoc_phi_snapshot) VALUES(v_k,1,1); SET v_d=LAST_INSERT_ID();
  IF (SELECT hoc_phi_snapshot FROM dang_ky_khoa_hoc WHERE id=v_d)<>1000000 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Snapshot giá khóa'; END IF; SET v_n=v_n+1;
  UPDATE khoa_hoc SET hoc_phi=2000000 WHERE id=v_k;
  IF (SELECT hoc_phi_snapshot FROM dang_ky_khoa_hoc WHERE id=v_d)<>1000000 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Đổi giá khóa làm đổi đăng ký cũ'; END IF; SET v_n=v_n+1;
  UPDATE lop_hoc SET khoa_hoc_id=v_k WHERE id=1;
  UPDATE lop_hoc_vien SET dang_ky_khoa_hoc_id=v_d WHERE id=1;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE lop_hoc_vien SET dang_ky_khoa_hoc_id=v_d WHERE id=2;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Ghi danh sai học viên/khóa'; END IF; SET v_n=v_n+1;
  INSERT INTO hoa_don(ma_hd,dang_ky_khoa_hoc_id,tong_tien,nguoi_lap_id) VALUES('HD-004',v_d,1000000,2); SET v_hd=LAST_INSERT_ID();
  IF NOT EXISTS(SELECT 1 FROM hoa_don WHERE id=v_hd AND thang IS NULL AND nam IS NULL AND lop_hoc_vien_id IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Hóa đơn khóa bị ép kỳ tháng/lớp';
  END IF; SET v_n=v_n+1;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET v_failed=TRUE;
    INSERT INTO hoa_don(ma_hd,dang_ky_khoa_hoc_id,tong_tien,nguoi_lap_id) VALUES('HD-004-DUP',v_d,1000000,2);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Thu trùng toàn khóa'; END IF; SET v_n=v_n+1;
  UPDATE hoa_don SET trang_thai='chua_thanh_toan',phat_hanh_boi=2,phat_hanh_luc=NOW() WHERE id=v_hd;
  INSERT INTO thanh_toan(hoa_don_id,so_tien,phuong_thuc,nguoi_thu_id) VALUES(v_hd,1000000,'tien_mat',2);
  UPDATE dang_ky_khoa_hoc SET trang_thai='thoi_hoc' WHERE id=v_d;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    INSERT INTO thanh_toan(hoa_don_id,loai,so_tien,phuong_thuc,nguoi_thu_id) VALUES(v_hd,'hoan',10000,'tien_mat',2);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Hoàn học phí khi tự thôi học'; END IF; SET v_n=v_n+1;

  SELECT id INTO v_b FROM buoi_hoc WHERE lop_id=2 AND ngay='2026-10-03';
  INSERT INTO don_xin_nghi(buoi_hoc_id,doi_tuong,hoc_vien_id,nguoi_gui_id,ly_do) VALUES(v_b,'hoc_vien',2,7,'Nghỉ cá nhân'); SET v_don=LAST_INSERT_ID();
  UPDATE don_xin_nghi SET trang_thai='da_duyet',nguoi_duyet_id=2,duyet_luc=NOW() WHERE id=v_don;
  IF (SELECT trang_thai FROM don_xin_nghi WHERE id=v_don)<>'da_duyet' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Giáo vụ duyệt nghỉ cá nhân'; END IF; SET v_n=v_n+1;
  SELECT COUNT(*) INTO v_count FROM hoa_don;
  WHILE v_i<12 DO
    INSERT INTO buoi_hoc(lop_id,loai,buoi_goc_id,pham_vi_bu,ngay,gio_bat_dau,gio_ket_thuc)
      VALUES(2,'bu',v_b,'ca_nhan',DATE_ADD('2026-12-01',INTERVAL v_i DAY),'16:00','18:00'); SET v_bu=LAST_INSERT_ID();
    INSERT INTO hoc_bu_hoc_vien(buoi_bu_id,hoc_vien_id,don_xin_nghi_id,xep_boi) VALUES(v_bu,2,v_don,2);
    SET v_i=v_i+1;
  END WHILE;
  IF v_count<>(SELECT COUNT(*) FROM hoa_don) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Lịch bù tự sinh phí'; END IF; SET v_n=v_n+1;
  IF (SELECT trang_thai FROM buoi_hoc WHERE id=v_b)<>'da_day' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Bù cá nhân làm hủy lớp gốc'; END IF; SET v_n=v_n+1;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    INSERT INTO so_theo_doi_buoi_hoc(buoi_hoc_id,hoc_vien_id,trang_thai_diem_danh,nguoi_nhap_id) VALUES(v_bu,3,'co_mat',5);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Điểm danh học viên không được xếp bù'; END IF; SET v_n=v_n+1;
  INSERT INTO so_theo_doi_buoi_hoc(buoi_hoc_id,hoc_vien_id,trang_thai_diem_danh,diem_bai_tap_giay,nguoi_nhap_id) VALUES(v_bu,2,'co_mat',8,5);
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET v_failed=TRUE;
    INSERT INTO so_theo_doi_buoi_hoc(buoi_hoc_id,hoc_vien_id,trang_thai_diem_danh,diem_bai_tap_giay,nguoi_nhap_id) VALUES(v_bu,2,'co_mat',9,5);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Hai bài chấm trong một buổi/học viên'; END IF; SET v_n=v_n+1;
  SELECT id INTO v_b FROM buoi_hoc WHERE lop_id=2 AND ngay='2026-10-10';
  INSERT INTO yeu_cau_nghi_lop(buoi_hoc_id,nguoi_gui_id,ly_do) VALUES(v_b,2,'Lớp nghỉ'); SET v_request=LAST_INSERT_ID();
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE yeu_cau_nghi_lop SET trang_thai='da_duyet',nguoi_duyet_id=2,duyet_luc=NOW() WHERE id=v_request;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Giáo vụ duyệt nghỉ cả lớp'; END IF; SET v_n=v_n+1;
  UPDATE yeu_cau_nghi_lop SET trang_thai='da_duyet',nguoi_duyet_id=1,duyet_luc=NOW() WHERE id=v_request;
  IF (SELECT trang_thai FROM buoi_hoc WHERE id=v_b)<>'huy' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Admin duyệt không cập nhật buổi nghỉ'; END IF; SET v_n=v_n+1;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    INSERT INTO bang_cong_thang(user_id,thang,nam,trang_thai,chot_boi,chot_luc) VALUES(4,1,2027,'da_chot',2,NOW());
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Giáo vụ tự chốt công'; END IF; SET v_n=v_n+1;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE role_group_function p JOIN `function` f ON f.function_id=p.function_id SET p.view=1 WHERE p.group_id=3 AND f.name_function='ChamCongLuong';
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Cấp quyền lương ngoài Admin'; END IF; SET v_n=v_n+1;
  ROLLBACK;

  -- Các CALL bên dưới có transaction riêng, chỉ tác động demo dùng một lần.
  UPDATE account SET status='active' WHERE user_id IN (1,2);
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    CALL sp_chot_cong(4,1,2027,2);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Procedure cho giáo vụ chốt'; END IF; SET v_n=v_n+1;
  SET v_failed=FALSE; BEGIN DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    CALL sp_dat_don_gia_gio(1,90000,2);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Giáo vụ tăng lương'; END IF; SET v_n=v_n+1;
  SELECT pc.id,pc.don_gia INTO v_pc,v_old FROM phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id WHERE pc.user_id=3 AND b.ngay='2026-10-01';
  INSERT INTO buoi_hoc(lop_id,ngay,gio_bat_dau,gio_ket_thuc) VALUES(1,DATE_ADD(CURDATE(),INTERVAL 10 DAY),'20:10','22:10');SET v_b=LAST_INSERT_ID();
  INSERT INTO phan_cong_buoi_hoc(buoi_hoc_id,user_id,vai_tro) VALUES(v_b,3,'day_chinh');SET v_future=LAST_INSERT_ID();
  CALL sp_dat_don_gia_gio(1,90000,1);
  IF (SELECT don_gia FROM phan_cong_buoi_hoc WHERE id=v_pc)<>v_old THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Tăng lương làm đổi công đã dạy'; END IF; SET v_n=v_n+1;
  IF NOT EXISTS(SELECT 1 FROM phan_cong_buoi_hoc WHERE id=v_future AND don_gia=90000 AND cach_tinh_luong='theo_gio') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Mức lương mới không áp dụng ca tương lai'; END IF; SET v_n=v_n+1;
  UPDATE account SET status='locked' WHERE user_id IN (1,2);
  SELECT CONCAT('PASS: ',v_n,' business-rule cases for database 004.') AS ket_qua;
END$$
DELIMITER ;
CALL test_business_rules();
DROP PROCEDURE test_business_rules;

