-- [THÊM] Kiểm thử tích hợp chỉ chạy trên database riêng có hậu tố _test, đã init + seed demo.
-- [LƯU Ý] Chạy bằng mysql --database=... --default-character-set=utf8mb4 < file này (không --force).
-- [LƯU Ý] Suite thay đổi dữ liệu demo trong transaction rồi ROLLBACK; không dùng trên dữ liệu thật.
SET SESSION transaction_isolation='READ-COMMITTED';
DELIMITER $$
DROP PROCEDURE IF EXISTS test_integrity$$
CREATE PROCEDURE test_integrity()
BEGIN
  DECLARE v_failed BOOL DEFAULT FALSE;
  DECLARE v_buoi BIGINT UNSIGNED;
  DECLARE v_num INT DEFAULT 0;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  IF DATABASE() NOT LIKE '%\_test' OR (SELECT COUNT(*) FROM users)<>8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Suite chỉ chạy trên database _test có đúng dữ liệu demo.';
  END IF;
  START TRANSACTION;
  SELECT id INTO v_buoi FROM buoi_hoc WHERE lop_id=1 AND ngay='2026-10-01';

  -- [THÊM] Khóa ngoại không cho học viên mồ côi.
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '23000' SET v_failed=TRUE;
    INSERT INTO hoc_vien(phu_huynh_user_id,ma_hv,ho_ten) VALUES(999999,'INVALID','Sai FK');
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: FK học viên'; END IF;
  SET v_num=v_num+1;

  -- [THÊM] Giá âm bị CHECK từ chối.
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET v_failed=TRUE;
    UPDATE lop_hoc SET hoc_phi_thang=-1 WHERE id=2;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: CHECK học phí tháng'; END IF;
  SET v_num=v_num+1;

  -- [THÊM] Sĩ số khi INSERT và khi chuyển trạng thái từ cho_xep_lop.
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    INSERT INTO lop_hoc_vien(lop_id,hoc_vien_id,ngay_vao) VALUES(1,2,'2026-10-01');
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Sĩ số INSERT'; END IF;
  SET v_num=v_num+1;
  INSERT INTO lop_hoc_vien(lop_id,hoc_vien_id,ngay_vao,trang_thai) VALUES(1,2,'2026-10-01','cho_xep_lop');
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE lop_hoc_vien SET trang_thai='dang_hoc' WHERE lop_id=1 AND hoc_vien_id=2;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Sĩ số UPDATE'; END IF;
  SET v_num=v_num+1;

  -- [THÊM] Hoàn quá thu và thu quá nợ đều bị chặn.
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    INSERT INTO thanh_toan(hoa_don_id,loai,so_tien,phuong_thuc,nguoi_thu_id) VALUES(1,'hoan',180001,'tien_mat',2);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Hoàn vượt số dư'; END IF;
  SET v_num=v_num+1;
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    INSERT INTO thanh_toan(hoa_don_id,loai,so_tien,phuong_thuc,nguoi_thu_id) VALUES(1,'thu',1,'tien_mat',2);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Thu vượt nợ'; END IF;
  SET v_num=v_num+1;
  INSERT INTO thanh_toan(hoa_don_id,loai,so_tien,phuong_thuc,nguoi_thu_id) VALUES(1,'hoan',50000,'tien_mat',2);
  IF NOT EXISTS(SELECT 1 FROM hoa_don WHERE id=1 AND da_thu=130000 AND trang_thai='mot_phan') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Lũy kế sau hoàn';
  END IF;
  SET v_num=v_num+1;

  -- [THÊM] Đổi hóa đơn của phiếu không được phép, hủy khoản thu làm âm dư cũng không được.
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE thanh_toan SET hoa_don_id=999 WHERE id=1;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Đổi danh tính phiếu'; END IF;
  SET v_num=v_num+1;
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE thanh_toan SET trang_thai='da_huy' WHERE id=1;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Hủy phiếu làm âm số dư'; END IF;
  SET v_num=v_num+1;

  -- [THÊM] Học bù không được tham chiếu chính nó/buổi chưa hủy.
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    INSERT INTO buoi_hoc(lop_id,loai,buoi_goc_id,ngay,gio_bat_dau,gio_ket_thuc) VALUES(1,'bu',v_buoi,'2026-11-01','10:00','11:00');
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Liên kết học bù'; END IF;
  SET v_num=v_num+1;

  -- [THÊM] Snapshot phân công mới được lưu từ cách tính của lớp.
  IF EXISTS (SELECT 1 FROM phan_cong_buoi_hoc WHERE cach_tinh_luong IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Snapshot phân công mới';
  END IF;
  SET v_num=v_num+1;
  ROLLBACK;
  SELECT CONCAT('PASS: ',v_num,' integration cases; test data rolled back.') AS ket_qua;
END$$
DELIMITER ;
CALL test_integrity();
DROP PROCEDURE test_integrity;
