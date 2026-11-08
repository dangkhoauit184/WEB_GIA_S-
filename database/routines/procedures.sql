-- [SỬA] Routine MySQL tách riêng theo trách nhiệm.
SET NAMES utf8mb4;
DELIMITER $$

-- [SỬA] Tách sp_sinh_buoi_hoc từ file gộp để bảo trì độc lập; giữ chú thích nghiệp vụ trong thân hàm/thủ tục.
DROP PROCEDURE IF EXISTS sp_sinh_buoi_hoc$$
-- [SỬA] Dùng đúng cột id/lop_id/ngay/thu, quy ước thứ 2=2 ... CN=8; sinh nguyên tử, không INSERT IGNORE che lỗi.
CREATE PROCEDURE sp_sinh_buoi_hoc(IN p_lop_id BIGINT UNSIGNED,IN p_tu DATE,IN p_den DATE)
BEGIN
  DECLARE v_day DATE;
  DECLARE v_lop BIGINT UNSIGNED;
  DECLARE v_end DATE;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  IF p_tu IS NULL OR p_den IS NULL OR p_tu>p_den THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Khoảng ngày sinh lịch không hợp lệ.';
  END IF;
  START TRANSACTION;
  SELECT id,ngay_ket_thuc INTO v_lop,v_end FROM lop_hoc WHERE id=p_lop_id FOR UPDATE;
  IF v_lop IS NULL OR NOT EXISTS (SELECT 1 FROM lich_hoc WHERE lop_id=p_lop_id AND is_active=1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lớp không tồn tại hoặc chưa có lịch tuần áp dụng.';
  END IF;
  SELECT GREATEST(p_tu,ngay_bat_dau) INTO v_day FROM lop_hoc WHERE id=p_lop_id;
  -- [SỬA] Không giới hạn CTE 1000 ngày, không sinh trước ngày khai giảng/sau ngày kết thúc.
  days_loop: WHILE v_day<=p_den AND (v_end IS NULL OR v_day<=v_end) DO
    INSERT INTO buoi_hoc(lop_id,lich_hoc_id,ngay,gio_bat_dau,gio_ket_thuc,phong_hoc,co_so_id)
    SELECT lh.lop_id,lh.id,v_day,lh.gio_bat_dau,lh.gio_ket_thuc,COALESCE(lh.phong_hoc,l.phong_hoc),l.co_so_id
      FROM lich_hoc lh JOIN lop_hoc l ON l.id=lh.lop_id
      WHERE lh.lop_id=p_lop_id AND lh.is_active=1 AND lh.thu=WEEKDAY(v_day)+2
        AND v_day>=lh.hieu_luc_tu AND (lh.hieu_luc_den IS NULL OR v_day<=lh.hieu_luc_den)
        AND NOT EXISTS (SELECT 1 FROM buoi_hoc b WHERE b.lop_id=lh.lop_id AND b.ngay=v_day AND b.gio_bat_dau=lh.gio_bat_dau);
    IF v_day='9999-12-31' THEN LEAVE days_loop; END IF;
    SET v_day=DATE_ADD(v_day,INTERVAL 1 DAY);
  END WHILE;
  INSERT INTO phan_cong_buoi_hoc(buoi_hoc_id,user_id,vai_tro,don_gia,cach_tinh_luong)
  SELECT b.id,ns.user_id,'day_chinh',ns.don_gia_gio_chuan,'theo_gio'
    FROM buoi_hoc b JOIN lop_hoc l ON l.id=b.lop_id JOIN nhan_su_chuyen_mon ns ON ns.id=l.nguoi_day_chinh_id
    WHERE b.lop_id=p_lop_id AND b.ngay BETWEEN p_tu AND p_den AND b.trang_thai='du_kien'
      AND NOT EXISTS (SELECT 1 FROM phan_cong_buoi_hoc pc WHERE pc.buoi_hoc_id=b.id
        AND (pc.vai_tro='day_chinh' AND pc.trang_thai<>'da_go' OR pc.user_id=ns.user_id));
  -- [LƯU Ý] Không tự bỏ ngày lễ, giới hạn số buổi hoặc gán lại người từng bị gỡ; giáo vụ quyết định theo hợp đồng.
  COMMIT;
END$$

-- [SỬA] Tách sp_chot_cong từ file gộp để bảo trì độc lập; giữ chú thích nghiệp vụ trong thân hàm/thủ tục.
DROP PROCEDURE IF EXISTS sp_chot_cong$$
-- [SỬA] Chốt theo đúng người/tháng/năm, chặn đơn điều chỉnh chờ duyệt và dùng snapshot tiền lương.
CREATE PROCEDURE sp_chot_cong(IN p_user BIGINT UNSIGNED,IN p_thang TINYINT,IN p_nam SMALLINT,IN p_nguoi_chot BIGINT UNSIGNED)
BEGIN
  DECLARE v_id BIGINT UNSIGNED;
  DECLARE v_tt VARCHAR(20);
  DECLARE v_user BIGINT UNSIGNED;
  DECLARE v_from DATE;
  DECLARE v_to DATE;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  IF p_thang IS NULL OR p_thang NOT BETWEEN 1 AND 12 OR p_nam IS NULL OR p_nam NOT BETWEEN 1000 AND 9998 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Kỳ công không hợp lệ.';
  END IF;
  -- [SỬA] 004: Người dùng xác nhận chỉ Admin chốt công; giáo vụ không có quyền này.
  IF NOT EXISTS (SELECT 1 FROM account a JOIN role_group g ON g.group_id=a.group_id
    WHERE a.user_id=p_nguoi_chot AND a.status='active' AND a.is_deleted=0 AND g.is_deleted=0 AND g.group_name='Admin') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Người chốt phải là Admin đang hoạt động.';
  END IF;
  SET v_from=STR_TO_DATE(CONCAT(p_nam,'-',LPAD(p_thang,2,'0'),'-01'),'%Y-%m-%d');
  SET v_to=DATE_ADD(v_from,INTERVAL 1 MONTH);
  START TRANSACTION;
  SELECT user_id INTO v_user FROM users WHERE user_id=p_user FOR UPDATE;
  -- [SỬA] Báo lỗi rõ ràng trước khi tạo bảng công cho một user không tồn tại.
  IF v_user IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không tìm thấy người cần chốt công.'; END IF;
  INSERT INTO bang_cong_thang(user_id,thang,nam) VALUES(p_user,p_thang,p_nam)
    ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id);
  SET v_id=LAST_INSERT_ID();
  SELECT trang_thai INTO v_tt FROM bang_cong_thang WHERE id=v_id FOR UPDATE;
  IF v_tt<>'mo' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Bảng công đã chốt hoặc đã trả.'; END IF;
  IF EXISTS (SELECT 1 FROM phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
    WHERE pc.user_id=p_user AND b.ngay>=v_from AND b.ngay<v_to AND pc.trang_thai<>'da_go'
      AND (pc.checkin_luc IS NOT NULL AND pc.checkout_luc IS NULL OR pc.trang_thai_cong='cho_duyet')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Còn ca chưa check-out hoặc công chờ duyệt trong kỳ.';
  END IF;
  IF EXISTS (SELECT 1 FROM dieu_chinh_cong d JOIN phan_cong_buoi_hoc pc ON pc.id=d.phan_cong_id
    JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id WHERE pc.user_id=p_user AND b.ngay>=v_from AND b.ngay<v_to AND d.trang_thai='cho_duyet') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Còn đơn điều chỉnh công chờ duyệt trong kỳ.';
  END IF;
  IF EXISTS (SELECT 1 FROM phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
    WHERE pc.user_id=p_user AND b.ngay>=v_from AND b.ngay<v_to AND pc.trang_thai_cong='hop_le' AND (pc.cach_tinh_luong IS NULL OR pc.so_phut_tinh_cong IS NULL)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cần xác minh snapshot cách tính lương lịch sử trước khi chốt.';
  END IF;
  -- [SỬA] Không âm thầm bỏ sót công hợp lệ nhưng trạng thái phân công/buổi học chưa hoàn thành.
  IF EXISTS (SELECT 1 FROM phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
    WHERE pc.user_id=p_user AND b.ngay>=v_from AND b.ngay<v_to AND pc.trang_thai_cong='hop_le'
      AND (pc.trang_thai<>'da_day' OR b.trang_thai<>'da_day')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Công hợp lệ chưa khớp trạng thái buổi dạy; cần kiểm tra trước khi chốt.';
  END IF;
  -- [SỬA] Khi chốt lại kỳ đã mở, gỡ liên kết cũ rồi chọn lại ca hợp lệ; không cộng ca đã gỡ/hủy công.
  UPDATE phan_cong_buoi_hoc SET bang_cong_id=NULL WHERE bang_cong_id=v_id;
  UPDATE phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id SET pc.bang_cong_id=v_id
    WHERE pc.user_id=p_user AND b.ngay>=v_from AND b.ngay<v_to AND b.trang_thai='da_day'
      AND pc.trang_thai='da_day' AND pc.trang_thai_cong='hop_le' AND pc.so_phut_tinh_cong IS NOT NULL AND pc.bang_cong_id IS NULL;
  UPDATE bang_cong_thang SET
    so_buoi=(SELECT COUNT(*) FROM phan_cong_buoi_hoc WHERE bang_cong_id=v_id),
    tong_phut=(SELECT COALESCE(SUM(so_phut_tinh_cong),0) FROM phan_cong_buoi_hoc WHERE bang_cong_id=v_id),
    -- [SỬA] Dùng cùng một function kiểm tra đầu vào và làm tròn VND cho mọi bảng công.
    tong_thu_nhap=(SELECT COALESCE(SUM(fn_tien_cong(cach_tinh_luong,don_gia,so_phut_tinh_cong)),0)
      FROM phan_cong_buoi_hoc WHERE bang_cong_id=v_id),
    so_buoi_bu=(SELECT COUNT(*) FROM phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id WHERE pc.bang_cong_id=v_id AND b.loai='bu'),
    so_lan_tre=(SELECT COUNT(*) FROM phan_cong_buoi_hoc WHERE bang_cong_id=v_id AND checkin_tre=1),
    so_buoi_vang=(SELECT COUNT(*) FROM phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
      WHERE pc.user_id=p_user AND b.ngay>=v_from AND b.ngay<v_to AND pc.trang_thai='vang'),
    trang_thai='da_chot',chot_boi=p_nguoi_chot,chot_luc=NOW() WHERE id=v_id;
  INSERT INTO audit_log(user_id,hanh_dong,bang,ban_ghi_id,du_lieu_moi)
    VALUES(p_nguoi_chot,'chot_cong','bang_cong_thang',v_id,JSON_OBJECT('thang',p_thang,'nam',p_nam));
  COMMIT;
END$$

-- [SỬA] Tách sp_mo_khoa_cong từ file gộp để bảo trì độc lập; giữ chú thích nghiệp vụ trong thân hàm/thủ tục.
DROP PROCEDURE IF EXISTS sp_mo_khoa_cong$$
-- [THÊM] Mở khóa có lý do và audit; giữ liên kết ca để tái tổng hợp kỳ đã mở, không xóa công cũ.
-- [LƯU Ý] Chỉ cấp EXECUTE routine này cho kết nối quản trị. ID người dùng không thay thế xác thực của API.
CREATE PROCEDURE sp_mo_khoa_cong(IN p_id BIGINT UNSIGNED,IN p_admin BIGINT UNSIGNED,IN p_ly_do VARCHAR(255))
BEGIN
  DECLARE v_tt VARCHAR(20);
  DECLARE v_user BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  IF p_ly_do IS NULL OR CHAR_LENGTH(TRIM(p_ly_do))=0 OR NOT EXISTS
    (SELECT 1 FROM account a JOIN role_group g ON g.group_id=a.group_id
     WHERE a.user_id=p_admin AND a.status='active' AND a.is_deleted=0 AND g.is_deleted=0 AND g.group_name='Admin') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Mở khóa cần Admin hoạt động và lý do.';
  END IF;
  START TRANSACTION;
  SELECT user_id INTO v_user FROM bang_cong_thang WHERE id=p_id;
  SELECT user_id INTO v_user FROM users WHERE user_id=v_user FOR UPDATE;
  SELECT trang_thai INTO v_tt FROM bang_cong_thang WHERE id=p_id FOR UPDATE;
  IF v_tt IS NULL OR v_tt<>'da_chot' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chỉ mở bảng đã chốt; bảng đã trả cần quy trình xác nhận riêng.';
  END IF;
  UPDATE bang_cong_thang SET trang_thai='mo',chot_boi=NULL,chot_luc=NULL,mo_khoa_boi=p_admin,mo_khoa_luc=NOW(),ly_do_mo_khoa=p_ly_do WHERE id=p_id;
  INSERT INTO audit_log(user_id,hanh_dong,bang,ban_ghi_id,du_lieu_cu,du_lieu_moi)
    VALUES(p_admin,'mo_khoa_cong','bang_cong_thang',p_id,JSON_OBJECT('trang_thai',v_tt),JSON_OBJECT('trang_thai','mo','ly_do',p_ly_do));
  COMMIT;
END$$
DELIMITER ;
