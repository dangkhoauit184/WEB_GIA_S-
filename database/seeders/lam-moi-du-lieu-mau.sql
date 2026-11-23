-- ============================================================================
-- EDUCENTER ERP · LÀM MỚI BỘ DỮ LIỆU TRÌNH DIỄN CHO TẤT CẢ VAI TRÒ (SCHEMA 006)
-- ============================================================================
-- Hướng dẫn chạy an toàn (MySQL 8.4, múi giờ Asia/Ho_Chi_Minh):
--   - File gồm 2 phần tách biệt rõ ràng:
--       PHẦN 1: XÓA BỘ SEED CŨ (có truy vấn xem trước số bản ghi trước khi xóa)
--       PHẦN 2: TẠO BỘ DỮ LIỆU MỚI (liên kết đầy đủ cho 6 vai trò đăng nhập + học viên)
--   - Tuyệt đối KHÔNG dùng DROP DATABASE, KHÔNG dùng TRUNCATE, KHÔNG tắt FOREIGN_KEY_CHECKS.
--   - Giữ nguyên tài khoản, mật khẩu đăng nhập hiện có (demo_admin, demo_giaovu, ...).
--   - Bảo toàn 100% dữ liệu môn học và khóa học Toán, Lịch sử cùng mọi dữ liệu ngoài bộ seed.
--   - Nên sao lưu DB trước khi thực thi. Có thể chạy lại Phần 1 rồi Phần 2 nhiều lần không trùng.
-- ============================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET SESSION time_zone = '+07:00';
SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED;

-- ============================================================================
-- 1. XÓA BỘ SEED CŨ
-- ============================================================================
-- Tập tài khoản seed cần dọn dữ liệu nghiệp vụ cũ (giữ nguyên tài khoản & mật khẩu):
--   demo_admin, demo_giaovu, demo_giaovien, demo_gv02..05, demo_giasu,
--   demo_trogiang, demo_tg02, demo_phuhuynh, demo_ph02..05
-- Quy tắc bảo toàn:
--   - KHÔNG xóa môn học (mon_hoc).
--   - KHÔNG xóa hoặc sửa bất kỳ khóa học (khoa_hoc) nào liên quan Toán / Lịch sử
--     hoặc khóa học tạo ngoài bộ seed (không mang mã SQLDEMO01-% / EC26-%).
--   - KHÔNG xóa dữ liệu của tài khoản không nằm trong danh sách seed trên.

-- 1.1. XEM TRƯỚC SỐ BẢN GHI SẼ BỊ XÓA (chỉ đọc — kiểm tra trước khi chạy xóa)
SELECT 'su_kien_thanh_toan' AS bang, COUNT(*) AS so_ban_ghi_se_xoa
FROM su_kien_thanh_toan sk
JOIN giao_dich_truc_tuyen gd ON gd.id = sk.giao_dich_id
JOIN hoa_don hd ON hd.id = gd.hoa_don_id
WHERE hd.ma_hd LIKE 'SQLDEMO01-%' OR hd.ma_hd LIKE 'EC26-%'
UNION ALL
SELECT 'giao_dich_truc_tuyen', COUNT(*)
FROM giao_dich_truc_tuyen gd
JOIN hoa_don hd ON hd.id = gd.hoa_don_id
WHERE hd.ma_hd LIKE 'SQLDEMO01-%' OR hd.ma_hd LIKE 'EC26-%'
UNION ALL
SELECT 'thanh_toan', COUNT(*)
FROM thanh_toan tt
JOIN hoa_don hd ON hd.id = tt.hoa_don_id
WHERE hd.ma_hd LIKE 'SQLDEMO01-%' OR hd.ma_hd LIKE 'EC26-%'
UNION ALL
SELECT 'hoa_don_chi_tiet', COUNT(*)
FROM hoa_don_chi_tiet ct
JOIN hoa_don hd ON hd.id = ct.hoa_don_id
WHERE hd.ma_hd LIKE 'SQLDEMO01-%' OR hd.ma_hd LIKE 'EC26-%'
UNION ALL
SELECT 'hoa_don', COUNT(*)
FROM hoa_don
WHERE ma_hd LIKE 'SQLDEMO01-%' OR ma_hd LIKE 'EC26-%'
UNION ALL
SELECT 'ung_tuyen_ca', COUNT(*)
FROM ung_tuyen_ca uc
JOIN de_xuat_tuyen_ca dx ON dx.id = uc.de_xuat_id
JOIN buoi_hoc b ON b.id = dx.buoi_hoc_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%' OR dx.ma_de_xuat LIKE 'EC26-%'
UNION ALL
SELECT 'de_xuat_tuyen_ca', COUNT(*)
FROM de_xuat_tuyen_ca dx
JOIN buoi_hoc b ON b.id = dx.buoi_hoc_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%' OR dx.ma_de_xuat LIKE 'EC26-%'
UNION ALL
SELECT 'so_theo_doi_buoi_hoc', COUNT(*)
FROM so_theo_doi_buoi_hoc s
JOIN buoi_hoc b ON b.id = s.buoi_hoc_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%'
UNION ALL
SELECT 'ket_qua_hoc_tap', COUNT(*)
FROM ket_qua_hoc_tap kq
JOIN lop_hoc l ON l.id = kq.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%'
UNION ALL
SELECT 'bai_tap', COUNT(*)
FROM bai_tap bt
JOIN buoi_hoc b ON b.id = bt.buoi_hoc_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%'
UNION ALL
SELECT 'hoc_bu_hoc_vien', COUNT(*)
FROM hoc_bu_hoc_vien hb
JOIN buoi_hoc b ON b.id = hb.buoi_bu_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%'
UNION ALL
SELECT 'don_xin_nghi', COUNT(*)
FROM don_xin_nghi dn
JOIN buoi_hoc b ON b.id = dn.buoi_hoc_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%'
UNION ALL
SELECT 'yeu_cau_nghi_lop', COUNT(*)
FROM yeu_cau_nghi_lop nl
JOIN buoi_hoc b ON b.id = nl.buoi_hoc_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%'
UNION ALL
SELECT 'dieu_chinh_cong', COUNT(*)
FROM dieu_chinh_cong dc
JOIN phan_cong_buoi_hoc pc ON pc.id = dc.phan_cong_id
JOIN buoi_hoc b ON b.id = pc.buoi_hoc_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%'
UNION ALL
SELECT 'phan_cong_buoi_hoc', COUNT(*)
FROM phan_cong_buoi_hoc pc
JOIN buoi_hoc b ON b.id = pc.buoi_hoc_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%'
UNION ALL
SELECT 'bang_cong_thang', COUNT(*)
FROM bang_cong_thang bc
JOIN account a ON a.user_id = bc.user_id
WHERE a.username IN ('demo_giaovien','demo_gv02','demo_gv03','demo_gv04','demo_gv05','demo_giasu','demo_trogiang','demo_tg02')
UNION ALL
SELECT 'buoi_hoc', COUNT(*)
FROM buoi_hoc b
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%'
UNION ALL
SELECT 'lich_hoc', COUNT(*)
FROM lich_hoc lh
JOIN lop_hoc l ON l.id = lh.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%'
UNION ALL
SELECT 'lop_hoc_vien', COUNT(*)
FROM lop_hoc_vien lhv
JOIN lop_hoc l ON l.id = lhv.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%'
UNION ALL
SELECT 'lop_hoc', COUNT(*)
FROM lop_hoc
WHERE ma_lop LIKE 'SQLDEMO01-%' OR ma_lop LIKE 'EC26-%'
UNION ALL
SELECT 'dang_ky_khoa_hoc', COUNT(*)
FROM dang_ky_khoa_hoc dk
JOIN hoc_vien hv ON hv.id = dk.hoc_vien_id
WHERE hv.ma_hv LIKE 'SQLDEMO01-%' OR hv.ma_hv LIKE 'EC26-%'
UNION ALL
SELECT 'de_xuat_nhan_su', COUNT(*)
FROM de_xuat_nhan_su dx
JOIN yeu_cau_hoc yc ON yc.id = dx.yeu_cau_id
WHERE yc.ma_yc LIKE 'SQLDEMO01-%' OR yc.ma_yc LIKE 'EC26-%'
UNION ALL
SELECT 'yeu_cau_lich', COUNT(*)
FROM yeu_cau_lich yl
JOIN yeu_cau_hoc yc ON yc.id = yl.yeu_cau_id
WHERE yc.ma_yc LIKE 'SQLDEMO01-%' OR yc.ma_yc LIKE 'EC26-%'
UNION ALL
SELECT 'yeu_cau_hoc', COUNT(*)
FROM yeu_cau_hoc
WHERE ma_yc LIKE 'SQLDEMO01-%' OR ma_yc LIKE 'EC26-%'
UNION ALL
SELECT 'hoc_vien', COUNT(*)
FROM hoc_vien
WHERE ma_hv LIKE 'SQLDEMO01-%' OR ma_hv LIKE 'EC26-%'
UNION ALL
SELECT 'khoa_hoc (trừ Toán và Lịch sử)', COUNT(*)
FROM khoa_hoc k
WHERE (k.ma LIKE 'SQLDEMO01-%' OR k.ma LIKE 'EC26-%')
  AND k.ten NOT LIKE '%Toán%' AND k.ten NOT LIKE '%Lịch sử%'
  AND k.ma NOT LIKE '%TOAN%' AND k.ma NOT LIKE '%SU%'
UNION ALL
SELECT 'co_so', COUNT(*)
FROM co_so
WHERE ma_co_so LIKE 'SQLDEMO01-%' OR ma_co_so LIKE 'EC26-%'
UNION ALL
SELECT 'thong_bao (tài khoản seed)', COUNT(*)
FROM thong_bao tb
JOIN account a ON a.user_id = tb.user_id
WHERE a.username IN ('demo_admin','demo_giaovu','demo_giaovien','demo_gv02','demo_gv03','demo_gv04','demo_gv05','demo_giasu','demo_trogiang','demo_tg02','demo_phuhuynh','demo_ph02','demo_ph03','demo_ph04','demo_ph05');

-- 1.2. THỰC THI XÓA BỘ SEED CŨ ĐÚNG THỨ TỰ KHÓA NGOẠI (FOREIGN_KEY_CHECKS = 1)
-- Tạm gỡ đúng 5 trigger BEFORE DELETE bảo vệ lịch sử bất biến để xóa riêng các bản ghi seed,
-- rồi khôi phục nguyên trạng ngay cuối Phần 1.
DROP TRIGGER IF EXISTS trg_sk_bd;
DROP TRIGGER IF EXISTS trg_gd_bd;
DROP TRIGGER IF EXISTS trg_tt_bd;
DROP TRIGGER IF EXISTS trg_don_gia_bd;
DROP TRIGGER IF EXISTS trg_pc_bd;

