-- [THÊM] Suite quy trình trên database dùng một lần _test, đã init + demo; có chốt/mở công thật trong DB thử nghiệm.
-- [LƯU Ý] Các CALL quản lý transaction riêng nên suite này KHÔNG cam kết rollback toàn bộ dữ liệu demo.
SET SESSION transaction_isolation='READ-COMMITTED';
DELIMITER $$
DROP PROCEDURE IF EXISTS test_workflows$$
CREATE PROCEDURE test_workflows()
BEGIN
  DECLARE v_failed BOOL DEFAULT FALSE;
  DECLARE v_count INT;
  DECLARE v_buoi BIGINT UNSIGNED;
  DECLARE v_target BIGINT UNSIGNED;
  DECLARE v_pc BIGINT UNSIGNED;
  DECLARE v_bc BIGINT UNSIGNED;
  DECLARE v_amount DECIMAL(12,0);
  DECLARE v_num INT DEFAULT 0;
  IF DATABASE() NOT LIKE '%\_test' OR (SELECT COUNT(*) FROM users)<>8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chỉ chạy workflow trên demo database _test.';
  END IF;
  SELECT COUNT(*) INTO v_count FROM buoi_hoc;
  CALL sp_sinh_buoi_hoc(1,'2026-10-01','2026-10-31');
  IF v_count<>(SELECT COUNT(*) FROM buoi_hoc) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Sinh lại tạo trùng buổi'; END IF;
  SET v_num=v_num+1;

  START TRANSACTION;
  UPDATE account SET status='active' WHERE user_id=1;
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    INSERT INTO buoi_hoc(lop_id,ngay,gio_bat_dau,gio_ket_thuc,phong_hoc,co_so_id) VALUES(1,'2026-10-03','10:00','12:00','Phòng 201',1);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Trùng phòng INSERT'; END IF;
  SET v_num=v_num+1;
  INSERT INTO buoi_hoc(lop_id,ngay,gio_bat_dau,gio_ket_thuc) VALUES(2,'2026-10-01','18:30','19:30');
  SET v_target=LAST_INSERT_ID();
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    INSERT INTO phan_cong_buoi_hoc(buoi_hoc_id,user_id,vai_tro,don_gia) VALUES(v_target,3,'tro_giang',50000);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Trùng nhân sự INSERT'; END IF;
  SET v_num=v_num+1;
  INSERT INTO phan_cong_buoi_hoc(buoi_hoc_id,user_id,vai_tro,don_gia) VALUES(v_target,6,'tro_giang',50000);
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE buoi_hoc SET ngay='2026-10-03',gio_bat_dau='09:30',gio_ket_thuc='10:30' WHERE id=v_target;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Đổi giờ gây trùng trợ giảng'; END IF;
  SET v_num=v_num+1;
  -- [THÊM] Khác cơ sở được dùng cùng tên phòng và cùng giờ.
  INSERT INTO buoi_hoc(lop_id,ngay,gio_bat_dau,gio_ket_thuc,phong_hoc,co_so_id) VALUES(1,'2026-10-03','10:00','12:00','Phòng 201',2);
  SET v_num=v_num+1;
  ROLLBACK;

  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE lop_hoc SET si_so_toi_thieu=1,si_so_toi_da=1 WHERE id=2;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Giảm sức chứa lớp'; END IF;
  SET v_num=v_num+1;

  -- [THÊM] Chỉ trên demo: mở tài khoản Admin để thử routine kiểm tra quyền.
  UPDATE account SET status='active' WHERE user_id IN (1,2);
  SELECT pc.id,pc.buoi_hoc_id INTO v_pc,v_buoi FROM phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
    WHERE pc.user_id=3 AND b.ngay='2026-10-01';
  INSERT INTO dieu_chinh_cong(phan_cong_id,loai,ly_do,nguoi_yeu_cau_id) VALUES(v_pc,'sua_gio','Kiểm thử chặn chốt',3);
  SET v_target=LAST_INSERT_ID();
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    CALL sp_chot_cong(3,10,2026,1);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Chốt khi còn điều chỉnh chờ'; END IF;
  SET v_num=v_num+1;
  UPDATE dieu_chinh_cong SET trang_thai='tu_choi',nguoi_duyet_id=1,duyet_luc=NOW() WHERE id=v_target;
  -- [THÊM] Đổi cách tính ở lớp không được thay snapshot theo_buoi của ca đã có.
  UPDATE lop_hoc SET cach_tinh_luong='theo_gio' WHERE id=1;
  CALL sp_chot_cong(3,10,2026,1);
  SELECT id,tong_thu_nhap INTO v_bc,v_amount FROM bang_cong_thang WHERE user_id=3 AND thang=10 AND nam=2026;
  IF v_amount<>140000 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Tính lương dùng giá lớp thay snapshot'; END IF;
  SET v_num=v_num+1;
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE phan_cong_buoi_hoc SET don_gia=1,bang_cong_id=NULL WHERE id=v_pc;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Vượt khóa ca công'; END IF;
  SET v_num=v_num+1;
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    CALL sp_mo_khoa_cong(v_bc,2,'Nhân viên thử mở');
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Nhân viên mở khóa'; END IF;
  SET v_num=v_num+1;
  CALL sp_mo_khoa_cong(v_bc,1,'Kiểm thử mở khóa đúng quyền');
  UPDATE phan_cong_buoi_hoc SET don_gia=150000,luong_sua_boi=1 WHERE id=v_pc;
  CALL sp_chot_cong(3,10,2026,1);
  IF NOT EXISTS (SELECT 1 FROM bang_cong_thang WHERE id=v_bc AND tong_thu_nhap=300000 AND trang_thai='da_chot') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Chốt lại kỳ đã mở';
  END IF;
  SET v_num=v_num+1;
  IF (SELECT COUNT(*) FROM audit_log WHERE ban_ghi_id=v_bc AND bang='bang_cong_thang')<>3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Audit chốt/mở khóa';
  END IF;
  SET v_num=v_num+1;
  UPDATE account SET status='locked' WHERE user_id IN (1,2);
  SELECT CONCAT('PASS: ',v_num,' workflow cases on disposable demo database.') AS ket_qua;
END$$
DELIMITER ;
CALL test_workflows();
DROP PROCEDURE test_workflows;
