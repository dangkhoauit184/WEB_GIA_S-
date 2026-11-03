-- [SỬA] Routine MySQL tách riêng theo trách nhiệm.
SET NAMES utf8mb4;
DELIMITER $$

-- [SỬA] Tách sp_kiem_tra_si_so từ file gộp để bảo trì độc lập; giữ chú thích nghiệp vụ trong thân hàm/thủ tục.
DROP PROCEDURE IF EXISTS sp_kiem_tra_si_so$$
CREATE PROCEDURE sp_kiem_tra_si_so(IN p_lop BIGINT UNSIGNED,IN p_tt VARCHAR(30),IN p_exclude BIGINT UNSIGNED)
BEGIN
  DECLARE v_max INT;
  SELECT si_so_toi_da INTO v_max FROM lop_hoc WHERE id=p_lop FOR UPDATE;
  IF p_tt IN ('dang_hoc','hoc_thu') AND
    (SELECT COUNT(*) FROM lop_hoc_vien WHERE lop_id=p_lop AND id<>p_exclude AND trang_thai IN ('dang_hoc','hoc_thu'))>=v_max THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lớp đã đạt sĩ số tối đa.';
  END IF;
END$$

-- [SỬA] Tách sp_kiem_tra_buoi từ file gộp để bảo trì độc lập; giữ chú thích nghiệp vụ trong thân hàm/thủ tục.
DROP PROCEDURE IF EXISTS sp_kiem_tra_buoi$$
CREATE PROCEDURE sp_kiem_tra_buoi(IN p_id BIGINT UNSIGNED,IN p_lop BIGINT UNSIGNED,
 IN p_lich BIGINT UNSIGNED,IN p_loai VARCHAR(20),IN p_goc BIGINT UNSIGNED,
 IN p_ngay DATE,IN p_bd TIME,IN p_kt TIME,IN p_coso INT UNSIGNED,IN p_phong VARCHAR(50),IN p_tt VARCHAR(20))
BEGIN
  DECLARE v_resource BIGINT UNSIGNED;
  DECLARE v_user BIGINT UNSIGNED;
  DECLARE v_done BOOL DEFAULT FALSE;
  DECLARE c CURSOR FOR SELECT user_id FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=p_id AND trang_thai<>'da_go' ORDER BY user_id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done=TRUE;
  -- [SỬA] Không khóa lại lop_hoc trong trigger: INSERT ... SELECT đang đọc lớp sẽ gây lỗi MySQL 1442.
  -- [LƯU Ý] sp_sinh_buoi_hoc đã khóa lớp; trigger khóa riêng tài nguyên phòng/người ở bên dưới.
  SELECT id INTO v_resource FROM lop_hoc WHERE id=p_lop;
  IF p_lich IS NOT NULL AND NOT EXISTS (SELECT 1 FROM lich_hoc WHERE id=p_lich AND lop_id=p_lop) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lịch tuần không thuộc lớp của buổi học.';
  END IF;
  IF p_loai='bu' AND (p_goc IS NULL OR p_goc=p_id OR NOT EXISTS
    (SELECT 1 FROM buoi_hoc WHERE id=p_goc AND lop_id=p_lop AND loai='thuong' AND trang_thai IN ('huy','doi'))) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ca bù cần buổi gốc thường cùng lớp đã hủy hoặc dời.';
  END IF;
  IF p_loai='thuong' AND p_goc IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Buổi thường không liên kết buổi gốc.';
  END IF;
  IF p_tt NOT IN ('huy','doi') THEN
    IF p_coso IS NOT NULL AND p_phong IS NOT NULL AND TRIM(p_phong)<>'' THEN
      SELECT id INTO v_resource FROM co_so WHERE id=p_coso FOR UPDATE;
      IF EXISTS (SELECT 1 FROM buoi_hoc WHERE id<>p_id AND co_so_id=p_coso AND phong_hoc=p_phong
        AND ngay=p_ngay AND trang_thai NOT IN ('huy','doi') AND gio_bat_dau<p_kt AND gio_ket_thuc>p_bd) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Phòng đã có buổi học cùng khung giờ.';
      END IF;
    END IF;
    -- [LƯU Ý] Phòng NULL không được xem là một phòng chung; lớp tại nhà/online không ép mã phòng.
    OPEN c;
    users_loop: LOOP
      FETCH c INTO v_user;
      IF v_done THEN LEAVE users_loop; END IF;
      SELECT user_id INTO v_resource FROM users WHERE user_id=v_user FOR UPDATE;
      -- [SỬA] Ca chưa có bang_cong_id cũng không được chuyển sang tháng người dạy đã khóa.
      IF EXISTS (SELECT 1 FROM bang_cong_thang WHERE user_id=v_user AND nam=YEAR(p_ngay)
        AND thang=MONTH(p_ngay) AND trang_thai<>'mo') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không dời ca vào kỳ công đã khóa.';
      END IF;
      IF EXISTS (SELECT 1 FROM phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
        WHERE pc.user_id=v_user AND pc.trang_thai<>'da_go' AND b.id<>p_id AND b.ngay=p_ngay
          AND b.trang_thai NOT IN ('huy','doi') AND b.gio_bat_dau<p_kt AND b.gio_ket_thuc>p_bd) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Đổi lịch gây trùng ca của người dạy hoặc trợ giảng.';
      END IF;
    END LOOP;
    CLOSE c;
  END IF;
END$$

