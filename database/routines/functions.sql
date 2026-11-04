-- [SỬA] Routine MySQL tách riêng theo trách nhiệm.
SET NAMES utf8mb4;
DELIMITER $$

-- [SỬA] Tách fn_trung_lich từ file gộp để bảo trì độc lập; giữ chú thích nghiệp vụ trong thân hàm/thủ tục.
DROP FUNCTION IF EXISTS fn_trung_lich$$
CREATE FUNCTION fn_trung_lich(p_user BIGINT UNSIGNED,p_buoi BIGINT UNSIGNED,p_loai_tru BIGINT UNSIGNED)
RETURNS TINYINT READS SQL DATA NOT DETERMINISTIC
BEGIN
  RETURN EXISTS (SELECT 1 FROM phan_cong_buoi_hoc pc
    JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id JOIN buoi_hoc target ON target.id=p_buoi
    WHERE pc.user_id=p_user AND pc.id<>COALESCE(p_loai_tru,0) AND b.id<>p_buoi
      AND pc.trang_thai<>'da_go' AND b.trang_thai NOT IN ('huy','doi')
      AND target.trang_thai NOT IN ('huy','doi') AND b.ngay=target.ngay
      AND b.gio_bat_dau<target.gio_ket_thuc AND b.gio_ket_thuc>target.gio_bat_dau);
END$$

-- [THÊM] Công thức thù lao dùng snapshot; hàm thuần không đọc lớp hiện tại, không cập nhật bảng.
DROP FUNCTION IF EXISTS fn_tien_cong$$
CREATE FUNCTION fn_tien_cong(p_cach VARCHAR(20),p_don_gia DECIMAL(12,0),p_so_phut INT)
RETURNS DECIMAL(14,0) DETERMINISTIC NO SQL
BEGIN
  IF p_cach IS NULL OR p_cach NOT IN ('theo_buoi','theo_gio') OR p_don_gia IS NULL OR p_don_gia<0
    OR p_so_phut IS NULL OR p_so_phut<0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dữ liệu tính công không hợp lệ hoặc chưa có snapshot.';
  END IF;
  RETURN IF(p_cach='theo_gio',ROUND(p_so_phut/60*p_don_gia,0),p_don_gia);
END$$
DELIMITER ;
