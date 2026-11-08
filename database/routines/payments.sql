-- [THÊM] 005: Chuẩn bị thu học phí online; API phải xác thực actor và xác minh giao dịch thật.
SET NAMES utf8mb4;
DELIMITER $$
DROP FUNCTION IF EXISTS fn_phu_huynh_hoa_don$$
CREATE FUNCTION fn_phu_huynh_hoa_don(p_hd BIGINT UNSIGNED)
RETURNS BIGINT UNSIGNED READS SQL DATA NOT DETERMINISTIC
BEGIN
  RETURN (SELECT hv.phu_huynh_user_id FROM hoa_don h
    LEFT JOIN dang_ky_khoa_hoc d ON d.id=h.dang_ky_khoa_hoc_id
    LEFT JOIN lop_hoc_vien l ON l.id=h.lop_hoc_vien_id
    JOIN hoc_vien hv ON hv.id=COALESCE(d.hoc_vien_id,l.hoc_vien_id) WHERE h.id=p_hd);
END$$

DROP PROCEDURE IF EXISTS sp_kiem_tra_giao_dich$$
CREATE PROCEDURE sp_kiem_tra_giao_dich(IN p_hd BIGINT UNSIGNED,IN p_ph BIGINT UNSIGNED,IN p_tien DECIMAL(12,0))
BEGIN
  DECLARE v_tt VARCHAR(30);
  DECLARE v_du DECIMAL(12,0);
  SELECT trang_thai,phai_thu-da_thu INTO v_tt,v_du FROM hoa_don WHERE id=p_hd FOR UPDATE;
  IF v_tt IS NULL OR v_tt IN ('nhap','huy','da_thanh_toan') OR p_tien IS NULL OR p_tien<=0 OR p_tien<>v_du THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Thanh toán phải đúng số tiền còn phải thu của hóa đơn đã phát hành.';
  END IF;
  IF p_ph IS NULL OR p_ph<>fn_phu_huynh_hoa_don(p_hd) OR NOT EXISTS(
    SELECT 1 FROM account a JOIN users u ON u.user_id=a.user_id JOIN role_group g ON g.group_id=a.group_id
    WHERE a.user_id=p_ph AND a.status='active' AND a.is_deleted=0 AND u.is_deleted=0 AND g.is_deleted=0 AND g.group_name='PhuHuynh') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Giao dịch phải thuộc phụ huynh của học viên trên hóa đơn.';
  END IF;
END$$

-- [THÊM] Admin đối soát tiền đã nhận. Khóa hóa đơn trước giao dịch; bấm/gửi lại không tạo phiếu thu thứ hai.
-- API chỉ gọi sau đối soát ngân hàng hoặc webhook đã kiểm tra chữ ký và merchant/amount/currency.
DROP PROCEDURE IF EXISTS sp_xac_nhan_giao_dich$$
CREATE PROCEDURE sp_xac_nhan_giao_dich(IN p_gd BIGINT UNSIGNED,IN p_admin BIGINT UNSIGNED)
BEGIN
  DECLARE v_hd BIGINT UNSIGNED;
  DECLARE v_lock BIGINT UNSIGNED;
  DECLARE v_tien DECIMAL(12,0);
  DECLARE v_tt VARCHAR(30);
  DECLARE v_phieu BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  IF fn_la_admin(p_admin)<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chỉ Admin xác nhận tiền đã nhận.'; END IF;
  SELECT hoa_don_id INTO v_hd FROM giao_dich_truc_tuyen WHERE id=p_gd;
  IF v_hd IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không tìm thấy giao dịch.'; END IF;
  START TRANSACTION;
  SELECT id INTO v_lock FROM hoa_don WHERE id=v_hd FOR UPDATE;
  SELECT so_tien,trang_thai,thanh_toan_id INTO v_tien,v_tt,v_phieu FROM giao_dich_truc_tuyen WHERE id=p_gd FOR UPDATE;
  IF v_tt='thanh_cong' THEN
    COMMIT;
    SELECT v_phieu AS thanh_toan_id;
  ELSE
    IF v_tt<>'cho_xac_nhan' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chỉ xác nhận giao dịch đang chờ đối soát.'; END IF;
    INSERT INTO thanh_toan(hoa_don_id,so_tien,phuong_thuc,nguoi_thu_id,ghi_chu)
      VALUES(v_hd,v_tien,'chuyen_khoan',p_admin,CONCAT('Đối soát giao dịch online #',p_gd));
    SET v_phieu=LAST_INSERT_ID();
    UPDATE giao_dich_truc_tuyen SET trang_thai='thanh_cong',thanh_toan_id=v_phieu,
      xac_nhan_boi=p_admin,xac_nhan_luc=NOW() WHERE id=p_gd;
    INSERT INTO audit_log(user_id,hanh_dong,bang,ban_ghi_id,du_lieu_moi)
      VALUES(p_admin,'duyet','giao_dich_truc_tuyen',p_gd,JSON_OBJECT('thanh_toan_id',v_phieu));
    COMMIT;
    SELECT v_phieu AS thanh_toan_id;
  END IF;
END$$
DELIMITER ;
