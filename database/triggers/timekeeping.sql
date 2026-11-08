-- [SỬA] Trigger timekeeping; nạp sau routines theo install-order.json.
-- [LƯU Ý] Kết nối ghi dùng READ COMMITTED; ứng dụng cần retry khi deadlock.
SET NAMES utf8mb4;
DELIMITER $$

-- [SỬA] trg_pc_bi: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_pc_bi$$
CREATE TRIGGER trg_pc_bi BEFORE INSERT ON phan_cong_buoi_hoc FOR EACH ROW
BEGIN
  DECLARE v_user BIGINT UNSIGNED;
  DECLARE v_rate DECIMAL(12,0);
  SELECT user_id INTO v_user FROM users WHERE user_id=NEW.user_id FOR UPDATE;
  IF EXISTS (SELECT 1 FROM bang_cong_thang bc JOIN buoi_hoc b ON b.id=NEW.buoi_hoc_id
    WHERE bc.user_id=NEW.user_id AND bc.nam=YEAR(b.ngay) AND bc.thang=MONTH(b.ngay) AND bc.trang_thai<>'mo') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Tháng đã khóa; cần mở khóa trước khi thêm phân công.';
  END IF;
  IF NEW.bang_cong_id IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Phân công mới không được tự gắn bảng công.';
  END IF;
  IF NEW.trang_thai<>'da_go' AND fn_trung_lich(NEW.user_id,NEW.buoi_hoc_id,0)=1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Trùng lịch người dạy hoặc trợ giảng.';
  END IF;
  -- [SỬA] 004: Mức tiền/giờ theo từng người; giá lớp và input caller không ghi đè snapshot mới.
  SELECT ns.don_gia_gio_chuan INTO v_rate FROM nhan_su_chuyen_mon ns WHERE ns.user_id=NEW.user_id AND ns.deleted_at IS NULL;
  IF v_rate IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Phân công cần hồ sơ người dạy có đơn giá giờ.'; END IF;
  SET NEW.don_gia=v_rate,NEW.cach_tinh_luong='theo_gio',NEW.quy_tac_cong=4;
  SET NEW.gio_lich_bat_dau=(SELECT TIMESTAMP(ngay,gio_bat_dau) FROM buoi_hoc WHERE id=NEW.buoi_hoc_id);
  SET NEW.gio_lich_ket_thuc=(SELECT TIMESTAMP(ngay,gio_ket_thuc) FROM buoi_hoc WHERE id=NEW.buoi_hoc_id);
  SET NEW.checkin_tre=IF(NEW.checkin_luc IS NULL,0,NEW.checkin_luc>NEW.gio_lich_bat_dau);
END$$

