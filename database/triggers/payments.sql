-- [THÊM] 005: Toàn vẹn giao dịch, thông báo và quyền xếp lớp. Không tự xác nhận từ trình duyệt.
SET NAMES utf8mb4;
DELIMITER $$
DROP TRIGGER IF EXISTS trg_gd_bi$$
CREATE TRIGGER trg_gd_bi BEFORE INSERT ON giao_dich_truc_tuyen FOR EACH ROW
BEGIN
  CALL sp_kiem_tra_giao_dich(NEW.hoa_don_id,NEW.phu_huynh_id,NEW.so_tien);
  IF NEW.trang_thai<>'khoi_tao' OR NEW.thanh_toan_id IS NOT NULL OR NEW.xac_nhan_boi IS NOT NULL OR NEW.xac_nhan_luc IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Giao dịch mới chưa được xác nhận thanh toán.';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_gd_bu$$
CREATE TRIGGER trg_gd_bu BEFORE UPDATE ON giao_dich_truc_tuyen FOR EACH ROW
BEGIN
  IF NEW.id<>OLD.id OR NEW.hoa_don_id<>OLD.hoa_don_id OR NEW.phu_huynh_id<>OLD.phu_huynh_id
    OR NEW.ma_yeu_cau<>OLD.ma_yeu_cau OR NEW.so_tien<>OLD.so_tien OR NEW.tien_te<>OLD.tien_te
    OR NEW.nha_cung_cap<>OLD.nha_cung_cap OR NEW.created_at<>OLD.created_at THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không đổi danh tính hoặc số tiền giao dịch; tạo yêu cầu mới.';
  END IF;
  IF OLD.trang_thai IN ('thanh_cong','that_bai','het_han','huy') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Giao dịch đã kết thúc; không sửa hoặc kích hoạt lại.';
  END IF;
  IF OLD.ma_giao_dich_cong IS NOT NULL AND NOT (NEW.ma_giao_dich_cong <=> OLD.ma_giao_dich_cong) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không đổi mã giao dịch của nhà cung cấp.';
  END IF;
  IF NEW.trang_thai='thanh_cong' THEN
    IF OLD.trang_thai<>'cho_xac_nhan' OR fn_la_admin(NEW.xac_nhan_boi)<>1 OR NEW.xac_nhan_luc IS NULL OR NOT EXISTS(
      SELECT 1 FROM thanh_toan t WHERE t.id=NEW.thanh_toan_id AND t.hoa_don_id=NEW.hoa_don_id
        AND t.so_tien=NEW.so_tien AND t.loai='thu' AND t.trang_thai='hop_le' AND t.nguoi_thu_id=NEW.xac_nhan_boi) THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Thành công cần phiếu thu đúng hóa đơn/số tiền và Admin xác nhận.';
    END IF;
  ELSEIF NEW.thanh_toan_id IS NOT NULL OR NEW.xac_nhan_boi IS NOT NULL OR NEW.xac_nhan_luc IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Giao dịch chưa thành công không có phiếu thu hoặc thông tin xác nhận.';
  ELSEIF OLD.trang_thai='cho_xac_nhan' AND NEW.trang_thai='khoi_tao' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không đưa giao dịch chờ đối soát về khởi tạo.';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_gd_bd$$
CREATE TRIGGER trg_gd_bd BEFORE DELETE ON giao_dich_truc_tuyen FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Giữ lịch sử giao dịch; dùng trạng thái hủy.';
END$$

DROP TRIGGER IF EXISTS trg_sk_bu$$
CREATE TRIGGER trg_sk_bu BEFORE UPDATE ON su_kien_thanh_toan FOR EACH ROW
BEGIN
  IF NEW.id<>OLD.id OR NEW.giao_dich_id<>OLD.giao_dich_id OR NEW.nha_cung_cap<>OLD.nha_cung_cap
    OR NEW.ma_su_kien<>OLD.ma_su_kien OR NEW.payload_sha256<>OLD.payload_sha256 OR NEW.nhan_luc<>OLD.nhan_luc THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không sửa nội dung/định danh sự kiện thanh toán.';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_sk_bd$$
CREATE TRIGGER trg_sk_bd BEFORE DELETE ON su_kien_thanh_toan FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không xóa dấu vết sự kiện thanh toán.';
END$$

