-- [SỬA] Trigger tuition; nạp sau routines theo install-order.json.
-- [LƯU Ý] Kết nối ghi dùng READ COMMITTED; ứng dụng cần retry khi deadlock.
SET NAMES utf8mb4;
DELIMITER $$

-- [SỬA] trg_tt_bi: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_tt_bi$$
CREATE TRIGGER trg_tt_bi BEFORE INSERT ON thanh_toan FOR EACH ROW
BEGIN
  -- [THÊM] Chứng từ hủy mới phải có người, thời điểm và lý do; không đoán lịch sử cũ.
  IF NEW.trang_thai='da_huy' AND (NEW.huy_boi IS NULL OR NEW.huy_luc IS NULL OR NEW.ly_do_huy IS NULL OR TRIM(NEW.ly_do_huy)='') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Hủy phiếu cần người hủy, thời điểm và lý do.';
  END IF;
  CALL sp_kiem_tra_thanh_toan(NEW.hoa_don_id,IF(NEW.trang_thai='hop_le',IF(NEW.loai='thu',NEW.so_tien,-NEW.so_tien),0));
END$$

-- [SỬA] trg_tt_bu: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_tt_bu$$
CREATE TRIGGER trg_tt_bu BEFORE UPDATE ON thanh_toan FOR EACH ROW
BEGIN
  IF NEW.trang_thai='da_huy' AND OLD.trang_thai<>'da_huy'
    AND (NEW.huy_boi IS NULL OR NEW.huy_luc IS NULL OR NEW.ly_do_huy IS NULL OR TRIM(NEW.ly_do_huy)='') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Hủy phiếu cần người hủy, thời điểm và lý do.';
  END IF;
  -- [SỬA] Bảo toàn cả ID phiếu, không chỉ các giá trị tiền.
  IF NEW.id<>OLD.id OR NEW.hoa_don_id<>OLD.hoa_don_id OR NEW.loai<>OLD.loai OR NEW.so_tien<>OLD.so_tien
    OR NEW.nguoi_thu_id<>OLD.nguoi_thu_id OR NEW.phuong_thuc<>OLD.phuong_thuc
    OR NOT (NEW.ma_giao_dich <=> OLD.ma_giao_dich) OR NEW.ngay_thanh_toan<>OLD.ngay_thanh_toan THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không sửa chứng từ thu/hoàn; hủy phiếu và lập phiếu thay thế.';
  END IF;
  CALL sp_kiem_tra_thanh_toan(OLD.hoa_don_id,
    IF(NEW.trang_thai='hop_le',IF(NEW.loai='thu',NEW.so_tien,-NEW.so_tien),0)
    - IF(OLD.trang_thai='hop_le',IF(OLD.loai='thu',OLD.so_tien,-OLD.so_tien),0));
END$$

-- [SỬA] trg_tt_ai: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_tt_ai$$
CREATE TRIGGER trg_tt_ai AFTER INSERT ON thanh_toan FOR EACH ROW
BEGIN
  CALL sp_cap_nhat_so_du(NEW.hoa_don_id);
END$$

-- [SỬA] trg_tt_au: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_tt_au$$
CREATE TRIGGER trg_tt_au AFTER UPDATE ON thanh_toan FOR EACH ROW
BEGIN
  CALL sp_cap_nhat_so_du(NEW.hoa_don_id);
END$$

-- [SỬA] trg_tt_bd: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_tt_bd$$
CREATE TRIGGER trg_tt_bd BEFORE DELETE ON thanh_toan FOR EACH ROW
BEGIN
  -- [THÊM] Bảo toàn dấu vết tài chính; trạng thái da_huy thay thế việc xóa phiếu.
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không xóa chứng từ thanh toán; dùng trạng thái da_huy.';
END$$

