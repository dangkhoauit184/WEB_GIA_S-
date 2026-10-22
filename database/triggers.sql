-- [SỬA] Trigger hoàn thiện thay thế trigger trong SQL gốc. Không có DROP TABLE/DELETE dữ liệu.
-- [LƯU Ý] Cài bằng tài khoản triển khai riêng trong lúc dừng ghi dữ liệu.
-- [LƯU Ý] Kết nối ghi dùng READ COMMITTED. Backend sau này phải retry toàn giao dịch khi deadlock.
SET NAMES utf8mb4;
DELIMITER $$

-- [SỬA] Khóa tài nguyên người dạy trước khi kiểm tra trùng, kiểm tra cả INSERT và UPDATE.
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

DROP TRIGGER IF EXISTS trg_pc_bu$$
CREATE TRIGGER trg_pc_bu BEFORE UPDATE ON phan_cong_buoi_hoc FOR EACH ROW
BEGIN
  DECLARE v_user BIGINT UNSIGNED;
  SELECT user_id INTO v_user FROM users WHERE user_id=NEW.user_id FOR UPDATE;
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

-- [THÊM] Không xóa ca đã quyết toán, tránh mất chứng từ dù không UPDATE.
DROP TRIGGER IF EXISTS trg_pc_bd$$
CREATE TRIGGER trg_pc_bd BEFORE DELETE ON phan_cong_buoi_hoc FOR EACH ROW
BEGIN
  IF OLD.bang_cong_id IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không xóa phân công đã gắn bảng công.';
  END IF;
END$$

-- [SỬA] Tái dùng kiểm tra sĩ số cho INSERT/UPDATE, khóa lớp để tránh hai người cùng chiếm chỗ cuối.
DROP TRIGGER IF EXISTS trg_lhv_bi$$
CREATE TRIGGER trg_lhv_bi BEFORE INSERT ON lop_hoc_vien FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_si_so(NEW.lop_id,NEW.trang_thai,0);
END$$
-- [THÊM] Chuyển hoc_thu/dang_hoc hoặc đổi lớp cũng phải kiểm tra sĩ số.
DROP TRIGGER IF EXISTS trg_lhv_bu$$
CREATE TRIGGER trg_lhv_bu BEFORE UPDATE ON lop_hoc_vien FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_si_so(NEW.lop_id,NEW.trang_thai,OLD.id);
END$$

