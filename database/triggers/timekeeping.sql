-- [SỬA] Trigger timekeeping; nạp sau routines theo install-order.json.
-- [LƯU Ý] Kết nối ghi dùng READ COMMITTED; ứng dụng cần retry khi deadlock.
SET NAMES utf8mb4;
DELIMITER $$

-- [SỬA] trg_pc_bi: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_pc_bi$$
CREATE TRIGGER trg_pc_bi BEFORE INSERT ON phan_cong_buoi_hoc FOR EACH ROW
BEGIN
  DECLARE v_user BIGINT UNSIGNED;
  DECLARE v_method VARCHAR(20);
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
  -- [THÊM] Snapshot cách tính lương; không đọc lại giá/cách tính hiện tại của lớp khi quyết toán.
  SELECT l.cach_tinh_luong INTO v_method FROM buoi_hoc b JOIN lop_hoc l ON l.id=b.lop_id WHERE b.id=NEW.buoi_hoc_id;
  IF NEW.cach_tinh_luong IS NULL THEN SET NEW.cach_tinh_luong=v_method; END IF;
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
  SELECT user_id INTO v_user FROM phan_cong_buoi_hoc WHERE id=NEW.phan_cong_id;
  SELECT user_id INTO v_user FROM users WHERE user_id=v_user FOR UPDATE;
END$$
DELIMITER ;