-- [SỬA] trg_pc_bu: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_pc_bu$$
CREATE TRIGGER trg_pc_bu BEFORE UPDATE ON phan_cong_buoi_hoc FOR EACH ROW
BEGIN
  DECLARE v_user BIGINT UNSIGNED;
  SELECT user_id INTO v_user FROM users WHERE user_id=NEW.user_id FOR UPDATE;
  -- [SỬA] PK của chứng từ phân công phải ổn định; FK không ngăn đổi ID nếu chưa có bảng tham chiếu.
  IF NEW.id<>OLD.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không đổi ID phân công.'; END IF;
  -- [SỬA] Không cho đổi danh tính ca hoặc gỡ bang_cong_id để lách khóa cũ.
  -- [LƯU Ý] Chỉ sp_mo_khoa_cong đổi trạng thái bảng công sang mo; không tin biến @admin do client tự đặt.
  IF OLD.bang_cong_id IS NOT NULL AND EXISTS
    (SELECT 1 FROM bang_cong_thang WHERE id=OLD.bang_cong_id AND trang_thai<>'mo') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ca đã khóa công; cần Admin mở khóa bảng công trước.';
  END IF;
  -- [THÊM] Không chuyển ca chưa khóa vào một tháng khác đã chốt.
  IF EXISTS (SELECT 1 FROM bang_cong_thang bc JOIN buoi_hoc b ON b.id=NEW.buoi_hoc_id
    WHERE bc.user_id=NEW.user_id AND bc.nam=YEAR(b.ngay) AND bc.thang=MONTH(b.ngay) AND bc.trang_thai<>'mo') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Kỳ công đích đã khóa.';
  END IF;
  IF OLD.bang_cong_id IS NOT NULL AND NOT (NEW.bang_cong_id <=> OLD.bang_cong_id) THEN
    IF NEW.bang_cong_id IS NOT NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không được chuyển ca sang bảng công khác.';
    END IF;
  END IF;
  IF NEW.bang_cong_id IS NOT NULL AND NOT EXISTS
    (SELECT 1 FROM bang_cong_thang bc JOIN buoi_hoc b ON b.id=NEW.buoi_hoc_id
     WHERE bc.id=NEW.bang_cong_id AND bc.user_id=NEW.user_id AND bc.nam=YEAR(b.ngay) AND bc.thang=MONTH(b.ngay)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Bảng công không cùng người dạy hoặc kỳ công.';
  END IF;
  -- [THÊM] Không hạ phiên bản để tính lại toàn bộ thời gian đến sớm/về muộn.
  IF OLD.quy_tac_cong=4 AND NEW.quy_tac_cong<>4 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không hạ quy tắc công 004.'; END IF;
  IF NOT (NEW.checkin_luc <=> OLD.checkin_luc) OR NOT (NEW.checkout_luc <=> OLD.checkout_luc)
    OR NEW.buoi_hoc_id<>OLD.buoi_hoc_id THEN SET NEW.quy_tac_cong=4; END IF;
  IF NEW.quy_tac_cong=4 THEN
    IF OLD.checkin_luc IS NULL OR OLD.quy_tac_cong=3 OR NEW.buoi_hoc_id<>OLD.buoi_hoc_id THEN
      SET NEW.gio_lich_bat_dau=(SELECT TIMESTAMP(ngay,gio_bat_dau) FROM buoi_hoc WHERE id=NEW.buoi_hoc_id);
      SET NEW.gio_lich_ket_thuc=(SELECT TIMESTAMP(ngay,gio_ket_thuc) FROM buoi_hoc WHERE id=NEW.buoi_hoc_id);
    ELSE
      SET NEW.gio_lich_bat_dau=OLD.gio_lich_bat_dau,NEW.gio_lich_ket_thuc=OLD.gio_lich_ket_thuc;
    END IF;
    SET NEW.checkin_tre=IF(NEW.checkin_luc IS NULL,0,NEW.checkin_luc>NEW.gio_lich_bat_dau);
  END IF;
  IF NOT (NEW.don_gia <=> OLD.don_gia) OR NOT (NEW.cach_tinh_luong <=> OLD.cach_tinh_luong) THEN
    IF fn_la_admin(NEW.luong_sua_boi)<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sửa đơn giá cần Admin thực hiện.'; END IF;
  END IF;
  IF NEW.trang_thai<>'da_go' AND fn_trung_lich(NEW.user_id,NEW.buoi_hoc_id,NEW.id)=1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Trùng lịch người dạy hoặc trợ giảng.';
  END IF;
END$$

-- [SỬA] trg_pc_bd: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_pc_bd$$
CREATE TRIGGER trg_pc_bd BEFORE DELETE ON phan_cong_buoi_hoc FOR EACH ROW
BEGIN
  DECLARE v_user BIGINT UNSIGNED;
  SELECT user_id INTO v_user FROM users WHERE user_id=OLD.user_id FOR UPDATE;
  IF OLD.bang_cong_id IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không xóa phân công đã gắn bảng công.';
  END IF;
  -- [SỬA] Phân công chưa được gắn bảng công cũng không được xóa sau khi kỳ đã khóa.
  IF EXISTS (SELECT 1 FROM bang_cong_thang bc JOIN buoi_hoc b ON b.id=OLD.buoi_hoc_id
    WHERE bc.user_id=OLD.user_id AND bc.nam=YEAR(b.ngay) AND bc.thang=MONTH(b.ngay) AND bc.trang_thai<>'mo') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không xóa phân công trong kỳ đã khóa.';
  END IF;
END$$

-- [THÊM] Đơn điều chỉnh lấy cùng khóa người dạy với chốt công, tránh chốt trong lúc đơn đang được ghi.
-- [LƯU Ý] Vẫn cho gửi khiếu nại kỳ cũ; mở khóa để xử lý thuộc Admin, không tự đổi giờ từ đơn.
DROP TRIGGER IF EXISTS trg_dcc_bi$$
CREATE TRIGGER trg_dcc_bi BEFORE INSERT ON dieu_chinh_cong FOR EACH ROW
BEGIN
  DECLARE v_user BIGINT UNSIGNED;
  IF NEW.trang_thai IN ('da_duyet','tu_choi') AND (fn_la_admin(NEW.nguoi_duyet_id)<>1 OR NEW.duyet_luc IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chỉ Admin được duyệt điều chỉnh công.';
  END IF;
  SELECT user_id INTO v_user FROM phan_cong_buoi_hoc WHERE id=NEW.phan_cong_id;
  SELECT user_id INTO v_user FROM users WHERE user_id=v_user FOR UPDATE;
END$$
DROP TRIGGER IF EXISTS trg_dcc_bu$$
CREATE TRIGGER trg_dcc_bu BEFORE UPDATE ON dieu_chinh_cong FOR EACH ROW
BEGIN
  DECLARE v_user BIGINT UNSIGNED;
  -- [SỬA] Đơn đã gửi không chuyển sang một ca khác để làm lệch lịch sử phê duyệt.
  IF NEW.phan_cong_id<>OLD.phan_cong_id OR NEW.id<>OLD.id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không đổi ca hoặc ID của đơn điều chỉnh công.';
  END IF;
  IF NEW.trang_thai IN ('da_duyet','tu_choi') AND (fn_la_admin(NEW.nguoi_duyet_id)<>1 OR NEW.duyet_luc IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chỉ Admin được duyệt điều chỉnh công.';
  END IF;
  SELECT user_id INTO v_user FROM phan_cong_buoi_hoc WHERE id=NEW.phan_cong_id;
  SELECT user_id INTO v_user FROM users WHERE user_id=v_user FOR UPDATE;
END$$
DELIMITER ;
