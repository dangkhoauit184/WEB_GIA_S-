-- [THÊM] Hồi quy thuộc tính 003 trên demo dùng một lần _test; mọi dữ liệu được ROLLBACK.
SET SESSION transaction_isolation='READ-COMMITTED';
DELIMITER $$
DROP PROCEDURE IF EXISTS test_attributes$$
CREATE PROCEDURE test_attributes()
BEGIN
  DECLARE v_failed BOOL DEFAULT FALSE;
  DECLARE v_num INT DEFAULT 0;
  DECLARE v_yc BIGINT UNSIGNED;
  DECLARE v_hd BIGINT UNSIGNED;
  DECLARE v_bc BIGINT UNSIGNED;
  DECLARE v_buoi BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  IF DATABASE() NOT LIKE '%\_test' OR (SELECT COUNT(*) FROM users)<>8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chỉ chạy trên demo _test.';
  END IF;
  START TRANSACTION;
  UPDATE thong_bao SET doc_luc='2026-11-03 10:00:00' WHERE user_id=3;
  IF EXISTS(SELECT 1 FROM thong_bao WHERE user_id=3 AND da_doc<>1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Đã đọc không suy ra từ thời điểm đọc';
  END IF;
  SET v_num=v_num+1;
  UPDATE thong_bao SET doc_luc=NULL WHERE user_id=3;
  IF EXISTS(SELECT 1 FROM thong_bao WHERE user_id=3 AND da_doc<>0) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Bỏ đánh dấu đọc';
  END IF;
  SET v_num=v_num+1;
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET v_failed=TRUE;
    UPDATE thong_bao SET da_doc=1 WHERE user_id=3;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Cho ghi cột đọc tự tính'; END IF;
  SET v_num=v_num+1;

  -- Bỏ cột học viên dư vẫn phải chặn khoản buổi học thuộc lớp khác.
  SELECT id INTO v_buoi FROM buoi_hoc WHERE lop_id=2 ORDER BY id LIMIT 1;
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    INSERT INTO hoa_don_chi_tiet(hoa_don_id,buoi_hoc_id,don_gia) VALUES(1,v_buoi,1000);
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Chi tiết khác lớp'; END IF;
  SET v_num=v_num+1;

  INSERT INTO yeu_cau_hoc(ma_yc,hoc_vien_id,mon_hoc_id,cap_lop_id,nguon,nguoi_gui_id,ngan_sach_max,don_vi_ngan_sach)
    VALUES('YC-003-WEB',1,1,9,'website',7,200000,'theo_buoi');
  SET v_yc=LAST_INSERT_ID();
  IF NOT EXISTS(SELECT 1 FROM yeu_cau_hoc WHERE id=v_yc AND nhan_vien_id IS NULL AND nguoi_gui_id=7) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Yêu cầu web chưa phân công';
  END IF;
  SET v_num=v_num+1;
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '23000' SET v_failed=TRUE;
    UPDATE yeu_cau_hoc SET nguoi_gui_id=999999 WHERE id=v_yc;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: FK người gửi'; END IF;
  SET v_num=v_num+1;
  INSERT INTO de_xuat_nhan_su(yeu_cau_id,nhan_su_id,nhan_vien_id,tt_nhan_su,tt_phu_huynh,
    ly_do_tu_choi_nhan_su,ly_do_tu_choi_phu_huynh,phu_huynh_phan_hoi_boi)
    VALUES(v_yc,1,1,'tu_choi','tu_choi','Bận lịch','Chưa phù hợp',7);
  IF NOT EXISTS(SELECT 1 FROM de_xuat_nhan_su WHERE yeu_cau_id=v_yc
    AND ly_do_tu_choi_nhan_su='Bận lịch' AND ly_do_tu_choi_phu_huynh='Chưa phù hợp') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Lý do hai bên ghi đè nhau';
  END IF;
  SET v_num=v_num+1;
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION SET v_failed=TRUE;
    UPDATE de_xuat_nhan_su SET tt_nhan_su='dong_y' WHERE yeu_cau_id=v_yc;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Lý do từ chối sai trạng thái'; END IF;
  SET v_num=v_num+1;

  INSERT INTO hoa_don(ma_hd,lop_hoc_vien_id,thang,nam,tong_tien,nguoi_lap_id)
    VALUES('HD-003',1,12,2026,100000,2);
  SET v_hd=LAST_INSERT_ID();
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE hoa_don SET trang_thai='chua_thanh_toan' WHERE id=v_hd;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Phát hành thiếu dấu vết'; END IF;
  SET v_num=v_num+1;
  UPDATE hoa_don SET trang_thai='chua_thanh_toan',phat_hanh_boi=2,phat_hanh_luc=NOW() WHERE id=v_hd;
  IF NOT EXISTS(SELECT 1 FROM hoa_don WHERE id=v_hd AND trang_thai='chua_thanh_toan' AND phat_hanh_boi=2) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Phát hành hợp lệ';
  END IF;
  SET v_num=v_num+1;
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE hoa_don SET trang_thai='huy' WHERE id=v_hd;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Hủy hóa đơn thiếu dấu vết'; END IF;
  SET v_num=v_num+1;
  UPDATE hoa_don SET trang_thai='huy',huy_boi=2,huy_luc=NOW(),ly_do_huy='Lập nhầm kỳ' WHERE id=v_hd;
  IF NOT EXISTS(SELECT 1 FROM hoa_don WHERE id=v_hd AND trang_thai='huy' AND ly_do_huy='Lập nhầm kỳ') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Hủy hóa đơn hợp lệ';
  END IF;
  SET v_num=v_num+1;
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE thanh_toan SET trang_thai='da_huy' WHERE id=1;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Hủy phiếu thiếu dấu vết'; END IF;
  SET v_num=v_num+1;
  UPDATE thanh_toan SET trang_thai='da_huy',huy_boi=2,huy_luc=NOW(),ly_do_huy='Phiếu trùng' WHERE id=1;
  IF NOT EXISTS(SELECT 1 FROM hoa_don WHERE id=1 AND da_thu=0 AND trang_thai='chua_thanh_toan') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Hủy phiếu không cập nhật số dư';
  END IF;
  SET v_num=v_num+1;

  INSERT INTO bang_cong_thang(user_id,thang,nam) VALUES(4,12,2026);
  SET v_bc=LAST_INSERT_ID();
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE bang_cong_thang SET trang_thai='da_tra',tra_boi=2,tra_luc=NOW(),ngay_tra=CURDATE() WHERE id=v_bc;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Trả lương chưa chốt'; END IF;
  SET v_num=v_num+1;
  UPDATE bang_cong_thang SET trang_thai='da_chot' WHERE id=v_bc;
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE bang_cong_thang SET trang_thai='da_tra' WHERE id=v_bc;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Trả lương thiếu người/thời điểm'; END IF;
  SET v_num=v_num+1;
  UPDATE bang_cong_thang SET trang_thai='da_tra',tra_boi=2,tra_luc=NOW(),ngay_tra=CURDATE(),ma_giao_dich_chi='CHI-003' WHERE id=v_bc;
  IF NOT EXISTS(SELECT 1 FROM bang_cong_thang WHERE id=v_bc AND trang_thai='da_tra' AND ma_giao_dich_chi='CHI-003') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Trả lương hợp lệ';
  END IF;
  SET v_num=v_num+1;
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE bang_cong_thang SET tong_thu_nhap=1000 WHERE id=v_bc;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Sửa kỳ đã trả'; END IF;
  SET v_num=v_num+1;

  SELECT id INTO v_buoi FROM buoi_hoc WHERE lop_id=1 AND ngay='2026-10-06';
  SET v_failed=FALSE;
  BEGIN
    DECLARE CONTINUE HANDLER FOR SQLSTATE '45000' SET v_failed=TRUE;
    UPDATE buoi_hoc SET trang_thai='huy' WHERE id=v_buoi;
  END;
  IF NOT v_failed THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Hủy buổi thiếu dấu vết'; END IF;
  SET v_num=v_num+1;
  UPDATE buoi_hoc SET trang_thai='huy',nguoi_huy_id=2,huy_luc=NOW(),ly_do_huy_doi='Báo nghỉ' WHERE id=v_buoi;
  IF NOT EXISTS(SELECT 1 FROM buoi_hoc WHERE id=v_buoi AND trang_thai='huy' AND nguoi_huy_id=2) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='FAIL: Hủy buổi hợp lệ';
  END IF;
  SET v_num=v_num+1;
  ROLLBACK;
  SELECT CONCAT('PASS: ',v_num,' attribute cases for database 003; rolled back.') AS ket_qua;
END$$
DELIMITER ;
CALL test_attributes();
DROP PROCEDURE test_attributes;
