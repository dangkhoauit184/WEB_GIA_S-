-- [THÊM] 004: Khóa học, học bù theo thỏa thuận và các quyền tài chính đã chốt.
SET NAMES utf8mb4;
DELIMITER $$
DROP TRIGGER IF EXISTS trg_dkk_bi$$
CREATE TRIGGER trg_dkk_bi BEFORE INSERT ON dang_ky_khoa_hoc FOR EACH ROW
BEGIN
  DECLARE v_gia DECIMAL(12,0);
  SELECT hoc_phi INTO v_gia FROM khoa_hoc WHERE id=NEW.khoa_hoc_id AND is_active=1 FOR UPDATE;
  IF v_gia IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Khóa không tồn tại hoặc ngừng đăng ký.'; END IF;
  IF NOT EXISTS(SELECT 1 FROM hoc_vien WHERE id=NEW.hoc_vien_id AND deleted_at IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Học viên không tồn tại hoặc đã xóa.';
  END IF;
  SET NEW.hoc_phi_snapshot=v_gia;
END$$
DROP TRIGGER IF EXISTS trg_dkk_bu$$
CREATE TRIGGER trg_dkk_bu BEFORE UPDATE ON dang_ky_khoa_hoc FOR EACH ROW
BEGIN
  IF NEW.id<>OLD.id OR NEW.khoa_hoc_id<>OLD.khoa_hoc_id OR NEW.hoc_vien_id<>OLD.hoc_vien_id
    OR NEW.hoc_phi_snapshot<>OLD.hoc_phi_snapshot OR NEW.ngay_dang_ky<>OLD.ngay_dang_ky THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không đổi danh tính hoặc giá khóa của đăng ký đã tạo.';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_lop_khoa_bi$$
CREATE TRIGGER trg_lop_khoa_bi BEFORE INSERT ON lop_hoc FOR EACH ROW
BEGIN
  IF NEW.khoa_hoc_id IS NOT NULL THEN
    SET NEW.cach_thu_phi='theo_khoa';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_lop_khoa_bu$$
CREATE TRIGGER trg_lop_khoa_bu BEFORE UPDATE ON lop_hoc FOR EACH ROW
BEGIN
  IF NOT (NEW.khoa_hoc_id <=> OLD.khoa_hoc_id) AND EXISTS(SELECT 1 FROM lop_hoc_vien WHERE lop_id=OLD.id AND dang_ky_khoa_hoc_id IS NOT NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lớp đã gắn đăng ký khóa; không chuyển sang khóa khác.';
  END IF;
  IF NEW.khoa_hoc_id IS NOT NULL THEN SET NEW.cach_thu_phi='theo_khoa'; END IF;
END$$
DROP TRIGGER IF EXISTS trg_lhv_khoa_bi$$
CREATE TRIGGER trg_lhv_khoa_bi BEFORE INSERT ON lop_hoc_vien FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_dang_ky_khoa(NEW.dang_ky_khoa_hoc_id,NEW.lop_id,NEW.hoc_vien_id);
END$$
DROP TRIGGER IF EXISTS trg_lhv_khoa_bu$$
CREATE TRIGGER trg_lhv_khoa_bu BEFORE UPDATE ON lop_hoc_vien FOR EACH ROW
BEGIN
  IF OLD.dang_ky_khoa_hoc_id IS NOT NULL AND NOT (NEW.dang_ky_khoa_hoc_id <=> OLD.dang_ky_khoa_hoc_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không xóa hoặc đổi liên kết đăng ký khóa của ghi danh.';
  END IF;
  IF NEW.trang_thai<>'da_roi' THEN CALL sp_kiem_tra_dang_ky_khoa(NEW.dang_ky_khoa_hoc_id,NEW.lop_id,NEW.hoc_vien_id); END IF;
END$$

DROP TRIGGER IF EXISTS trg_hd_khoa_bi$$
CREATE TRIGGER trg_hd_khoa_bi BEFORE INSERT ON hoa_don FOR EACH ROW
BEGIN
  DECLARE v_phi DECIMAL(12,0);
  IF NEW.dang_ky_khoa_hoc_id IS NOT NULL THEN
    SELECT hoc_phi_snapshot INTO v_phi FROM dang_ky_khoa_hoc WHERE id=NEW.dang_ky_khoa_hoc_id AND trang_thai<>'thoi_hoc';
    IF v_phi IS NULL OR NEW.tong_tien<>v_phi OR NEW.giam_gia<>0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Hóa đơn trọn khóa phải đúng học phí đăng ký; không sinh phí bù.';
    END IF;
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_hd_khoa_bu$$
CREATE TRIGGER trg_hd_khoa_bu BEFORE UPDATE ON hoa_don FOR EACH ROW
BEGIN
  IF NOT (NEW.dang_ky_khoa_hoc_id <=> OLD.dang_ky_khoa_hoc_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không chuyển hóa đơn sang đăng ký khóa khác.';
  END IF;
  IF NEW.dang_ky_khoa_hoc_id IS NOT NULL AND (NEW.tong_tien<>OLD.tong_tien OR NEW.giam_gia<>OLD.giam_gia) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Học phí trọn khóa được giữ theo đăng ký.';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_tt_thoi_hoc_bi$$
CREATE TRIGGER trg_tt_thoi_hoc_bi BEFORE INSERT ON thanh_toan FOR EACH ROW
BEGIN
  IF NEW.loai='hoan' AND EXISTS(SELECT 1 FROM hoa_don h JOIN dang_ky_khoa_hoc d ON d.id=h.dang_ky_khoa_hoc_id
    WHERE h.id=NEW.hoa_don_id AND d.trang_thai='thoi_hoc') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Tự thôi học không được hoàn học phí khóa.';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_tt_thoi_hoc_bu$$
CREATE TRIGGER trg_tt_thoi_hoc_bu BEFORE UPDATE ON thanh_toan FOR EACH ROW
BEGIN
  IF OLD.trang_thai='da_huy' AND NEW.trang_thai='hop_le' AND NEW.loai='hoan' AND EXISTS
    (SELECT 1 FROM hoa_don h JOIN dang_ky_khoa_hoc d ON d.id=h.dang_ky_khoa_hoc_id
      WHERE h.id=NEW.hoa_don_id AND d.trang_thai='thoi_hoc') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không kích hoạt phiếu hoàn cho khóa đã tự thôi học.';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_dxn_quyen_bi$$
CREATE TRIGGER trg_dxn_quyen_bi BEFORE INSERT ON don_xin_nghi FOR EACH ROW
BEGIN
  IF NEW.trang_thai IN ('da_duyet','tu_choi') AND (fn_la_giao_vu(NEW.nguoi_duyet_id)<>1 OR NEW.duyet_luc IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Nghỉ cá nhân cần giáo vụ hoặc Admin duyệt.';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_dxn_quyen_bu$$
CREATE TRIGGER trg_dxn_quyen_bu BEFORE UPDATE ON don_xin_nghi FOR EACH ROW
BEGIN
  IF NEW.trang_thai IN ('da_duyet','tu_choi') AND
    (NEW.trang_thai<>OLD.trang_thai OR NOT (NEW.nguoi_duyet_id <=> OLD.nguoi_duyet_id))
    AND (fn_la_giao_vu(NEW.nguoi_duyet_id)<>1 OR NEW.duyet_luc IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Nghỉ cá nhân cần giáo vụ hoặc Admin duyệt.';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_nghi_lop_bi$$
CREATE TRIGGER trg_nghi_lop_bi BEFORE INSERT ON yeu_cau_nghi_lop FOR EACH ROW
BEGIN
  IF NEW.trang_thai<>'cho_duyet' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Yêu cầu nghỉ lớp mới phải chờ duyệt.'; END IF;
END$$
DROP TRIGGER IF EXISTS trg_nghi_lop_bu$$
CREATE TRIGGER trg_nghi_lop_bu BEFORE UPDATE ON yeu_cau_nghi_lop FOR EACH ROW
BEGIN
  IF NEW.id<>OLD.id OR NEW.buoi_hoc_id<>OLD.buoi_hoc_id OR NEW.nguoi_gui_id<>OLD.nguoi_gui_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không đổi danh tính yêu cầu nghỉ lớp.';
  END IF;
  IF OLD.trang_thai<>'cho_duyet' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Yêu cầu nghỉ lớp đã có quyết định.'; END IF;
  IF NEW.trang_thai IN ('da_duyet','tu_choi') AND (fn_la_admin(NEW.nguoi_duyet_id)<>1 OR NEW.duyet_luc IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chỉ Admin được duyệt nghỉ lớp.';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_nghi_lop_au$$
CREATE TRIGGER trg_nghi_lop_au AFTER UPDATE ON yeu_cau_nghi_lop FOR EACH ROW
BEGIN
  IF OLD.trang_thai='cho_duyet' AND NEW.trang_thai='da_duyet' THEN
    UPDATE buoi_hoc SET trang_thai='huy',nguoi_huy_id=NEW.nguoi_duyet_id,huy_luc=NEW.duyet_luc,
      huy_boi='trung_tam',ly_do_huy_doi=NEW.ly_do WHERE id=NEW.buoi_hoc_id;
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_hoc_bu_bi$$
CREATE TRIGGER trg_hoc_bu_bi BEFORE INSERT ON hoc_bu_hoc_vien FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_hoc_bu_ca_nhan(NEW.buoi_bu_id,NEW.hoc_vien_id,NEW.don_xin_nghi_id,NEW.xep_boi);
END$$
DROP TRIGGER IF EXISTS trg_hoc_bu_bu$$
CREATE TRIGGER trg_hoc_bu_bu BEFORE UPDATE ON hoc_bu_hoc_vien FOR EACH ROW
BEGIN
  IF NEW.id<>OLD.id OR NEW.hoc_vien_id<>OLD.hoc_vien_id OR NEW.buoi_bu_id<>OLD.buoi_bu_id THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không đổi danh tính lịch bù học viên.';
  END IF;
  CALL sp_kiem_tra_hoc_bu_ca_nhan(NEW.buoi_bu_id,NEW.hoc_vien_id,NEW.don_xin_nghi_id,NEW.xep_boi);
END$$

-- [THÊM] Ma trận không thể cấp xem/sửa công-lương cho nhóm ngoài Admin.
DROP TRIGGER IF EXISTS trg_quyen_luong_bi$$
CREATE TRIGGER trg_quyen_luong_bi BEFORE INSERT ON role_group_function FOR EACH ROW
BEGIN
  IF EXISTS(SELECT 1 FROM `function` f JOIN role_group g ON g.group_id=NEW.group_id
    WHERE f.function_id=NEW.function_id AND f.name_function='ChamCongLuong' AND g.group_name<>'Admin')
    AND (NEW.view<>0 OR NEW.`add`<>0 OR NEW.edit<>0 OR NEW.`delete`<>0 OR NEW.download<>0) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Công và lương chỉ dành cho Admin.';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_quyen_luong_bu$$
CREATE TRIGGER trg_quyen_luong_bu BEFORE UPDATE ON role_group_function FOR EACH ROW
BEGIN
  IF EXISTS(SELECT 1 FROM `function` f JOIN role_group g ON g.group_id=NEW.group_id
    WHERE f.function_id=NEW.function_id AND f.name_function='ChamCongLuong' AND g.group_name<>'Admin')
    AND (NEW.view<>0 OR NEW.`add`<>0 OR NEW.edit<>0 OR NEW.`delete`<>0 OR NEW.download<>0) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Công và lương chỉ dành cho Admin.';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_don_gia_bi$$
CREATE TRIGGER trg_don_gia_bi BEFORE INSERT ON lich_su_don_gia_gio FOR EACH ROW
BEGIN
  IF fn_la_admin(NEW.nguoi_dat_id)<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chỉ Admin được đặt đơn giá giờ.'; END IF;
END$$
DROP TRIGGER IF EXISTS trg_don_gia_bu$$
CREATE TRIGGER trg_don_gia_bu BEFORE UPDATE ON lich_su_don_gia_gio FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không sửa lịch sử đơn giá; ghi thay đổi mới.';
END$$
DROP TRIGGER IF EXISTS trg_don_gia_bd$$
CREATE TRIGGER trg_don_gia_bd BEFORE DELETE ON lich_su_don_gia_gio FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không xóa lịch sử đơn giá.';
END$$
DELIMITER ;
