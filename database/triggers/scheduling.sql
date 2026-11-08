-- [SỬA] Trigger scheduling; nạp sau routines theo install-order.json.
-- [LƯU Ý] Kết nối ghi dùng READ COMMITTED; ứng dụng cần retry khi deadlock.
SET NAMES utf8mb4;
DELIMITER $$

-- [SỬA] trg_buoi_bi: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_buoi_bi$$
CREATE TRIGGER trg_buoi_bi BEFORE INSERT ON buoi_hoc FOR EACH ROW
BEGIN
  DECLARE v_coso INT UNSIGNED;
  -- [THÊM] huy_boi là nhóm nguyên nhân, nguoi_huy_id mới là tài khoản thao tác.
  IF NEW.trang_thai='huy' AND (NEW.nguoi_huy_id IS NULL OR NEW.huy_luc IS NULL OR NEW.ly_do_huy_doi IS NULL OR TRIM(NEW.ly_do_huy_doi)='') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Hủy buổi học cần người thực hiện, thời điểm và lý do.';
  END IF;
  IF NEW.trang_thai='huy' AND fn_la_admin(NEW.nguoi_huy_id)<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Nghỉ cả lớp cần Admin duyệt.'; END IF;
  SELECT co_so_id INTO v_coso FROM lop_hoc WHERE id=NEW.lop_id;
  IF NEW.co_so_id IS NULL THEN SET NEW.co_so_id=v_coso; END IF;
  CALL sp_kiem_tra_buoi(0,NEW.lop_id,NEW.lich_hoc_id,NEW.loai,NEW.buoi_goc_id,
    NEW.ngay,NEW.gio_bat_dau,NEW.gio_ket_thuc,NEW.co_so_id,NEW.phong_hoc,NEW.trang_thai,NEW.pham_vi_bu);
END$$

-- [SỬA] trg_buoi_bu: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_buoi_bu$$
CREATE TRIGGER trg_buoi_bu BEFORE UPDATE ON buoi_hoc FOR EACH ROW
BEGIN
  IF NEW.trang_thai='huy' AND OLD.trang_thai<>'huy'
    AND (NEW.nguoi_huy_id IS NULL OR NEW.huy_luc IS NULL OR NEW.ly_do_huy_doi IS NULL OR TRIM(NEW.ly_do_huy_doi)='') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Hủy buổi học cần người thực hiện, thời điểm và lý do.';
  END IF;
  IF NEW.trang_thai='huy' AND OLD.trang_thai<>'huy' AND fn_la_admin(NEW.nguoi_huy_id)<>1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Nghỉ cả lớp cần Admin duyệt.';
  END IF;
  IF (NEW.ngay<>OLD.ngay OR NEW.gio_bat_dau<>OLD.gio_bat_dau OR NEW.gio_ket_thuc<>OLD.gio_ket_thuc)
    AND EXISTS(SELECT 1 FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=OLD.id AND quy_tac_cong=4 AND checkin_luc IS NOT NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Buổi đã check-in; không sửa lịch làm thay đổi công.';
  END IF;
  -- [SỬA] Giữ ID buổi ổn định và không làm vô hiệu liên kết học bù đã có.
  IF NEW.id<>OLD.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không đổi ID buổi học.'; END IF;
  IF EXISTS (SELECT 1 FROM buoi_hoc WHERE buoi_goc_id=OLD.id) AND
    (NEW.lop_id<>OLD.lop_id OR NEW.loai<>'thuong' OR NEW.trang_thai NOT IN ('huy','doi')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Buổi gốc đã có ca bù; không đổi lớp/loại hoặc khôi phục lịch gốc.';
  END IF;
  -- [THÊM] Không đổi lịch, buổi gốc hay học vụ của ca thuộc kỳ đã khóa.
  IF EXISTS (SELECT 1 FROM phan_cong_buoi_hoc pc JOIN bang_cong_thang bc ON bc.id=pc.bang_cong_id
    WHERE pc.buoi_hoc_id=OLD.id AND bc.trang_thai<>'mo') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Buổi học thuộc bảng công đã khóa.';
  END IF;
  CALL sp_kiem_tra_buoi(OLD.id,NEW.lop_id,NEW.lich_hoc_id,NEW.loai,NEW.buoi_goc_id,
    NEW.ngay,NEW.gio_bat_dau,NEW.gio_ket_thuc,NEW.co_so_id,NEW.phong_hoc,NEW.trang_thai,NEW.pham_vi_bu);
END$$
DELIMITER ;
