-- [SỬA] Trigger academic; nạp sau routines theo install-order.json.
-- [LƯU Ý] Kết nối ghi dùng READ COMMITTED; ứng dụng cần retry khi deadlock.
SET NAMES utf8mb4;
DELIMITER $$

-- [SỬA] trg_sotd_bi: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_sotd_bi$$
CREATE TRIGGER trg_sotd_bi BEFORE INSERT ON so_theo_doi_buoi_hoc FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_hoc_vu(NEW.buoi_hoc_id,NEW.hoc_vien_id,NEW.bai_tap_id);
END$$

-- [SỬA] trg_sotd_bu: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_sotd_bu$$
CREATE TRIGGER trg_sotd_bu BEFORE UPDATE ON so_theo_doi_buoi_hoc FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_hoc_vu(NEW.buoi_hoc_id,NEW.hoc_vien_id,NEW.bai_tap_id);
END$$

-- [SỬA] trg_dxn_bi: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_dxn_bi$$
CREATE TRIGGER trg_dxn_bi BEFORE INSERT ON don_xin_nghi FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_don_nghi(NEW.buoi_hoc_id,NEW.doi_tuong,NEW.hoc_vien_id,NEW.buoi_bu_id);
END$$

-- [SỬA] trg_dxn_bu: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_dxn_bu$$
CREATE TRIGGER trg_dxn_bu BEFORE UPDATE ON don_xin_nghi FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_don_nghi(NEW.buoi_hoc_id,NEW.doi_tuong,NEW.hoc_vien_id,NEW.buoi_bu_id);
END$$
DELIMITER ;
