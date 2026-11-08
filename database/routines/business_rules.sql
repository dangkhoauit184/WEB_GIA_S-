-- [THÊM] 004: Quy tắc xác nhận ngày 08/11/2026; API vẫn phải xác thực actor thật.
SET NAMES utf8mb4;
DELIMITER $$
DROP FUNCTION IF EXISTS fn_la_admin$$
CREATE FUNCTION fn_la_admin(p_user BIGINT UNSIGNED)
RETURNS TINYINT READS SQL DATA NOT DETERMINISTIC
BEGIN
  RETURN EXISTS(SELECT 1 FROM account a JOIN role_group g ON g.group_id=a.group_id
    JOIN users u ON u.user_id=a.user_id WHERE a.user_id=p_user AND a.status='active'
    AND a.is_deleted=0 AND u.is_deleted=0 AND g.is_deleted=0 AND g.group_name='Admin');
END$$

DROP FUNCTION IF EXISTS fn_la_giao_vu$$
CREATE FUNCTION fn_la_giao_vu(p_user BIGINT UNSIGNED)
RETURNS TINYINT READS SQL DATA NOT DETERMINISTIC
BEGIN
  RETURN EXISTS(SELECT 1 FROM account a JOIN role_group g ON g.group_id=a.group_id
    JOIN users u ON u.user_id=a.user_id WHERE a.user_id=p_user AND a.status='active'
    AND a.is_deleted=0 AND u.is_deleted=0 AND g.is_deleted=0 AND g.group_name IN ('Admin','NhanVien'));
END$$

-- [THÊM] Tăng lương từ lúc thay đổi. Khóa người dạy, cập nhật ca tương lai chưa check-in; giữ ca đã dạy.
DROP PROCEDURE IF EXISTS sp_dat_don_gia_gio$$
CREATE PROCEDURE sp_dat_don_gia_gio(IN p_nhan_su BIGINT UNSIGNED,IN p_gia DECIMAL(12,0),IN p_admin BIGINT UNSIGNED)
BEGIN
  DECLARE v_user BIGINT UNSIGNED;
  DECLARE v_old DECIMAL(12,0);
  DECLARE v_now DATETIME(6);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  IF fn_la_admin(p_admin)<>1 OR p_gia IS NULL OR p_gia<0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chỉ Admin được đặt đơn giá giờ không âm.';
  END IF;
  START TRANSACTION;
  SELECT user_id,don_gia_gio_chuan INTO v_user,v_old FROM nhan_su_chuyen_mon
    WHERE id=p_nhan_su AND deleted_at IS NULL FOR UPDATE;
  IF v_user IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không tìm thấy người dạy.'; END IF;
  SELECT user_id INTO v_user FROM users WHERE user_id=v_user FOR UPDATE;
  SET v_now=NOW(6);
  INSERT INTO lich_su_don_gia_gio(nhan_su_id,don_gia_gio,hieu_luc_tu,nguoi_dat_id)
    VALUES(p_nhan_su,p_gia,v_now,p_admin);
  UPDATE nhan_su_chuyen_mon SET don_gia_gio_chuan=p_gia WHERE id=p_nhan_su;
  UPDATE phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
    SET pc.don_gia=p_gia,pc.cach_tinh_luong='theo_gio',pc.luong_sua_boi=p_admin
    WHERE pc.user_id=v_user AND pc.checkin_luc IS NULL AND pc.trang_thai='da_phan_cong'
      AND b.trang_thai='du_kien' AND TIMESTAMP(b.ngay,b.gio_bat_dau)>v_now;
  INSERT INTO audit_log(user_id,hanh_dong,bang,ban_ghi_id,du_lieu_cu,du_lieu_moi)
    VALUES(p_admin,'sua','nhan_su_chuyen_mon',p_nhan_su,
      JSON_OBJECT('don_gia_gio',v_old),JSON_OBJECT('don_gia_gio',p_gia,'hieu_luc_tu',v_now));
  COMMIT;
END$$

-- [THÊM] Kiểm tra đúng đăng ký khóa khi gắn vào lớp; dữ liệu 003 chưa có khóa giữ NULL.
DROP PROCEDURE IF EXISTS sp_kiem_tra_dang_ky_khoa$$
CREATE PROCEDURE sp_kiem_tra_dang_ky_khoa(IN p_dkk BIGINT UNSIGNED,IN p_lop BIGINT UNSIGNED,IN p_hv BIGINT UNSIGNED)
BEGIN
  IF p_dkk IS NOT NULL AND NOT EXISTS(SELECT 1 FROM dang_ky_khoa_hoc d JOIN lop_hoc l ON l.khoa_hoc_id=d.khoa_hoc_id
    WHERE d.id=p_dkk AND l.id=p_lop AND d.hoc_vien_id=p_hv AND d.trang_thai<>'thoi_hoc') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ghi danh phải đúng khóa và học viên đang đăng ký.';
  END IF;
END$$

DROP PROCEDURE IF EXISTS sp_kiem_tra_hoc_bu_ca_nhan$$
CREATE PROCEDURE sp_kiem_tra_hoc_bu_ca_nhan(IN p_bu BIGINT UNSIGNED,IN p_hv BIGINT UNSIGNED,IN p_don BIGINT UNSIGNED,IN p_actor BIGINT UNSIGNED)
BEGIN
  IF fn_la_giao_vu(p_actor)<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lịch bù do giáo vụ hoặc Admin xếp.'; END IF;
  IF NOT EXISTS(SELECT 1 FROM buoi_hoc b JOIN lop_hoc_vien l ON l.lop_id=b.lop_id
    WHERE b.id=p_bu AND b.loai='bu' AND b.pham_vi_bu='ca_nhan' AND l.hoc_vien_id=p_hv
      AND l.ngay_vao<=b.ngay AND (l.ngay_roi IS NULL OR l.ngay_roi>=b.ngay)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Buổi bù cá nhân phải đúng học viên thuộc lớp.';
  END IF;
  IF p_don IS NOT NULL AND NOT EXISTS(SELECT 1 FROM don_xin_nghi d JOIN buoi_hoc b ON b.buoi_goc_id=d.buoi_hoc_id
    WHERE b.id=p_bu AND d.id=p_don AND d.hoc_vien_id=p_hv AND d.trang_thai='da_duyet') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Đơn nghỉ liên kết phải được duyệt và đúng học viên/buổi gốc.';
  END IF;
END$$
DELIMITER ;