-- [THÊM] Không giảm sĩ số tối đa của lớp xuống dưới số học viên đang chiếm chỗ.
DROP TRIGGER IF EXISTS trg_lop_bu$$
CREATE TRIGGER trg_lop_bu BEFORE UPDATE ON lop_hoc FOR EACH ROW
BEGIN
  IF NEW.si_so_toi_da<(SELECT COUNT(*) FROM lop_hoc_vien WHERE lop_id=OLD.id AND trang_thai IN ('dang_hoc','hoc_thu')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sĩ số tối đa mới thấp hơn số học viên hiện có.';
  END IF;
END$$

-- [THÊM] Chống xung đột phòng và bảo đảm học bù/lịch tuần thuộc đúng lớp khi tạo ca.
DROP TRIGGER IF EXISTS trg_buoi_bi$$
CREATE TRIGGER trg_buoi_bi BEFORE INSERT ON buoi_hoc FOR EACH ROW
BEGIN
  DECLARE v_coso INT UNSIGNED;
  SELECT co_so_id INTO v_coso FROM lop_hoc WHERE id=NEW.lop_id;
  IF NEW.co_so_id IS NULL THEN SET NEW.co_so_id=v_coso; END IF;
  CALL sp_kiem_tra_buoi(0,NEW.lop_id,NEW.lich_hoc_id,NEW.loai,NEW.buoi_goc_id,
    NEW.ngay,NEW.gio_bat_dau,NEW.gio_ket_thuc,NEW.co_so_id,NEW.phong_hoc,NEW.trang_thai);
END$$
DROP TRIGGER IF EXISTS trg_buoi_bu$$
CREATE TRIGGER trg_buoi_bu BEFORE UPDATE ON buoi_hoc FOR EACH ROW
BEGIN
  -- [THÊM] Không đổi lịch, buổi gốc hay học vụ của ca thuộc kỳ đã khóa.
  IF EXISTS (SELECT 1 FROM phan_cong_buoi_hoc pc JOIN bang_cong_thang bc ON bc.id=pc.bang_cong_id
    WHERE pc.buoi_hoc_id=OLD.id AND bc.trang_thai<>'mo') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Buổi học thuộc bảng công đã khóa.';
  END IF;
  CALL sp_kiem_tra_buoi(OLD.id,NEW.lop_id,NEW.lich_hoc_id,NEW.loai,NEW.buoi_goc_id,
    NEW.ngay,NEW.gio_bat_dau,NEW.gio_ket_thuc,NEW.co_so_id,NEW.phong_hoc,NEW.trang_thai);
END$$

-- [SỬA] Điểm danh INSERT và UPDATE đều kiểm tra học viên thuộc lớp; bài tập có thể từ buổi trước cùng lớp.
DROP TRIGGER IF EXISTS trg_sotd_bi$$
CREATE TRIGGER trg_sotd_bi BEFORE INSERT ON so_theo_doi_buoi_hoc FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_hoc_vu(NEW.buoi_hoc_id,NEW.hoc_vien_id,NEW.bai_tap_id);
END$$
DROP TRIGGER IF EXISTS trg_sotd_bu$$
CREATE TRIGGER trg_sotd_bu BEFORE UPDATE ON so_theo_doi_buoi_hoc FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_hoc_vu(NEW.buoi_hoc_id,NEW.hoc_vien_id,NEW.bai_tap_id);
END$$

-- [THÊM] Kiểm tra học viên xin nghỉ thuộc lớp; không tự hủy buổi nhóm khi duyệt nghỉ.
DROP TRIGGER IF EXISTS trg_dxn_bi$$
CREATE TRIGGER trg_dxn_bi BEFORE INSERT ON don_xin_nghi FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_don_nghi(NEW.buoi_hoc_id,NEW.doi_tuong,NEW.hoc_vien_id,NEW.buoi_bu_id);
END$$
DROP TRIGGER IF EXISTS trg_dxn_bu$$
CREATE TRIGGER trg_dxn_bu BEFORE UPDATE ON don_xin_nghi FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_don_nghi(NEW.buoi_hoc_id,NEW.doi_tuong,NEW.hoc_vien_id,NEW.buoi_bu_id);
END$$

-- [SỬA] Chặn thu vượt nợ VÀ hoàn vượt số đã thu, khóa hóa đơn trước khi tính số dư.
DROP TRIGGER IF EXISTS trg_tt_bi$$
CREATE TRIGGER trg_tt_bi BEFORE INSERT ON thanh_toan FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_thanh_toan(NEW.hoa_don_id,IF(NEW.trang_thai='hop_le',IF(NEW.loai='thu',NEW.so_tien,-NEW.so_tien),0));
END$$
-- [THÊM] Không đổi số tiền/danh tính chứng từ đã ghi; sai phiếu thì hủy có kiểm tra số dư, lập phiếu mới.
DROP TRIGGER IF EXISTS trg_tt_bu$$
CREATE TRIGGER trg_tt_bu BEFORE UPDATE ON thanh_toan FOR EACH ROW
BEGIN
  IF NEW.hoa_don_id<>OLD.hoa_don_id OR NEW.loai<>OLD.loai OR NEW.so_tien<>OLD.so_tien
    OR NEW.nguoi_thu_id<>OLD.nguoi_thu_id OR NEW.phuong_thuc<>OLD.phuong_thuc
    OR NOT (NEW.ma_giao_dich <=> OLD.ma_giao_dich) OR NEW.ngay_thanh_toan<>OLD.ngay_thanh_toan THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không sửa chứng từ thu/hoàn; hủy phiếu và lập phiếu thay thế.';
  END IF;
  CALL sp_kiem_tra_thanh_toan(OLD.hoa_don_id,
    IF(NEW.trang_thai='hop_le',IF(NEW.loai='thu',NEW.so_tien,-NEW.so_tien),0)
    - IF(OLD.trang_thai='hop_le',IF(OLD.loai='thu',OLD.so_tien,-OLD.so_tien),0));
END$$
DROP TRIGGER IF EXISTS trg_tt_ai$$
CREATE TRIGGER trg_tt_ai AFTER INSERT ON thanh_toan FOR EACH ROW
BEGIN
  CALL sp_cap_nhat_so_du(NEW.hoa_don_id);
END$$
DROP TRIGGER IF EXISTS trg_tt_au$$
CREATE TRIGGER trg_tt_au AFTER UPDATE ON thanh_toan FOR EACH ROW
BEGIN
  CALL sp_cap_nhat_so_du(NEW.hoa_don_id);
END$$
DROP TRIGGER IF EXISTS trg_tt_bd$$
CREATE TRIGGER trg_tt_bd BEFORE DELETE ON thanh_toan FOR EACH ROW
BEGIN
  -- [THÊM] Bảo toàn dấu vết tài chính; trạng thái da_huy thay thế việc xóa phiếu.
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không xóa chứng từ thanh toán; dùng trạng thái da_huy.';
END$$

-- [THÊM] Không cho hóa đơn lệch số dư so với chứng từ, kể cả người dùng UPDATE trực tiếp.
DROP TRIGGER IF EXISTS trg_hd_bu$$
CREATE TRIGGER trg_hd_bu BEFORE UPDATE ON hoa_don FOR EACH ROW
BEGIN
  DECLARE v_net DECIMAL(12,0);
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
-- [THÊM] Chỉ INSERT hóa đơn với số dư 0, tiền thu phải qua chứng từ.
DROP TRIGGER IF EXISTS trg_hd_bi$$
CREATE TRIGGER trg_hd_bi BEFORE INSERT ON hoa_don FOR EACH ROW
BEGIN
  IF NEW.da_thu<>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Hóa đơn mới phải có da_thu=0.'; END IF;
  IF NEW.trang_thai NOT IN ('nhap','huy') THEN
    SET NEW.trang_thai=IF(NEW.tong_tien=NEW.giam_gia,'da_thanh_toan','chua_thanh_toan');
  END IF;
END$$

-- [THÊM] Chi tiết thu đúng học viên/lớp của hóa đơn, cả khi UPDATE.
DROP TRIGGER IF EXISTS trg_hdct_bi$$
CREATE TRIGGER trg_hdct_bi BEFORE INSERT ON hoa_don_chi_tiet FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_khoan_thu(NEW.hoa_don_id,NEW.hoc_vien_id,NEW.buoi_hoc_id);
END$$
DROP TRIGGER IF EXISTS trg_hdct_bu$$
CREATE TRIGGER trg_hdct_bu BEFORE UPDATE ON hoa_don_chi_tiet FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_khoan_thu(NEW.hoa_don_id,NEW.hoc_vien_id,NEW.buoi_hoc_id);
END$$
DELIMITER ;