START TRANSACTION;

-- Bước 1: Chứng từ thanh toán & hóa đơn thuộc bộ seed
DELETE sk FROM su_kien_thanh_toan sk
JOIN giao_dich_truc_tuyen gd ON gd.id = sk.giao_dich_id
JOIN hoa_don hd ON hd.id = gd.hoa_don_id
WHERE hd.ma_hd LIKE 'SQLDEMO01-%' OR hd.ma_hd LIKE 'EC26-%';

DELETE gd FROM giao_dich_truc_tuyen gd
JOIN hoa_don hd ON hd.id = gd.hoa_don_id
WHERE hd.ma_hd LIKE 'SQLDEMO01-%' OR hd.ma_hd LIKE 'EC26-%';

DELETE tt FROM thanh_toan tt
JOIN hoa_don hd ON hd.id = tt.hoa_don_id
WHERE hd.ma_hd LIKE 'SQLDEMO01-%' OR hd.ma_hd LIKE 'EC26-%';

DELETE ct FROM hoa_don_chi_tiet ct
JOIN hoa_don hd ON hd.id = ct.hoa_don_id
WHERE hd.ma_hd LIKE 'SQLDEMO01-%' OR hd.ma_hd LIKE 'EC26-%';

DELETE FROM hoa_don
WHERE ma_hd LIKE 'SQLDEMO01-%' OR ma_hd LIKE 'EC26-%';

-- Bước 2: Đề xuất tuyển ca & ứng tuyển ca thuộc lớp seed
DELETE uc FROM ung_tuyen_ca uc
JOIN de_xuat_tuyen_ca dx ON dx.id = uc.de_xuat_id
JOIN buoi_hoc b ON b.id = dx.buoi_hoc_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%' OR dx.ma_de_xuat LIKE 'EC26-%';

DELETE dx FROM de_xuat_tuyen_ca dx
JOIN buoi_hoc b ON b.id = dx.buoi_hoc_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%' OR dx.ma_de_xuat LIKE 'EC26-%';

-- Bước 3: Học vụ, bài tập, điểm danh, học bù, đơn nghỉ, điều chỉnh công thuộc lớp seed
DELETE s FROM so_theo_doi_buoi_hoc s
JOIN buoi_hoc b ON b.id = s.buoi_hoc_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%';

DELETE kq FROM ket_qua_hoc_tap kq
JOIN lop_hoc l ON l.id = kq.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%';

DELETE bt FROM bai_tap bt
JOIN buoi_hoc b ON b.id = bt.buoi_hoc_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%';

DELETE hb FROM hoc_bu_hoc_vien hb
JOIN buoi_hoc b ON b.id = hb.buoi_bu_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%';

DELETE dn FROM don_xin_nghi dn
JOIN buoi_hoc b ON b.id = dn.buoi_hoc_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%';

DELETE nl FROM yeu_cau_nghi_lop nl
JOIN buoi_hoc b ON b.id = nl.buoi_hoc_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%';

DELETE dc FROM dieu_chinh_cong dc
JOIN phan_cong_buoi_hoc pc ON pc.id = dc.phan_cong_id
JOIN buoi_hoc b ON b.id = pc.buoi_hoc_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%';

-- Bước 4: Phân công buổi học & bảng công tháng của nhân sự seed
DELETE pc FROM phan_cong_buoi_hoc pc
JOIN buoi_hoc b ON b.id = pc.buoi_hoc_id
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%';

DELETE bc FROM bang_cong_thang bc
JOIN account a ON a.user_id = bc.user_id
WHERE a.username IN ('demo_giaovien','demo_gv02','demo_gv03','demo_gv04','demo_gv05','demo_giasu','demo_trogiang','demo_tg02')
  AND NOT EXISTS (SELECT 1 FROM phan_cong_buoi_hoc pc WHERE pc.bang_cong_id = bc.id);

