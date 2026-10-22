-- [SỬA] Thay file procedure khung không khớp cột bằng routine dựa trên schema gốc.
-- [LƯU Ý] Bản khung cũ giữ nguyên tại docs/reference/database/procedures.sql.
-- [LƯU Ý] Routine có START TRANSACTION phải được CALL độc lập, không nằm trong transaction của caller.
SET NAMES utf8mb4;
DELIMITER $$

-- [SỬA] NULL loại trừ không làm điều kiện pc.id<>NULL bỏ sót toàn bộ lịch.
DROP FUNCTION IF EXISTS fn_trung_lich$$
CREATE FUNCTION fn_trung_lich(p_user BIGINT UNSIGNED,p_buoi BIGINT UNSIGNED,p_loai_tru BIGINT UNSIGNED)
RETURNS TINYINT READS SQL DATA NOT DETERMINISTIC
BEGIN
  RETURN EXISTS (SELECT 1 FROM phan_cong_buoi_hoc pc
    JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id JOIN buoi_hoc target ON target.id=p_buoi
    WHERE pc.user_id=p_user AND pc.id<>COALESCE(p_loai_tru,0) AND b.id<>p_buoi
      AND pc.trang_thai<>'da_go' AND b.trang_thai NOT IN ('huy','doi')
      AND target.trang_thai NOT IN ('huy','doi') AND b.ngay=target.ngay
      AND b.gio_bat_dau<target.gio_ket_thuc AND b.gio_ket_thuc>target.gio_bat_dau);
END$$

-- [THÊM] Dùng chung cho hai trigger sĩ số; không đổi quy tắc đếm dang_hoc/hoc_thu của SQL gốc.
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

-- [THÊM] Đổi giờ buổi học phải kiểm tra cả phòng và toàn bộ người đã được phân công.
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
      IF EXISTS (SELECT 1 FROM phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
        WHERE pc.user_id=v_user AND pc.trang_thai<>'da_go' AND b.id<>p_id AND b.ngay=p_ngay
          AND b.trang_thai NOT IN ('huy','doi') AND b.gio_bat_dau<p_kt AND b.gio_ket_thuc>p_bd) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Đổi lịch gây trùng ca của người dạy hoặc trợ giảng.';
      END IF;
    END LOOP;
    CLOSE c;
  END IF;
END$$

-- [THÊM] Giữ việc chấm bài từ buổi trước, chỉ kiểm tra cùng lớp thay vì ép cùng buổi.
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

DROP PROCEDURE IF EXISTS sp_kiem_tra_don_nghi$$
-- [THÊM] Đơn học viên và liên kết buổi bù không được trỏ sang lớp khác.
CREATE PROCEDURE sp_kiem_tra_don_nghi(IN p_buoi BIGINT UNSIGNED,IN p_doi_tuong VARCHAR(20),IN p_hv BIGINT UNSIGNED,IN p_bu BIGINT UNSIGNED)
BEGIN
  IF p_doi_tuong='hoc_vien' THEN CALL sp_kiem_tra_hoc_vu(p_buoi,p_hv,NULL); END IF;
  IF p_bu IS NOT NULL AND NOT EXISTS (SELECT 1 FROM buoi_hoc WHERE id=p_bu AND loai='bu' AND buoi_goc_id=p_buoi) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Buổi bù của đơn phải liên kết đúng buổi gốc.';
  END IF;
END$$

DROP PROCEDURE IF EXISTS sp_kiem_tra_khoan_thu$$
-- [THÊM] Chi tiết hóa đơn theo buổi/tháng/khóa cùng học viên và lớp, không tự sinh phí thiếu chính sách.
CREATE PROCEDURE sp_kiem_tra_khoan_thu(IN p_hd BIGINT UNSIGNED,IN p_hv BIGINT UNSIGNED,IN p_buoi BIGINT UNSIGNED)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM hoa_don hd JOIN lop_hoc_vien lhv ON lhv.id=hd.lop_hoc_vien_id
    WHERE hd.id=p_hd AND lhv.hoc_vien_id=p_hv AND (p_buoi IS NULL OR EXISTS
      (SELECT 1 FROM buoi_hoc WHERE id=p_buoi AND lop_id=lhv.lop_id))) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Khoản thu không đúng học viên hoặc lớp của hóa đơn.';
  END IF;
END$$

DROP PROCEDURE IF EXISTS sp_kiem_tra_thanh_toan$$
-- [SỬA] So số dư hiện tại dưới khóa hóa đơn, áp dụng cả khi hủy/kích hoạt lại phiếu.
CREATE PROCEDURE sp_kiem_tra_thanh_toan(IN p_hd BIGINT UNSIGNED,IN p_delta DECIMAL(12,0))
BEGIN
  DECLARE v_due DECIMAL(12,0);
  DECLARE v_net DECIMAL(12,0);
  DECLARE v_tt VARCHAR(30);
  SELECT phai_thu,da_thu,trang_thai INTO v_due,v_net,v_tt FROM hoa_don WHERE id=p_hd FOR UPDATE;
  IF v_tt IN ('nhap','huy') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Hóa đơn chưa phát hành hoặc đã hủy.';
  END IF;
  IF v_net+p_delta<0 OR v_net+p_delta>v_due THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Thu vượt phải thu hoặc hoàn/hủy phiếu vượt số dư.';
  END IF;