-- Thông báo trong web là bản ghi DB; email được worker BE gửi sau, không gửi mạng trong trigger.
DROP TRIGGER IF EXISTS trg_hd_thong_bao_ai$$
CREATE TRIGGER trg_hd_thong_bao_ai AFTER INSERT ON hoa_don FOR EACH ROW
BEGIN
  IF NEW.dang_ky_khoa_hoc_id IS NOT NULL AND NEW.trang_thai NOT IN ('nhap','huy') THEN
    INSERT INTO thong_bao(user_id,loai,tieu_de,noi_dung,doi_tuong_loai,doi_tuong_id,khoa_chong_trung)
      VALUES(fn_phu_huynh_hoa_don(NEW.id),'hoa_don','Thông báo học phí khóa học',CONCAT('Hóa đơn ',NEW.ma_hd,': ',NEW.phai_thu,' VND.'),'hoa_don',NEW.id,CONCAT('hocphi:',NEW.id));
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_hd_thong_bao_au$$
CREATE TRIGGER trg_hd_thong_bao_au AFTER UPDATE ON hoa_don FOR EACH ROW
BEGIN
  IF NEW.dang_ky_khoa_hoc_id IS NOT NULL AND OLD.trang_thai='nhap' AND NEW.trang_thai NOT IN ('nhap','huy') THEN
    INSERT INTO thong_bao(user_id,loai,tieu_de,noi_dung,doi_tuong_loai,doi_tuong_id,khoa_chong_trung)
      VALUES(fn_phu_huynh_hoa_don(NEW.id),'hoa_don','Thông báo học phí khóa học',CONCAT('Hóa đơn ',NEW.ma_hd,': ',NEW.phai_thu,' VND.'),'hoa_don',NEW.id,CONCAT('hocphi:',NEW.id))
      ON DUPLICATE KEY UPDATE id=thong_bao.id;
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_tt_thong_bao_ai$$
CREATE TRIGGER trg_tt_thong_bao_ai AFTER INSERT ON thanh_toan FOR EACH ROW
BEGIN
  IF NEW.loai='thu' AND NEW.trang_thai='hop_le' AND EXISTS(SELECT 1 FROM hoa_don WHERE id=NEW.hoa_don_id AND dang_ky_khoa_hoc_id IS NOT NULL) THEN
    INSERT INTO thong_bao(user_id,loai,tieu_de,noi_dung,doi_tuong_loai,doi_tuong_id,khoa_chong_trung)
      SELECT a.user_id,'hoa_don','Đã ghi nhận học phí',CONCAT('Hóa đơn #',NEW.hoa_don_id,' nhận ',NEW.so_tien,' VND.'),'thanh_toan',NEW.id,CONCAT('thu:',NEW.id)
      FROM account a JOIN users u ON u.user_id=a.user_id JOIN role_group g ON g.group_id=a.group_id
      WHERE a.status='active' AND a.is_deleted=0 AND u.is_deleted=0 AND g.is_deleted=0 AND g.group_name IN ('Admin','NhanVien');
    INSERT INTO thong_bao(user_id,loai,tieu_de,noi_dung,doi_tuong_loai,doi_tuong_id,khoa_chong_trung)
      VALUES(fn_phu_huynh_hoa_don(NEW.hoa_don_id),'hoa_don','Đã ghi nhận thanh toán',CONCAT('Đã nhận ',NEW.so_tien,' VND cho hóa đơn #',NEW.hoa_don_id,'.'),'thanh_toan',NEW.id,CONCAT('thu:',NEW.id));
  END IF;
END$$

-- Chỉ áp dụng kiểm tra mới khi ghi danh/gán khóa mới; không bịa actor cho hồ sơ lịch sử.
DROP TRIGGER IF EXISTS trg_lhv_admin_bi$$
CREATE TRIGGER trg_lhv_admin_bi BEFORE INSERT ON lop_hoc_vien FOR EACH ROW
BEGIN
  IF NEW.dang_ky_khoa_hoc_id IS NOT NULL AND (fn_la_admin(NEW.xep_boi)<>1 OR NEW.xep_luc IS NULL) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Xếp lớp khóa học cần Admin thực hiện; không phụ thuộc học phí.';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_lhv_admin_bu$$
CREATE TRIGGER trg_lhv_admin_bu BEFORE UPDATE ON lop_hoc_vien FOR EACH ROW
BEGIN
  IF NEW.dang_ky_khoa_hoc_id IS NOT NULL AND (NOT (NEW.dang_ky_khoa_hoc_id <=> OLD.dang_ky_khoa_hoc_id)
    OR NEW.lop_id<>OLD.lop_id OR NEW.hoc_vien_id<>OLD.hoc_vien_id OR NEW.trang_thai<>OLD.trang_thai
    OR NEW.ngay_vao<>OLD.ngay_vao OR NOT (NEW.ngay_roi <=> OLD.ngay_roi)
    OR NOT (NEW.xep_boi <=> OLD.xep_boi) OR NOT (NEW.xep_luc <=> OLD.xep_luc)) THEN
    IF fn_la_admin(NEW.xep_boi)<>1 OR NEW.xep_luc IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Thay đổi xếp lớp cần Admin thực hiện.';
    END IF;
  END IF;
END$$
DELIMITER ;
