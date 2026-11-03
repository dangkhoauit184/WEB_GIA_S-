-- [THÊM] Dấu vết chi trả cho mô hình mỗi bảng công được trả một lần.
-- Tài khoản API vẫn phải xác thực người trả và kiểm tra quyền trước khi UPDATE.
SET NAMES utf8mb4;
DELIMITER $$
DROP TRIGGER IF EXISTS trg_bc_bi$$
CREATE TRIGGER trg_bc_bi BEFORE INSERT ON bang_cong_thang FOR EACH ROW
BEGIN
  IF NEW.trang_thai='da_tra' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Phải chốt bảng công trước khi ghi nhận trả lương.';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_bc_bu$$
CREATE TRIGGER trg_bc_bu BEFORE UPDATE ON bang_cong_thang FOR EACH ROW
BEGIN
  IF NEW.trang_thai='da_tra' AND OLD.trang_thai<>'da_tra' THEN
    IF OLD.trang_thai<>'da_chot' OR NEW.tra_boi IS NULL OR NEW.tra_luc IS NULL OR NEW.ngay_tra IS NULL
      OR DATE(NEW.tra_luc)<>NEW.ngay_tra THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Trả lương cần bảng đã chốt, người trả và thời điểm khớp ngày trả.';
    END IF;
  END IF;
  -- [THÊM] Kỳ đã trả giữ nguyên chứng từ; quy trình điều chỉnh riêng chưa được đặc tả.
  IF OLD.trang_thai='da_tra' AND (NEW.trang_thai<>OLD.trang_thai OR NEW.user_id<>OLD.user_id
    OR NEW.thang<>OLD.thang OR NEW.nam<>OLD.nam OR NEW.tong_thu_nhap<>OLD.tong_thu_nhap
    OR NEW.thuong<>OLD.thuong OR NEW.khau_tru<>OLD.khau_tru
    OR NOT (NEW.tra_boi <=> OLD.tra_boi) OR NOT (NEW.tra_luc <=> OLD.tra_luc)
    OR NOT (NEW.ngay_tra <=> OLD.ngay_tra) OR NOT (NEW.ma_giao_dich_chi <=> OLD.ma_giao_dich_chi)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không sửa kỳ công đã trả; cần quy trình điều chỉnh riêng.';
  END IF;
END$$
DELIMITER ;
