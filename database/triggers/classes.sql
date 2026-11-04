-- [SỬA] Trigger classes; nạp sau routines theo install-order.json.
-- [LƯU Ý] Kết nối ghi dùng READ COMMITTED; ứng dụng cần retry khi deadlock.
SET NAMES utf8mb4;
DELIMITER $$

-- [SỬA] trg_lhv_bi: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_lhv_bi$$
CREATE TRIGGER trg_lhv_bi BEFORE INSERT ON lop_hoc_vien FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_si_so(NEW.lop_id,NEW.trang_thai,0);
END$$

-- [SỬA] trg_lhv_bu: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_lhv_bu$$
CREATE TRIGGER trg_lhv_bu BEFORE UPDATE ON lop_hoc_vien FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_si_so(NEW.lop_id,NEW.trang_thai,OLD.id);
END$$

-- [SỬA] trg_lop_bu: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_lop_bu$$
CREATE TRIGGER trg_lop_bu BEFORE UPDATE ON lop_hoc FOR EACH ROW
BEGIN
  IF NEW.si_so_toi_da<(SELECT COUNT(*) FROM lop_hoc_vien WHERE lop_id=OLD.id AND trang_thai IN ('dang_hoc','hoc_thu')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sĩ số tối đa mới thấp hơn số học viên hiện có.';
  END IF;
END$$
DELIMITER ;
