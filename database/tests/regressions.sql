-- [THÊM] Kiểm tra các lỗi được phát hiện khi rà soát phiên bản 002.
-- [LƯU Ý] Chỉ dùng demo _test một lần; gọi procedure chốt công có transaction riêng.
SET SESSION transaction_isolation='READ-COMMITTED';
DELIMITER $$
DROP PROCEDURE IF EXISTS test_regressions$$
CREATE PROCEDURE test_regressions()
BEGIN
  DECLARE v_failed BOOL DEFAULT FALSE;
  DECLARE v_num INT DEFAULT 0;
  DECLARE v_id BIGINT UNSIGNED;
  DECLARE v_pc BIGINT UNSIGNED;
  DECLARE v_money DECIMAL(14,0);
  IF DATABASE() NOT LIKE '%\_test' OR (SELECT COUNT(*) FROM users)<>8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chỉ chạy trên demo _test.';
  END IF;
  START TRANSACTION;
  UPDATE account SET status='active' WHERE user_id=1;
  IF fn_tien_cong('theo_gio',70000,95)<>110833 OR fn_tien_cong('theo_buoi',140000,127)<>140000 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Công thức snapshot/làm tròn tiền công';
  END IF;
  SET v_num=v_num+1;
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    SET v_money=fn_tien_cong(NULL,140000,60);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Hàm chấp nhận snapshot NULL'; END IF;
  SET v_num=v_num+1;
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE hoa_don SET lop_hoc_vien_id=2 WHERE id=1;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Chuyển nợ hóa đơn sang học viên khác'; END IF;
  SET v_num=v_num+1;
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE thanh_toan SET id=9999 WHERE id=1;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Đổi ID phiếu thu'; END IF;
  SET v_num=v_num+1;
  -- [THÊM] Kỳ đã chốt nhưng chưa có ca gắn bảng công: UPDATE buổi và DELETE phân công vẫn phải bị chặn.
  INSERT INTO bang_cong_thang(user_id,thang,nam,trang_thai,chot_boi,chot_luc) VALUES(3,11,2026,'da_chot',1,NOW());
  SELECT id INTO v_id FROM buoi_hoc WHERE lop_id=1 AND ngay='2026-10-06';
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE buoi_hoc SET ngay='2026-11-03' WHERE id=v_id;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Dời ca sang kỳ đã khóa'; END IF;
  SET v_num=v_num+1;
  INSERT INTO bang_cong_thang(user_id,thang,nam,trang_thai,chot_boi,chot_luc) VALUES(3,10,2026,'da_chot',1,NOW());
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    DELETE FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=v_id AND user_id=3;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Xóa ca không gắn công trong kỳ đã khóa'; END IF;
  SET v_num=v_num+1;
  ROLLBACK;
  START TRANSACTION;
  UPDATE account SET status='active' WHERE user_id=1;
  UPDATE buoi_hoc SET trang_thai='huy',nguoi_huy_id=1,huy_luc=NOW(),ly_do_huy_doi='Kiểm thử học bù' WHERE id=v_id;
  INSERT INTO buoi_hoc(lop_id,loai,buoi_goc_id,ngay,gio_bat_dau,gio_ket_thuc)
    VALUES(1,'bu',v_id,'2026-11-03','18:00','20:00');
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE buoi_hoc SET trang_thai='du_kien' WHERE id=v_id;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Khôi phục buổi gốc đã có ca bù'; END IF;
  SET v_num=v_num+1;
  ROLLBACK;

  -- [THÊM] Không chốt và âm thầm bỏ sót ca có công hợp lệ nhưng trạng thái chưa khớp.
  UPDATE account SET status='active' WHERE user_id=1;
  SELECT pc.id INTO v_pc FROM phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
    WHERE pc.user_id=3 AND b.ngay='2026-10-01';
  UPDATE phan_cong_buoi_hoc SET trang_thai='da_phan_cong' WHERE id=v_pc;
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    CALL sp_chot_cong(3,10,2026,1);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Bỏ sót trạng thái công chưa khớp'; END IF;
  SET v_num=v_num+1;
  UPDATE phan_cong_buoi_hoc SET trang_thai='da_day' WHERE id=v_pc;
  UPDATE account SET status='locked' WHERE user_id=1;
  SELECT CONCAT('PASS: ',v_num,' regression cases for database 002.') AS ket_qua;
END$$
DELIMITER ;
CALL test_regressions();
DROP PROCEDURE test_regressions;