END$$

DROP PROCEDURE IF EXISTS sp_cap_nhat_so_du$$
-- [SỬA] Tính lại lũy kế chỉ từ chứng từ hợp lệ; không đổi hóa đơn hủy/nháp khi số dư bằng 0.
CREATE PROCEDURE sp_cap_nhat_so_du(IN p_hd BIGINT UNSIGNED)
BEGIN
  DECLARE v_net DECIMAL(12,0);
  SELECT COALESCE(SUM(IF(loai='thu',so_tien,-so_tien)),0) INTO v_net FROM thanh_toan WHERE hoa_don_id=p_hd AND trang_thai='hop_le';
  UPDATE hoa_don SET da_thu=v_net, trang_thai=CASE WHEN trang_thai IN ('nhap','huy') THEN trang_thai
    WHEN v_net=phai_thu THEN 'da_thanh_toan' WHEN v_net>0 THEN 'mot_phan' ELSE 'chua_thanh_toan' END WHERE id=p_hd;
END$$

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
  SELECT b.id,ns.user_id,'day_chinh',l.muc_tra_chinh,l.cach_tinh_luong
    FROM buoi_hoc b JOIN lop_hoc l ON l.id=b.lop_id JOIN nhan_su_chuyen_mon ns ON ns.id=l.nguoi_day_chinh_id
    WHERE b.lop_id=p_lop_id AND b.ngay BETWEEN p_tu AND p_den AND b.trang_thai='du_kien'
      AND NOT EXISTS (SELECT 1 FROM phan_cong_buoi_hoc pc WHERE pc.buoi_hoc_id=b.id
        AND (pc.vai_tro='day_chinh' AND pc.trang_thai<>'da_go' OR pc.user_id=ns.user_id));
  -- [LƯU Ý] Không tự bỏ ngày lễ, giới hạn số buổi hoặc gán lại người từng bị gỡ; giáo vụ quyết định theo hợp đồng.
  COMMIT;
END$$

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
  -- [LƯU Ý] Tài liệu Word và architecture.md khác người chốt; cho phép Admin hoặc NhanVien theo mô tả giáo vụ.
  IF NOT EXISTS (SELECT 1 FROM account a JOIN role_group g ON g.group_id=a.group_id
    WHERE a.user_id=p_nguoi_chot AND a.status='active' AND a.is_deleted=0 AND g.is_deleted=0 AND g.group_name IN ('Admin','NhanVien')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Người chốt phải là Admin hoặc Nhân viên đang hoạt động.';
  END IF;
  SET v_from=STR_TO_DATE(CONCAT(p_nam,'-',LPAD(p_thang,2,'0'),'-01'),'%Y-%m-%d');
  SET v_to=DATE_ADD(v_from,INTERVAL 1 MONTH);
  START TRANSACTION;
  SELECT user_id INTO v_user FROM users WHERE user_id=p_user FOR UPDATE;
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
    WHERE pc.user_id=p_user AND b.ngay>=v_from AND b.ngay<v_to AND pc.trang_thai_cong='hop_le' AND pc.cach_tinh_luong IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cần xác minh snapshot cách tính lương lịch sử trước khi chốt.';
  END IF;
  -- [SỬA] Khi chốt lại kỳ đã mở, gỡ liên kết cũ rồi chọn lại ca hợp lệ; không cộng ca đã gỡ/hủy công.
  UPDATE phan_cong_buoi_hoc SET bang_cong_id=NULL WHERE bang_cong_id=v_id;
  UPDATE phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id SET pc.bang_cong_id=v_id
    WHERE pc.user_id=p_user AND b.ngay>=v_from AND b.ngay<v_to AND b.trang_thai='da_day'
      AND pc.trang_thai='da_day' AND pc.trang_thai_cong='hop_le' AND pc.so_phut_thuc_te IS NOT NULL AND pc.bang_cong_id IS NULL;
  UPDATE bang_cong_thang SET
    so_buoi=(SELECT COUNT(*) FROM phan_cong_buoi_hoc WHERE bang_cong_id=v_id),
    tong_phut=(SELECT COALESCE(SUM(so_phut_thuc_te),0) FROM phan_cong_buoi_hoc WHERE bang_cong_id=v_id),
    tong_thu_nhap=(SELECT COALESCE(SUM(IF(cach_tinh_luong='theo_gio',ROUND(so_phut_thuc_te/60*don_gia,0),don_gia)),0)
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
  UPDATE bang_cong_thang SET trang_thai='mo',chot_boi=NULL,chot_luc=NULL WHERE id=p_id;
  INSERT INTO audit_log(user_id,hanh_dong,bang,ban_ghi_id,du_lieu_cu,du_lieu_moi)
    VALUES(p_admin,'mo_khoa_cong','bang_cong_thang',p_id,JSON_OBJECT('trang_thai',v_tt),JSON_OBJECT('trang_thai','mo','ly_do',p_ly_do));
  COMMIT;
END$$
DELIMITER ;