-- [SỬA] Tách sp_kiem_tra_hoc_vu từ file gộp để bảo trì độc lập; giữ chú thích nghiệp vụ trong thân hàm/thủ tục.
DROP PROCEDURE IF EXISTS sp_kiem_tra_hoc_vu$$
CREATE PROCEDURE sp_kiem_tra_hoc_vu(IN p_buoi BIGINT UNSIGNED,IN p_hv BIGINT UNSIGNED,IN p_bt BIGINT UNSIGNED)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM buoi_hoc b JOIN lop_hoc_vien l ON l.lop_id=b.lop_id
    WHERE b.id=p_buoi AND l.hoc_vien_id=p_hv AND l.ngay_vao<=b.ngay AND (l.ngay_roi IS NULL OR l.ngay_roi>=b.ngay)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Học viên không thuộc lớp trong ngày học.';
  END IF;
  IF p_bt IS NOT NULL AND NOT EXISTS (SELECT 1 FROM bai_tap bt JOIN buoi_hoc source ON source.id=bt.buoi_hoc_id
    JOIN buoi_hoc target ON target.id=p_buoi WHERE bt.id=p_bt AND source.lop_id=target.lop_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Bài tập không thuộc lớp của học viên.';
  END IF;
END$$

-- [SỬA] Tách sp_kiem_tra_don_nghi từ file gộp để bảo trì độc lập; giữ chú thích nghiệp vụ trong thân hàm/thủ tục.
DROP PROCEDURE IF EXISTS sp_kiem_tra_don_nghi$$
-- [THÊM] Đơn học viên và liên kết buổi bù không được trỏ sang lớp khác.
CREATE PROCEDURE sp_kiem_tra_don_nghi(IN p_buoi BIGINT UNSIGNED,IN p_doi_tuong VARCHAR(20),IN p_hv BIGINT UNSIGNED,IN p_bu BIGINT UNSIGNED)
BEGIN
  IF p_doi_tuong='hoc_vien' THEN CALL sp_kiem_tra_hoc_vu(p_buoi,p_hv,NULL); END IF;
  IF p_bu IS NOT NULL AND NOT EXISTS (SELECT 1 FROM buoi_hoc WHERE id=p_bu AND loai='bu' AND buoi_goc_id=p_buoi) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Buổi bù của đơn phải liên kết đúng buổi gốc.';
  END IF;
END$$

-- [SỬA] Tách sp_kiem_tra_khoan_thu từ file gộp để bảo trì độc lập; giữ chú thích nghiệp vụ trong thân hàm/thủ tục.
DROP PROCEDURE IF EXISTS sp_kiem_tra_khoan_thu$$
-- [THÊM] Chi tiết hóa đơn theo buổi/tháng/khóa cùng học viên và lớp, không tự sinh phí thiếu chính sách.
CREATE PROCEDURE sp_kiem_tra_khoan_thu(IN p_hd BIGINT UNSIGNED,IN p_buoi BIGINT UNSIGNED)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM hoa_don hd JOIN lop_hoc_vien lhv ON lhv.id=hd.lop_hoc_vien_id
    WHERE hd.id=p_hd AND (p_buoi IS NULL OR EXISTS
      (SELECT 1 FROM buoi_hoc WHERE id=p_buoi AND lop_id=lhv.lop_id))) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Khoản thu không đúng học viên hoặc lớp của hóa đơn.';
  END IF;
END$$

-- [SỬA] Tách sp_kiem_tra_thanh_toan từ file gộp để bảo trì độc lập; giữ chú thích nghiệp vụ trong thân hàm/thủ tục.
DROP PROCEDURE IF EXISTS sp_kiem_tra_thanh_toan$$
-- [SỬA] So số dư hiện tại dưới khóa hóa đơn, áp dụng cả khi hủy/kích hoạt lại phiếu.
CREATE PROCEDURE sp_kiem_tra_thanh_toan(IN p_hd BIGINT UNSIGNED,IN p_delta DECIMAL(12,0))
BEGIN
  DECLARE v_due DECIMAL(12,0);
  DECLARE v_net DECIMAL(12,0);
  DECLARE v_tt VARCHAR(30);
  SELECT phai_thu,da_thu,trang_thai INTO v_due,v_net,v_tt FROM hoa_don WHERE id=p_hd FOR UPDATE;
  -- [SỬA] Không để SELECT không tìm thấy hóa đơn làm các biểu thức NULL vượt qua kiểm tra.
  IF v_tt IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không tìm thấy hóa đơn.'; END IF;
  IF v_tt IN ('nhap','huy') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Hóa đơn chưa phát hành hoặc đã hủy.';
  END IF;
  IF v_net+p_delta<0 OR v_net+p_delta>v_due THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Thu vượt phải thu hoặc hoàn/hủy phiếu vượt số dư.';
  END IF;
END$$

-- [SỬA] Tách sp_cap_nhat_so_du từ file gộp để bảo trì độc lập; giữ chú thích nghiệp vụ trong thân hàm/thủ tục.
DROP PROCEDURE IF EXISTS sp_cap_nhat_so_du$$
-- [SỬA] Tính lại lũy kế chỉ từ chứng từ hợp lệ; không đổi hóa đơn hủy/nháp khi số dư bằng 0.
CREATE PROCEDURE sp_cap_nhat_so_du(IN p_hd BIGINT UNSIGNED)
BEGIN
  DECLARE v_net DECIMAL(12,0);
  SELECT COALESCE(SUM(IF(loai='thu',so_tien,-so_tien)),0) INTO v_net FROM thanh_toan WHERE hoa_don_id=p_hd AND trang_thai='hop_le';
  UPDATE hoa_don SET da_thu=v_net, trang_thai=CASE WHEN trang_thai IN ('nhap','huy') THEN trang_thai
    WHEN v_net=phai_thu THEN 'da_thanh_toan' WHEN v_net>0 THEN 'mot_phan' ELSE 'chua_thanh_toan' END WHERE id=p_hd;
END$$
DELIMITER ;