-- Bước 5: Buổi học (xóa buổi bù trước để giữ FK buoi_goc_id), lịch tuần, ghi danh, lớp học seed
DELETE b FROM buoi_hoc b
JOIN lop_hoc l ON l.id = b.lop_id
WHERE (l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%') AND b.buoi_goc_id IS NOT NULL;

DELETE b FROM buoi_hoc b
JOIN lop_hoc l ON l.id = b.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%';

DELETE lh FROM lich_hoc lh
JOIN lop_hoc l ON l.id = lh.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%';

DELETE lhv FROM lop_hoc_vien lhv
JOIN lop_hoc l ON l.id = lhv.lop_id
WHERE l.ma_lop LIKE 'SQLDEMO01-%' OR l.ma_lop LIKE 'EC26-%';

DELETE FROM lop_hoc
WHERE ma_lop LIKE 'SQLDEMO01-%' OR ma_lop LIKE 'EC26-%';

-- Bước 6: Đăng ký khóa học, tuyển sinh, học viên seed
DELETE dk FROM dang_ky_khoa_hoc dk
JOIN hoc_vien hv ON hv.id = dk.hoc_vien_id
WHERE (hv.ma_hv LIKE 'SQLDEMO01-%' OR hv.ma_hv LIKE 'EC26-%')
  AND NOT EXISTS (SELECT 1 FROM hoa_don hd WHERE hd.dang_ky_khoa_hoc_id = dk.id)
  AND NOT EXISTS (SELECT 1 FROM lop_hoc_vien lhv WHERE lhv.dang_ky_khoa_hoc_id = dk.id);

DELETE dx FROM de_xuat_nhan_su dx
JOIN yeu_cau_hoc yc ON yc.id = dx.yeu_cau_id
WHERE yc.ma_yc LIKE 'SQLDEMO01-%' OR yc.ma_yc LIKE 'EC26-%';

DELETE yl FROM yeu_cau_lich yl
JOIN yeu_cau_hoc yc ON yc.id = yl.yeu_cau_id
WHERE yc.ma_yc LIKE 'SQLDEMO01-%' OR yc.ma_yc LIKE 'EC26-%';

DELETE FROM yeu_cau_hoc
WHERE ma_yc LIKE 'SQLDEMO01-%' OR ma_yc LIKE 'EC26-%';

DELETE FROM hoc_vien
WHERE (ma_hv LIKE 'SQLDEMO01-%' OR ma_hv LIKE 'EC26-%')
  AND NOT EXISTS (SELECT 1 FROM dang_ky_khoa_hoc dk WHERE dk.hoc_vien_id = hoc_vien.id)
  AND NOT EXISTS (SELECT 1 FROM lop_hoc_vien lhv WHERE lhv.hoc_vien_id = hoc_vien.id);

-- Bước 7: Khóa học seed cũ (BẢO TOÀN 100% khóa học Toán, Lịch sử và khóa ngoài bộ seed)
DELETE FROM khoa_hoc
WHERE (ma LIKE 'SQLDEMO01-%' OR ma LIKE 'EC26-%')
  AND ten NOT LIKE '%Toán%'
  AND ten NOT LIKE '%Lịch sử%'
  AND ma NOT LIKE '%TOAN%'
  AND ma NOT LIKE '%SU%'
  AND NOT EXISTS (SELECT 1 FROM lop_hoc l WHERE l.khoa_hoc_id = khoa_hoc.id)
  AND NOT EXISTS (SELECT 1 FROM dang_ky_khoa_hoc dk WHERE dk.khoa_hoc_id = khoa_hoc.id);

-- Bước 8: Chuyên môn/lịch rảnh/lịch sử đơn giá/thông báo của riêng các tài khoản seed
DELETE nsm FROM nhan_su_mon nsm
JOIN nhan_su_chuyen_mon ns ON ns.id = nsm.nhan_su_id
JOIN account a ON a.user_id = ns.user_id
WHERE a.username IN ('demo_giaovien','demo_gv02','demo_gv03','demo_gv04','demo_gv05','demo_giasu','demo_trogiang','demo_tg02');

DELETE nsk FROM nhan_su_khu_vuc nsk
JOIN nhan_su_chuyen_mon ns ON ns.id = nsk.nhan_su_id
JOIN account a ON a.user_id = ns.user_id
WHERE a.username IN ('demo_giaovien','demo_gv02','demo_gv03','demo_gv04','demo_gv05','demo_giasu','demo_trogiang','demo_tg02');

DELETE nsl FROM nhan_su_lich_ranh nsl
JOIN nhan_su_chuyen_mon ns ON ns.id = nsl.nhan_su_id
JOIN account a ON a.user_id = ns.user_id
WHERE a.username IN ('demo_giaovien','demo_gv02','demo_gv03','demo_gv04','demo_gv05','demo_giasu','demo_trogiang','demo_tg02');

DELETE dg FROM lich_su_don_gia_gio dg
JOIN nhan_su_chuyen_mon ns ON ns.id = dg.nhan_su_id
JOIN account a ON a.user_id = ns.user_id
WHERE a.username IN ('demo_giaovien','demo_gv02','demo_gv03','demo_gv04','demo_gv05','demo_giasu','demo_trogiang','demo_tg02');

DELETE tb FROM thong_bao tb
JOIN account a ON a.user_id = tb.user_id
WHERE a.username IN ('demo_admin','demo_giaovu','demo_giaovien','demo_gv02','demo_gv03','demo_gv04','demo_gv05','demo_giasu','demo_trogiang','demo_tg02','demo_phuhuynh','demo_ph02','demo_ph03','demo_ph04','demo_ph05');

-- Bước 9: Gỡ liên kết cơ sở seed ở nhân viên seed rồi xóa cơ sở seed (nếu không còn lớp ngoài seed tham chiếu)
UPDATE nhan_vien nv
JOIN account a ON a.user_id = nv.user_id
SET nv.co_so_id = NULL
WHERE a.username IN ('demo_giaovu');

DELETE FROM co_so
WHERE (ma_co_so LIKE 'SQLDEMO01-%' OR ma_co_so LIKE 'EC26-%')
  AND NOT EXISTS (SELECT 1 FROM lop_hoc l WHERE l.co_so_id = co_so.id)
  AND NOT EXISTS (SELECT 1 FROM buoi_hoc b WHERE b.co_so_id = co_so.id)
  AND NOT EXISTS (SELECT 1 FROM nhan_vien nv WHERE nv.co_so_id = co_so.id);

COMMIT;

-- Khôi phục ngay 5 trigger BEFORE DELETE bảo vệ toàn vẹn dữ liệu
DELIMITER $$
CREATE TRIGGER trg_sk_bd BEFORE DELETE ON su_kien_thanh_toan FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không xóa dấu vết sự kiện thanh toán.';
END$$

CREATE TRIGGER trg_gd_bd BEFORE DELETE ON giao_dich_truc_tuyen FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Giữ lịch sử giao dịch; dùng trạng thái hủy.';
END$$

CREATE TRIGGER trg_tt_bd BEFORE DELETE ON thanh_toan FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không xóa chứng từ thanh toán; dùng trạng thái da_huy.';
END$$

CREATE TRIGGER trg_don_gia_bd BEFORE DELETE ON lich_su_don_gia_gio FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không xóa lịch sử đơn giá.';
END$$

CREATE TRIGGER trg_pc_bd BEFORE DELETE ON phan_cong_buoi_hoc FOR EACH ROW
BEGIN
  DECLARE v_user BIGINT UNSIGNED;
  SELECT user_id INTO v_user FROM users WHERE user_id=OLD.user_id FOR UPDATE;
  IF OLD.bang_cong_id IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không xóa phân công đã gắn bảng công.';
  END IF;
  IF EXISTS (SELECT 1 FROM bang_cong_thang bc JOIN buoi_hoc b ON b.id=OLD.buoi_hoc_id
    WHERE bc.user_id=OLD.user_id AND bc.nam=YEAR(b.ngay) AND bc.thang=MONTH(b.ngay) AND bc.trang_thai<>'mo') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Không xóa phân công trong kỳ đã khóa.';
  END IF;
END$$
DELIMITER ;

SELECT 'Hoàn tất Phần 1: Đã xóa đúng dữ liệu seed cũ, giữ nguyên tài khoản/mật khẩu và bảo toàn Toán/Lịch sử.' AS ket_qua_phan_1;


-- ============================================================================
-- 2. TẠO BỘ DỮ LIỆU MỚI (TÊN TIẾNG VIỆT TỰ NHIÊN, ĐỦ 6 VAI TRÒ + HỌC VIÊN)
-- ============================================================================
-- Lưu ý:
--   - Giữ nguyên tài khoản & mật khẩu đang có. Nếu DB chưa có tài khoản nào trong bộ này,
--     script tạo mới tài khoản với hash bcrypt (không lưu mật khẩu plaintext).
--   - KHÔNG tạo thêm khóa học hoặc môn học trùng cho Toán / Lịch sử.
--   - Sử dụng ngày động theo CURDATE() để lịch dạy, ca hôm nay và báo cáo luôn hiển thị sống động.

START TRANSACTION;

-- 2.1. Mã định danh nội bộ và mật khẩu bcrypt mặc định (chỉ dùng khi tài khoản chưa tồn tại)
SET @pwd_hash := '$2y$12$3Kl2ij6XsDRxK.o4INzAAOV6BYkn55b5VECylB226AfMae4NkBkom';
SET @today := CURDATE();
SET @d_past1 := DATE_SUB(@today, INTERVAL 7 DAY);
SET @d_past2 := DATE_SUB(@today, INTERVAL 3 DAY);
SET @d_next1 := DATE_ADD(@today, INTERVAL 1 DAY);
SET @d_next2 := DATE_ADD(@today, INTERVAL 2 DAY);
SET @d_next3 := DATE_ADD(@today, INTERVAL 4 DAY);

-- Bảo đảm danh mục khu vực chuẩn có ít nhất Thủ Đức & Bình Thạnh (không ghi đè tên hiện có)
INSERT INTO khu_vuc (id, ten) VALUES (1, 'Thủ Đức'), (2, 'Bình Thạnh')
ON DUPLICATE KEY UPDATE ten = ten;

SET @kv_thuduc := (SELECT id FROM khu_vuc WHERE ten = 'Thủ Đức' LIMIT 1);
SET @kv_binhthanh := (SELECT id FROM khu_vuc WHERE ten = 'Bình Thạnh' LIMIT 1);
SET @mon_van := (SELECT id FROM mon_hoc WHERE ma = 'VAN' LIMIT 1);
SET @mon_anh := (SELECT id FROM mon_hoc WHERE ma = 'ANH' LIMIT 1);
SET @mon_ly  := (SELECT id FROM mon_hoc WHERE ma = 'LY'  LIMIT 1);

-- 2.2. Hai cơ sở đào tạo thực tế (không có chữ DEMO)
INSERT INTO co_so (ma_co_so, ten_co_so, dia_chi, khu_vuc_id, is_active)
VALUES
  ('EC26-CS01', 'Cơ sở Võ Văn Ngân — Thủ Đức', '142 Võ Văn Ngân, TP. Thủ Đức, TP.HCM', @kv_thuduc, 1),
  ('EC26-CS02', 'Cơ sở Điện Biên Phủ — Bình Thạnh', '268 Điện Biên Phủ, Quận Bình Thạnh, TP.HCM', @kv_binhthanh, 1)
ON DUPLICATE KEY UPDATE ten_co_so = VALUES(ten_co_so), dia_chi = VALUES(dia_chi), is_active = 1;

SET @cs1 := (SELECT id FROM co_so WHERE ma_co_so = 'EC26-CS01' LIMIT 1);
SET @cs2 := (SELECT id FROM co_so WHERE ma_co_so = 'EC26-CS02' LIMIT 1);

-- 2.3. Tái sử dụng hoặc bổ sung tài khoản cho 6 vai trò (giữ nguyên mật khẩu hiện có)
SET @grp_admin := (SELECT group_id FROM role_group WHERE group_name = 'Admin' LIMIT 1);
SET @grp_nv    := (SELECT group_id FROM role_group WHERE group_name = 'NhanVien' LIMIT 1);
SET @grp_gv    := (SELECT group_id FROM role_group WHERE group_name = 'GiangVien' LIMIT 1);
SET @grp_tg    := (SELECT group_id FROM role_group WHERE group_name = 'TroGiang' LIMIT 1);
SET @grp_ph    := (SELECT group_id FROM role_group WHERE group_name = 'PhuHuynh' LIMIT 1);

-- Admin (demo_admin)
SET @u_admin := (SELECT user_id FROM account WHERE username = 'demo_admin' AND is_deleted = 0 LIMIT 1);
INSERT INTO users (hoten, email, sdt, diachi, nghe_nghiep)
SELECT 'Trần Quang Huy', 'quantri.quanghuy@educenter.test', '0901200001', 'TP. Thủ Đức, TP.HCM', 'Quản trị hệ thống'
WHERE @u_admin IS NULL;
SET @u_admin := COALESCE(@u_admin, LAST_INSERT_ID());
INSERT IGNORE INTO account (user_id, group_id, username, password_hash, status)
VALUES (@u_admin, @grp_admin, 'demo_admin', @pwd_hash, 'active');
UPDATE users SET hoten = 'Trần Quang Huy', email = 'quantri.quanghuy@educenter.test', sdt = '0901200001', diachi = 'TP. Thủ Đức, TP.HCM', nghe_nghiep = 'Quản trị hệ thống' WHERE user_id = @u_admin;

-- Giáo vụ (demo_giaovu)
SET @u_gv_staff := (SELECT user_id FROM account WHERE username = 'demo_giaovu' AND is_deleted = 0 LIMIT 1);
INSERT INTO users (hoten, email, sdt, diachi, nghe_nghiep, khu_vuc_id)
SELECT 'Lê Minh Thư', 'giaovu.minhthu@educenter.test', '0901200002', 'TP. Thủ Đức, TP.HCM', 'Điều phối học vụ', @kv_thuduc
WHERE @u_gv_staff IS NULL;
SET @u_gv_staff := COALESCE(@u_gv_staff, LAST_INSERT_ID());
INSERT IGNORE INTO account (user_id, group_id, username, password_hash, status)
VALUES (@u_gv_staff, @grp_nv, 'demo_giaovu', @pwd_hash, 'active');
UPDATE users SET hoten = 'Lê Minh Thư', email = 'giaovu.minhthu@educenter.test', sdt = '0901200002', diachi = 'TP. Thủ Đức, TP.HCM', khu_vuc_id = @kv_thuduc WHERE user_id = @u_gv_staff;
INSERT INTO nhan_vien (user_id, ma_nv, co_so_id, chuc_vu, ngay_vao_lam, trang_thai)
VALUES (@u_gv_staff, 'EC26-NV01', @cs1, 'Giáo vụ & Điều phối', '2025-09-01', 'dang_lam')
ON DUPLICATE KEY UPDATE co_so_id = NULL, chuc_vu = 'Giáo vụ & Điều phối', trang_thai = 'dang_lam';
-- Để Giáo vụ điều phối được cả 2 cơ sở trong buổi trình diễn (co_so_id = NULL)
UPDATE nhan_vien SET co_so_id = NULL, chuc_vu = 'Giáo vụ & Điều phối', ngay_vao_lam = '2025-09-01', trang_thai = 'dang_lam' WHERE user_id = @u_gv_staff;
SET @nv_id := (SELECT id FROM nhan_vien WHERE user_id = @u_gv_staff LIMIT 1);

-- Giáo viên 1 (demo_giaovien)
SET @u_gv1 := (SELECT user_id FROM account WHERE username = 'demo_giaovien' AND is_deleted = 0 LIMIT 1);
INSERT INTO users (hoten, email, sdt, diachi, nghe_nghiep, khu_vuc_id)
SELECT 'Nguyễn Minh Anh', 'gv.minhanh@educenter.test', '0901200011', 'TP. Thủ Đức, TP.HCM', 'Giáo viên Ngữ văn', @kv_thuduc
WHERE @u_gv1 IS NULL;
SET @u_gv1 := COALESCE(@u_gv1, LAST_INSERT_ID());
INSERT IGNORE INTO account (user_id, group_id, username, password_hash, status)
VALUES (@u_gv1, @grp_gv, 'demo_giaovien', @pwd_hash, 'active');
UPDATE users SET hoten = 'Nguyễn Minh Anh', email = 'gv.minhanh@educenter.test', sdt = '0901200011', diachi = 'TP. Thủ Đức, TP.HCM', khu_vuc_id = @kv_thuduc WHERE user_id = @u_gv1;
INSERT INTO nhan_su_chuyen_mon (user_id, ma_ns, phan_loai, gioi_tinh, don_vi_cong_tac, kinh_nghiem_nam, gioi_thieu, hinh_thuc_day, don_gia_gio_chuan, trang_thai)
VALUES (@u_gv1, 'EC26-NS01', 'giao_vien', 'nu', 'THCS Lê Quý Đôn', 6, 'Chuyên luyện thi Ngữ văn vào lớp 10 và rèn kỹ năng viết nghị luận.', 'online,offline', 220000, 'hoat_dong')
ON DUPLICATE KEY UPDATE phan_loai = 'giao_vien', gioi_tinh = 'nu', don_vi_cong_tac = 'THCS Lê Quý Đôn', kinh_nghiem_nam = 6, gioi_thieu = VALUES(gioi_thieu), hinh_thuc_day = 'online,offline', don_gia_gio_chuan = 220000, trang_thai = 'hoat_dong';
SET @ns_gv1 := (SELECT id FROM nhan_su_chuyen_mon WHERE user_id = @u_gv1 LIMIT 1);

-- Giáo viên 2 (demo_gv02)
SET @u_gv2 := (SELECT user_id FROM account WHERE username = 'demo_gv02' AND is_deleted = 0 LIMIT 1);
INSERT INTO users (hoten, email, sdt, diachi, nghe_nghiep, khu_vuc_id)
SELECT 'Phạm Hoàng Long', 'gv.hoanglong@educenter.test', '0901200012', 'Quận Bình Thạnh, TP.HCM', 'Giáo viên Tiếng Anh', @kv_binhthanh
WHERE @u_gv2 IS NULL;
SET @u_gv2 := COALESCE(@u_gv2, LAST_INSERT_ID());
INSERT IGNORE INTO account (user_id, group_id, username, password_hash, status)
VALUES (@u_gv2, @grp_gv, 'demo_gv02', @pwd_hash, 'active');
UPDATE users SET hoten = 'Phạm Hoàng Long', email = 'gv.hoanglong@educenter.test', sdt = '0901200012', diachi = 'Quận Bình Thạnh, TP.HCM', khu_vuc_id = @kv_binhthanh WHERE user_id = @u_gv2;
INSERT INTO nhan_su_chuyen_mon (user_id, ma_ns, phan_loai, gioi_tinh, don_vi_cong_tac, kinh_nghiem_nam, gioi_thieu, hinh_thuc_day, don_gia_gio_chuan, trang_thai)
VALUES (@u_gv2, 'EC26-NS02', 'giao_vien', 'nam', 'THPT Gia Định', 5, 'Giảng dạy Tiếng Anh THCS và luyện kỹ năng đọc hiểu, ngữ pháp học thuật.', 'online,offline', 240000, 'hoat_dong')
ON DUPLICATE KEY UPDATE phan_loai = 'giao_vien', gioi_tinh = 'nam', don_vi_cong_tac = 'THPT Gia Định', kinh_nghiem_nam = 5, gioi_thieu = VALUES(gioi_thieu), hinh_thuc_day = 'online,offline', don_gia_gio_chuan = 240000, trang_thai = 'hoat_dong';
SET @ns_gv2 := (SELECT id FROM nhan_su_chuyen_mon WHERE user_id = @u_gv2 LIMIT 1);

-- Giáo viên 3 (demo_gv03)
SET @u_gv3 := (SELECT user_id FROM account WHERE username = 'demo_gv03' AND is_deleted = 0 LIMIT 1);
INSERT INTO users (hoten, email, sdt, diachi, nghe_nghiep, khu_vuc_id)
SELECT 'Lê Thu Hà', 'gv.thuha@educenter.test', '0901200013', 'TP. Thủ Đức, TP.HCM', 'Giáo viên Vật lý', @kv_thuduc
WHERE @u_gv3 IS NULL;
SET @u_gv3 := COALESCE(@u_gv3, LAST_INSERT_ID());
INSERT IGNORE INTO account (user_id, group_id, username, password_hash, status)
VALUES (@u_gv3, @grp_gv, 'demo_gv03', @pwd_hash, 'active');
UPDATE users SET hoten = 'Lê Thu Hà', email = 'gv.thuha@educenter.test', sdt = '0901200013', diachi = 'TP. Thủ Đức, TP.HCM', khu_vuc_id = @kv_thuduc WHERE user_id = @u_gv3;
INSERT INTO nhan_su_chuyen_mon (user_id, ma_ns, phan_loai, gioi_tinh, don_vi_cong_tac, kinh_nghiem_nam, gioi_thieu, hinh_thuc_day, don_gia_gio_chuan, trang_thai)
VALUES (@u_gv3, 'EC26-NS03', 'giao_vien', 'nu', 'THPT Thủ Đức', 7, 'Phụ trách Vật lý THPT chuyên đề Cơ học, Điện học và bài tập thí nghiệm.', 'online,offline', 230000, 'hoat_dong')
ON DUPLICATE KEY UPDATE phan_loai = 'giao_vien', gioi_tinh = 'nu', don_vi_cong_tac = 'THPT Thủ Đức', kinh_nghiem_nam = 7, gioi_thieu = VALUES(gioi_thieu), hinh_thuc_day = 'online,offline', don_gia_gio_chuan = 230000, trang_thai = 'hoat_dong';
SET @ns_gv3 := (SELECT id FROM nhan_su_chuyen_mon WHERE user_id = @u_gv3 LIMIT 1);

-- Gia sư chính (demo_giasu) — thuần gia_su (chỉ xếp lớp kèm 1:1 / 1:2)
SET @u_gs1 := (SELECT user_id FROM account WHERE username = 'demo_giasu' AND is_deleted = 0 LIMIT 1);
INSERT INTO users (hoten, email, sdt, diachi, nghe_nghiep, khu_vuc_id)
SELECT 'Đỗ Ngọc Ánh', 'gs.ngocanh@educenter.test', '0901200021', 'Quận Bình Thạnh, TP.HCM', 'Gia sư Tiếng Anh & Ngữ văn', @kv_binhthanh
WHERE @u_gs1 IS NULL;
SET @u_gs1 := COALESCE(@u_gs1, LAST_INSERT_ID());
INSERT IGNORE INTO account (user_id, group_id, username, password_hash, status)
VALUES (@u_gs1, @grp_gv, 'demo_giasu', @pwd_hash, 'active');
UPDATE users SET hoten = 'Đỗ Ngọc Ánh', email = 'gs.ngocanh@educenter.test', sdt = '0901200021', diachi = 'Quận Bình Thạnh, TP.HCM', khu_vuc_id = @kv_binhthanh WHERE user_id = @u_gs1;
INSERT INTO nhan_su_chuyen_mon (user_id, ma_ns, phan_loai, gioi_tinh, don_vi_cong_tac, kinh_nghiem_nam, gioi_thieu, hinh_thuc_day, don_gia_gio_chuan, trang_thai)
VALUES (@u_gs1, 'EC26-NS04', 'gia_su', 'nu', 'Đại học Sư phạm TP.HCM', 3, 'Chuyên kèm 1:1 và 1:2 môn Tiếng Anh và Ngữ văn bậc THCS, theo sát bài tập mỗi buổi.', 'online,offline', 170000, 'hoat_dong')
ON DUPLICATE KEY UPDATE phan_loai = 'gia_su', gioi_tinh = 'nu', don_vi_cong_tac = 'Đại học Sư phạm TP.HCM', kinh_nghiem_nam = 3, gioi_thieu = VALUES(gioi_thieu), hinh_thuc_day = 'online,offline', don_gia_gio_chuan = 170000, trang_thai = 'hoat_dong';
SET @ns_gs1 := (SELECT id FROM nhan_su_chuyen_mon WHERE user_id = @u_gs1 LIMIT 1);

-- Trợ giảng chính (demo_trogiang)
SET @u_tg1 := (SELECT user_id FROM account WHERE username = 'demo_trogiang' AND is_deleted = 0 LIMIT 1);
INSERT INTO users (hoten, email, sdt, diachi, nghe_nghiep, khu_vuc_id)
SELECT 'Nguyễn Thảo Vy', 'tg.thaovy@educenter.test', '0901200031', 'TP. Thủ Đức, TP.HCM', 'Trợ giảng học vụ', @kv_thuduc
WHERE @u_tg1 IS NULL;
SET @u_tg1 := COALESCE(@u_tg1, LAST_INSERT_ID());
INSERT IGNORE INTO account (user_id, group_id, username, password_hash, status)
VALUES (@u_tg1, @grp_tg, 'demo_trogiang', @pwd_hash, 'active');
UPDATE users SET hoten = 'Nguyễn Thảo Vy', email = 'tg.thaovy@educenter.test', sdt = '0901200031', diachi = 'TP. Thủ Đức, TP.HCM', khu_vuc_id = @kv_thuduc WHERE user_id = @u_tg1;
INSERT INTO nhan_su_chuyen_mon (user_id, ma_ns, phan_loai, gioi_tinh, don_vi_cong_tac, kinh_nghiem_nam, gioi_thieu, hinh_thuc_day, don_gia_gio_chuan, trang_thai)
VALUES (@u_tg1, 'EC26-NS05', 'tro_giang', 'nu', 'Đại học Khoa học Xã hội & Nhân văn', 2, 'Hỗ trợ điểm danh, chấm bài tập giấy và kèm học viên yếu trong giờ luyện đề.', 'online,offline', 90000, 'hoat_dong')
ON DUPLICATE KEY UPDATE phan_loai = 'tro_giang', gioi_tinh = 'nu', don_vi_cong_tac = 'Đại học Khoa học Xã hội & Nhân văn', kinh_nghiem_nam = 2, gioi_thieu = VALUES(gioi_thieu), hinh_thuc_day = 'online,offline', don_gia_gio_chuan = 90000, trang_thai = 'hoat_dong';
SET @ns_tg1 := (SELECT id FROM nhan_su_chuyen_mon WHERE user_id = @u_tg1 LIMIT 1);

-- Phụ huynh 1, 2, 3 (demo_phuhuynh, demo_ph02, demo_ph03)
SET @u_ph1 := (SELECT user_id FROM account WHERE username = 'demo_phuhuynh' AND is_deleted = 0 LIMIT 1);
INSERT INTO users (hoten, email, sdt, diachi, nghe_nghiep, khu_vuc_id)
SELECT 'Nguyễn Thị Lan', 'ph.thilan@educenter.test', '0901200101', 'Phường Linh Chiểu, TP. Thủ Đức', 'Kế toán trưởng', @kv_thuduc
WHERE @u_ph1 IS NULL;
SET @u_ph1 := COALESCE(@u_ph1, LAST_INSERT_ID());
INSERT IGNORE INTO account (user_id, group_id, username, password_hash, status)
VALUES (@u_ph1, @grp_ph, 'demo_phuhuynh', @pwd_hash, 'active');
UPDATE users SET hoten = 'Nguyễn Thị Lan', email = 'ph.thilan@educenter.test', sdt = '0901200101', diachi = 'Phường Linh Chiểu, TP. Thủ Đức', nghe_nghiep = 'Kế toán trưởng', khu_vuc_id = @kv_thuduc WHERE user_id = @u_ph1;

SET @u_ph2 := (SELECT user_id FROM account WHERE username = 'demo_ph02' AND is_deleted = 0 LIMIT 1);
INSERT INTO users (hoten, email, sdt, diachi, nghe_nghiep, khu_vuc_id)
SELECT 'Trần Văn Hùng', 'ph.vanhung@educenter.test', '0901200102', 'Phường 25, Quận Bình Thạnh', 'Kỹ sư xây dựng', @kv_binhthanh
WHERE @u_ph2 IS NULL;
SET @u_ph2 := COALESCE(@u_ph2, LAST_INSERT_ID());
INSERT IGNORE INTO account (user_id, group_id, username, password_hash, status)
VALUES (@u_ph2, @grp_ph, 'demo_ph02', @pwd_hash, 'active');
UPDATE users SET hoten = 'Trần Văn Hùng', email = 'ph.vanhung@educenter.test', sdt = '0901200102', diachi = 'Phường 25, Quận Bình Thạnh', nghe_nghiep = 'Kỹ sư xây dựng', khu_vuc_id = @kv_binhthanh WHERE user_id = @u_ph2;

SET @u_ph3 := (SELECT user_id FROM account WHERE username = 'demo_ph03' AND is_deleted = 0 LIMIT 1);
INSERT INTO users (hoten, email, sdt, diachi, nghe_nghiep, khu_vuc_id)
SELECT 'Lê Thị Hạnh', 'ph.thihanh@educenter.test', '0901200103', 'Phường Bình Thọ, TP. Thủ Đức', 'Dược sĩ', @kv_thuduc
WHERE @u_ph3 IS NULL;
SET @u_ph3 := COALESCE(@u_ph3, LAST_INSERT_ID());
INSERT IGNORE INTO account (user_id, group_id, username, password_hash, status)
VALUES (@u_ph3, @grp_ph, 'demo_ph03', @pwd_hash, 'active');
UPDATE users SET hoten = 'Lê Thị Hạnh', email = 'ph.thihanh@educenter.test', sdt = '0901200103', diachi = 'Phường Bình Thọ, TP. Thủ Đức', nghe_nghiep = 'Dược sĩ', khu_vuc_id = @kv_thuduc WHERE user_id = @u_ph3;

-- Làm sạch nhãn [SQLDEMO] ở các tài khoản seed phụ nếu còn tồn tại trong DB
UPDATE users u JOIN account a ON a.user_id = u.user_id
SET u.hoten = TRIM(REPLACE(u.hoten, '[SQLDEMO]', ''))
WHERE a.username IN ('demo_gv04','demo_gv05','demo_tg02','demo_ph04','demo_ph05')
  AND u.hoten LIKE '%[SQLDEMO]%';

-- 2.4. Chuyên môn, khu vực, lịch rảnh và lịch sử đơn giá chuẩn cho nhân sự
INSERT INTO nhan_su_mon (nhan_su_id, mon_hoc_id, cap_lop_tu, cap_lop_den, hoc_phi_buoi_de_xuat) VALUES
  (@ns_gv1, @mon_van, 6, 12, 220000),
  (@ns_gv2, @mon_anh, 6, 12, 240000),
  (@ns_gv3, @mon_ly,  8, 12, 230000),
  (@ns_gs1, @mon_anh, 6, 9,  200000),
  (@ns_gs1, @mon_van, 6, 9,  190000),
  (@ns_tg1, @mon_van, 6, 12, 90000),
  (@ns_tg1, @mon_anh, 6, 12, 90000),
  (@ns_tg1, @mon_ly,  8, 12, 90000);

INSERT INTO nhan_su_khu_vuc (nhan_su_id, khu_vuc_id) VALUES
  (@ns_gv1, @kv_thuduc), (@ns_gv1, @kv_binhthanh),
  (@ns_gv2, @kv_thuduc), (@ns_gv2, @kv_binhthanh),
  (@ns_gv3, @kv_thuduc),
  (@ns_gs1, @kv_thuduc), (@ns_gs1, @kv_binhthanh),
  (@ns_tg1, @kv_thuduc), (@ns_tg1, @kv_binhthanh);

-- Lịch rảnh phủ trọn các thứ trong tuần khung 17:00–21:30 để dễ trình diễn xếp ca & ứng tuyển
INSERT INTO nhan_su_lich_ranh (nhan_su_id, thu, gio_bat_dau, gio_ket_thuc) VALUES
  (@ns_gv1, 2, '17:00', '21:30'), (@ns_gv1, 3, '17:00', '21:30'), (@ns_gv1, 4, '17:00', '21:30'), (@ns_gv1, 5, '17:00', '21:30'), (@ns_gv1, 6, '17:00', '21:30'), (@ns_gv1, 7, '08:00', '21:30'), (@ns_gv1, 8, '08:00', '21:30'),
  (@ns_gv2, 2, '17:00', '21:30'), (@ns_gv2, 3, '17:00', '21:30'), (@ns_gv2, 4, '17:00', '21:30'), (@ns_gv2, 5, '17:00', '21:30'), (@ns_gv2, 6, '17:00', '21:30'), (@ns_gv2, 7, '08:00', '21:30'), (@ns_gv2, 8, '08:00', '21:30'),
  (@ns_gv3, 2, '17:00', '21:30'), (@ns_gv3, 3, '17:00', '21:30'), (@ns_gv3, 4, '17:00', '21:30'), (@ns_gv3, 5, '17:00', '21:30'), (@ns_gv3, 6, '17:00', '21:30'), (@ns_gv3, 7, '08:00', '21:30'), (@ns_gv3, 8, '08:00', '21:30'),
  (@ns_gs1, 2, '17:00', '21:30'), (@ns_gs1, 3, '17:00', '21:30'), (@ns_gs1, 4, '17:00', '21:30'), (@ns_gs1, 5, '17:00', '21:30'), (@ns_gs1, 6, '17:00', '21:30'), (@ns_gs1, 7, '08:00', '21:30'), (@ns_gs1, 8, '08:00', '21:30'),
  (@ns_tg1, 2, '17:00', '21:30'), (@ns_tg1, 3, '17:00', '21:30'), (@ns_tg1, 4, '17:00', '21:30'), (@ns_tg1, 5, '17:00', '21:30'), (@ns_tg1, 6, '17:00', '21:30'), (@ns_tg1, 7, '08:00', '21:30'), (@ns_tg1, 8, '08:00', '21:30');

INSERT INTO lich_su_don_gia_gio (nhan_su_id, don_gia_gio, hieu_luc_tu, nguoi_dat_id) VALUES
  (@ns_gv1, 220000, NOW(), @u_admin),
  (@ns_gv2, 240000, NOW(), @u_admin),
  (@ns_gv3, 230000, NOW(), @u_admin),
  (@ns_gs1, 170000, NOW(), @u_admin),
  (@ns_tg1,  90000, NOW(), @u_admin);

-- 2.5. Khóa học trọn khóa mới (KHÔNG trùng hoặc sửa khóa Toán / Lịch sử)
INSERT INTO khoa_hoc (ma, ten, mo_ta, hoc_phi, is_active) VALUES
  ('EC26-KH01', 'Ngữ văn 9 — Luyện thi vào lớp 10', 'Chương trình ôn luyện đọc hiểu, nghị luận xã hội và nghị luận văn học trọn khóa 24 buổi.', 3600000, 1),
  ('EC26-KH02', 'Tiếng Anh 8 — Ngữ pháp & Đọc hiểu Học thuật', 'Hệ thống hóa ngữ pháp trọng tâm và phát triển kỹ năng đọc viết chuẩn bậc THCS.', 3900000, 1),
  ('EC26-KH03', 'Vật lý 11 — Chuyên đề Cơ & Điện học', 'Luyện giải bài tập chuyên sâu, nắm vững bản chất vật lý và kỹ năng làm bài trắc nghiệm.', 4200000, 1),
  ('EC26-KH04', 'Kèm 1:1 Tiếng Anh 9 — Tăng tốc tuyển sinh', 'Lộ trình cá nhân hóa 1 kèm 1 cùng gia sư theo sát mục tiêu điểm số từng tuần.', 4800000, 1);

SET @kh1 := (SELECT id FROM khoa_hoc WHERE ma = 'EC26-KH01' LIMIT 1);
SET @kh2 := (SELECT id FROM khoa_hoc WHERE ma = 'EC26-KH02' LIMIT 1);
SET @kh3 := (SELECT id FROM khoa_hoc WHERE ma = 'EC26-KH03' LIMIT 1);
SET @kh4 := (SELECT id FROM khoa_hoc WHERE ma = 'EC26-KH04' LIMIT 1);

-- 2.6. Học viên liên kết phụ huynh (1 phụ huynh nhiều con, 1 học viên 1 phụ huynh)
INSERT INTO hoc_vien (ma_hv, ho_ten, ngay_sinh, gioi_tinh, cap_lop_id, truong, phu_huynh_user_id, khu_vuc_id, trang_thai) VALUES
  ('EC26-HV01', 'Nguyễn Minh An',  '2012-04-12', 'nam', 9,  'THCS Bình Thọ',      @u_ph1, @kv_thuduc,    'dang_hoc'),
  ('EC26-HV02', 'Nguyễn Hải Yến',  '2013-08-25', 'nu',  8,  'THCS Bình Thọ',      @u_ph1, @kv_thuduc,    'dang_hoc'),
  ('EC26-HV03', 'Trần Gia Hân',    '2012-11-03', 'nu',  9,  'THCS Lê Văn Tám',    @u_ph2, @kv_binhthanh, 'dang_hoc'),
  ('EC26-HV04', 'Trần Minh Khang', '2013-02-19', 'nam', 8,  'THCS Lê Văn Tám',    @u_ph2, @kv_binhthanh, 'dang_hoc'),
  ('EC26-HV05', 'Lê Ngọc Chi',     '2010-06-14', 'nu',  11, 'THPT Thủ Đức',       @u_ph3, @kv_thuduc,    'dang_hoc'),
  ('EC26-HV06', 'Lê Hoàng Nam',    '2012-09-30', 'nam', 9,  'THCS Hoa Lư',        @u_ph3, @kv_thuduc,    'dang_hoc');

SET @hv1 := (SELECT id FROM hoc_vien WHERE ma_hv = 'EC26-HV01' LIMIT 1);
SET @hv2 := (SELECT id FROM hoc_vien WHERE ma_hv = 'EC26-HV02' LIMIT 1);
SET @hv3 := (SELECT id FROM hoc_vien WHERE ma_hv = 'EC26-HV03' LIMIT 1);
SET @hv4 := (SELECT id FROM hoc_vien WHERE ma_hv = 'EC26-HV04' LIMIT 1);
SET @hv5 := (SELECT id FROM hoc_vien WHERE ma_hv = 'EC26-HV05' LIMIT 1);
SET @hv6 := (SELECT id FROM hoc_vien WHERE ma_hv = 'EC26-HV06' LIMIT 1);

-- 2.7. Lớp học (3 lớp nhóm cho Giáo viên + Trợ giảng, 2 lớp kèm cho Gia sư)
INSERT INTO lop_hoc (
  ma_lop, ten_lop, loai_lop, khoa_hoc_id, mon_hoc_id, cap_lop_id, nguoi_day_chinh_id,
  co_so_id, phong_hoc, hinh_thuc, khu_vuc_id, si_so_toi_thieu, si_so_toi_da,
  cach_thu_phi, hoc_phi_buoi, hoc_phi_khoa, muc_tra_chinh, muc_tra_tro_giang,
  cach_tinh_luong, so_buoi_du_kien, ngay_bat_dau, trang_thai, nhan_vien_id
) VALUES
  ('EC26-LOP01', 'Ngữ văn 9 — Luyện đề nhóm A1', 'nhom',    @kh1, @mon_van, 9,  @ns_gv1, @cs1, 'P201', 'offline', @kv_thuduc,    2, 12, 'theo_khoa', 0, 3600000, 220000, 90000, 'theo_gio', 24, DATE_SUB(@today, INTERVAL 21 DAY), 'dang_hoc', @nv_id),
  ('EC26-LOP02', 'Tiếng Anh 8 — Học thuật B1',   'nhom',    @kh2, @mon_anh, 8,  @ns_gv2, @cs2, 'P302', 'offline', @kv_binhthanh, 2, 10, 'theo_khoa', 0, 3900000, 240000, 90000, 'theo_gio', 24, DATE_SUB(@today, INTERVAL 21 DAY), 'dang_hoc', @nv_id),
  ('EC26-LOP03', 'Vật lý 11 — Chuyên đề K11',    'nhom',    @kh3, @mon_ly,  11, @ns_gv3, @cs1, 'P105', 'offline', @kv_thuduc,    2, 12, 'theo_khoa', 0, 4200000, 230000, 90000, 'theo_gio', 24, DATE_SUB(@today, INTERVAL 21 DAY), 'dang_hoc', @nv_id),
  ('EC26-LOP04', 'Kèm 1:1 Tiếng Anh 9 — Gia Hân','kem_1_1', @kh4, @mon_anh, 9,  @ns_gs1, @cs2, 'K101', 'offline', @kv_binhthanh, 1, 1,  'theo_khoa', 0, 4800000, 170000, 0,     'theo_gio', 20, DATE_SUB(@today, INTERVAL 21 DAY), 'dang_hoc', @nv_id),
  ('EC26-LOP05', 'Kèm 1:2 Ngữ văn 9 — Thủ Đức',  'kem_1_2', @kh1, @mon_van, 9,  @ns_gs1, @cs1, 'K202', 'offline', @kv_thuduc,    1, 2,  'theo_khoa', 0, 3600000, 170000, 0,     'theo_gio', 20, DATE_SUB(@today, INTERVAL 21 DAY), 'dang_hoc', @nv_id);

SET @lop1 := (SELECT id FROM lop_hoc WHERE ma_lop = 'EC26-LOP01' LIMIT 1);
SET @lop2 := (SELECT id FROM lop_hoc WHERE ma_lop = 'EC26-LOP02' LIMIT 1);
SET @lop3 := (SELECT id FROM lop_hoc WHERE ma_lop = 'EC26-LOP03' LIMIT 1);
SET @lop4 := (SELECT id FROM lop_hoc WHERE ma_lop = 'EC26-LOP04' LIMIT 1);
SET @lop5 := (SELECT id FROM lop_hoc WHERE ma_lop = 'EC26-LOP05' LIMIT 1);

-- 2.8. Đăng ký khóa học + Xếp lớp bởi Admin + Hóa đơn + Phiếu thu xác nhận & giao dịch chờ đối soát
INSERT INTO dang_ky_khoa_hoc (hoc_vien_id, khoa_hoc_id, hoc_phi_snapshot, trang_thai) VALUES
  (@hv1, @kh1, 3600000, 'dang_hoc'),
  (@hv6, @kh1, 3600000, 'dang_hoc'),
  (@hv2, @kh2, 3900000, 'dang_hoc'),
  (@hv4, @kh2, 3900000, 'dang_hoc'),
  (@hv5, @kh3, 4200000, 'dang_hoc'),
  (@hv3, @kh4, 4800000, 'dang_hoc'),
  (@hv1, @kh2, 3900000, 'cho_xep_lop');

SET @dk1 := (SELECT id FROM dang_ky_khoa_hoc WHERE hoc_vien_id = @hv1 AND khoa_hoc_id = @kh1 ORDER BY id DESC LIMIT 1);
SET @dk2 := (SELECT id FROM dang_ky_khoa_hoc WHERE hoc_vien_id = @hv6 AND khoa_hoc_id = @kh1 ORDER BY id DESC LIMIT 1);
SET @dk3 := (SELECT id FROM dang_ky_khoa_hoc WHERE hoc_vien_id = @hv2 AND khoa_hoc_id = @kh2 ORDER BY id DESC LIMIT 1);
SET @dk4 := (SELECT id FROM dang_ky_khoa_hoc WHERE hoc_vien_id = @hv4 AND khoa_hoc_id = @kh2 ORDER BY id DESC LIMIT 1);
SET @dk5 := (SELECT id FROM dang_ky_khoa_hoc WHERE hoc_vien_id = @hv5 AND khoa_hoc_id = @kh3 ORDER BY id DESC LIMIT 1);
SET @dk6 := (SELECT id FROM dang_ky_khoa_hoc WHERE hoc_vien_id = @hv3 AND khoa_hoc_id = @kh4 ORDER BY id DESC LIMIT 1);
SET @dk7 := (SELECT id FROM dang_ky_khoa_hoc WHERE hoc_vien_id = @hv1 AND khoa_hoc_id = @kh2 ORDER BY id DESC LIMIT 1);

INSERT INTO lop_hoc_vien (lop_id, hoc_vien_id, dang_ky_khoa_hoc_id, ngay_vao, trang_thai, xep_boi, xep_luc) VALUES
  (@lop1, @hv1, @dk1, DATE_SUB(@today, INTERVAL 21 DAY), 'dang_hoc', @u_admin, NOW()),
  (@lop1, @hv6, @dk2, DATE_SUB(@today, INTERVAL 21 DAY), 'dang_hoc', @u_admin, NOW()),
  (@lop2, @hv2, @dk3, DATE_SUB(@today, INTERVAL 21 DAY), 'dang_hoc', @u_admin, NOW()),
  (@lop2, @hv4, @dk4, DATE_SUB(@today, INTERVAL 21 DAY), 'dang_hoc', @u_admin, NOW()),
  (@lop3, @hv5, @dk5, DATE_SUB(@today, INTERVAL 21 DAY), 'dang_hoc', @u_admin, NOW()),
  (@lop4, @hv3, @dk6, DATE_SUB(@today, INTERVAL 21 DAY), 'dang_hoc', @u_admin, NOW()),
  (@lop5, @hv1, @dk1, DATE_SUB(@today, INTERVAL 14 DAY), 'hoc_thu',  @u_admin, NOW());

-- Hóa đơn phát hành (kích hoạt trigger thông báo học phí tới phụ huynh)
INSERT INTO hoa_don (ma_hd, dang_ky_khoa_hoc_id, tong_tien, giam_gia, da_thu, trang_thai, han_thanh_toan, nguoi_lap_id, phat_hanh_boi, phat_hanh_luc) VALUES
  ('EC26-HD01', @dk1, 3600000, 0, 0, 'chua_thanh_toan', DATE_ADD(@today, INTERVAL 7 DAY),  @u_gv_staff, @u_gv_staff, NOW()),
  ('EC26-HD02', @dk2, 3600000, 0, 0, 'chua_thanh_toan', DATE_ADD(@today, INTERVAL 7 DAY),  @u_gv_staff, @u_gv_staff, NOW()),
  ('EC26-HD03', @dk3, 3900000, 0, 0, 'chua_thanh_toan', DATE_ADD(@today, INTERVAL 10 DAY), @u_gv_staff, @u_gv_staff, NOW()),
  ('EC26-HD04', @dk4, 3900000, 0, 0, 'chua_thanh_toan', DATE_ADD(@today, INTERVAL 10 DAY), @u_gv_staff, @u_gv_staff, NOW()),
  ('EC26-HD05', @dk5, 4200000, 0, 0, 'chua_thanh_toan', DATE_ADD(@today, INTERVAL 10 DAY), @u_gv_staff, @u_gv_staff, NOW()),
  ('EC26-HD06', @dk6, 4800000, 0, 0, 'chua_thanh_toan', DATE_ADD(@today, INTERVAL 14 DAY), @u_gv_staff, @u_gv_staff, NOW()),
  ('EC26-HD07', @dk7, 3900000, 0, 0, 'chua_thanh_toan', DATE_ADD(@today, INTERVAL 14 DAY), @u_gv_staff, @u_gv_staff, NOW());

SET @hd1 := (SELECT id FROM hoa_don WHERE ma_hd = 'EC26-HD01' LIMIT 1);
SET @hd2 := (SELECT id FROM hoa_don WHERE ma_hd = 'EC26-HD02' LIMIT 1);
SET @hd3 := (SELECT id FROM hoa_don WHERE ma_hd = 'EC26-HD03' LIMIT 1);
SET @hd4 := (SELECT id FROM hoa_don WHERE ma_hd = 'EC26-HD04' LIMIT 1);
SET @hd5 := (SELECT id FROM hoa_don WHERE ma_hd = 'EC26-HD05' LIMIT 1);
SET @hd6 := (SELECT id FROM hoa_don WHERE ma_hd = 'EC26-HD06' LIMIT 1);
SET @hd7 := (SELECT id FROM hoa_don WHERE ma_hd = 'EC26-HD07' LIMIT 1);

-- Phiếu thu đã xác nhận (trigger tự cập nhật da_thu và trạng thái hóa đơn)
INSERT INTO thanh_toan (hoa_don_id, loai, so_tien, phuong_thuc, ma_giao_dich, ngay_thanh_toan, nguoi_thu_id, trang_thai, ghi_chu) VALUES
  (@hd1, 'thu', 3600000, 'chuyen_khoan', 'EC26-CK001', DATE_SUB(NOW(), INTERVAL 5 DAY), @u_admin, 'hop_le', 'Thanh toán trọn khóa Ngữ văn 9'),
  (@hd2, 'thu', 3600000, 'tien_mat',     'EC26-TM002', DATE_SUB(NOW(), INTERVAL 4 DAY), @u_admin, 'hop_le', 'Thu tiền mặt tại cơ sở Thủ Đức'),
  (@hd3, 'thu', 2000000, 'chuyen_khoan', 'EC26-CK003', DATE_SUB(NOW(), INTERVAL 2 DAY), @u_admin, 'hop_le', 'Thanh toán đợt 1 Tiếng Anh 8'),
  (@hd5, 'thu', 4200000, 'chuyen_khoan', 'EC26-CK005', DATE_SUB(NOW(), INTERVAL 1 DAY), @u_admin, 'hop_le', 'Thanh toán trọn khóa Vật lý 11'),
  (@hd6, 'thu', 4800000, 'chuyen_khoan', 'EC26-CK006', NOW(),                           @u_admin, 'hop_le', 'Thanh toán khóa kèm 1:1 Tiếng Anh 9');

-- Một giao dịch chuyển khoản trực tuyến đang chờ Admin đối soát (không tính vào thực thu)
INSERT INTO giao_dich_truc_tuyen (hoa_don_id, phu_huynh_id, ma_yeu_cau, nha_cung_cap, so_tien, tien_te, trang_thai, het_han_luc)
VALUES (@hd4, @u_ph2, 'EC26-GD04', 'bank_qr', 3900000, 'VND', 'khoi_tao', DATE_ADD(NOW(), INTERVAL 2 DAY));
UPDATE giao_dich_truc_tuyen SET trang_thai = 'cho_xac_nhan', ma_giao_dich_cong = 'EC26-REF04' WHERE ma_yeu_cau = 'EC26-GD04';

-- 2.9. Lịch học tuần & Buổi học (quá khứ đã dạy, hôm nay sắp diễn ra, tương lai gần)
INSERT INTO lich_hoc (lop_id, thu, gio_bat_dau, gio_ket_thuc, phong_hoc, hieu_luc_tu, is_active) VALUES
  (@lop1, 2, '18:00', '20:00', 'P201', DATE_SUB(@today, INTERVAL 21 DAY), 1),
  (@lop1, 5, '18:00', '20:00', 'P201', DATE_SUB(@today, INTERVAL 21 DAY), 1),
  (@lop2, 3, '18:00', '20:00', 'P302', DATE_SUB(@today, INTERVAL 21 DAY), 1),
  (@lop3, 4, '18:00', '20:00', 'P105', DATE_SUB(@today, INTERVAL 21 DAY), 1),
  (@lop4, 6, '18:30', '20:00', 'K101', DATE_SUB(@today, INTERVAL 21 DAY), 1),
  (@lop5, 7, '09:00', '10:30', 'K202', DATE_SUB(@today, INTERVAL 21 DAY), 1);

-- Buổi đã hoàn thành (có check-in/out, điểm danh, điểm, nhận xét, bài tập)
INSERT INTO buoi_hoc (lop_id, ngay, gio_bat_dau, gio_ket_thuc, co_so_id, phong_hoc, chu_de_bai_hoc, noi_dung_bai_hoc, trang_thai) VALUES
  (@lop1, @d_past1, '18:00', '20:00', @cs1, 'P201', 'Phân tích nghị luận xã hội đề thi tuyển sinh', 'Hướng dẫn lập dàn ý chi tiết và cách dẫn chứng thực tế ngắn gọn.', 'da_day'),
  (@lop2, @d_past1, '18:00', '20:00', @cs2, 'P302', 'Câu điều kiện và mệnh đề quan hệ nâng cao', 'Thực hành chuyển đổi câu và bài đọc hiểu ngắn chủ đề giáo dục.', 'da_day'),
  (@lop3, @d_past2, '18:00', '20:00', @cs1, 'P105', 'Định luật Ôm cho toàn mạch và bài tập mạch điện', 'Giải 10 dạng bài mạch hỗn hợp có biến trở.', 'da_day'),
  (@lop4, @d_past2, '18:30', '20:00', @cs2, 'K101', 'Luyện kỹ năng viết lại câu & phát âm đuôi -ed/-s', 'Sửa lỗi trực tiếp trên đề thi thử và giao phiếu ôn tập.', 'da_day');

SET @b_l1_past := (SELECT id FROM buoi_hoc WHERE lop_id = @lop1 AND ngay = @d_past1 LIMIT 1);
SET @b_l2_past := (SELECT id FROM buoi_hoc WHERE lop_id = @lop2 AND ngay = @d_past1 LIMIT 1);
SET @b_l3_past := (SELECT id FROM buoi_hoc WHERE lop_id = @lop3 AND ngay = @d_past2 LIMIT 1);
SET @b_l4_past := (SELECT id FROM buoi_hoc WHERE lop_id = @lop4 AND ngay = @d_past2 LIMIT 1);

-- Buổi sắp diễn ra (hôm nay / 1-4 ngày tới) cho tất cả vai trò Check-in, ghi sổ, nhắc lịch & tuyển ca
INSERT INTO buoi_hoc (lop_id, ngay, gio_bat_dau, gio_ket_thuc, co_so_id, phong_hoc, chu_de_bai_hoc, trang_thai) VALUES
  (@lop1, @d_next1, '18:00', '20:00', @cs1, 'P201', 'Luyện viết mở bài và kết bài ấn tượng', 'du_kien'),
  (@lop2, @d_next1, '18:00', '20:00', @cs2, 'P302', 'Kỹ năng đọc hiểu tìm ý chính (Skimming & Scanning)', 'du_kien'),
  (@lop3, @d_next2, '18:00', '20:00', @cs1, 'P105', 'Từ trường của dòng điện chạy trong dây dẫn thẳng', 'du_kien'),
  (@lop4, @d_next1, '18:30', '20:00', @cs2, 'K101', 'Chữa đề thi thử Tiếng Anh vào 10 — Đề số 02', 'du_kien'),
  (@lop5, @d_next2, '09:00', '10:30', @cs1, 'K202', 'Ôn tập truyện ngắn hiện đại Việt Nam', 'du_kien'),
  (@lop1, @d_next3, '18:00', '20:00', @cs1, 'P201', 'Tổng ôn kiến thức trọng tâm giữa kỳ', 'du_kien'),
  (@lop4, @d_next3, '18:30', '20:00', @cs2, 'K101', 'Luyện đề tổng hợp kỹ năng nghe - đọc - viết', 'du_kien');

SET @b_l1_next := (SELECT id FROM buoi_hoc WHERE lop_id = @lop1 AND ngay = @d_next1 LIMIT 1);
SET @b_l2_next := (SELECT id FROM buoi_hoc WHERE lop_id = @lop2 AND ngay = @d_next1 LIMIT 1);
SET @b_l3_next := (SELECT id FROM buoi_hoc WHERE lop_id = @lop3 AND ngay = @d_next2 LIMIT 1);
SET @b_l4_next := (SELECT id FROM buoi_hoc WHERE lop_id = @lop4 AND ngay = @d_next1 LIMIT 1);
SET @b_l5_next := (SELECT id FROM buoi_hoc WHERE lop_id = @lop5 AND ngay = @d_next2 LIMIT 1);
SET @b_l1_open := (SELECT id FROM buoi_hoc WHERE lop_id = @lop1 AND ngay = @d_next3 LIMIT 1);
SET @b_l4_open := (SELECT id FROM buoi_hoc WHERE lop_id = @lop4 AND ngay = @d_next3 LIMIT 1);

-- 2.10. Phân công buổi học (quá khứ đã chấm công hợp lệ + tương lai sắp dạy)
INSERT INTO phan_cong_buoi_hoc (buoi_hoc_id, user_id, vai_tro, don_gia, cach_tinh_luong, phan_cong_boi, checkin_luc, checkout_luc, trang_thai, trang_thai_cong) VALUES
  (@b_l1_past, @u_gv1, 'day_chinh', 220000, 'theo_gio', @u_gv_staff, CONCAT(@d_past1, ' 18:00:00'), CONCAT(@d_past1, ' 20:00:00'), 'da_day', 'hop_le'),
  (@b_l1_past, @u_tg1, 'tro_giang',  90000, 'theo_gio', @u_gv_staff, CONCAT(@d_past1, ' 18:05:00'), CONCAT(@d_past1, ' 20:00:00'), 'da_day', 'hop_le'),
  (@b_l2_past, @u_gv2, 'day_chinh', 240000, 'theo_gio', @u_gv_staff, CONCAT(@d_past1, ' 18:00:00'), CONCAT(@d_past1, ' 20:00:00'), 'da_day', 'hop_le'),
  (@b_l3_past, @u_gv3, 'day_chinh', 230000, 'theo_gio', @u_gv_staff, CONCAT(@d_past2, ' 18:10:00'), CONCAT(@d_past2, ' 20:00:00'), 'da_day', 'hop_le'),
  (@b_l4_past, @u_gs1, 'day_chinh', 170000, 'theo_gio', @u_gv_staff, CONCAT(@d_past2, ' 18:30:00'), CONCAT(@d_past2, ' 20:00:00'), 'da_day', 'hop_le');

INSERT INTO phan_cong_buoi_hoc (buoi_hoc_id, user_id, vai_tro, don_gia, cach_tinh_luong, phan_cong_boi, trang_thai) VALUES
  (@b_l1_next, @u_gv1, 'day_chinh', 220000, 'theo_gio', @u_gv_staff, 'da_phan_cong'),
  (@b_l1_next, @u_tg1, 'tro_giang',  90000, 'theo_gio', @u_gv_staff, 'da_phan_cong'),
  (@b_l2_next, @u_gv2, 'day_chinh', 240000, 'theo_gio', @u_gv_staff, 'da_phan_cong'),
  (@b_l3_next, @u_gv3, 'day_chinh', 230000, 'theo_gio', @u_gv_staff, 'da_phan_cong'),
  (@b_l4_next, @u_gs1, 'day_chinh', 170000, 'theo_gio', @u_gv_staff, 'da_phan_cong'),
  (@b_l5_next, @u_gs1, 'day_chinh', 170000, 'theo_gio', @u_gv_staff, 'da_phan_cong');

SET @pc_gv3_past := (SELECT id FROM phan_cong_buoi_hoc WHERE buoi_hoc_id = @b_l3_past AND user_id = @u_gv3 LIMIT 1);

-- 2.11. Bài tập & Sổ theo dõi điểm danh / chấm điểm cho các buổi đã học
INSERT INTO bai_tap (buoi_hoc_id, tieu_de, noi_dung, file_de_bai_url, han_nop, nguoi_tao_id) VALUES
  (@b_l1_past, 'Phiếu luyện viết đoạn văn nghị luận 200 chữ', 'Chọn 1 trong 2 đề về tinh thần tự học và viết đoạn văn hoàn chỉnh.', 'https://files.educenter.test/nguvan9-de01.pdf', CONCAT(@d_next1, ' 17:30:00'), @u_gv1),
  (@b_l2_past, 'Bài tập mệnh đề quan hệ — Tuần 3', 'Hoàn thành 25 câu trắc nghiệm và 5 câu viết lại câu.', 'https://files.educenter.test/anh8-tuan03.pdf', CONCAT(@d_next1, ' 17:30:00'), @u_gv2),
  (@b_l4_past, 'Đề luyện tuyển sinh Tiếng Anh 9 — Số 01', 'Làm trọn bộ đề 40 câu trong 60 phút, ghi chú các câu còn phân vân.', 'https://files.educenter.test/anh9-kem-de01.pdf', CONCAT(@d_next1, ' 18:00:00'), @u_gs1);

SET @bt1 := (SELECT id FROM bai_tap WHERE buoi_hoc_id = @b_l1_past LIMIT 1);
SET @bt2 := (SELECT id FROM bai_tap WHERE buoi_hoc_id = @b_l2_past LIMIT 1);
SET @bt4 := (SELECT id FROM bai_tap WHERE buoi_hoc_id = @b_l4_past LIMIT 1);

INSERT INTO so_theo_doi_buoi_hoc (buoi_hoc_id, hoc_vien_id, trang_thai_diem_danh, bai_tap_id, diem_bai_tap_giay, nhan_xet_bai_tap, muc_tiep_thu, thai_do, nhan_xet_buoi_hoc, huong_cai_thien, nguoi_nhap_id) VALUES
  (@b_l1_past, @hv1, 'co_mat',      @bt1, 8.75, 'Lập luận mạch lạc, dẫn chứng tốt.', 5, 'tot', 'Tích cực phát biểu và nắm chắc bố cục bài nghị luận.', 'Mở rộng thêm vốn từ liên kết đoạn.', @u_gv1),
  (@b_l1_past, @hv6, 'di_tre',      @bt1, 7.50, 'Đủ ý chính nhưng còn lặp từ.',      4, 'kha', 'Vào lớp trễ 10 phút, tập trung tốt phần thực hành.',  'Chủ động đến đúng giờ và đọc kỹ đề.', @u_gv1),
  (@b_l2_past, @hv2, 'co_mat',      @bt2, 9.00, 'Làm đúng hầu hết các câu nâng cao.', 5, 'tot', 'Phát âm rõ, phản xạ ngữ pháp nhanh.',                  'Luyện thêm dạng câu đảo ngữ.',       @u_gv2),
  (@b_l2_past, @hv4, 'vang_co_phep',NULL, NULL, NULL,                                NULL, NULL, 'Phụ huynh đã xin phép nghỉ do lịch kiểm tra ở trường.','Giáo vụ hỗ trợ xếp buổi ôn lại.',    @u_gv2),
  (@b_l3_past, @hv5, 'co_mat',      NULL, NULL, NULL,                                4, 'tot', 'Hiểu nhanh nguyên lý mạch nối tiếp và song song.',     'Luyện thêm bài tập đồ thị U-I.',     @u_gv3),
  (@b_l4_past, @hv3, 'co_mat',      @bt4, 8.50, 'Phần viết lại câu tiến bộ rõ rệt.', 5, 'tot', 'Tập trung cao trong suốt 90 phút kèm 1:1.',            'Ôn kỹ trọng âm từ 3 âm tiết.',       @u_gs1);

-- 2.12. Đơn từ, tuyển sinh, đề xuất tuyển ca (cho Admin, Giáo vụ, Giáo viên, Gia sư, Trợ giảng)
-- Đơn nghỉ cá nhân chờ Giáo vụ duyệt
INSERT INTO don_xin_nghi (buoi_hoc_id, doi_tuong, hoc_vien_id, nguoi_gui_id, ly_do, trang_thai)
VALUES (@b_l1_next, 'hoc_vien', @hv6, @u_ph3, 'Học viên có lịch thi học sinh giỏi cấp trường, xin phép nghỉ và học bù sau.', 'cho_duyet');

-- Yêu cầu nghỉ cả lớp chờ Admin duyệt
INSERT INTO yeu_cau_nghi_lop (buoi_hoc_id, nguoi_gui_id, ly_do, trang_thai)
VALUES (@b_l3_next, @u_gv3, 'Giáo viên có lịch tập huấn chuyên môn của Sở Giáo dục, đề xuất cho lớp nghỉ buổi này.', 'cho_duyet');

-- Yêu cầu điều chỉnh công chờ Admin duyệt
INSERT INTO dieu_chinh_cong (phan_cong_id, loai, checkin_cu, checkout_cu, checkin_moi, checkout_moi, ly_do, nguoi_yeu_cau_id, trang_thai)
VALUES (@pc_gv3_past, 'sua_gio', CONCAT(@d_past2, ' 18:10:00'), CONCAT(@d_past2, ' 20:00:00'), CONCAT(@d_past2, ' 18:00:00'), CONCAT(@d_past2, ' 20:00:00'), 'Đã có mặt đúng 18:00 nhưng mạng cơ sở chập chờn nên thao tác bấm check-in bị trễ.', @u_gv3, 'cho_duyet');

-- Nhu cầu học kèm 1:1 mới từ phụ huynh (hiển thị trong cơ hội nhận lớp kèm của Gia sư)
INSERT INTO yeu_cau_hoc (ma_yc, nguoi_gui_id, hoc_vien_id, mon_hoc_id, cap_lop_id, khu_vuc_id, hinh_thuc, loai_lop_mong_muon, muc_tieu, nhan_vien_id, trang_thai)
VALUES ('EC26-YC01', @u_ph1, @hv2, @mon_anh, 8, @kv_thuduc, 'offline', 'kem_1_1', 'Củng cố ngữ pháp và nâng điểm bài kiểm tra cuối học kỳ môn Tiếng Anh 8.', @nv_id, 'dang_tim');
SET @yc1 := (SELECT id FROM yeu_cau_hoc WHERE ma_yc = 'EC26-YC01' LIMIT 1);
INSERT INTO yeu_cau_lich (yeu_cau_id, thu, gio_bat_dau, gio_ket_thuc) VALUES (@yc1, 4, '18:00', '19:30');

-- Đề xuất tuyển nhân sự ca học (1 ca lớp nhóm tuyển GV, 1 ca lớp nhóm tuyển Trợ giảng, 1 ca kèm 1:1 tuyển Gia sư)
INSERT INTO de_xuat_tuyen_ca (ma_de_xuat, buoi_hoc_id, vai_tro, so_luong, yeu_cau, trang_thai, nguoi_tao_id) VALUES
  ('EC26-TC01', @b_l1_open, 'day_chinh', 1, 'Cần Giáo viên Ngữ văn 9 đứng lớp buổi tổng ôn giữa kỳ.', 'dang_mo', @u_gv_staff),
  ('EC26-TC02', @b_l2_next, 'tro_giang', 1, 'Cần Trợ giảng Tiếng Anh hỗ trợ kiểm tra bài tập đầu giờ.', 'dang_mo', @u_gv_staff),
  ('EC26-TC03', @b_l4_open, 'day_chinh', 1, 'Cần Gia sư kèm 1:1 Tiếng Anh 9 luyện đề tổng hợp.', 'dang_mo', @u_gv_staff);

SET @tc2 := (SELECT id FROM de_xuat_tuyen_ca WHERE ma_de_xuat = 'EC26-TC02' LIMIT 1);
-- Một đơn ứng tuyển Trợ giảng đang chờ Giáo vụ duyệt (để Giáo vụ thấy ngay danh sách ứng viên)
INSERT INTO ung_tuyen_ca (de_xuat_id, nhan_su_id, user_id, ghi_chu, trang_thai)
VALUES (@tc2, @ns_tg1, @u_tg1, 'Em rảnh khung 17:00–21:30 và có thể hỗ trợ chấm bài tập giấy ngay tại cơ sở Bình Thạnh.', 'cho_duyet');

-- Thông báo mẫu (đã bật co_gui_email = 1 cho các sự kiện sẵn sàng để kiểm thử Worker CLI trên Mailpit)
INSERT IGNORE INTO thong_bao (user_id, loai, tieu_de, noi_dung, doi_tuong_loai, doi_tuong_id, khoa_chong_trung, co_gui_email) VALUES
  (@u_gv1, 'phan_cong', 'Phân công ca học · EC26-LOP01', 'Bạn được phân công đứng lớp Ngữ văn 9 — Luyện đề nhóm A1 vào ngày mai lúc 18:00–20:00.', 'buoi_hoc', @b_l1_next, 'seed-ec26-pc-gv1', 1),
  (@u_gs1, 'phan_cong', 'Phân công ca kèm 1:1 · EC26-LOP04', 'Bạn được phân công ca kèm 1:1 Tiếng Anh 9 — Gia Hân vào ngày mai lúc 18:30–20:00.', 'buoi_hoc', @b_l4_next, 'seed-ec26-pc-gs1', 1),
  (@u_tg1, 'phan_cong', 'Phân công hỗ trợ ca · EC26-LOP01', 'Bạn được phân công làm Trợ giảng lớp Ngữ văn 9 — Luyện đề nhóm A1 vào ngày mai lúc 18:00–20:00.', 'buoi_hoc', @b_l1_next, 'seed-ec26-pc-tg1', 1),
  (@u_ph1, 'lop_moi',   'Thông báo xếp lớp · EC26-LOP01', 'Học viên Nguyễn Minh An đã được xếp vào lớp Ngữ văn 9 — Luyện đề nhóm A1 tại Cơ sở Võ Văn Ngân.', 'lop_hoc', @lop1, 'seed-ec26-lop-ph1', 1);

COMMIT;

SELECT 'Hoàn tất Phần 2: Đã tạo bộ dữ liệu trình diễn đầy đủ cho Admin, Giáo vụ, Giáo viên, Gia sư, Trợ giảng, Phụ huynh và Học viên.' AS ket_qua_phan_2;