-- [SỬA] trg_hd_bu: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_hd_bu$$
CREATE TRIGGER trg_hd_bu BEFORE UPDATE ON hoa_don FOR EACH ROW
BEGIN
  DECLARE v_net DECIMAL(12,0);
  -- [THÊM] Kiểm tra dấu vết khi phát hành/hủy mới; bản ghi lịch sử thiếu thông tin giữ NULL.
  IF OLD.trang_thai='nhap' AND NEW.trang_thai NOT IN ('nhap','huy')
    AND (NEW.phat_hanh_boi IS NULL OR NEW.phat_hanh_luc IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Phát hành cần người thực hiện và thời điểm.';
  END IF;
  IF NEW.trang_thai='huy' AND OLD.trang_thai<>'huy'
    AND (NEW.huy_boi IS NULL OR NEW.huy_luc IS NULL OR NEW.ly_do_huy IS NULL OR TRIM(NEW.ly_do_huy)='') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Hủy hóa đơn cần người hủy, thời điểm và lý do.';
  END IF;
  -- [SỬA] Hóa đơn đã có chi tiết/chứng từ không được chuyển nợ sang học viên hoặc kỳ khác.
  IF NEW.id<>OLD.id OR ((NEW.lop_hoc_vien_id<>OLD.lop_hoc_vien_id OR NEW.thang<>OLD.thang OR NEW.nam<>OLD.nam) AND
    (EXISTS(SELECT 1 FROM thanh_toan WHERE hoa_don_id=OLD.id) OR EXISTS(SELECT 1 FROM hoa_don_chi_tiet WHERE hoa_don_id=OLD.id))) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không đổi ID hoặc chủ sở hữu/kỳ của hóa đơn đã có chứng từ.';
  END IF;
  SELECT COALESCE(SUM(IF(loai='thu',so_tien,-so_tien)),0) INTO v_net
    FROM thanh_toan WHERE hoa_don_id=OLD.id AND trang_thai='hop_le';
  IF NEW.da_thu<>v_net OR NEW.tong_tien-NEW.giam_gia<v_net THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Số dư hóa đơn không khớp phiếu thu/hoàn.';
  END IF;
  IF NEW.trang_thai IN ('nhap','huy') AND v_net<>0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Phải xử lý số dư trước khi hủy hoặc đưa hóa đơn về nháp.';
  END IF;
  IF NEW.trang_thai NOT IN ('nhap','huy') THEN
    SET NEW.trang_thai=IF(v_net=NEW.tong_tien-NEW.giam_gia,'da_thanh_toan',IF(v_net>0,'mot_phan','chua_thanh_toan'));
  END IF;
END$$

-- [SỬA] trg_hd_bi: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_hd_bi$$
CREATE TRIGGER trg_hd_bi BEFORE INSERT ON hoa_don FOR EACH ROW
BEGIN
  IF NEW.trang_thai NOT IN ('nhap','huy') AND (NEW.phat_hanh_boi IS NULL OR NEW.phat_hanh_luc IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Phát hành cần người thực hiện và thời điểm.';
  END IF;
  IF NEW.trang_thai='huy' AND (NEW.huy_boi IS NULL OR NEW.huy_luc IS NULL OR NEW.ly_do_huy IS NULL OR TRIM(NEW.ly_do_huy)='') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Hủy hóa đơn cần người hủy, thời điểm và lý do.';
  END IF;
  IF NEW.da_thu<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Hóa đơn mới phải có da_thu=0.'; END IF;
  IF NEW.trang_thai NOT IN ('nhap','huy') THEN
    SET NEW.trang_thai=IF(NEW.tong_tien=NEW.giam_gia,'da_thanh_toan','chua_thanh_toan');
  END IF;
END$$

-- [SỬA] trg_hdct_bi: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_hdct_bi$$
CREATE TRIGGER trg_hdct_bi BEFORE INSERT ON hoa_don_chi_tiet FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_khoan_thu(NEW.hoa_don_id,NEW.buoi_hoc_id);
END$$

-- [SỬA] trg_hdct_bu: tách theo nghiệp vụ; kiểm tra cả thao tác ghi tương ứng.
DROP TRIGGER IF EXISTS trg_hdct_bu$$
CREATE TRIGGER trg_hdct_bu BEFORE UPDATE ON hoa_don_chi_tiet FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_khoan_thu(NEW.hoa_don_id,NEW.buoi_hoc_id);
END$$
DELIMITER ;
