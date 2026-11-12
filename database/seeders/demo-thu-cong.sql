-- EDUCENTER ERP · MYSQL 8.4 · SCHEMA 005
-- PHẦN 1 THÊM: chạy từ USE đến hết mục A8. Mỗi INSERT thêm một dòng, đủ tên cột.
-- PHẦN 2 XÓA: ở cuối file, nằm trong khối chú thích. Chọn chạy riêng khi cần.
-- Phần xóa giữ thanh toán, hóa đơn, lịch sử và hồ sơ tham chiếu, gồm HV008 tự thêm.
-- Tài khoản Giáo vụ mẫu: sqldemo01_giaovu / DemoGiaoVu@123.
-- Đổi @sd_tag thành SQLDEMO02, SQLDEMO03... để thêm bộ khác.
-- Danh mục/quyền có sẵn được giữ bằng ON DUPLICATE KEY UPDATE.
-- Cột tự tính dùng DEFAULT. Lỗi thêm dữ liệu sẽ rollback toàn bộ bộ mới.

-- ============================================================================
-- PHẦN 1 · THÊM DỮ LIỆU
-- ============================================================================
USE trung_tam_gia_su;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET SESSION time_zone = '+07:00';
SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED;
SET @sd_tag = 'SQLDEMO01';

DELIMITER $$
DROP PROCEDURE IF EXISTS sp_them_du_lieu_sqldemo$$
CREATE PROCEDURE sp_them_du_lieu_sqldemo()
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
    IF @sd_tag NOT REGEXP '^SQLDEMO[0-9]{2}$' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ma bo du lieu phai la SQLDEMO01..SQLDEMO99.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM schema_migrations WHERE version='005_operations_ready' AND status='applied') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Can DB da cai schema 005.';
    END IF;
    IF EXISTS (SELECT 1 FROM users WHERE email=CONCAT(LOWER(@sd_tag),'.giaovu@example.test')) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Bo du lieu nay da co. Doi @sd_tag sang so tiep theo.';
    END IF;
    SET @sd_admin = (SELECT MIN(a.user_id) FROM account a JOIN role_group g ON g.group_id=a.group_id
        JOIN users u ON u.user_id=a.user_id WHERE g.group_name='Admin' AND g.is_deleted=0
        AND a.status='active' AND a.is_deleted=0 AND u.is_deleted=0);
    IF @sd_admin IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Can mot Admin dang hoat dong de gan actor.'; END IF;
    SET @sd_past = DATE_SUB(CURDATE(), INTERVAL 14 DAY);
    SET @sd_next = DATE_ADD(CURDATE(), INTERVAL 1 DAY);
    SET @sd_makeup_day = DATE_ADD(CURDATE(), INTERVAL 2 DAY);
    START TRANSACTION;

-- A1. Danh muc chuan. Moi dong mot INSERT, giu nguyen dong da ton tai.
INSERT INTO `role_group` (`group_id`, `group_name`, `description`, `created_at`, `is_deleted`)
VALUES (NULL, 'Admin', 'Quản trị viên', DEFAULT, 0) ON DUPLICATE KEY UPDATE `group_id` = `role_group`.`group_id`;
SET @sd_group1 = (SELECT group_id FROM role_group WHERE group_name='Admin');
INSERT INTO `role_group` (`group_id`, `group_name`, `description`, `created_at`, `is_deleted`)
VALUES (NULL, 'NhanVien', 'Giáo vụ / Điều phối', DEFAULT, 0) ON DUPLICATE KEY UPDATE `group_id` = `role_group`.`group_id`;
SET @sd_group2 = (SELECT group_id FROM role_group WHERE group_name='NhanVien');
INSERT INTO `role_group` (`group_id`, `group_name`, `description`, `created_at`, `is_deleted`)
VALUES (NULL, 'GiangVien', 'Giáo viên / Gia sư', DEFAULT, 0) ON DUPLICATE KEY UPDATE `group_id` = `role_group`.`group_id`;
SET @sd_group3 = (SELECT group_id FROM role_group WHERE group_name='GiangVien');
INSERT INTO `role_group` (`group_id`, `group_name`, `description`, `created_at`, `is_deleted`)
VALUES (NULL, 'TroGiang', 'Trợ giảng', DEFAULT, 0) ON DUPLICATE KEY UPDATE `group_id` = `role_group`.`group_id`;
SET @sd_group4 = (SELECT group_id FROM role_group WHERE group_name='TroGiang');
INSERT INTO `role_group` (`group_id`, `group_name`, `description`, `created_at`, `is_deleted`)
VALUES (NULL, 'PhuHuynh', 'Phụ huynh', DEFAULT, 0) ON DUPLICATE KEY UPDATE `group_id` = `role_group`.`group_id`;
SET @sd_group5 = (SELECT group_id FROM role_group WHERE group_name='PhuHuynh');
INSERT INTO `function` (`function_id`, `name_function`, `created_at`, `is_deleted`)
VALUES (NULL, 'QuanLyTaiKhoan', DEFAULT, 0) ON DUPLICATE KEY UPDATE `function_id` = `function`.`function_id`;
SET @sd_fn1 = (SELECT function_id FROM `function` WHERE name_function='QuanLyTaiKhoan');
INSERT INTO `function` (`function_id`, `name_function`, `created_at`, `is_deleted`)
VALUES (NULL, 'QuanLyNhanSu', DEFAULT, 0) ON DUPLICATE KEY UPDATE `function_id` = `function`.`function_id`;
SET @sd_fn2 = (SELECT function_id FROM `function` WHERE name_function='QuanLyNhanSu');
INSERT INTO `function` (`function_id`, `name_function`, `created_at`, `is_deleted`)
VALUES (NULL, 'TiepNhanYeuCau', DEFAULT, 0) ON DUPLICATE KEY UPDATE `function_id` = `function`.`function_id`;
SET @sd_fn3 = (SELECT function_id FROM `function` WHERE name_function='TiepNhanYeuCau');
INSERT INTO `function` (`function_id`, `name_function`, `created_at`, `is_deleted`)
VALUES (NULL, 'QuanLyLopHoc', DEFAULT, 0) ON DUPLICATE KEY UPDATE `function_id` = `function`.`function_id`;
SET @sd_fn4 = (SELECT function_id FROM `function` WHERE name_function='QuanLyLopHoc');
INSERT INTO `function` (`function_id`, `name_function`, `created_at`, `is_deleted`)
VALUES (NULL, 'VanHanhBuoiHoc', DEFAULT, 0) ON DUPLICATE KEY UPDATE `function_id` = `function`.`function_id`;
SET @sd_fn5 = (SELECT function_id FROM `function` WHERE name_function='VanHanhBuoiHoc');
INSERT INTO `function` (`function_id`, `name_function`, `created_at`, `is_deleted`)
VALUES (NULL, 'ChamCongLuong', DEFAULT, 0) ON DUPLICATE KEY UPDATE `function_id` = `function`.`function_id`;
SET @sd_fn6 = (SELECT function_id FROM `function` WHERE name_function='ChamCongLuong');
INSERT INTO `function` (`function_id`, `name_function`, `created_at`, `is_deleted`)
VALUES (NULL, 'HocPhiThanhToan', DEFAULT, 0) ON DUPLICATE KEY UPDATE `function_id` = `function`.`function_id`;
SET @sd_fn7 = (SELECT function_id FROM `function` WHERE name_function='HocPhiThanhToan');
INSERT INTO `function` (`function_id`, `name_function`, `created_at`, `is_deleted`)
VALUES (NULL, 'BaoCaoThongKe', DEFAULT, 0) ON DUPLICATE KEY UPDATE `function_id` = `function`.`function_id`;
SET @sd_fn8 = (SELECT function_id FROM `function` WHERE name_function='BaoCaoThongKe');
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group1, @sd_fn1, 1, 1, 1, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group1, @sd_fn2, 1, 1, 1, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group1, @sd_fn3, 1, 1, 1, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group1, @sd_fn4, 1, 1, 1, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group1, @sd_fn5, 1, 1, 1, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group1, @sd_fn6, 1, 1, 1, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group1, @sd_fn7, 1, 1, 1, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group1, @sd_fn8, 1, 1, 1, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group2, @sd_fn3, 1, 1, 0, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group2, @sd_fn4, 1, 1, 0, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group2, @sd_fn5, 1, 1, 0, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group2, @sd_fn7, 1, 1, 0, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group2, @sd_fn8, 0, 0, 0, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group3, @sd_fn5, 1, 1, 0, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group3, @sd_fn6, 0, 0, 0, 0, 0, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group4, @sd_fn5, 0, 1, 0, 0, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group4, @sd_fn6, 0, 0, 0, 0, 0, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group5, @sd_fn5, 0, 0, 0, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, @sd_group5, @sd_fn7, 0, 0, 0, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `mon_hoc` (`id`, `ma`, `ten`, `is_active`)
VALUES (NULL, 'TOAN', 'Toán', 1) ON DUPLICATE KEY UPDATE `id` = `mon_hoc`.`id`;
SET @sd_mon_TOAN = (SELECT id FROM mon_hoc WHERE ma='TOAN');
INSERT INTO `mon_hoc` (`id`, `ma`, `ten`, `is_active`)
VALUES (NULL, 'VAN', 'Ngữ văn', 1) ON DUPLICATE KEY UPDATE `id` = `mon_hoc`.`id`;
SET @sd_mon_VAN = (SELECT id FROM mon_hoc WHERE ma='VAN');
INSERT INTO `mon_hoc` (`id`, `ma`, `ten`, `is_active`)
VALUES (NULL, 'ANH', 'Tiếng Anh', 1) ON DUPLICATE KEY UPDATE `id` = `mon_hoc`.`id`;
SET @sd_mon_ANH = (SELECT id FROM mon_hoc WHERE ma='ANH');
INSERT INTO `mon_hoc` (`id`, `ma`, `ten`, `is_active`)
VALUES (NULL, 'LY', 'Vật lý', 1) ON DUPLICATE KEY UPDATE `id` = `mon_hoc`.`id`;
SET @sd_mon_LY = (SELECT id FROM mon_hoc WHERE ma='LY');
INSERT INTO `mon_hoc` (`id`, `ma`, `ten`, `is_active`)
VALUES (NULL, 'HOA', 'Hóa học', 1) ON DUPLICATE KEY UPDATE `id` = `mon_hoc`.`id`;
SET @sd_mon_HOA = (SELECT id FROM mon_hoc WHERE ma='HOA');
INSERT INTO `mon_hoc` (`id`, `ma`, `ten`, `is_active`)
VALUES (NULL, 'SINH', 'Sinh học', 1) ON DUPLICATE KEY UPDATE `id` = `mon_hoc`.`id`;
SET @sd_mon_SINH = (SELECT id FROM mon_hoc WHERE ma='SINH');
INSERT INTO `cap_lop` (`id`, `ten`, `cap_hoc`)
VALUES (1, 'Lớp 1', 'tieu_hoc') ON DUPLICATE KEY UPDATE `id` = `cap_lop`.`id`;
INSERT INTO `cap_lop` (`id`, `ten`, `cap_hoc`)
VALUES (2, 'Lớp 2', 'tieu_hoc') ON DUPLICATE KEY UPDATE `id` = `cap_lop`.`id`;
INSERT INTO `cap_lop` (`id`, `ten`, `cap_hoc`)
VALUES (3, 'Lớp 3', 'tieu_hoc') ON DUPLICATE KEY UPDATE `id` = `cap_lop`.`id`;
INSERT INTO `cap_lop` (`id`, `ten`, `cap_hoc`)
VALUES (4, 'Lớp 4', 'tieu_hoc') ON DUPLICATE KEY UPDATE `id` = `cap_lop`.`id`;
INSERT INTO `cap_lop` (`id`, `ten`, `cap_hoc`)
VALUES (5, 'Lớp 5', 'tieu_hoc') ON DUPLICATE KEY UPDATE `id` = `cap_lop`.`id`;
INSERT INTO `cap_lop` (`id`, `ten`, `cap_hoc`)
VALUES (6, 'Lớp 6', 'thcs') ON DUPLICATE KEY UPDATE `id` = `cap_lop`.`id`;
INSERT INTO `cap_lop` (`id`, `ten`, `cap_hoc`)
VALUES (7, 'Lớp 7', 'thcs') ON DUPLICATE KEY UPDATE `id` = `cap_lop`.`id`;
INSERT INTO `cap_lop` (`id`, `ten`, `cap_hoc`)
VALUES (8, 'Lớp 8', 'thcs') ON DUPLICATE KEY UPDATE `id` = `cap_lop`.`id`;
INSERT INTO `cap_lop` (`id`, `ten`, `cap_hoc`)
VALUES (9, 'Lớp 9', 'thcs') ON DUPLICATE KEY UPDATE `id` = `cap_lop`.`id`;
INSERT INTO `cap_lop` (`id`, `ten`, `cap_hoc`)
VALUES (10, 'Lớp 10', 'thpt') ON DUPLICATE KEY UPDATE `id` = `cap_lop`.`id`;
INSERT INTO `cap_lop` (`id`, `ten`, `cap_hoc`)
VALUES (11, 'Lớp 11', 'thpt') ON DUPLICATE KEY UPDATE `id` = `cap_lop`.`id`;
INSERT INTO `cap_lop` (`id`, `ten`, `cap_hoc`)
VALUES (12, 'Lớp 12', 'thpt') ON DUPLICATE KEY UPDATE `id` = `cap_lop`.`id`;
INSERT INTO `khu_vuc` (`id`, `ten`, `thanh_pho`, `is_active`)
VALUES (NULL, 'Thủ Đức', 'TP. Hồ Chí Minh', 1) ON DUPLICATE KEY UPDATE `id` = `khu_vuc`.`id`;
SET @sd_region1 = (SELECT id FROM khu_vuc WHERE ten='Thủ Đức' AND thanh_pho='TP. Hồ Chí Minh');
INSERT INTO `khu_vuc` (`id`, `ten`, `thanh_pho`, `is_active`)
VALUES (NULL, 'Bình Thạnh', 'TP. Hồ Chí Minh', 1) ON DUPLICATE KEY UPDATE `id` = `khu_vuc`.`id`;
SET @sd_region2 = (SELECT id FROM khu_vuc WHERE ten='Bình Thạnh' AND thanh_pho='TP. Hồ Chí Minh');
INSERT INTO `cau_hinh` (`khoa`, `gia_tri`, `mo_ta`, `updated_at`)
VALUES ('checkin_truoc_phut', '-1', 'Không giới hạn check-in sớm', DEFAULT) ON DUPLICATE KEY UPDATE `khoa` = `cau_hinh`.`khoa`;
INSERT INTO `cau_hinh` (`khoa`, `gia_tri`, `mo_ta`, `updated_at`)
VALUES ('checkin_tre_sau_phut', '0', 'Check-in sau giờ lịch là trễ', DEFAULT) ON DUPLICATE KEY UPDATE `khoa` = `cau_hinh`.`khoa`;
INSERT INTO `cau_hinh` (`khoa`, `gia_tri`, `mo_ta`, `updated_at`)
VALUES ('checkout_sau_phut', '-1', 'Không cộng công ngoài lịch', DEFAULT) ON DUPLICATE KEY UPDATE `khoa` = `cau_hinh`.`khoa`;
INSERT INTO `cau_hinh` (`khoa`, `gia_tri`, `mo_ta`, `updated_at`)
VALUES ('checkin_minh_chung', 'khong', 'Bấm nút, giờ máy chủ', DEFAULT) ON DUPLICATE KEY UPDATE `khoa` = `cau_hinh`.`khoa`;
INSERT INTO `cau_hinh` (`khoa`, `gia_tri`, `mo_ta`, `updated_at`)
VALUES ('han_nhap_bao_cao_gio', '24', 'Hạn nhập báo cáo', DEFAULT) ON DUPLICATE KEY UPDATE `khoa` = `cau_hinh`.`khoa`;
INSERT INTO `schema_migrations` (`version`, `status`, `applied_at`)
VALUES ('005_operations_ready', 'applied', (SELECT old.applied_at FROM (SELECT applied_at FROM schema_migrations WHERE version='005_operations_ready') old)) ON DUPLICATE KEY UPDATE `version` = `schema_migrations`.`version`;

-- A2. Giao vu, co so va tai khoan demo. Dung Admin hien co de ghi dau vet.
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Lê Minh Thư [SQLDEMO]', NULL, CONCAT(LOWER(@sd_tag),'.giaovu@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Giáo vụ', @sd_region1, DEFAULT, 0, NULL);
SET @sd_giaovu = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, @sd_giaovu, @sd_group2, CONCAT(LOWER(@sd_tag),'_giaovu'), '$2y$12$JyjorAFyqYGkvTODM.QfFuqTTgE4w9S1OasxJFPOO.K8M5uBycLGS', 'active', DEFAULT, 0, NULL);
INSERT INTO `co_so` (`id`, `ma_co_so`, `ten_co_so`, `dia_chi`, `so_dien_thoai`, `email`, `khu_vuc_id`, `quan_ly_id`, `is_active`, `created_at`)
VALUES (NULL, CONCAT(@sd_tag,'-CS01'), 'EduCenter Thủ Đức [SQLDEMO]', 'Cơ sở minh họa Thủ Đức', '02800000000', CONCAT(LOWER(@sd_tag),'.cs1@example.test'), @sd_region1, @sd_giaovu, 1, DEFAULT);
SET @sd_campus1 = LAST_INSERT_ID();
INSERT INTO `co_so` (`id`, `ma_co_so`, `ten_co_so`, `dia_chi`, `so_dien_thoai`, `email`, `khu_vuc_id`, `quan_ly_id`, `is_active`, `created_at`)
VALUES (NULL, CONCAT(@sd_tag,'-CS02'), 'EduCenter Bình Thạnh [SQLDEMO]', 'Cơ sở minh họa Bình Thạnh', '02800000000', CONCAT(LOWER(@sd_tag),'.cs2@example.test'), @sd_region2, @sd_giaovu, 1, DEFAULT);
SET @sd_campus2 = LAST_INSERT_ID();
INSERT INTO `nhan_vien` (`id`, `user_id`, `ma_nv`, `chuc_vu`, `co_so_id`, `ngay_vao_lam`, `trang_thai`, `created_at`)
VALUES (NULL, @sd_giaovu, CONCAT(@sd_tag,'-NV01'), 'Giáo vụ', NULL, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 'dang_lam', DEFAULT);
SET @sd_employee = LAST_INSERT_ID();
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Nguyễn Minh Anh [SQLDEMO]', NULL, CONCAT(LOWER(@sd_tag),'.teacher1@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Người dạy', @sd_region1, DEFAULT, 0, NULL);
SET @sd_teacher1 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, @sd_teacher1, @sd_group3, CONCAT(LOWER(@sd_tag),'_gv01'), '$2y$12$JyjorAFyqYGkvTODM.QfFuqTTgE4w9S1OasxJFPOO.K8M5uBycLGS', 'active', DEFAULT, 0, NULL);
INSERT INTO `nhan_su_chuyen_mon` (`id`, `user_id`, `ma_ns`, `phan_loai`, `gioi_tinh`, `cccd`, `nghe_nghiep`, `don_vi_cong_tac`, `gioi_thieu`, `kinh_nghiem_nam`, `hinh_thuc_day`, `don_gia_gio_chuan`, `ngan_hang`, `so_tai_khoan`, `chu_tai_khoan`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_teacher1, CONCAT(@sd_tag,'-NS01'), 'giao_vien', 'nu', NULL, 'giao_vien', 'EduCenter', 'Hồ sơ giả lập phục vụ kiểm thử giao diện', 3, 'online,offline', 160000, 'Ngân hàng minh họa', 'DEMO1', 'Nguyễn Minh Anh', 'hoat_dong', DEFAULT, DEFAULT, NULL);
SET @sd_ns1 = LAST_INSERT_ID();
INSERT INTO `lich_su_don_gia_gio` (`id`, `nhan_su_id`, `don_gia_gio`, `hieu_luc_tu`, `nguoi_dat_id`)
VALUES (NULL, @sd_ns1, 160000, TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 7 DAY)), @sd_admin);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (@sd_ns1, @sd_region1);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (@sd_ns1, @sd_region2);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns1, @sd_mon_TOAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns1, @sd_mon_VAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns1, @sd_mon_ANH, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns1, @sd_mon_LY, 6, 12, 250000);
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns1, 2, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns1, 3, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns1, 4, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns1, 5, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns1, 6, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns1, 7, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns1, 8, '08:00', '21:00');
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Trần Quốc Huy [SQLDEMO]', NULL, CONCAT(LOWER(@sd_tag),'.teacher2@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Người dạy', @sd_region1, DEFAULT, 0, NULL);
SET @sd_teacher2 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, @sd_teacher2, @sd_group3, CONCAT(LOWER(@sd_tag),'_gv02'), '$2y$12$JyjorAFyqYGkvTODM.QfFuqTTgE4w9S1OasxJFPOO.K8M5uBycLGS', 'active', DEFAULT, 0, NULL);
INSERT INTO `nhan_su_chuyen_mon` (`id`, `user_id`, `ma_ns`, `phan_loai`, `gioi_tinh`, `cccd`, `nghe_nghiep`, `don_vi_cong_tac`, `gioi_thieu`, `kinh_nghiem_nam`, `hinh_thuc_day`, `don_gia_gio_chuan`, `ngan_hang`, `so_tai_khoan`, `chu_tai_khoan`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_teacher2, CONCAT(@sd_tag,'-NS02'), 'giao_vien', 'nam', NULL, 'giao_vien', 'EduCenter', 'Hồ sơ giả lập phục vụ kiểm thử giao diện', 3, 'online,offline', 170000, 'Ngân hàng minh họa', 'DEMO2', 'Trần Quốc Huy', 'hoat_dong', DEFAULT, DEFAULT, NULL);
SET @sd_ns2 = LAST_INSERT_ID();
INSERT INTO `lich_su_don_gia_gio` (`id`, `nhan_su_id`, `don_gia_gio`, `hieu_luc_tu`, `nguoi_dat_id`)
VALUES (NULL, @sd_ns2, 170000, TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 7 DAY)), @sd_admin);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (@sd_ns2, @sd_region1);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (@sd_ns2, @sd_region2);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns2, @sd_mon_TOAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns2, @sd_mon_VAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns2, @sd_mon_ANH, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns2, @sd_mon_LY, 6, 12, 250000);
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns2, 2, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns2, 3, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns2, 4, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns2, 5, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns2, 6, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns2, 7, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns2, 8, '08:00', '21:00');
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Lê Thu Hà [SQLDEMO]', NULL, CONCAT(LOWER(@sd_tag),'.teacher3@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Người dạy', @sd_region1, DEFAULT, 0, NULL);
SET @sd_teacher3 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, @sd_teacher3, @sd_group3, CONCAT(LOWER(@sd_tag),'_gv03'), '$2y$12$JyjorAFyqYGkvTODM.QfFuqTTgE4w9S1OasxJFPOO.K8M5uBycLGS', 'active', DEFAULT, 0, NULL);
INSERT INTO `nhan_su_chuyen_mon` (`id`, `user_id`, `ma_ns`, `phan_loai`, `gioi_tinh`, `cccd`, `nghe_nghiep`, `don_vi_cong_tac`, `gioi_thieu`, `kinh_nghiem_nam`, `hinh_thuc_day`, `don_gia_gio_chuan`, `ngan_hang`, `so_tai_khoan`, `chu_tai_khoan`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_teacher3, CONCAT(@sd_tag,'-NS03'), 'giao_vien', 'nu', NULL, 'giao_vien', 'EduCenter', 'Hồ sơ giả lập phục vụ kiểm thử giao diện', 3, 'online,offline', 180000, 'Ngân hàng minh họa', 'DEMO3', 'Lê Thu Hà', 'hoat_dong', DEFAULT, DEFAULT, NULL);
SET @sd_ns3 = LAST_INSERT_ID();
INSERT INTO `lich_su_don_gia_gio` (`id`, `nhan_su_id`, `don_gia_gio`, `hieu_luc_tu`, `nguoi_dat_id`)
VALUES (NULL, @sd_ns3, 180000, TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 7 DAY)), @sd_admin);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (@sd_ns3, @sd_region1);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (@sd_ns3, @sd_region2);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns3, @sd_mon_TOAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns3, @sd_mon_VAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns3, @sd_mon_ANH, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns3, @sd_mon_LY, 6, 12, 250000);
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns3, 2, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns3, 3, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns3, 4, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns3, 5, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns3, 6, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns3, 7, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns3, 8, '08:00', '21:00');
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Phạm Quốc Bảo [SQLDEMO]', NULL, CONCAT(LOWER(@sd_tag),'.teacher4@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Người dạy', @sd_region1, DEFAULT, 0, NULL);
SET @sd_teacher4 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, @sd_teacher4, @sd_group3, CONCAT(LOWER(@sd_tag),'_gv04'), '$2y$12$JyjorAFyqYGkvTODM.QfFuqTTgE4w9S1OasxJFPOO.K8M5uBycLGS', 'active', DEFAULT, 0, NULL);
INSERT INTO `nhan_su_chuyen_mon` (`id`, `user_id`, `ma_ns`, `phan_loai`, `gioi_tinh`, `cccd`, `nghe_nghiep`, `don_vi_cong_tac`, `gioi_thieu`, `kinh_nghiem_nam`, `hinh_thuc_day`, `don_gia_gio_chuan`, `ngan_hang`, `so_tai_khoan`, `chu_tai_khoan`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_teacher4, CONCAT(@sd_tag,'-NS04'), 'gia_su', 'nam', NULL, 'giao_vien', 'EduCenter', 'Hồ sơ giả lập phục vụ kiểm thử giao diện', 3, 'online,offline', 190000, 'Ngân hàng minh họa', 'DEMO4', 'Phạm Quốc Bảo', 'hoat_dong', DEFAULT, DEFAULT, NULL);
SET @sd_ns4 = LAST_INSERT_ID();
INSERT INTO `lich_su_don_gia_gio` (`id`, `nhan_su_id`, `don_gia_gio`, `hieu_luc_tu`, `nguoi_dat_id`)
VALUES (NULL, @sd_ns4, 190000, TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 7 DAY)), @sd_admin);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (@sd_ns4, @sd_region1);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (@sd_ns4, @sd_region2);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns4, @sd_mon_TOAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns4, @sd_mon_VAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns4, @sd_mon_ANH, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns4, @sd_mon_LY, 6, 12, 250000);
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns4, 2, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns4, 3, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns4, 4, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns4, 5, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns4, 6, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns4, 7, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns4, 8, '08:00', '21:00');
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Võ Thanh Tâm [SQLDEMO]', NULL, CONCAT(LOWER(@sd_tag),'.teacher5@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Người dạy', @sd_region1, DEFAULT, 0, NULL);
SET @sd_teacher5 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, @sd_teacher5, @sd_group3, CONCAT(LOWER(@sd_tag),'_gv05'), '$2y$12$JyjorAFyqYGkvTODM.QfFuqTTgE4w9S1OasxJFPOO.K8M5uBycLGS', 'active', DEFAULT, 0, NULL);
INSERT INTO `nhan_su_chuyen_mon` (`id`, `user_id`, `ma_ns`, `phan_loai`, `gioi_tinh`, `cccd`, `nghe_nghiep`, `don_vi_cong_tac`, `gioi_thieu`, `kinh_nghiem_nam`, `hinh_thuc_day`, `don_gia_gio_chuan`, `ngan_hang`, `so_tai_khoan`, `chu_tai_khoan`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_teacher5, CONCAT(@sd_tag,'-NS05'), 'giao_vien', 'nu', NULL, 'giao_vien', 'EduCenter', 'Hồ sơ giả lập phục vụ kiểm thử giao diện', 3, 'online,offline', 200000, 'Ngân hàng minh họa', 'DEMO5', 'Võ Thanh Tâm', 'hoat_dong', DEFAULT, DEFAULT, NULL);
SET @sd_ns5 = LAST_INSERT_ID();
INSERT INTO `lich_su_don_gia_gio` (`id`, `nhan_su_id`, `don_gia_gio`, `hieu_luc_tu`, `nguoi_dat_id`)
VALUES (NULL, @sd_ns5, 200000, TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 7 DAY)), @sd_admin);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (@sd_ns5, @sd_region1);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (@sd_ns5, @sd_region2);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns5, @sd_mon_TOAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns5, @sd_mon_VAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns5, @sd_mon_ANH, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns5, @sd_mon_LY, 6, 12, 250000);
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns5, 2, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns5, 3, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns5, 4, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns5, 5, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns5, 6, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns5, 7, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns5, 8, '08:00', '21:00');
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Đỗ Ngọc Ánh [SQLDEMO]', NULL, CONCAT(LOWER(@sd_tag),'.teacher6@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Người dạy', @sd_region1, DEFAULT, 0, NULL);
SET @sd_teacher6 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, @sd_teacher6, @sd_group3, CONCAT(LOWER(@sd_tag),'_gv06'), '$2y$12$JyjorAFyqYGkvTODM.QfFuqTTgE4w9S1OasxJFPOO.K8M5uBycLGS', 'active', DEFAULT, 0, NULL);
INSERT INTO `nhan_su_chuyen_mon` (`id`, `user_id`, `ma_ns`, `phan_loai`, `gioi_tinh`, `cccd`, `nghe_nghiep`, `don_vi_cong_tac`, `gioi_thieu`, `kinh_nghiem_nam`, `hinh_thuc_day`, `don_gia_gio_chuan`, `ngan_hang`, `so_tai_khoan`, `chu_tai_khoan`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_teacher6, CONCAT(@sd_tag,'-NS06'), 'gia_su', 'nam', NULL, 'giao_vien', 'EduCenter', 'Hồ sơ giả lập phục vụ kiểm thử giao diện', 3, 'online,offline', 210000, 'Ngân hàng minh họa', 'DEMO6', 'Đỗ Ngọc Ánh', 'hoat_dong', DEFAULT, DEFAULT, NULL);
SET @sd_ns6 = LAST_INSERT_ID();
INSERT INTO `lich_su_don_gia_gio` (`id`, `nhan_su_id`, `don_gia_gio`, `hieu_luc_tu`, `nguoi_dat_id`)
VALUES (NULL, @sd_ns6, 210000, TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 7 DAY)), @sd_admin);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (@sd_ns6, @sd_region1);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (@sd_ns6, @sd_region2);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns6, @sd_mon_TOAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns6, @sd_mon_VAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns6, @sd_mon_ANH, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns6, @sd_mon_LY, 6, 12, 250000);
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns6, 2, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns6, 3, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns6, 4, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns6, 5, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns6, 6, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns6, 7, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns6, 8, '08:00', '21:00');
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Nguyễn Thảo Vy [SQLDEMO]', NULL, CONCAT(LOWER(@sd_tag),'.teacher7@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Trợ giảng', @sd_region1, DEFAULT, 0, NULL);
SET @sd_teacher7 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, @sd_teacher7, @sd_group4, CONCAT(LOWER(@sd_tag),'_gv07'), '$2y$12$JyjorAFyqYGkvTODM.QfFuqTTgE4w9S1OasxJFPOO.K8M5uBycLGS', 'active', DEFAULT, 0, NULL);
INSERT INTO `nhan_su_chuyen_mon` (`id`, `user_id`, `ma_ns`, `phan_loai`, `gioi_tinh`, `cccd`, `nghe_nghiep`, `don_vi_cong_tac`, `gioi_thieu`, `kinh_nghiem_nam`, `hinh_thuc_day`, `don_gia_gio_chuan`, `ngan_hang`, `so_tai_khoan`, `chu_tai_khoan`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_teacher7, CONCAT(@sd_tag,'-NS07'), 'tro_giang', 'nu', NULL, 'sinh_vien', 'EduCenter', 'Hồ sơ giả lập phục vụ kiểm thử giao diện', 3, 'online,offline', 80000, 'Ngân hàng minh họa', 'DEMO7', 'Nguyễn Thảo Vy', 'hoat_dong', DEFAULT, DEFAULT, NULL);
SET @sd_ns7 = LAST_INSERT_ID();
INSERT INTO `lich_su_don_gia_gio` (`id`, `nhan_su_id`, `don_gia_gio`, `hieu_luc_tu`, `nguoi_dat_id`)
VALUES (NULL, @sd_ns7, 80000, TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 7 DAY)), @sd_admin);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (@sd_ns7, @sd_region1);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (@sd_ns7, @sd_region2);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns7, @sd_mon_TOAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns7, @sd_mon_VAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns7, @sd_mon_ANH, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns7, @sd_mon_LY, 6, 12, 250000);
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns7, 2, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns7, 3, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns7, 4, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns7, 5, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns7, 6, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns7, 7, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns7, 8, '08:00', '21:00');
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Trần Đức Minh [SQLDEMO]', NULL, CONCAT(LOWER(@sd_tag),'.teacher8@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Trợ giảng', @sd_region1, DEFAULT, 0, NULL);
SET @sd_teacher8 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, @sd_teacher8, @sd_group4, CONCAT(LOWER(@sd_tag),'_gv08'), '$2y$12$JyjorAFyqYGkvTODM.QfFuqTTgE4w9S1OasxJFPOO.K8M5uBycLGS', 'active', DEFAULT, 0, NULL);
INSERT INTO `nhan_su_chuyen_mon` (`id`, `user_id`, `ma_ns`, `phan_loai`, `gioi_tinh`, `cccd`, `nghe_nghiep`, `don_vi_cong_tac`, `gioi_thieu`, `kinh_nghiem_nam`, `hinh_thuc_day`, `don_gia_gio_chuan`, `ngan_hang`, `so_tai_khoan`, `chu_tai_khoan`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_teacher8, CONCAT(@sd_tag,'-NS08'), 'tro_giang', 'nam', NULL, 'sinh_vien', 'EduCenter', 'Hồ sơ giả lập phục vụ kiểm thử giao diện', 3, 'online,offline', 80000, 'Ngân hàng minh họa', 'DEMO8', 'Trần Đức Minh', 'hoat_dong', DEFAULT, DEFAULT, NULL);
SET @sd_ns8 = LAST_INSERT_ID();
INSERT INTO `lich_su_don_gia_gio` (`id`, `nhan_su_id`, `don_gia_gio`, `hieu_luc_tu`, `nguoi_dat_id`)
VALUES (NULL, @sd_ns8, 80000, TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 7 DAY)), @sd_admin);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (@sd_ns8, @sd_region1);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (@sd_ns8, @sd_region2);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns8, @sd_mon_TOAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns8, @sd_mon_VAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns8, @sd_mon_ANH, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, @sd_ns8, @sd_mon_LY, 6, 12, 250000);
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns8, 2, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns8, 3, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns8, 4, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns8, 5, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns8, 6, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns8, 7, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_ns8, 8, '08:00', '21:00');

-- A3. Phu huynh, hoc vien va khoa hoc. Mot phu huynh co nhieu con.
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Nguyễn Thị Lan [SQLDEMO]', NULL, CONCAT(LOWER(@sd_tag),'.parent1@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Phụ huynh', @sd_region1, DEFAULT, 0, NULL);
SET @sd_parent1 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, @sd_parent1, @sd_group5, CONCAT(LOWER(@sd_tag),'_ph01'), '$2y$12$JyjorAFyqYGkvTODM.QfFuqTTgE4w9S1OasxJFPOO.K8M5uBycLGS', 'active', DEFAULT, 0, NULL);
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Trần Văn Hùng [SQLDEMO]', NULL, CONCAT(LOWER(@sd_tag),'.parent2@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Phụ huynh', @sd_region1, DEFAULT, 0, NULL);
SET @sd_parent2 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, @sd_parent2, @sd_group5, CONCAT(LOWER(@sd_tag),'_ph02'), '$2y$12$JyjorAFyqYGkvTODM.QfFuqTTgE4w9S1OasxJFPOO.K8M5uBycLGS', 'active', DEFAULT, 0, NULL);
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Lê Thị Hạnh [SQLDEMO]', NULL, CONCAT(LOWER(@sd_tag),'.parent3@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Phụ huynh', @sd_region1, DEFAULT, 0, NULL);
SET @sd_parent3 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, @sd_parent3, @sd_group5, CONCAT(LOWER(@sd_tag),'_ph03'), '$2y$12$JyjorAFyqYGkvTODM.QfFuqTTgE4w9S1OasxJFPOO.K8M5uBycLGS', 'active', DEFAULT, 0, NULL);
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Phạm Văn Nam [SQLDEMO]', NULL, CONCAT(LOWER(@sd_tag),'.parent4@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Phụ huynh', @sd_region1, DEFAULT, 0, NULL);
SET @sd_parent4 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, @sd_parent4, @sd_group5, CONCAT(LOWER(@sd_tag),'_ph04'), '$2y$12$JyjorAFyqYGkvTODM.QfFuqTTgE4w9S1OasxJFPOO.K8M5uBycLGS', 'active', DEFAULT, 0, NULL);
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Võ Thị Mai [SQLDEMO]', NULL, CONCAT(LOWER(@sd_tag),'.parent5@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Phụ huynh', @sd_region1, DEFAULT, 0, NULL);
SET @sd_parent5 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, @sd_parent5, @sd_group5, CONCAT(LOWER(@sd_tag),'_ph05'), '$2y$12$JyjorAFyqYGkvTODM.QfFuqTTgE4w9S1OasxJFPOO.K8M5uBycLGS', 'active', DEFAULT, 0, NULL);
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_parent1, CONCAT(@sd_tag,'-HV01'), 'Nguyễn Minh An [SQLDEMO]', '2011-05-15', 'nam', 'Trường minh họa', 9, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', @sd_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET @sd_student1 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_parent1, CONCAT(@sd_tag,'-HV02'), 'Nguyễn Ngọc Bảo [SQLDEMO]', '2011-05-15', 'nu', 'Trường minh họa', 9, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', @sd_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET @sd_student2 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_parent2, CONCAT(@sd_tag,'-HV03'), 'Trần Gia Hân [SQLDEMO]', '2008-05-15', 'nam', 'Trường minh họa', 12, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', @sd_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET @sd_student3 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_parent2, CONCAT(@sd_tag,'-HV04'), 'Trần Minh Khang [SQLDEMO]', '2008-05-15', 'nu', 'Trường minh họa', 12, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', @sd_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET @sd_student4 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_parent3, CONCAT(@sd_tag,'-HV05'), 'Lê Ngọc Chi [SQLDEMO]', '2009-05-15', 'nam', 'Trường minh họa', 11, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', @sd_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET @sd_student5 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_parent3, CONCAT(@sd_tag,'-HV06'), 'Lê Hoàng Nam [SQLDEMO]', '2009-05-15', 'nu', 'Trường minh họa', 11, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', @sd_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET @sd_student6 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_parent4, CONCAT(@sd_tag,'-HV07'), 'Phạm Anh Duy [SQLDEMO]', '2014-05-15', 'nam', 'Trường minh họa', 6, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', @sd_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET @sd_student7 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_parent4, CONCAT(@sd_tag,'-HV08'), 'Phạm Bảo Ngọc [SQLDEMO]', '2014-05-15', 'nu', 'Trường minh họa', 6, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', @sd_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET @sd_student8 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_parent5, CONCAT(@sd_tag,'-HV09'), 'Võ Khánh Linh [SQLDEMO]', '2011-05-15', 'nam', 'Trường minh họa', 9, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', @sd_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET @sd_student9 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_parent5, CONCAT(@sd_tag,'-HV10'), 'Võ Minh Tuấn [SQLDEMO]', '2011-05-15', 'nu', 'Trường minh họa', 9, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', @sd_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET @sd_student10 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_parent1, CONCAT(@sd_tag,'-HV11'), 'Nguyễn Hải Yến [SQLDEMO]', '2011-05-15', 'nam', 'Trường minh họa', 9, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', @sd_region1, 'Dữ liệu giả lập chờ xếp lớp', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET @sd_student11 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, @sd_parent2, CONCAT(@sd_tag,'-HV12'), 'Trần Nhật Minh [SQLDEMO]', '2011-05-15', 'nu', 'Trường minh họa', 9, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', @sd_region1, 'Dữ liệu giả lập chưa đăng ký khóa', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET @sd_student12 = LAST_INSERT_ID();
INSERT INTO `khoa_hoc` (`id`, `ma`, `ten`, `mo_ta`, `hoc_phi`, `is_active`, `created_at`)
VALUES (NULL, CONCAT(@sd_tag,'-KH01'), 'Toán 9 cơ bản học kỳ 1 [SQLDEMO]', 'Thu trọn khóa học. Lịch học bù do giáo vụ xếp theo thỏa thuận.', 1600000, 1, DEFAULT);
SET @sd_course1 = LAST_INSERT_ID();
INSERT INTO `khoa_hoc` (`id`, `ma`, `ten`, `mo_ta`, `hoc_phi`, `is_active`, `created_at`)
VALUES (NULL, CONCAT(@sd_tag,'-KH02'), 'Toán 12 ôn luyện chuyên [SQLDEMO]', 'Thu trọn khóa học. Lịch học bù do giáo vụ xếp theo thỏa thuận.', 1700000, 1, DEFAULT);
SET @sd_course2 = LAST_INSERT_ID();
INSERT INTO `khoa_hoc` (`id`, `ma`, `ten`, `mo_ta`, `hoc_phi`, `is_active`, `created_at`)
VALUES (NULL, CONCAT(@sd_tag,'-KH03'), 'Vật lý 11 nâng cao học kỳ 2 [SQLDEMO]', 'Thu trọn khóa học. Lịch học bù do giáo vụ xếp theo thỏa thuận.', 1800000, 1, DEFAULT);
SET @sd_course3 = LAST_INSERT_ID();
INSERT INTO `khoa_hoc` (`id`, `ma`, `ten`, `mo_ta`, `hoc_phi`, `is_active`, `created_at`)
VALUES (NULL, CONCAT(@sd_tag,'-KH04'), 'Tiếng Anh 6 nền tảng cả năm [SQLDEMO]', 'Thu trọn khóa học. Lịch học bù do giáo vụ xếp theo thỏa thuận.', 1900000, 1, DEFAULT);
SET @sd_course4 = LAST_INSERT_ID();
INSERT INTO `khoa_hoc` (`id`, `ma`, `ten`, `mo_ta`, `hoc_phi`, `is_active`, `created_at`)
VALUES (NULL, CONCAT(@sd_tag,'-KH05'), 'Ngữ văn 9 luyện đề [SQLDEMO]', 'Thu trọn khóa học. Lịch học bù do giáo vụ xếp theo thỏa thuận.', 2000000, 1, DEFAULT);
SET @sd_course5 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, @sd_course1, @sd_student1, 1600000, 'dang_hoc', TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)));
SET @sd_registration1 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, @sd_course1, @sd_student2, 1600000, 'dang_hoc', TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)));
SET @sd_registration2 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, @sd_course2, @sd_student3, 1700000, 'dang_hoc', TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)));
SET @sd_registration3 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, @sd_course2, @sd_student4, 1700000, 'dang_hoc', TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)));
SET @sd_registration4 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, @sd_course3, @sd_student5, 1800000, 'dang_hoc', TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)));
SET @sd_registration5 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, @sd_course3, @sd_student6, 1800000, 'dang_hoc', TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)));
SET @sd_registration6 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, @sd_course4, @sd_student7, 1900000, 'dang_hoc', TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)));
SET @sd_registration7 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, @sd_course4, @sd_student8, 1900000, 'dang_hoc', TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)));
SET @sd_registration8 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, @sd_course5, @sd_student9, 2000000, 'dang_hoc', TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)));
SET @sd_registration9 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, @sd_course5, @sd_student10, 2000000, 'dang_hoc', TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)));
SET @sd_registration10 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, @sd_course1, @sd_student11, 1600000, 'cho_xep_lop', TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)));
SET @sd_registration11 = LAST_INSERT_ID();

-- A4. Nhu cau, de xuat, lop va xep lop. Admin xep lop doc lap hoc phi.
INSERT INTO `yeu_cau_hoc` (`id`, `ma_yc`, `hoc_vien_id`, `ho_ten_lien_he`, `sdt_lien_he`, `email_lien_he`, `mon_hoc_id`, `cap_lop_id`, `loai_lop_mong_muon`, `co_so_mong_muon_id`, `hinh_thuc`, `khu_vuc_id`, `dia_chi_hoc`, `ngan_sach_min`, `ngan_sach_max`, `muc_tieu`, `ghi_chu`, `nguon`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `nguoi_gui_id`, `don_vi_ngan_sach`)
VALUES (NULL, CONCAT(@sd_tag,'-YC01'), @sd_student1, 'Phụ huynh minh họa', '0900000000', CONCAT(LOWER(@sd_tag),'.parent1@example.test'), @sd_mon_TOAN, 9, 'nhom', @sd_campus1, 'offline', @sd_region1, 'Tại cơ sở EduCenter', 1000000, 3000000, 'Củng cố kiến thức và luyện bài tập', 'Nhu cầu mẫu', 'truc_tiep', 'da_chot', @sd_employee, DEFAULT, DEFAULT, @sd_giaovu, 'theo_khoa');
SET @sd_request1 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_lich` (`id`, `yeu_cau_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_request1, WEEKDAY(@sd_next)+2, '10:00', '12:00');
INSERT INTO `de_xuat_nhan_su` (`id`, `yeu_cau_id`, `nhan_su_id`, `nhan_vien_id`, `hoc_phi_de_xuat`, `tt_nhan_su`, `tt_phu_huynh`, `ghi_chu`, `created_at`, `updated_at`, `cach_thu_phi_de_xuat`, `nhan_su_phan_hoi_luc`, `phu_huynh_phan_hoi_luc`, `phu_huynh_phan_hoi_boi`, `ly_do_tu_choi_nhan_su`, `ly_do_tu_choi_phu_huynh`)
VALUES (NULL, @sd_request1, @sd_ns1, @sd_employee, 1600000, 'dong_y', 'dong_y', 'Hai bên đã xác nhận', DEFAULT, DEFAULT, 'theo_khoa', TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)), TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)), @sd_parent1, NULL, NULL);
SET @sd_proposal1 = LAST_INSERT_ID();
INSERT INTO `lop_hoc` (`id`, `ma_lop`, `ten_lop`, `loai_lop`, `de_xuat_id`, `mon_hoc_id`, `cap_lop_id`, `nguoi_day_chinh_id`, `co_so_id`, `phong_hoc`, `hinh_thuc`, `dia_diem`, `link_hoc`, `khu_vuc_id`, `si_so_toi_thieu`, `si_so_toi_da`, `cach_thu_phi`, `hoc_phi_buoi`, `hoc_phi_khoa`, `muc_tra_chinh`, `muc_tra_tro_giang`, `cach_tinh_luong`, `so_buoi_du_kien`, `ngay_bat_dau`, `ngay_ket_thuc`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `deleted_at`, `hoc_phi_thang`, `khoa_hoc_id`)
VALUES (NULL, CONCAT(@sd_tag,'-LOP01'), 'Toán 9 cơ bản học kỳ 1 [SQLDEMO]', 'nhom', @sd_proposal1, @sd_mon_TOAN, 9, @sd_ns1, @sd_campus1, 'P01', 'offline', 'Cơ sở EduCenter', NULL, @sd_region1, 1, 12, 'theo_khoa', 0, 1600000, 0, 0, 'theo_gio', 24, @sd_past, DATE_ADD(CURDATE(),INTERVAL 90 DAY), 'dang_hoc', @sd_employee, DEFAULT, DEFAULT, NULL, NULL, @sd_course1);
SET @sd_class1 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, @sd_class1, @sd_student1, @sd_past, NULL, 'dang_hoc', DEFAULT, NULL, @sd_registration1, @sd_admin, TIMESTAMP(@sd_past));
SET @sd_enrollment1 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, @sd_class1, @sd_student2, @sd_past, NULL, 'dang_hoc', DEFAULT, NULL, @sd_registration2, @sd_admin, TIMESTAMP(@sd_past));
SET @sd_enrollment2 = LAST_INSERT_ID();
INSERT INTO `lich_hoc` (`id`, `lop_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `hieu_luc_tu`, `hieu_luc_den`, `is_active`)
VALUES (NULL, @sd_class1, WEEKDAY(@sd_next)+2, '10:00', '12:00', 'P01', @sd_past, DATE_ADD(CURDATE(),INTERVAL 90 DAY), 1);
SET @sd_weekly1 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_hoc` (`id`, `ma_yc`, `hoc_vien_id`, `ho_ten_lien_he`, `sdt_lien_he`, `email_lien_he`, `mon_hoc_id`, `cap_lop_id`, `loai_lop_mong_muon`, `co_so_mong_muon_id`, `hinh_thuc`, `khu_vuc_id`, `dia_chi_hoc`, `ngan_sach_min`, `ngan_sach_max`, `muc_tieu`, `ghi_chu`, `nguon`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `nguoi_gui_id`, `don_vi_ngan_sach`)
VALUES (NULL, CONCAT(@sd_tag,'-YC02'), @sd_student3, 'Phụ huynh minh họa', '0900000000', CONCAT(LOWER(@sd_tag),'.parent2@example.test'), @sd_mon_TOAN, 12, 'nhom', @sd_campus1, 'offline', @sd_region1, 'Tại cơ sở EduCenter', 1000000, 3000000, 'Củng cố kiến thức và luyện bài tập', 'Nhu cầu mẫu', 'truc_tiep', 'da_chot', @sd_employee, DEFAULT, DEFAULT, @sd_giaovu, 'theo_khoa');
SET @sd_request2 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_lich` (`id`, `yeu_cau_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_request2, WEEKDAY(@sd_next)+2, '10:00', '12:00');
INSERT INTO `de_xuat_nhan_su` (`id`, `yeu_cau_id`, `nhan_su_id`, `nhan_vien_id`, `hoc_phi_de_xuat`, `tt_nhan_su`, `tt_phu_huynh`, `ghi_chu`, `created_at`, `updated_at`, `cach_thu_phi_de_xuat`, `nhan_su_phan_hoi_luc`, `phu_huynh_phan_hoi_luc`, `phu_huynh_phan_hoi_boi`, `ly_do_tu_choi_nhan_su`, `ly_do_tu_choi_phu_huynh`)
VALUES (NULL, @sd_request2, @sd_ns2, @sd_employee, 1700000, 'dong_y', 'dong_y', 'Hai bên đã xác nhận', DEFAULT, DEFAULT, 'theo_khoa', TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)), TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)), @sd_parent2, NULL, NULL);
SET @sd_proposal2 = LAST_INSERT_ID();
INSERT INTO `lop_hoc` (`id`, `ma_lop`, `ten_lop`, `loai_lop`, `de_xuat_id`, `mon_hoc_id`, `cap_lop_id`, `nguoi_day_chinh_id`, `co_so_id`, `phong_hoc`, `hinh_thuc`, `dia_diem`, `link_hoc`, `khu_vuc_id`, `si_so_toi_thieu`, `si_so_toi_da`, `cach_thu_phi`, `hoc_phi_buoi`, `hoc_phi_khoa`, `muc_tra_chinh`, `muc_tra_tro_giang`, `cach_tinh_luong`, `so_buoi_du_kien`, `ngay_bat_dau`, `ngay_ket_thuc`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `deleted_at`, `hoc_phi_thang`, `khoa_hoc_id`)
VALUES (NULL, CONCAT(@sd_tag,'-LOP02'), 'Toán 12 ôn luyện chuyên [SQLDEMO]', 'nhom', @sd_proposal2, @sd_mon_TOAN, 12, @sd_ns2, @sd_campus1, 'P02', 'offline', 'Cơ sở EduCenter', NULL, @sd_region1, 1, 12, 'theo_khoa', 0, 1700000, 0, 0, 'theo_gio', 24, @sd_past, DATE_ADD(CURDATE(),INTERVAL 90 DAY), 'dang_hoc', @sd_employee, DEFAULT, DEFAULT, NULL, NULL, @sd_course2);
SET @sd_class2 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, @sd_class2, @sd_student3, @sd_past, NULL, 'dang_hoc', DEFAULT, NULL, @sd_registration3, @sd_admin, TIMESTAMP(@sd_past));
SET @sd_enrollment3 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, @sd_class2, @sd_student4, @sd_past, NULL, 'dang_hoc', DEFAULT, NULL, @sd_registration4, @sd_admin, TIMESTAMP(@sd_past));
SET @sd_enrollment4 = LAST_INSERT_ID();
INSERT INTO `lich_hoc` (`id`, `lop_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `hieu_luc_tu`, `hieu_luc_den`, `is_active`)
VALUES (NULL, @sd_class2, WEEKDAY(@sd_next)+2, '10:00', '12:00', 'P02', @sd_past, DATE_ADD(CURDATE(),INTERVAL 90 DAY), 1);
SET @sd_weekly2 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_hoc` (`id`, `ma_yc`, `hoc_vien_id`, `ho_ten_lien_he`, `sdt_lien_he`, `email_lien_he`, `mon_hoc_id`, `cap_lop_id`, `loai_lop_mong_muon`, `co_so_mong_muon_id`, `hinh_thuc`, `khu_vuc_id`, `dia_chi_hoc`, `ngan_sach_min`, `ngan_sach_max`, `muc_tieu`, `ghi_chu`, `nguon`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `nguoi_gui_id`, `don_vi_ngan_sach`)
VALUES (NULL, CONCAT(@sd_tag,'-YC03'), @sd_student5, 'Phụ huynh minh họa', '0900000000', CONCAT(LOWER(@sd_tag),'.parent3@example.test'), @sd_mon_LY, 11, 'nhom', @sd_campus1, 'offline', @sd_region1, 'Tại cơ sở EduCenter', 1000000, 3000000, 'Củng cố kiến thức và luyện bài tập', 'Nhu cầu mẫu', 'truc_tiep', 'da_chot', @sd_employee, DEFAULT, DEFAULT, @sd_giaovu, 'theo_khoa');
SET @sd_request3 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_lich` (`id`, `yeu_cau_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_request3, WEEKDAY(@sd_next)+2, '10:00', '12:00');
INSERT INTO `de_xuat_nhan_su` (`id`, `yeu_cau_id`, `nhan_su_id`, `nhan_vien_id`, `hoc_phi_de_xuat`, `tt_nhan_su`, `tt_phu_huynh`, `ghi_chu`, `created_at`, `updated_at`, `cach_thu_phi_de_xuat`, `nhan_su_phan_hoi_luc`, `phu_huynh_phan_hoi_luc`, `phu_huynh_phan_hoi_boi`, `ly_do_tu_choi_nhan_su`, `ly_do_tu_choi_phu_huynh`)
VALUES (NULL, @sd_request3, @sd_ns3, @sd_employee, 1800000, 'dong_y', 'dong_y', 'Hai bên đã xác nhận', DEFAULT, DEFAULT, 'theo_khoa', TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)), TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)), @sd_parent3, NULL, NULL);
SET @sd_proposal3 = LAST_INSERT_ID();
INSERT INTO `lop_hoc` (`id`, `ma_lop`, `ten_lop`, `loai_lop`, `de_xuat_id`, `mon_hoc_id`, `cap_lop_id`, `nguoi_day_chinh_id`, `co_so_id`, `phong_hoc`, `hinh_thuc`, `dia_diem`, `link_hoc`, `khu_vuc_id`, `si_so_toi_thieu`, `si_so_toi_da`, `cach_thu_phi`, `hoc_phi_buoi`, `hoc_phi_khoa`, `muc_tra_chinh`, `muc_tra_tro_giang`, `cach_tinh_luong`, `so_buoi_du_kien`, `ngay_bat_dau`, `ngay_ket_thuc`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `deleted_at`, `hoc_phi_thang`, `khoa_hoc_id`)
VALUES (NULL, CONCAT(@sd_tag,'-LOP03'), 'Vật lý 11 nâng cao học kỳ 2 [SQLDEMO]', 'nhom', @sd_proposal3, @sd_mon_LY, 11, @sd_ns3, @sd_campus1, 'P03', 'offline', 'Cơ sở EduCenter', NULL, @sd_region1, 1, 12, 'theo_khoa', 0, 1800000, 0, 0, 'theo_gio', 24, @sd_past, DATE_ADD(CURDATE(),INTERVAL 90 DAY), 'dang_hoc', @sd_employee, DEFAULT, DEFAULT, NULL, NULL, @sd_course3);
SET @sd_class3 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, @sd_class3, @sd_student5, @sd_past, NULL, 'dang_hoc', DEFAULT, NULL, @sd_registration5, @sd_admin, TIMESTAMP(@sd_past));
SET @sd_enrollment5 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, @sd_class3, @sd_student6, @sd_past, NULL, 'dang_hoc', DEFAULT, NULL, @sd_registration6, @sd_admin, TIMESTAMP(@sd_past));
SET @sd_enrollment6 = LAST_INSERT_ID();
INSERT INTO `lich_hoc` (`id`, `lop_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `hieu_luc_tu`, `hieu_luc_den`, `is_active`)
VALUES (NULL, @sd_class3, WEEKDAY(@sd_next)+2, '10:00', '12:00', 'P03', @sd_past, DATE_ADD(CURDATE(),INTERVAL 90 DAY), 1);
SET @sd_weekly3 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_hoc` (`id`, `ma_yc`, `hoc_vien_id`, `ho_ten_lien_he`, `sdt_lien_he`, `email_lien_he`, `mon_hoc_id`, `cap_lop_id`, `loai_lop_mong_muon`, `co_so_mong_muon_id`, `hinh_thuc`, `khu_vuc_id`, `dia_chi_hoc`, `ngan_sach_min`, `ngan_sach_max`, `muc_tieu`, `ghi_chu`, `nguon`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `nguoi_gui_id`, `don_vi_ngan_sach`)
VALUES (NULL, CONCAT(@sd_tag,'-YC04'), @sd_student7, 'Phụ huynh minh họa', '0900000000', CONCAT(LOWER(@sd_tag),'.parent4@example.test'), @sd_mon_ANH, 6, 'nhom', @sd_campus2, 'offline', @sd_region1, 'Tại cơ sở EduCenter', 1000000, 3000000, 'Củng cố kiến thức và luyện bài tập', 'Nhu cầu mẫu', 'truc_tiep', 'da_chot', @sd_employee, DEFAULT, DEFAULT, @sd_giaovu, 'theo_khoa');
SET @sd_request4 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_lich` (`id`, `yeu_cau_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_request4, WEEKDAY(@sd_next)+2, '10:00', '12:00');
INSERT INTO `de_xuat_nhan_su` (`id`, `yeu_cau_id`, `nhan_su_id`, `nhan_vien_id`, `hoc_phi_de_xuat`, `tt_nhan_su`, `tt_phu_huynh`, `ghi_chu`, `created_at`, `updated_at`, `cach_thu_phi_de_xuat`, `nhan_su_phan_hoi_luc`, `phu_huynh_phan_hoi_luc`, `phu_huynh_phan_hoi_boi`, `ly_do_tu_choi_nhan_su`, `ly_do_tu_choi_phu_huynh`)
VALUES (NULL, @sd_request4, @sd_ns4, @sd_employee, 1900000, 'dong_y', 'dong_y', 'Hai bên đã xác nhận', DEFAULT, DEFAULT, 'theo_khoa', TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)), TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)), @sd_parent4, NULL, NULL);
SET @sd_proposal4 = LAST_INSERT_ID();
INSERT INTO `lop_hoc` (`id`, `ma_lop`, `ten_lop`, `loai_lop`, `de_xuat_id`, `mon_hoc_id`, `cap_lop_id`, `nguoi_day_chinh_id`, `co_so_id`, `phong_hoc`, `hinh_thuc`, `dia_diem`, `link_hoc`, `khu_vuc_id`, `si_so_toi_thieu`, `si_so_toi_da`, `cach_thu_phi`, `hoc_phi_buoi`, `hoc_phi_khoa`, `muc_tra_chinh`, `muc_tra_tro_giang`, `cach_tinh_luong`, `so_buoi_du_kien`, `ngay_bat_dau`, `ngay_ket_thuc`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `deleted_at`, `hoc_phi_thang`, `khoa_hoc_id`)
VALUES (NULL, CONCAT(@sd_tag,'-LOP04'), 'Tiếng Anh 6 nền tảng cả năm [SQLDEMO]', 'nhom', @sd_proposal4, @sd_mon_ANH, 6, @sd_ns4, @sd_campus2, 'P04', 'offline', 'Cơ sở EduCenter', NULL, @sd_region1, 1, 12, 'theo_khoa', 0, 1900000, 0, 0, 'theo_gio', 24, @sd_past, DATE_ADD(CURDATE(),INTERVAL 90 DAY), 'dang_hoc', @sd_employee, DEFAULT, DEFAULT, NULL, NULL, @sd_course4);
SET @sd_class4 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, @sd_class4, @sd_student7, @sd_past, NULL, 'dang_hoc', DEFAULT, NULL, @sd_registration7, @sd_admin, TIMESTAMP(@sd_past));
SET @sd_enrollment7 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, @sd_class4, @sd_student8, @sd_past, NULL, 'dang_hoc', DEFAULT, NULL, @sd_registration8, @sd_admin, TIMESTAMP(@sd_past));
SET @sd_enrollment8 = LAST_INSERT_ID();
INSERT INTO `lich_hoc` (`id`, `lop_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `hieu_luc_tu`, `hieu_luc_den`, `is_active`)
VALUES (NULL, @sd_class4, WEEKDAY(@sd_next)+2, '10:00', '12:00', 'P04', @sd_past, DATE_ADD(CURDATE(),INTERVAL 90 DAY), 1);
SET @sd_weekly4 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_hoc` (`id`, `ma_yc`, `hoc_vien_id`, `ho_ten_lien_he`, `sdt_lien_he`, `email_lien_he`, `mon_hoc_id`, `cap_lop_id`, `loai_lop_mong_muon`, `co_so_mong_muon_id`, `hinh_thuc`, `khu_vuc_id`, `dia_chi_hoc`, `ngan_sach_min`, `ngan_sach_max`, `muc_tieu`, `ghi_chu`, `nguon`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `nguoi_gui_id`, `don_vi_ngan_sach`)
VALUES (NULL, CONCAT(@sd_tag,'-YC05'), @sd_student9, 'Phụ huynh minh họa', '0900000000', CONCAT(LOWER(@sd_tag),'.parent5@example.test'), @sd_mon_VAN, 9, 'nhom', @sd_campus2, 'offline', @sd_region1, 'Tại cơ sở EduCenter', 1000000, 3000000, 'Củng cố kiến thức và luyện bài tập', 'Nhu cầu mẫu', 'truc_tiep', 'da_chot', @sd_employee, DEFAULT, DEFAULT, @sd_giaovu, 'theo_khoa');
SET @sd_request5 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_lich` (`id`, `yeu_cau_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, @sd_request5, WEEKDAY(@sd_next)+2, '10:00', '12:00');
INSERT INTO `de_xuat_nhan_su` (`id`, `yeu_cau_id`, `nhan_su_id`, `nhan_vien_id`, `hoc_phi_de_xuat`, `tt_nhan_su`, `tt_phu_huynh`, `ghi_chu`, `created_at`, `updated_at`, `cach_thu_phi_de_xuat`, `nhan_su_phan_hoi_luc`, `phu_huynh_phan_hoi_luc`, `phu_huynh_phan_hoi_boi`, `ly_do_tu_choi_nhan_su`, `ly_do_tu_choi_phu_huynh`)
VALUES (NULL, @sd_request5, @sd_ns5, @sd_employee, 2000000, 'dong_y', 'dong_y', 'Hai bên đã xác nhận', DEFAULT, DEFAULT, 'theo_khoa', TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)), TIMESTAMP(DATE_SUB(@sd_past, INTERVAL 1 DAY)), @sd_parent5, NULL, NULL);
SET @sd_proposal5 = LAST_INSERT_ID();
INSERT INTO `lop_hoc` (`id`, `ma_lop`, `ten_lop`, `loai_lop`, `de_xuat_id`, `mon_hoc_id`, `cap_lop_id`, `nguoi_day_chinh_id`, `co_so_id`, `phong_hoc`, `hinh_thuc`, `dia_diem`, `link_hoc`, `khu_vuc_id`, `si_so_toi_thieu`, `si_so_toi_da`, `cach_thu_phi`, `hoc_phi_buoi`, `hoc_phi_khoa`, `muc_tra_chinh`, `muc_tra_tro_giang`, `cach_tinh_luong`, `so_buoi_du_kien`, `ngay_bat_dau`, `ngay_ket_thuc`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `deleted_at`, `hoc_phi_thang`, `khoa_hoc_id`)
VALUES (NULL, CONCAT(@sd_tag,'-LOP05'), 'Ngữ văn 9 luyện đề [SQLDEMO]', 'nhom', @sd_proposal5, @sd_mon_VAN, 9, @sd_ns5, @sd_campus2, 'P05', 'offline', 'Cơ sở EduCenter', NULL, @sd_region1, 1, 12, 'theo_khoa', 0, 2000000, 0, 0, 'theo_gio', 24, @sd_past, DATE_ADD(CURDATE(),INTERVAL 90 DAY), 'dang_hoc', @sd_employee, DEFAULT, DEFAULT, NULL, NULL, @sd_course5);
SET @sd_class5 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, @sd_class5, @sd_student9, @sd_past, NULL, 'dang_hoc', DEFAULT, NULL, @sd_registration9, @sd_admin, TIMESTAMP(@sd_past));
SET @sd_enrollment9 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, @sd_class5, @sd_student10, @sd_past, NULL, 'dang_hoc', DEFAULT, NULL, @sd_registration10, @sd_admin, TIMESTAMP(@sd_past));
SET @sd_enrollment10 = LAST_INSERT_ID();
INSERT INTO `lich_hoc` (`id`, `lop_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `hieu_luc_tu`, `hieu_luc_den`, `is_active`)
VALUES (NULL, @sd_class5, WEEKDAY(@sd_next)+2, '10:00', '12:00', 'P05', @sd_past, DATE_ADD(CURDATE(),INTERVAL 90 DAY), 1);
SET @sd_weekly5 = LAST_INSERT_ID();

-- A5. Moi giao vien co ca 10-12 va 14-16 ngay mai, phong khac nhau.
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class1, NULL, 'thuong', NULL, @sd_past, '18:00', '20:00', 'P01', 'Ôn tập kiến thức nền', 'Ôn lý thuyết và chữa bài tập', NULL, 'da_day', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus1, 'ca_lop');
SET @sd_past1 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_past1, @sd_teacher1, 'day_chinh', 'da_day', 0, NULL, @sd_giaovu, NULL, NULL, NULL, TIMESTAMP(@sd_past,'18:10:00'), NULL, NULL, 0, 'Bản ghi minh họa giờ server', TIMESTAMP(@sd_past,'20:05:00'), DEFAULT, 'hop_le', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET @sd_assignment_past1 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class1, @sd_weekly1, 'thuong', NULL, @sd_next, '10:00', '12:00', 'P01', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus1, 'ca_lop');
SET @sd_morning1 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_morning1, @sd_teacher1, 'day_chinh', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET @sd_assignment_morning1 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class1, NULL, 'thuong', NULL, @sd_next, '14:00', '16:00', 'P01', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus1, 'ca_lop');
SET @sd_afternoon1 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_afternoon1, @sd_teacher1, 'day_chinh', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET @sd_assignment_afternoon1 = LAST_INSERT_ID();
INSERT INTO `don_xin_nghi` (`id`, `buoi_hoc_id`, `doi_tuong`, `hoc_vien_id`, `nguoi_gui_id`, `ly_do`, `trang_thai`, `nguoi_duyet_id`, `duyet_luc`, `ly_do_tu_choi`, `buoi_bu_id`, `created_at`)
VALUES (NULL, @sd_past1, 'hoc_vien', @sd_student2, @sd_parent1, 'Gia đình đã trao đổi với giáo vụ', 'da_duyet', @sd_giaovu, TIMESTAMP(@sd_past), NULL, NULL, DEFAULT);
SET @sd_leave1 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class1, NULL, 'bu', @sd_past1, @sd_makeup_day, '18:00', '20:00', 'P01', 'Học bù theo lịch đã thỏa thuận', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus1, 'ca_nhan');
SET @sd_makeup1 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_makeup1, @sd_teacher1, 'day_chinh', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `hoc_bu_hoc_vien` (`id`, `buoi_bu_id`, `hoc_vien_id`, `don_xin_nghi_id`, `xep_boi`, `ghi_chu`)
VALUES (NULL, @sd_makeup1, @sd_student2, @sd_leave1, @sd_giaovu, 'Phụ huynh và giáo vụ đã thống nhất ngày học');
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class2, NULL, 'thuong', NULL, @sd_past, '18:00', '20:00', 'P02', 'Ôn tập kiến thức nền', 'Ôn lý thuyết và chữa bài tập', NULL, 'da_day', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus1, 'ca_lop');
SET @sd_past2 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_past2, @sd_teacher2, 'day_chinh', 'da_day', 0, NULL, @sd_giaovu, NULL, NULL, NULL, TIMESTAMP(@sd_past,'18:10:00'), NULL, NULL, 0, 'Bản ghi minh họa giờ server', TIMESTAMP(@sd_past,'19:30:00'), DEFAULT, 'hop_le', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET @sd_assignment_past2 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class2, @sd_weekly2, 'thuong', NULL, @sd_next, '10:00', '12:00', 'P02', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus1, 'ca_lop');
SET @sd_morning2 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_morning2, @sd_teacher2, 'day_chinh', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET @sd_assignment_morning2 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class2, NULL, 'thuong', NULL, @sd_next, '14:00', '16:00', 'P02', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus1, 'ca_lop');
SET @sd_afternoon2 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_afternoon2, @sd_teacher2, 'day_chinh', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET @sd_assignment_afternoon2 = LAST_INSERT_ID();
INSERT INTO `don_xin_nghi` (`id`, `buoi_hoc_id`, `doi_tuong`, `hoc_vien_id`, `nguoi_gui_id`, `ly_do`, `trang_thai`, `nguoi_duyet_id`, `duyet_luc`, `ly_do_tu_choi`, `buoi_bu_id`, `created_at`)
VALUES (NULL, @sd_past2, 'hoc_vien', @sd_student4, @sd_parent2, 'Gia đình đã trao đổi với giáo vụ', 'da_duyet', @sd_giaovu, TIMESTAMP(@sd_past), NULL, NULL, DEFAULT);
SET @sd_leave2 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class2, NULL, 'bu', @sd_past2, @sd_makeup_day, '18:00', '20:00', 'P02', 'Học bù theo lịch đã thỏa thuận', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus1, 'ca_nhan');
SET @sd_makeup2 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_makeup2, @sd_teacher2, 'day_chinh', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `hoc_bu_hoc_vien` (`id`, `buoi_bu_id`, `hoc_vien_id`, `don_xin_nghi_id`, `xep_boi`, `ghi_chu`)
VALUES (NULL, @sd_makeup2, @sd_student4, @sd_leave2, @sd_giaovu, 'Phụ huynh và giáo vụ đã thống nhất ngày học');
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class3, NULL, 'thuong', NULL, @sd_past, '18:00', '20:00', 'P03', 'Ôn tập kiến thức nền', 'Ôn lý thuyết và chữa bài tập', NULL, 'da_day', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus1, 'ca_lop');
SET @sd_past3 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_past3, @sd_teacher3, 'day_chinh', 'da_day', 0, NULL, @sd_giaovu, NULL, NULL, NULL, TIMESTAMP(@sd_past,'18:10:00'), NULL, NULL, 0, 'Bản ghi minh họa giờ server', TIMESTAMP(@sd_past,'20:05:00'), DEFAULT, 'hop_le', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET @sd_assignment_past3 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class3, @sd_weekly3, 'thuong', NULL, @sd_next, '10:00', '12:00', 'P03', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus1, 'ca_lop');
SET @sd_morning3 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_morning3, @sd_teacher3, 'day_chinh', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET @sd_assignment_morning3 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class3, NULL, 'thuong', NULL, @sd_next, '14:00', '16:00', 'P03', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus1, 'ca_lop');
SET @sd_afternoon3 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_afternoon3, @sd_teacher3, 'day_chinh', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET @sd_assignment_afternoon3 = LAST_INSERT_ID();
INSERT INTO `don_xin_nghi` (`id`, `buoi_hoc_id`, `doi_tuong`, `hoc_vien_id`, `nguoi_gui_id`, `ly_do`, `trang_thai`, `nguoi_duyet_id`, `duyet_luc`, `ly_do_tu_choi`, `buoi_bu_id`, `created_at`)
VALUES (NULL, @sd_past3, 'hoc_vien', @sd_student6, @sd_parent3, 'Gia đình đã trao đổi với giáo vụ', 'da_duyet', @sd_giaovu, TIMESTAMP(@sd_past), NULL, NULL, DEFAULT);
SET @sd_leave3 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class3, NULL, 'bu', @sd_past3, @sd_makeup_day, '18:00', '20:00', 'P03', 'Học bù theo lịch đã thỏa thuận', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus1, 'ca_nhan');
SET @sd_makeup3 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_makeup3, @sd_teacher3, 'day_chinh', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `hoc_bu_hoc_vien` (`id`, `buoi_bu_id`, `hoc_vien_id`, `don_xin_nghi_id`, `xep_boi`, `ghi_chu`)
VALUES (NULL, @sd_makeup3, @sd_student6, @sd_leave3, @sd_giaovu, 'Phụ huynh và giáo vụ đã thống nhất ngày học');
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class4, NULL, 'thuong', NULL, @sd_past, '18:00', '20:00', 'P04', 'Ôn tập kiến thức nền', 'Ôn lý thuyết và chữa bài tập', NULL, 'da_day', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus2, 'ca_lop');
SET @sd_past4 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_past4, @sd_teacher4, 'day_chinh', 'da_day', 0, NULL, @sd_giaovu, NULL, NULL, NULL, TIMESTAMP(@sd_past,'18:10:00'), NULL, NULL, 0, 'Bản ghi minh họa giờ server', TIMESTAMP(@sd_past,'20:05:00'), DEFAULT, 'hop_le', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET @sd_assignment_past4 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class4, @sd_weekly4, 'thuong', NULL, @sd_next, '10:00', '12:00', 'P04', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus2, 'ca_lop');
SET @sd_morning4 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_morning4, @sd_teacher4, 'day_chinh', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET @sd_assignment_morning4 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class4, NULL, 'thuong', NULL, @sd_next, '14:00', '16:00', 'P04', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus2, 'ca_lop');
SET @sd_afternoon4 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_afternoon4, @sd_teacher4, 'day_chinh', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET @sd_assignment_afternoon4 = LAST_INSERT_ID();
INSERT INTO `don_xin_nghi` (`id`, `buoi_hoc_id`, `doi_tuong`, `hoc_vien_id`, `nguoi_gui_id`, `ly_do`, `trang_thai`, `nguoi_duyet_id`, `duyet_luc`, `ly_do_tu_choi`, `buoi_bu_id`, `created_at`)
VALUES (NULL, @sd_past4, 'hoc_vien', @sd_student8, @sd_parent4, 'Gia đình đã trao đổi với giáo vụ', 'da_duyet', @sd_giaovu, TIMESTAMP(@sd_past), NULL, NULL, DEFAULT);
SET @sd_leave4 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class4, NULL, 'bu', @sd_past4, @sd_makeup_day, '18:00', '20:00', 'P04', 'Học bù theo lịch đã thỏa thuận', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus2, 'ca_nhan');
SET @sd_makeup4 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_makeup4, @sd_teacher4, 'day_chinh', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `hoc_bu_hoc_vien` (`id`, `buoi_bu_id`, `hoc_vien_id`, `don_xin_nghi_id`, `xep_boi`, `ghi_chu`)
VALUES (NULL, @sd_makeup4, @sd_student8, @sd_leave4, @sd_giaovu, 'Phụ huynh và giáo vụ đã thống nhất ngày học');
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class5, NULL, 'thuong', NULL, @sd_past, '18:00', '20:00', 'P05', 'Ôn tập kiến thức nền', 'Ôn lý thuyết và chữa bài tập', NULL, 'da_day', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus2, 'ca_lop');
SET @sd_past5 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_past5, @sd_teacher5, 'day_chinh', 'da_day', 0, NULL, @sd_giaovu, NULL, NULL, NULL, TIMESTAMP(@sd_past,'18:10:00'), NULL, NULL, 0, 'Bản ghi minh họa giờ server', TIMESTAMP(@sd_past,'20:05:00'), DEFAULT, 'hop_le', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET @sd_assignment_past5 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class5, @sd_weekly5, 'thuong', NULL, @sd_next, '10:00', '12:00', 'P05', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus2, 'ca_lop');
SET @sd_morning5 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_morning5, @sd_teacher5, 'day_chinh', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET @sd_assignment_morning5 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class5, NULL, 'thuong', NULL, @sd_next, '14:00', '16:00', 'P05', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus2, 'ca_lop');
SET @sd_afternoon5 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_afternoon5, @sd_teacher5, 'day_chinh', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET @sd_assignment_afternoon5 = LAST_INSERT_ID();
INSERT INTO `don_xin_nghi` (`id`, `buoi_hoc_id`, `doi_tuong`, `hoc_vien_id`, `nguoi_gui_id`, `ly_do`, `trang_thai`, `nguoi_duyet_id`, `duyet_luc`, `ly_do_tu_choi`, `buoi_bu_id`, `created_at`)
VALUES (NULL, @sd_past5, 'hoc_vien', @sd_student10, @sd_parent5, 'Gia đình đã trao đổi với giáo vụ', 'da_duyet', @sd_giaovu, TIMESTAMP(@sd_past), NULL, NULL, DEFAULT);
SET @sd_leave5 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, @sd_class5, NULL, 'bu', @sd_past5, @sd_makeup_day, '18:00', '20:00', 'P05', 'Học bù theo lịch đã thỏa thuận', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, @sd_campus2, 'ca_nhan');
SET @sd_makeup5 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_makeup5, @sd_teacher5, 'day_chinh', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `hoc_bu_hoc_vien` (`id`, `buoi_bu_id`, `hoc_vien_id`, `don_xin_nghi_id`, `xep_boi`, `ghi_chu`)
VALUES (NULL, @sd_makeup5, @sd_student10, @sd_leave5, @sd_giaovu, 'Phụ huynh và giáo vụ đã thống nhất ngày học');
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_morning1, @sd_teacher7, 'tro_giang', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_afternoon1, @sd_teacher7, 'tro_giang', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_morning2, @sd_teacher8, 'tro_giang', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, @sd_afternoon2, @sd_teacher8, 'tro_giang', 'da_phan_cong', 0, NULL, @sd_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `yeu_cau_nghi_lop` (`id`, `buoi_hoc_id`, `nguoi_gui_id`, `ly_do`, `trang_thai`, `nguoi_duyet_id`, `duyet_luc`, `ghi_chu`)
VALUES (NULL, @sd_morning1, @sd_teacher1, 'Đề nghị nghỉ cả lớp để Admin xem xét', 'cho_duyet', NULL, NULL, 'Đơn chờ duyệt, chưa hủy buổi');
INSERT INTO `dieu_chinh_cong` (`id`, `phan_cong_id`, `loai`, `checkin_cu`, `checkout_cu`, `checkin_moi`, `checkout_moi`, `ly_do`, `nguoi_yeu_cau_id`, `trang_thai`, `nguoi_duyet_id`, `duyet_luc`, `ghi_chu_duyet`, `created_at`)
VALUES (NULL, @sd_assignment_past1, 'sua_gio', TIMESTAMP(@sd_past,'18:10:00'), TIMESTAMP(@sd_past,'20:05:00'), TIMESTAMP(@sd_past,'18:00:00'), TIMESTAMP(@sd_past,'20:05:00'), 'Đề nghị Admin kiểm tra lại giờ vào lớp', @sd_teacher1, 'cho_duyet', NULL, NULL, NULL, DEFAULT);

-- A6. Hoc vu, bai cham, ket qua va bang cong mo. Cot generated dung DEFAULT.
INSERT INTO `bai_tap` (`id`, `buoi_hoc_id`, `tieu_de`, `noi_dung`, `file_de_bai_url`, `han_nop`, `nguoi_tao_id`, `created_at`, `updated_at`)
VALUES (NULL, @sd_past1, 'Bài ôn tập số 1', 'Làm các câu hỏi đã giao trên lớp', NULL, TIMESTAMP(@sd_next), @sd_teacher1, DEFAULT, NULL);
SET @sd_homework1 = LAST_INSERT_ID();
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, @sd_past1, @sd_student1, 'co_mat', @sd_homework1, 8.5, 'Nắm kiến thức cơ bản', 4, 'tot', 'Tích cực phát biểu', 'Luyện thêm bài vận dụng', @sd_teacher1, DEFAULT);
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, @sd_past1, @sd_student2, 'vang_co_phep', NULL, NULL, NULL, NULL, NULL, 'Vắng có phép, đã thỏa thuận học bù', NULL, @sd_teacher1, DEFAULT);
INSERT INTO `ket_qua_hoc_tap` (`id`, `lop_id`, `hoc_vien_id`, `loai`, `ten`, `diem`, `diem_toi_da`, `ngay`, `nhan_xet`, `nguoi_nhap_id`, `created_at`, `updated_at`)
VALUES (NULL, @sd_class1, @sd_student1, 'kiem_tra', 'Kiểm tra đầu khóa', 8, 10, @sd_past, 'Đạt yêu cầu', @sd_teacher1, DEFAULT, NULL);
INSERT INTO `bang_cong_thang` (`id`, `user_id`, `thang`, `nam`, `so_buoi`, `tong_phut`, `so_buoi_bu`, `so_buoi_vang`, `so_lan_tre`, `tong_thu_nhap`, `thuong`, `khau_tru`, `thuc_nhan`, `trang_thai`, `chot_boi`, `chot_luc`, `ngay_tra`, `ghi_chu`, `created_at`, `updated_at`, `tra_boi`, `tra_luc`, `ma_giao_dich_chi`, `mo_khoa_boi`, `mo_khoa_luc`, `ly_do_mo_khoa`)
VALUES (NULL, @sd_teacher1, MONTH(@sd_past), YEAR(@sd_past), 1, 110, 0, 0, 1, ROUND(110 / 60 * 160000), 0, 0, DEFAULT, 'mo', NULL, NULL, NULL, 'Bảng công mẫu đang mở, chưa chốt/trả', DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, NULL);
SET @sd_payroll1 = LAST_INSERT_ID();

UPDATE phan_cong_buoi_hoc SET bang_cong_id=@sd_payroll1 WHERE id=@sd_assignment_past1;
INSERT INTO `bai_tap` (`id`, `buoi_hoc_id`, `tieu_de`, `noi_dung`, `file_de_bai_url`, `han_nop`, `nguoi_tao_id`, `created_at`, `updated_at`)
VALUES (NULL, @sd_past2, 'Bài ôn tập số 1', 'Làm các câu hỏi đã giao trên lớp', NULL, TIMESTAMP(@sd_next), @sd_teacher2, DEFAULT, NULL);
SET @sd_homework2 = LAST_INSERT_ID();
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, @sd_past2, @sd_student3, 'co_mat', @sd_homework2, 8.5, 'Nắm kiến thức cơ bản', 4, 'tot', 'Tích cực phát biểu', 'Luyện thêm bài vận dụng', @sd_teacher2, DEFAULT);
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, @sd_past2, @sd_student4, 'vang_co_phep', NULL, NULL, NULL, NULL, NULL, 'Vắng có phép, đã thỏa thuận học bù', NULL, @sd_teacher2, DEFAULT);
INSERT INTO `ket_qua_hoc_tap` (`id`, `lop_id`, `hoc_vien_id`, `loai`, `ten`, `diem`, `diem_toi_da`, `ngay`, `nhan_xet`, `nguoi_nhap_id`, `created_at`, `updated_at`)
VALUES (NULL, @sd_class2, @sd_student3, 'kiem_tra', 'Kiểm tra đầu khóa', 8, 10, @sd_past, 'Đạt yêu cầu', @sd_teacher2, DEFAULT, NULL);
INSERT INTO `bang_cong_thang` (`id`, `user_id`, `thang`, `nam`, `so_buoi`, `tong_phut`, `so_buoi_bu`, `so_buoi_vang`, `so_lan_tre`, `tong_thu_nhap`, `thuong`, `khau_tru`, `thuc_nhan`, `trang_thai`, `chot_boi`, `chot_luc`, `ngay_tra`, `ghi_chu`, `created_at`, `updated_at`, `tra_boi`, `tra_luc`, `ma_giao_dich_chi`, `mo_khoa_boi`, `mo_khoa_luc`, `ly_do_mo_khoa`)
VALUES (NULL, @sd_teacher2, MONTH(@sd_past), YEAR(@sd_past), 1, 80, 0, 0, 1, ROUND(80 / 60 * 170000), 0, 0, DEFAULT, 'mo', NULL, NULL, NULL, 'Bảng công mẫu đang mở, chưa chốt/trả', DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, NULL);
SET @sd_payroll2 = LAST_INSERT_ID();

UPDATE phan_cong_buoi_hoc SET bang_cong_id=@sd_payroll2 WHERE id=@sd_assignment_past2;
INSERT INTO `bai_tap` (`id`, `buoi_hoc_id`, `tieu_de`, `noi_dung`, `file_de_bai_url`, `han_nop`, `nguoi_tao_id`, `created_at`, `updated_at`)
VALUES (NULL, @sd_past3, 'Bài ôn tập số 1', 'Làm các câu hỏi đã giao trên lớp', NULL, TIMESTAMP(@sd_next), @sd_teacher3, DEFAULT, NULL);
SET @sd_homework3 = LAST_INSERT_ID();
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, @sd_past3, @sd_student5, 'co_mat', @sd_homework3, 8.5, 'Nắm kiến thức cơ bản', 4, 'tot', 'Tích cực phát biểu', 'Luyện thêm bài vận dụng', @sd_teacher3, DEFAULT);
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, @sd_past3, @sd_student6, 'vang_co_phep', NULL, NULL, NULL, NULL, NULL, 'Vắng có phép, đã thỏa thuận học bù', NULL, @sd_teacher3, DEFAULT);
INSERT INTO `ket_qua_hoc_tap` (`id`, `lop_id`, `hoc_vien_id`, `loai`, `ten`, `diem`, `diem_toi_da`, `ngay`, `nhan_xet`, `nguoi_nhap_id`, `created_at`, `updated_at`)
VALUES (NULL, @sd_class3, @sd_student5, 'kiem_tra', 'Kiểm tra đầu khóa', 8, 10, @sd_past, 'Đạt yêu cầu', @sd_teacher3, DEFAULT, NULL);
INSERT INTO `bang_cong_thang` (`id`, `user_id`, `thang`, `nam`, `so_buoi`, `tong_phut`, `so_buoi_bu`, `so_buoi_vang`, `so_lan_tre`, `tong_thu_nhap`, `thuong`, `khau_tru`, `thuc_nhan`, `trang_thai`, `chot_boi`, `chot_luc`, `ngay_tra`, `ghi_chu`, `created_at`, `updated_at`, `tra_boi`, `tra_luc`, `ma_giao_dich_chi`, `mo_khoa_boi`, `mo_khoa_luc`, `ly_do_mo_khoa`)
VALUES (NULL, @sd_teacher3, MONTH(@sd_past), YEAR(@sd_past), 1, 110, 0, 0, 1, ROUND(110 / 60 * 180000), 0, 0, DEFAULT, 'mo', NULL, NULL, NULL, 'Bảng công mẫu đang mở, chưa chốt/trả', DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, NULL);
SET @sd_payroll3 = LAST_INSERT_ID();

UPDATE phan_cong_buoi_hoc SET bang_cong_id=@sd_payroll3 WHERE id=@sd_assignment_past3;
INSERT INTO `bai_tap` (`id`, `buoi_hoc_id`, `tieu_de`, `noi_dung`, `file_de_bai_url`, `han_nop`, `nguoi_tao_id`, `created_at`, `updated_at`)
VALUES (NULL, @sd_past4, 'Bài ôn tập số 1', 'Làm các câu hỏi đã giao trên lớp', NULL, TIMESTAMP(@sd_next), @sd_teacher4, DEFAULT, NULL);
SET @sd_homework4 = LAST_INSERT_ID();
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, @sd_past4, @sd_student7, 'co_mat', @sd_homework4, 8.5, 'Nắm kiến thức cơ bản', 4, 'tot', 'Tích cực phát biểu', 'Luyện thêm bài vận dụng', @sd_teacher4, DEFAULT);
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, @sd_past4, @sd_student8, 'vang_co_phep', NULL, NULL, NULL, NULL, NULL, 'Vắng có phép, đã thỏa thuận học bù', NULL, @sd_teacher4, DEFAULT);
INSERT INTO `ket_qua_hoc_tap` (`id`, `lop_id`, `hoc_vien_id`, `loai`, `ten`, `diem`, `diem_toi_da`, `ngay`, `nhan_xet`, `nguoi_nhap_id`, `created_at`, `updated_at`)
VALUES (NULL, @sd_class4, @sd_student7, 'kiem_tra', 'Kiểm tra đầu khóa', 8, 10, @sd_past, 'Đạt yêu cầu', @sd_teacher4, DEFAULT, NULL);
INSERT INTO `bang_cong_thang` (`id`, `user_id`, `thang`, `nam`, `so_buoi`, `tong_phut`, `so_buoi_bu`, `so_buoi_vang`, `so_lan_tre`, `tong_thu_nhap`, `thuong`, `khau_tru`, `thuc_nhan`, `trang_thai`, `chot_boi`, `chot_luc`, `ngay_tra`, `ghi_chu`, `created_at`, `updated_at`, `tra_boi`, `tra_luc`, `ma_giao_dich_chi`, `mo_khoa_boi`, `mo_khoa_luc`, `ly_do_mo_khoa`)
VALUES (NULL, @sd_teacher4, MONTH(@sd_past), YEAR(@sd_past), 1, 110, 0, 0, 1, ROUND(110 / 60 * 190000), 0, 0, DEFAULT, 'mo', NULL, NULL, NULL, 'Bảng công mẫu đang mở, chưa chốt/trả', DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, NULL);
SET @sd_payroll4 = LAST_INSERT_ID();

UPDATE phan_cong_buoi_hoc SET bang_cong_id=@sd_payroll4 WHERE id=@sd_assignment_past4;
INSERT INTO `bai_tap` (`id`, `buoi_hoc_id`, `tieu_de`, `noi_dung`, `file_de_bai_url`, `han_nop`, `nguoi_tao_id`, `created_at`, `updated_at`)
VALUES (NULL, @sd_past5, 'Bài ôn tập số 1', 'Làm các câu hỏi đã giao trên lớp', NULL, TIMESTAMP(@sd_next), @sd_teacher5, DEFAULT, NULL);
SET @sd_homework5 = LAST_INSERT_ID();
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, @sd_past5, @sd_student9, 'co_mat', @sd_homework5, 8.5, 'Nắm kiến thức cơ bản', 4, 'tot', 'Tích cực phát biểu', 'Luyện thêm bài vận dụng', @sd_teacher5, DEFAULT);
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, @sd_past5, @sd_student10, 'vang_co_phep', NULL, NULL, NULL, NULL, NULL, 'Vắng có phép, đã thỏa thuận học bù', NULL, @sd_teacher5, DEFAULT);
INSERT INTO `ket_qua_hoc_tap` (`id`, `lop_id`, `hoc_vien_id`, `loai`, `ten`, `diem`, `diem_toi_da`, `ngay`, `nhan_xet`, `nguoi_nhap_id`, `created_at`, `updated_at`)
VALUES (NULL, @sd_class5, @sd_student9, 'kiem_tra', 'Kiểm tra đầu khóa', 8, 10, @sd_past, 'Đạt yêu cầu', @sd_teacher5, DEFAULT, NULL);
INSERT INTO `bang_cong_thang` (`id`, `user_id`, `thang`, `nam`, `so_buoi`, `tong_phut`, `so_buoi_bu`, `so_buoi_vang`, `so_lan_tre`, `tong_thu_nhap`, `thuong`, `khau_tru`, `thuc_nhan`, `trang_thai`, `chot_boi`, `chot_luc`, `ngay_tra`, `ghi_chu`, `created_at`, `updated_at`, `tra_boi`, `tra_luc`, `ma_giao_dich_chi`, `mo_khoa_boi`, `mo_khoa_luc`, `ly_do_mo_khoa`)
VALUES (NULL, @sd_teacher5, MONTH(@sd_past), YEAR(@sd_past), 1, 110, 0, 0, 1, ROUND(110 / 60 * 200000), 0, 0, DEFAULT, 'mo', NULL, NULL, NULL, 'Bảng công mẫu đang mở, chưa chốt/trả', DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, NULL);
SET @sd_payroll5 = LAST_INSERT_ID();

UPDATE phan_cong_buoi_hoc SET bang_cong_id=@sd_payroll5 WHERE id=@sd_assignment_past5;

-- A7. Hoa don tron khoa, phieu thu, giao dich va su kien gia lap.
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(@sd_tag,'-HD01'), NULL, NULL, NULL, 1600000, 0, DEFAULT, 0, 'chua_thanh_toan', DATE_ADD(CURDATE(), INTERVAL 14 DAY), @sd_giaovu, DEFAULT, DEFAULT, NOW(), @sd_giaovu, NULL, NULL, NULL, @sd_registration1);
SET @sd_invoice1 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, @sd_invoice1, NULL, 'khoa_hoc', 'Toán 9 cơ bản học kỳ 1', 1600000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(@sd_tag,'-HD02'), NULL, NULL, NULL, 1600000, 0, DEFAULT, 0, 'chua_thanh_toan', DATE_ADD(CURDATE(), INTERVAL 14 DAY), @sd_giaovu, DEFAULT, DEFAULT, NOW(), @sd_giaovu, NULL, NULL, NULL, @sd_registration2);
SET @sd_invoice2 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, @sd_invoice2, NULL, 'khoa_hoc', 'Toán 9 cơ bản học kỳ 1', 1600000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(@sd_tag,'-HD03'), NULL, NULL, NULL, 1700000, 0, DEFAULT, 0, 'chua_thanh_toan', DATE_ADD(CURDATE(), INTERVAL 14 DAY), @sd_giaovu, DEFAULT, DEFAULT, NOW(), @sd_giaovu, NULL, NULL, NULL, @sd_registration3);
SET @sd_invoice3 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, @sd_invoice3, NULL, 'khoa_hoc', 'Toán 12 ôn luyện chuyên', 1700000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(@sd_tag,'-HD04'), NULL, NULL, NULL, 1700000, 0, DEFAULT, 0, 'chua_thanh_toan', DATE_ADD(CURDATE(), INTERVAL 14 DAY), @sd_giaovu, DEFAULT, DEFAULT, NOW(), @sd_giaovu, NULL, NULL, NULL, @sd_registration4);
SET @sd_invoice4 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, @sd_invoice4, NULL, 'khoa_hoc', 'Toán 12 ôn luyện chuyên', 1700000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(@sd_tag,'-HD05'), NULL, NULL, NULL, 1800000, 0, DEFAULT, 0, 'chua_thanh_toan', DATE_ADD(CURDATE(), INTERVAL 14 DAY), @sd_giaovu, DEFAULT, DEFAULT, NOW(), @sd_giaovu, NULL, NULL, NULL, @sd_registration5);
SET @sd_invoice5 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, @sd_invoice5, NULL, 'khoa_hoc', 'Vật lý 11 nâng cao học kỳ 2', 1800000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(@sd_tag,'-HD06'), NULL, NULL, NULL, 1800000, 0, DEFAULT, 0, 'chua_thanh_toan', DATE_ADD(CURDATE(), INTERVAL 14 DAY), @sd_giaovu, DEFAULT, DEFAULT, NOW(), @sd_giaovu, NULL, NULL, NULL, @sd_registration6);
SET @sd_invoice6 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, @sd_invoice6, NULL, 'khoa_hoc', 'Vật lý 11 nâng cao học kỳ 2', 1800000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(@sd_tag,'-HD07'), NULL, NULL, NULL, 1900000, 0, DEFAULT, 0, 'nhap', DATE_ADD(CURDATE(), INTERVAL 14 DAY), @sd_giaovu, DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, @sd_registration7);
SET @sd_invoice7 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, @sd_invoice7, NULL, 'khoa_hoc', 'Tiếng Anh 6 nền tảng cả năm', 1900000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(@sd_tag,'-HD08'), NULL, NULL, NULL, 1900000, 0, DEFAULT, 0, 'nhap', DATE_ADD(CURDATE(), INTERVAL 14 DAY), @sd_giaovu, DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, @sd_registration8);
SET @sd_invoice8 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, @sd_invoice8, NULL, 'khoa_hoc', 'Tiếng Anh 6 nền tảng cả năm', 1900000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(@sd_tag,'-HD09'), NULL, NULL, NULL, 2000000, 0, DEFAULT, 0, 'nhap', DATE_ADD(CURDATE(), INTERVAL 14 DAY), @sd_giaovu, DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, @sd_registration9);
SET @sd_invoice9 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, @sd_invoice9, NULL, 'khoa_hoc', 'Ngữ văn 9 luyện đề', 2000000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(@sd_tag,'-HD10'), NULL, NULL, NULL, 2000000, 0, DEFAULT, 0, 'nhap', DATE_ADD(CURDATE(), INTERVAL 14 DAY), @sd_giaovu, DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, @sd_registration10);
SET @sd_invoice10 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, @sd_invoice10, NULL, 'khoa_hoc', 'Ngữ văn 9 luyện đề', 2000000);
INSERT INTO `thanh_toan` (`id`, `hoa_don_id`, `loai`, `so_tien`, `phuong_thuc`, `ma_giao_dich`, `ngay_thanh_toan`, `nguoi_thu_id`, `trang_thai`, `ghi_chu`, `huy_luc`, `huy_boi`, `ly_do_huy`)
VALUES (NULL, @sd_invoice1, 'thu', 500000, 'tien_mat', CONCAT(@sd_tag,'-PT01'), DEFAULT, @sd_admin, 'hop_le', 'Phiếu thu giả lập, chưa thu đủ trọn khóa', NULL, NULL, NULL);
INSERT INTO `giao_dich_truc_tuyen` (`id`, `ma_yeu_cau`, `hoa_don_id`, `phu_huynh_id`, `nha_cung_cap`, `ma_giao_dich_cong`, `so_tien`, `tien_te`, `trang_thai`, `thanh_toan_id`, `xac_nhan_boi`, `xac_nhan_luc`, `het_han_luc`, `created_at`, `updated_at`)
VALUES (NULL, CONCAT(@sd_tag,'-PAY01'), @sd_invoice2, @sd_parent1, 'demo_no_gateway', CONCAT(@sd_tag,'-BANK01'), 1600000, 'VND', 'khoi_tao', NULL, NULL, NULL, DATE_ADD(NOW(), INTERVAL 1 DAY), DEFAULT, DEFAULT);
SET @sd_payment1 = LAST_INSERT_ID();
INSERT INTO `su_kien_thanh_toan` (`id`, `giao_dich_id`, `nha_cung_cap`, `ma_su_kien`, `payload_sha256`, `trang_thai`, `loi`, `nhan_luc`, `xu_ly_luc`)
VALUES (NULL, @sd_payment1, 'demo_no_gateway', CONCAT(@sd_tag,'-EVENT01'), SHA2(CONCAT(@sd_tag,'-FAKE-EVENT-1'),256), 'cho_xu_ly', NULL, DEFAULT, NULL);
SET @sd_event1 = LAST_INSERT_ID();

UPDATE giao_dich_truc_tuyen SET trang_thai='cho_xac_nhan' WHERE id=@sd_payment1;
INSERT INTO `giao_dich_truc_tuyen` (`id`, `ma_yeu_cau`, `hoa_don_id`, `phu_huynh_id`, `nha_cung_cap`, `ma_giao_dich_cong`, `so_tien`, `tien_te`, `trang_thai`, `thanh_toan_id`, `xac_nhan_boi`, `xac_nhan_luc`, `het_han_luc`, `created_at`, `updated_at`)
VALUES (NULL, CONCAT(@sd_tag,'-PAY02'), @sd_invoice3, @sd_parent2, 'demo_no_gateway', CONCAT(@sd_tag,'-BANK02'), 1700000, 'VND', 'khoi_tao', NULL, NULL, NULL, DATE_ADD(NOW(), INTERVAL 1 DAY), DEFAULT, DEFAULT);
SET @sd_payment2 = LAST_INSERT_ID();
INSERT INTO `su_kien_thanh_toan` (`id`, `giao_dich_id`, `nha_cung_cap`, `ma_su_kien`, `payload_sha256`, `trang_thai`, `loi`, `nhan_luc`, `xu_ly_luc`)
VALUES (NULL, @sd_payment2, 'demo_no_gateway', CONCAT(@sd_tag,'-EVENT02'), SHA2(CONCAT(@sd_tag,'-FAKE-EVENT-2'),256), 'cho_xu_ly', NULL, DEFAULT, NULL);
SET @sd_event2 = LAST_INSERT_ID();

UPDATE giao_dich_truc_tuyen SET trang_thai='cho_xac_nhan' WHERE id=@sd_payment2;
INSERT INTO `thanh_toan` (`id`, `hoa_don_id`, `loai`, `so_tien`, `phuong_thuc`, `ma_giao_dich`, `ngay_thanh_toan`, `nguoi_thu_id`, `trang_thai`, `ghi_chu`, `huy_luc`, `huy_boi`, `ly_do_huy`)
VALUES (NULL, @sd_invoice3, 'thu', 1700000, 'chuyen_khoan', CONCAT(@sd_tag,'-PT02'), DEFAULT, @sd_admin, 'hop_le', 'Đối soát giả lập dữ liệu, không gọi ngân hàng', NULL, NULL, NULL);
SET @sd_receipt2 = LAST_INSERT_ID();

UPDATE giao_dich_truc_tuyen SET trang_thai='thanh_cong',thanh_toan_id=@sd_receipt2,xac_nhan_boi=@sd_admin,xac_nhan_luc=NOW() WHERE id=@sd_payment2;
UPDATE su_kien_thanh_toan SET trang_thai='da_xu_ly',xu_ly_luc=NOW() WHERE id=@sd_event2;
INSERT INTO `thong_bao` (`id`, `user_id`, `loai`, `tieu_de`, `noi_dung`, `doi_tuong_loai`, `doi_tuong_id`, `khoa_chong_trung`, `da_doc`, `doc_luc`, `co_gui_email`, `so_lan_thu_email`, `trang_thai_email`, `loi_email`, `created_at`, `email_gui_luc`, `email_thu_gan_nhat_luc`, `email_thu_tiep_luc`)
VALUES (NULL, @sd_giaovu, 'he_thong', 'Bộ dữ liệu SQLDEMO đã sẵn sàng', 'Kiểm tra lớp, sĩ số, ca dạy, học vụ và hóa đơn.', 'lop_hoc', @sd_class1, CONCAT(@sd_tag,'-NOTICE'), DEFAULT, NULL, 0, 0, 'chua_gui', NULL, DEFAULT, NULL, NULL, NULL);
INSERT INTO `audit_log` (`id`, `user_id`, `hanh_dong`, `bang`, `ban_ghi_id`, `du_lieu_cu`, `du_lieu_moi`, `ip`, `created_at`)
VALUES (NULL, @sd_admin, 'them', 'lop_hoc', @sd_class1, NULL, JSON_OBJECT('nguon','manual_sql_fixture','ma_bo',@sd_tag), '127.0.0.1', DEFAULT);

    COMMIT;
END$$
DELIMITER ;
CALL sp_them_du_lieu_sqldemo();
DROP PROCEDURE sp_them_du_lieu_sqldemo;

-- A8. Xem ket qua. Trinh duyet mo ngay @sd_next de thay 2 ca/ngay/nguoi day.
SELECT @sd_tag AS bo_du_lieu, @sd_next AS ngay_demo, @sd_makeup_day AS ngay_hoc_bu;
SELECT username, hoten, group_name FROM account a JOIN users u ON u.user_id=a.user_id
JOIN role_group g ON g.group_id=a.group_id WHERE u.email LIKE CONCAT(LOWER(@sd_tag),'.%@example.test');
SELECT h.ma_hd,h.tong_tien,h.da_thu,h.phai_thu-h.da_thu AS con_lai,h.trang_thai
FROM hoa_don h WHERE h.ma_hd LIKE CONCAT(@sd_tag,'-%');
SELECT b.ngay,l.ma_lop,b.gio_bat_dau,b.gio_ket_thuc,pc.so_phut_tinh_cong,pc.checkin_tre
FROM buoi_hoc b JOIN lop_hoc l ON l.id=b.lop_id JOIN phan_cong_buoi_hoc pc ON pc.buoi_hoc_id=b.id
WHERE l.ma_lop LIKE CONCAT(@sd_tag,'-%') ORDER BY b.ngay,b.gio_bat_dau,l.ma_lop;

-- KẾT THÚC PHẦN 1 · THÊM DỮ LIỆU

/*
===============================================================================
PHẦN 2 · XÓA DỮ LIỆU DEMO
===============================================================================
-- Chọn từ USE bên dưới đến DROP PROCEDURE cuối file, rồi chạy phần đã chọn.
-- DGTEST  = dọn bộ Giáo vụ cũ.
-- SQLDEMO = dọn bộ mới ở phần 1.
-- CA_HAI  = dọn cả hai bộ.
-- Giữ THANH TOÁN, HÓA ĐƠN, LỊCH SỬ và hồ sơ tham chiếu.
-- Ket qua cho biet so dong xoa va so dong duoc giu do lien ket/lich su.
-- Thu tuc chi xoa theo ID/moc demo da chon, khong tat FK/trigger.

USE trung_tam_gia_su;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET SESSION time_zone = '+07:00';
SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED;
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_don_du_lieu_demo$$
CREATE PROCEDURE sp_don_du_lieu_demo(IN p_bo VARCHAR(10))
BEGIN
    DECLARE v_thay_doi INT DEFAULT 1;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
    IF p_bo NOT IN ('DGTEST','SQLDEMO','CA_HAI') OR p_bo IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chon DGTEST, SQLDEMO hoac CA_HAI.';
    END IF;
    START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_users`;
CREATE TEMPORARY TABLE `tmp_sd_x_users` (`user_id` bigint unsigned NOT NULL, PRIMARY KEY (`user_id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_users`;
CREATE TEMPORARY TABLE `tmp_sd_k_users` (`user_id` bigint unsigned NOT NULL, PRIMARY KEY (`user_id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_role_group`;
CREATE TEMPORARY TABLE `tmp_sd_x_role_group` (`group_id` int unsigned NOT NULL, PRIMARY KEY (`group_id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_role_group`;
CREATE TEMPORARY TABLE `tmp_sd_k_role_group` (`group_id` int unsigned NOT NULL, PRIMARY KEY (`group_id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_account`;
CREATE TEMPORARY TABLE `tmp_sd_x_account` (`account_id` bigint unsigned NOT NULL, PRIMARY KEY (`account_id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_account`;
CREATE TEMPORARY TABLE `tmp_sd_k_account` (`account_id` bigint unsigned NOT NULL, PRIMARY KEY (`account_id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_function`;
CREATE TEMPORARY TABLE `tmp_sd_x_function` (`function_id` int unsigned NOT NULL, PRIMARY KEY (`function_id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_function`;
CREATE TEMPORARY TABLE `tmp_sd_k_function` (`function_id` int unsigned NOT NULL, PRIMARY KEY (`function_id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_role_group_function`;
CREATE TEMPORARY TABLE `tmp_sd_x_role_group_function` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_role_group_function`;
CREATE TEMPORARY TABLE `tmp_sd_k_role_group_function` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_mon_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_x_mon_hoc` (`id` int unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_mon_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_k_mon_hoc` (`id` int unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_cap_lop`;
CREATE TEMPORARY TABLE `tmp_sd_x_cap_lop` (`id` tinyint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_cap_lop`;
CREATE TEMPORARY TABLE `tmp_sd_k_cap_lop` (`id` tinyint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_khu_vuc`;
CREATE TEMPORARY TABLE `tmp_sd_x_khu_vuc` (`id` int unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_khu_vuc`;
CREATE TEMPORARY TABLE `tmp_sd_k_khu_vuc` (`id` int unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_co_so`;
CREATE TEMPORARY TABLE `tmp_sd_x_co_so` (`id` int unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_co_so`;
CREATE TEMPORARY TABLE `tmp_sd_k_co_so` (`id` int unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_cau_hinh`;
CREATE TEMPORARY TABLE `tmp_sd_x_cau_hinh` (`khoa` varchar(60) NOT NULL, PRIMARY KEY (`khoa`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_cau_hinh`;
CREATE TEMPORARY TABLE `tmp_sd_k_cau_hinh` (`khoa` varchar(60) NOT NULL, PRIMARY KEY (`khoa`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_nhan_vien`;
CREATE TEMPORARY TABLE `tmp_sd_x_nhan_vien` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_nhan_vien`;
CREATE TEMPORARY TABLE `tmp_sd_k_nhan_vien` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_nhan_su_chuyen_mon`;
CREATE TEMPORARY TABLE `tmp_sd_x_nhan_su_chuyen_mon` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_nhan_su_chuyen_mon`;
CREATE TEMPORARY TABLE `tmp_sd_k_nhan_su_chuyen_mon` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_hoc_vien`;
CREATE TEMPORARY TABLE `tmp_sd_x_hoc_vien` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_hoc_vien`;
CREATE TEMPORARY TABLE `tmp_sd_k_hoc_vien` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_nhan_su_mon`;
CREATE TEMPORARY TABLE `tmp_sd_x_nhan_su_mon` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_nhan_su_mon`;
CREATE TEMPORARY TABLE `tmp_sd_k_nhan_su_mon` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_nhan_su_khu_vuc`;
CREATE TEMPORARY TABLE `tmp_sd_x_nhan_su_khu_vuc` (`nhan_su_id` bigint unsigned NOT NULL, `khu_vuc_id` int unsigned NOT NULL, PRIMARY KEY (`nhan_su_id`,`khu_vuc_id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_nhan_su_khu_vuc`;
CREATE TEMPORARY TABLE `tmp_sd_k_nhan_su_khu_vuc` (`nhan_su_id` bigint unsigned NOT NULL, `khu_vuc_id` int unsigned NOT NULL, PRIMARY KEY (`nhan_su_id`,`khu_vuc_id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_nhan_su_lich_ranh`;
CREATE TEMPORARY TABLE `tmp_sd_x_nhan_su_lich_ranh` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_nhan_su_lich_ranh`;
CREATE TEMPORARY TABLE `tmp_sd_k_nhan_su_lich_ranh` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_yeu_cau_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_x_yeu_cau_hoc` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_yeu_cau_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_k_yeu_cau_hoc` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_yeu_cau_lich`;
CREATE TEMPORARY TABLE `tmp_sd_x_yeu_cau_lich` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_yeu_cau_lich`;
CREATE TEMPORARY TABLE `tmp_sd_k_yeu_cau_lich` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_de_xuat_nhan_su`;
CREATE TEMPORARY TABLE `tmp_sd_x_de_xuat_nhan_su` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_de_xuat_nhan_su`;
CREATE TEMPORARY TABLE `tmp_sd_k_de_xuat_nhan_su` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_khoa_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_x_khoa_hoc` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_khoa_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_k_khoa_hoc` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_dang_ky_khoa_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_x_dang_ky_khoa_hoc` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_dang_ky_khoa_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_k_dang_ky_khoa_hoc` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_lich_su_don_gia_gio`;
CREATE TEMPORARY TABLE `tmp_sd_x_lich_su_don_gia_gio` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_lich_su_don_gia_gio`;
CREATE TEMPORARY TABLE `tmp_sd_k_lich_su_don_gia_gio` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_lop_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_x_lop_hoc` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_lop_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_k_lop_hoc` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_lop_hoc_vien`;
CREATE TEMPORARY TABLE `tmp_sd_x_lop_hoc_vien` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_lop_hoc_vien`;
CREATE TEMPORARY TABLE `tmp_sd_k_lop_hoc_vien` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_lich_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_x_lich_hoc` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_lich_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_k_lich_hoc` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_buoi_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_x_buoi_hoc` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_buoi_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_k_buoi_hoc` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_phan_cong_buoi_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_x_phan_cong_buoi_hoc` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_phan_cong_buoi_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_k_phan_cong_buoi_hoc` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_dieu_chinh_cong`;
CREATE TEMPORARY TABLE `tmp_sd_x_dieu_chinh_cong` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_dieu_chinh_cong`;
CREATE TEMPORARY TABLE `tmp_sd_k_dieu_chinh_cong` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_don_xin_nghi`;
CREATE TEMPORARY TABLE `tmp_sd_x_don_xin_nghi` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_don_xin_nghi`;
CREATE TEMPORARY TABLE `tmp_sd_k_don_xin_nghi` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_bai_tap`;
CREATE TEMPORARY TABLE `tmp_sd_x_bai_tap` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_bai_tap`;
CREATE TEMPORARY TABLE `tmp_sd_k_bai_tap` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_so_theo_doi_buoi_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_x_so_theo_doi_buoi_hoc` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_so_theo_doi_buoi_hoc`;
CREATE TEMPORARY TABLE `tmp_sd_k_so_theo_doi_buoi_hoc` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_ket_qua_hoc_tap`;
CREATE TEMPORARY TABLE `tmp_sd_x_ket_qua_hoc_tap` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_ket_qua_hoc_tap`;
CREATE TEMPORARY TABLE `tmp_sd_k_ket_qua_hoc_tap` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_bang_cong_thang`;
CREATE TEMPORARY TABLE `tmp_sd_x_bang_cong_thang` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_bang_cong_thang`;
CREATE TEMPORARY TABLE `tmp_sd_k_bang_cong_thang` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_hoa_don`;
CREATE TEMPORARY TABLE `tmp_sd_x_hoa_don` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_hoa_don`;
CREATE TEMPORARY TABLE `tmp_sd_k_hoa_don` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_hoa_don_chi_tiet`;
CREATE TEMPORARY TABLE `tmp_sd_x_hoa_don_chi_tiet` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_hoa_don_chi_tiet`;
CREATE TEMPORARY TABLE `tmp_sd_k_hoa_don_chi_tiet` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_thanh_toan`;
CREATE TEMPORARY TABLE `tmp_sd_x_thanh_toan` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_thanh_toan`;
CREATE TEMPORARY TABLE `tmp_sd_k_thanh_toan` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_thong_bao`;
CREATE TEMPORARY TABLE `tmp_sd_x_thong_bao` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_thong_bao`;
CREATE TEMPORARY TABLE `tmp_sd_k_thong_bao` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_audit_log`;
CREATE TEMPORARY TABLE `tmp_sd_x_audit_log` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_audit_log`;
CREATE TEMPORARY TABLE `tmp_sd_k_audit_log` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_schema_migrations`;
CREATE TEMPORARY TABLE `tmp_sd_x_schema_migrations` (`version` varchar(80) NOT NULL, PRIMARY KEY (`version`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_schema_migrations`;
CREATE TEMPORARY TABLE `tmp_sd_k_schema_migrations` (`version` varchar(80) NOT NULL, PRIMARY KEY (`version`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_yeu_cau_nghi_lop`;
CREATE TEMPORARY TABLE `tmp_sd_x_yeu_cau_nghi_lop` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_yeu_cau_nghi_lop`;
CREATE TEMPORARY TABLE `tmp_sd_k_yeu_cau_nghi_lop` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_hoc_bu_hoc_vien`;
CREATE TEMPORARY TABLE `tmp_sd_x_hoc_bu_hoc_vien` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_hoc_bu_hoc_vien`;
CREATE TEMPORARY TABLE `tmp_sd_k_hoc_bu_hoc_vien` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_giao_dich_truc_tuyen`;
CREATE TEMPORARY TABLE `tmp_sd_x_giao_dich_truc_tuyen` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_giao_dich_truc_tuyen`;
CREATE TEMPORARY TABLE `tmp_sd_k_giao_dich_truc_tuyen` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_x_su_kien_thanh_toan`;
CREATE TEMPORARY TABLE `tmp_sd_x_su_kien_thanh_toan` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS `tmp_sd_k_su_kien_thanh_toan`;
CREATE TEMPORARY TABLE `tmp_sd_k_su_kien_thanh_toan` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS tmp_sd_buoi_copy;
CREATE TEMPORARY TABLE tmp_sd_buoi_copy (id BIGINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB;
DROP TEMPORARY TABLE IF EXISTS tmp_sd_ket_qua;
CREATE TEMPORARY TABLE tmp_sd_ket_qua (bang VARCHAR(64) PRIMARY KEY, chon_xoa BIGINT, giu_lai BIGINT DEFAULT 0, da_xoa BIGINT DEFAULT 0) ENGINE=InnoDB;
-- B1. Pham vi du lieu demo. Hoc vien/nhu cau tu them giu rieng theo ma.
INSERT INTO `tmp_sd_x_users` SELECT `user_id` FROM `users` WHERE ((p_bo IN ('DGTEST','CA_HAI') AND email REGEXP '^dgtest[.][a-z0-9]+@example[.]test$') OR (p_bo IN ('SQLDEMO','CA_HAI') AND email REGEXP '^sqldemo[0-9]{2}[.][a-z0-9]+@example[.]test$')) FOR UPDATE;
INSERT INTO `tmp_sd_x_co_so` SELECT `id` FROM `co_so` WHERE ((p_bo IN ('DGTEST','CA_HAI') AND ma_co_so LIKE 'DGTEST-%') OR (p_bo IN ('SQLDEMO','CA_HAI') AND ma_co_so REGEXP '^SQLDEMO[0-9]{2}-')) FOR UPDATE;
INSERT INTO `tmp_sd_x_nhan_vien` SELECT `id` FROM `nhan_vien` WHERE ((p_bo IN ('DGTEST','CA_HAI') AND ma_nv LIKE 'DGTEST-%') OR (p_bo IN ('SQLDEMO','CA_HAI') AND ma_nv REGEXP '^SQLDEMO[0-9]{2}-')) FOR UPDATE;
INSERT INTO `tmp_sd_x_nhan_su_chuyen_mon` SELECT `id` FROM `nhan_su_chuyen_mon` WHERE ((p_bo IN ('DGTEST','CA_HAI') AND ma_ns LIKE 'DGTEST-%') OR (p_bo IN ('SQLDEMO','CA_HAI') AND ma_ns REGEXP '^SQLDEMO[0-9]{2}-')) FOR UPDATE;
INSERT INTO `tmp_sd_x_hoc_vien` SELECT `id` FROM `hoc_vien` WHERE ((p_bo IN ('DGTEST','CA_HAI') AND ma_hv LIKE 'DGTEST-%') OR (p_bo IN ('SQLDEMO','CA_HAI') AND ma_hv REGEXP '^SQLDEMO[0-9]{2}-')) FOR UPDATE;
INSERT INTO `tmp_sd_x_khoa_hoc` SELECT `id` FROM `khoa_hoc` WHERE ((p_bo IN ('DGTEST','CA_HAI') AND ma LIKE 'DGTEST-%') OR (p_bo IN ('SQLDEMO','CA_HAI') AND ma REGEXP '^SQLDEMO[0-9]{2}-')) FOR UPDATE;
INSERT INTO `tmp_sd_x_yeu_cau_hoc` SELECT `id` FROM `yeu_cau_hoc` WHERE ((p_bo IN ('DGTEST','CA_HAI') AND ma_yc LIKE 'DGTEST-%') OR (p_bo IN ('SQLDEMO','CA_HAI') AND ma_yc REGEXP '^SQLDEMO[0-9]{2}-')) FOR UPDATE;
INSERT INTO `tmp_sd_x_lop_hoc` SELECT `id` FROM `lop_hoc` WHERE ((p_bo IN ('DGTEST','CA_HAI') AND ma_lop LIKE 'DGTEST-%') OR (p_bo IN ('SQLDEMO','CA_HAI') AND ma_lop REGEXP '^SQLDEMO[0-9]{2}-')) FOR UPDATE;
INSERT INTO `tmp_sd_x_account` SELECT `account_id` FROM `account` WHERE user_id IN (SELECT `user_id` FROM `tmp_sd_x_users`) FOR UPDATE;
INSERT INTO `tmp_sd_x_nhan_su_mon` SELECT `id` FROM `nhan_su_mon` WHERE nhan_su_id IN (SELECT `id` FROM `tmp_sd_x_nhan_su_chuyen_mon`) FOR UPDATE;
INSERT INTO `tmp_sd_x_nhan_su_khu_vuc` SELECT `nhan_su_id`,`khu_vuc_id` FROM `nhan_su_khu_vuc` WHERE nhan_su_id IN (SELECT `id` FROM `tmp_sd_x_nhan_su_chuyen_mon`) FOR UPDATE;
INSERT INTO `tmp_sd_x_nhan_su_lich_ranh` SELECT `id` FROM `nhan_su_lich_ranh` WHERE nhan_su_id IN (SELECT `id` FROM `tmp_sd_x_nhan_su_chuyen_mon`) FOR UPDATE;
INSERT INTO `tmp_sd_x_dang_ky_khoa_hoc` SELECT `id` FROM `dang_ky_khoa_hoc` WHERE hoc_vien_id IN (SELECT `id` FROM `tmp_sd_x_hoc_vien`) FOR UPDATE;
INSERT INTO `tmp_sd_x_de_xuat_nhan_su` SELECT `id` FROM `de_xuat_nhan_su` WHERE yeu_cau_id IN (SELECT `id` FROM `tmp_sd_x_yeu_cau_hoc`) FOR UPDATE;
INSERT INTO `tmp_sd_x_yeu_cau_lich` SELECT `id` FROM `yeu_cau_lich` WHERE yeu_cau_id IN (SELECT `id` FROM `tmp_sd_x_yeu_cau_hoc`) FOR UPDATE;
INSERT INTO `tmp_sd_x_lop_hoc_vien` SELECT `id` FROM `lop_hoc_vien` WHERE hoc_vien_id IN (SELECT `id` FROM `tmp_sd_x_hoc_vien`) AND lop_id IN (SELECT `id` FROM `tmp_sd_x_lop_hoc`) FOR UPDATE;
INSERT INTO `tmp_sd_x_lich_hoc` SELECT `id` FROM `lich_hoc` WHERE lop_id IN (SELECT `id` FROM `tmp_sd_x_lop_hoc`) FOR UPDATE;
INSERT INTO `tmp_sd_x_buoi_hoc` SELECT `id` FROM `buoi_hoc` WHERE lop_id IN (SELECT `id` FROM `tmp_sd_x_lop_hoc`) FOR UPDATE;
INSERT INTO `tmp_sd_x_phan_cong_buoi_hoc` SELECT `id` FROM `phan_cong_buoi_hoc` WHERE buoi_hoc_id IN (SELECT `id` FROM `tmp_sd_x_buoi_hoc`) FOR UPDATE;
INSERT INTO `tmp_sd_x_bai_tap` SELECT `id` FROM `bai_tap` WHERE buoi_hoc_id IN (SELECT `id` FROM `tmp_sd_x_buoi_hoc`) FOR UPDATE;
INSERT INTO `tmp_sd_x_so_theo_doi_buoi_hoc` SELECT `id` FROM `so_theo_doi_buoi_hoc` WHERE buoi_hoc_id IN (SELECT `id` FROM `tmp_sd_x_buoi_hoc`) AND hoc_vien_id IN (SELECT `id` FROM `tmp_sd_x_hoc_vien`) FOR UPDATE;
INSERT INTO `tmp_sd_x_ket_qua_hoc_tap` SELECT `id` FROM `ket_qua_hoc_tap` WHERE hoc_vien_id IN (SELECT `id` FROM `tmp_sd_x_hoc_vien`) AND lop_id IN (SELECT `id` FROM `tmp_sd_x_lop_hoc`) FOR UPDATE;
INSERT INTO `tmp_sd_x_don_xin_nghi` SELECT `id` FROM `don_xin_nghi` WHERE buoi_hoc_id IN (SELECT `id` FROM `tmp_sd_x_buoi_hoc`) AND (doi_tuong<>'hoc_vien' OR hoc_vien_id IN (SELECT `id` FROM `tmp_sd_x_hoc_vien`)) FOR UPDATE;
INSERT INTO `tmp_sd_x_yeu_cau_nghi_lop` SELECT `id` FROM `yeu_cau_nghi_lop` WHERE buoi_hoc_id IN (SELECT `id` FROM `tmp_sd_x_buoi_hoc`) FOR UPDATE;
INSERT INTO `tmp_sd_x_hoc_bu_hoc_vien` SELECT `id` FROM `hoc_bu_hoc_vien` WHERE buoi_bu_id IN (SELECT `id` FROM `tmp_sd_x_buoi_hoc`) AND hoc_vien_id IN (SELECT `id` FROM `tmp_sd_x_hoc_vien`) FOR UPDATE;
INSERT INTO `tmp_sd_x_dieu_chinh_cong` SELECT `id` FROM `dieu_chinh_cong` WHERE phan_cong_id IN (SELECT `id` FROM `tmp_sd_x_phan_cong_buoi_hoc`) FOR UPDATE;
INSERT INTO `tmp_sd_x_hoa_don` SELECT `id` FROM `hoa_don` WHERE dang_ky_khoa_hoc_id IN (SELECT `id` FROM `tmp_sd_x_dang_ky_khoa_hoc`) FOR UPDATE;
INSERT INTO `tmp_sd_x_hoa_don_chi_tiet` SELECT `id` FROM `hoa_don_chi_tiet` WHERE hoa_don_id IN (SELECT `id` FROM `tmp_sd_x_hoa_don`) FOR UPDATE;
INSERT INTO `tmp_sd_x_thanh_toan` SELECT `id` FROM `thanh_toan` WHERE hoa_don_id IN (SELECT `id` FROM `tmp_sd_x_hoa_don`) FOR UPDATE;
INSERT INTO `tmp_sd_x_giao_dich_truc_tuyen` SELECT `id` FROM `giao_dich_truc_tuyen` WHERE hoa_don_id IN (SELECT `id` FROM `tmp_sd_x_hoa_don`) FOR UPDATE;
INSERT INTO `tmp_sd_x_su_kien_thanh_toan` SELECT `id` FROM `su_kien_thanh_toan` WHERE giao_dich_id IN (SELECT `id` FROM `tmp_sd_x_giao_dich_truc_tuyen`) FOR UPDATE;
INSERT INTO `tmp_sd_x_lich_su_don_gia_gio` SELECT `id` FROM `lich_su_don_gia_gio` WHERE nhan_su_id IN (SELECT `id` FROM `tmp_sd_x_nhan_su_chuyen_mon`) FOR UPDATE;
INSERT INTO `tmp_sd_x_bang_cong_thang` SELECT `id` FROM `bang_cong_thang` WHERE user_id IN (SELECT `user_id` FROM `tmp_sd_x_users`) FOR UPDATE;
INSERT INTO `tmp_sd_x_thong_bao` SELECT `id` FROM `thong_bao` WHERE user_id IN (SELECT `user_id` FROM `tmp_sd_x_users`) FOR UPDATE;
-- B2. Giu nguyen toan bo tai chinh/lich su va moi dong ngoai pham vi demo.
INSERT INTO `tmp_sd_k_users` SELECT r.`user_id` FROM `users` r LEFT JOIN `tmp_sd_x_users` x ON r.`user_id`=x.`user_id` WHERE x.`user_id` IS NULL;
INSERT INTO `tmp_sd_k_role_group` SELECT r.`group_id` FROM `role_group` r LEFT JOIN `tmp_sd_x_role_group` x ON r.`group_id`=x.`group_id` WHERE x.`group_id` IS NULL;
INSERT INTO `tmp_sd_k_account` SELECT r.`account_id` FROM `account` r LEFT JOIN `tmp_sd_x_account` x ON r.`account_id`=x.`account_id` WHERE x.`account_id` IS NULL;
INSERT INTO `tmp_sd_k_function` SELECT r.`function_id` FROM `function` r LEFT JOIN `tmp_sd_x_function` x ON r.`function_id`=x.`function_id` WHERE x.`function_id` IS NULL;
INSERT INTO `tmp_sd_k_role_group_function` SELECT r.`id` FROM `role_group_function` r LEFT JOIN `tmp_sd_x_role_group_function` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_mon_hoc` SELECT r.`id` FROM `mon_hoc` r LEFT JOIN `tmp_sd_x_mon_hoc` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_cap_lop` SELECT r.`id` FROM `cap_lop` r LEFT JOIN `tmp_sd_x_cap_lop` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_khu_vuc` SELECT r.`id` FROM `khu_vuc` r LEFT JOIN `tmp_sd_x_khu_vuc` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_co_so` SELECT r.`id` FROM `co_so` r LEFT JOIN `tmp_sd_x_co_so` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_cau_hinh` SELECT r.`khoa` FROM `cau_hinh` r LEFT JOIN `tmp_sd_x_cau_hinh` x ON r.`khoa`=x.`khoa` WHERE x.`khoa` IS NULL;
INSERT INTO `tmp_sd_k_nhan_vien` SELECT r.`id` FROM `nhan_vien` r LEFT JOIN `tmp_sd_x_nhan_vien` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_nhan_su_chuyen_mon` SELECT r.`id` FROM `nhan_su_chuyen_mon` r LEFT JOIN `tmp_sd_x_nhan_su_chuyen_mon` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_hoc_vien` SELECT r.`id` FROM `hoc_vien` r LEFT JOIN `tmp_sd_x_hoc_vien` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_nhan_su_mon` SELECT r.`id` FROM `nhan_su_mon` r LEFT JOIN `tmp_sd_x_nhan_su_mon` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_nhan_su_khu_vuc` SELECT r.`nhan_su_id`,r.`khu_vuc_id` FROM `nhan_su_khu_vuc` r LEFT JOIN `tmp_sd_x_nhan_su_khu_vuc` x ON r.`nhan_su_id`=x.`nhan_su_id` AND r.`khu_vuc_id`=x.`khu_vuc_id` WHERE x.`nhan_su_id` IS NULL;
INSERT INTO `tmp_sd_k_nhan_su_lich_ranh` SELECT r.`id` FROM `nhan_su_lich_ranh` r LEFT JOIN `tmp_sd_x_nhan_su_lich_ranh` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_yeu_cau_hoc` SELECT r.`id` FROM `yeu_cau_hoc` r LEFT JOIN `tmp_sd_x_yeu_cau_hoc` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_yeu_cau_lich` SELECT r.`id` FROM `yeu_cau_lich` r LEFT JOIN `tmp_sd_x_yeu_cau_lich` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_de_xuat_nhan_su` SELECT r.`id` FROM `de_xuat_nhan_su` r LEFT JOIN `tmp_sd_x_de_xuat_nhan_su` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_khoa_hoc` SELECT r.`id` FROM `khoa_hoc` r LEFT JOIN `tmp_sd_x_khoa_hoc` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_dang_ky_khoa_hoc` SELECT r.`id` FROM `dang_ky_khoa_hoc` r LEFT JOIN `tmp_sd_x_dang_ky_khoa_hoc` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_lich_su_don_gia_gio` SELECT r.`id` FROM `lich_su_don_gia_gio` r;
INSERT INTO `tmp_sd_k_lop_hoc` SELECT r.`id` FROM `lop_hoc` r LEFT JOIN `tmp_sd_x_lop_hoc` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_lop_hoc_vien` SELECT r.`id` FROM `lop_hoc_vien` r LEFT JOIN `tmp_sd_x_lop_hoc_vien` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_lich_hoc` SELECT r.`id` FROM `lich_hoc` r LEFT JOIN `tmp_sd_x_lich_hoc` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_buoi_hoc` SELECT r.`id` FROM `buoi_hoc` r LEFT JOIN `tmp_sd_x_buoi_hoc` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_phan_cong_buoi_hoc` SELECT r.`id` FROM `phan_cong_buoi_hoc` r LEFT JOIN `tmp_sd_x_phan_cong_buoi_hoc` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_dieu_chinh_cong` SELECT r.`id` FROM `dieu_chinh_cong` r LEFT JOIN `tmp_sd_x_dieu_chinh_cong` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_don_xin_nghi` SELECT r.`id` FROM `don_xin_nghi` r LEFT JOIN `tmp_sd_x_don_xin_nghi` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_bai_tap` SELECT r.`id` FROM `bai_tap` r LEFT JOIN `tmp_sd_x_bai_tap` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_so_theo_doi_buoi_hoc` SELECT r.`id` FROM `so_theo_doi_buoi_hoc` r LEFT JOIN `tmp_sd_x_so_theo_doi_buoi_hoc` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_ket_qua_hoc_tap` SELECT r.`id` FROM `ket_qua_hoc_tap` r LEFT JOIN `tmp_sd_x_ket_qua_hoc_tap` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_bang_cong_thang` SELECT r.`id` FROM `bang_cong_thang` r;
INSERT INTO `tmp_sd_k_hoa_don` SELECT r.`id` FROM `hoa_don` r;
INSERT INTO `tmp_sd_k_hoa_don_chi_tiet` SELECT r.`id` FROM `hoa_don_chi_tiet` r;
INSERT INTO `tmp_sd_k_thanh_toan` SELECT r.`id` FROM `thanh_toan` r;
INSERT INTO `tmp_sd_k_thong_bao` SELECT r.`id` FROM `thong_bao` r LEFT JOIN `tmp_sd_x_thong_bao` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_audit_log` SELECT r.`id` FROM `audit_log` r;
INSERT INTO `tmp_sd_k_schema_migrations` SELECT r.`version` FROM `schema_migrations` r LEFT JOIN `tmp_sd_x_schema_migrations` x ON r.`version`=x.`version` WHERE x.`version` IS NULL;
INSERT INTO `tmp_sd_k_yeu_cau_nghi_lop` SELECT r.`id` FROM `yeu_cau_nghi_lop` r LEFT JOIN `tmp_sd_x_yeu_cau_nghi_lop` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_hoc_bu_hoc_vien` SELECT r.`id` FROM `hoc_bu_hoc_vien` r LEFT JOIN `tmp_sd_x_hoc_bu_hoc_vien` x ON r.`id`=x.`id` WHERE x.`id` IS NULL;
INSERT INTO `tmp_sd_k_giao_dich_truc_tuyen` SELECT r.`id` FROM `giao_dich_truc_tuyen` r;
INSERT INTO `tmp_sd_k_su_kien_thanh_toan` SELECT r.`id` FROM `su_kien_thanh_toan` r;
INSERT IGNORE INTO `tmp_sd_k_phan_cong_buoi_hoc` SELECT id FROM phan_cong_buoi_hoc WHERE checkin_luc IS NOT NULL OR checkout_luc IS NOT NULL OR bang_cong_id IS NOT NULL;
INSERT IGNORE INTO `tmp_sd_k_phan_cong_buoi_hoc` SELECT p.id FROM phan_cong_buoi_hoc p JOIN buoi_hoc b ON b.id=p.buoi_hoc_id JOIN bang_cong_thang bc ON bc.user_id=p.user_id AND bc.thang=MONTH(b.ngay) AND bc.nam=YEAR(b.ngay) WHERE bc.trang_thai<>'mo';
INSERT IGNORE INTO `tmp_sd_k_buoi_hoc` SELECT id FROM buoi_hoc WHERE trang_thai IN ('dang_day','da_day');
INSERT IGNORE INTO `tmp_sd_k_so_theo_doi_buoi_hoc` SELECT id FROM so_theo_doi_buoi_hoc;
INSERT IGNORE INTO `tmp_sd_k_ket_qua_hoc_tap` SELECT id FROM ket_qua_hoc_tap;
-- B3. Giu them cac dong cha duoc lich su/ho so ngoai demo tham chieu.
WHILE v_thay_doi>0 DO
    SET v_thay_doi=0;
    INSERT IGNORE INTO `tmp_sd_k_role_group` SELECT p.`group_id` FROM `role_group` p JOIN `account` c ON c.`group_id`=p.`group_id` JOIN `tmp_sd_k_account` k ON c.`account_id`=k.`account_id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `account` c ON c.`user_id`=p.`user_id` JOIN `tmp_sd_k_account` k ON c.`account_id`=k.`account_id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `audit_log` c ON c.`user_id`=p.`user_id` JOIN `tmp_sd_k_audit_log` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_buoi_hoc` SELECT p.`id` FROM `buoi_hoc` p JOIN `bai_tap` c ON c.`buoi_hoc_id`=p.`id` JOIN `tmp_sd_k_bai_tap` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `bai_tap` c ON c.`nguoi_tao_id`=p.`user_id` JOIN `tmp_sd_k_bai_tap` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `bang_cong_thang` c ON c.`mo_khoa_boi`=p.`user_id` JOIN `tmp_sd_k_bang_cong_thang` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `bang_cong_thang` c ON c.`tra_boi`=p.`user_id` JOIN `tmp_sd_k_bang_cong_thang` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `bang_cong_thang` c ON c.`chot_boi`=p.`user_id` JOIN `tmp_sd_k_bang_cong_thang` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `bang_cong_thang` c ON c.`user_id`=p.`user_id` JOIN `tmp_sd_k_bang_cong_thang` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_co_so` SELECT p.`id` FROM `co_so` p JOIN `buoi_hoc` c ON c.`co_so_id`=p.`id` JOIN `tmp_sd_k_buoi_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    DELETE FROM tmp_sd_buoi_copy;
    INSERT INTO tmp_sd_buoi_copy SELECT id FROM `tmp_sd_k_buoi_hoc`;
    INSERT IGNORE INTO `tmp_sd_k_buoi_hoc` SELECT p.`id` FROM `buoi_hoc` p JOIN `buoi_hoc` c ON c.`buoi_goc_id`=p.`id` JOIN `tmp_sd_buoi_copy` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_lich_hoc` SELECT p.`id` FROM `lich_hoc` p JOIN `buoi_hoc` c ON c.`lich_hoc_id`=p.`id` JOIN `tmp_sd_k_buoi_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_lop_hoc` SELECT p.`id` FROM `lop_hoc` p JOIN `buoi_hoc` c ON c.`lop_id`=p.`id` JOIN `tmp_sd_k_buoi_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `buoi_hoc` c ON c.`nguoi_huy_id`=p.`user_id` JOIN `tmp_sd_k_buoi_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_khu_vuc` SELECT p.`id` FROM `khu_vuc` p JOIN `co_so` c ON c.`khu_vuc_id`=p.`id` JOIN `tmp_sd_k_co_so` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `co_so` c ON c.`quan_ly_id`=p.`user_id` JOIN `tmp_sd_k_co_so` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_hoc_vien` SELECT p.`id` FROM `hoc_vien` p JOIN `dang_ky_khoa_hoc` c ON c.`hoc_vien_id`=p.`id` JOIN `tmp_sd_k_dang_ky_khoa_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_khoa_hoc` SELECT p.`id` FROM `khoa_hoc` p JOIN `dang_ky_khoa_hoc` c ON c.`khoa_hoc_id`=p.`id` JOIN `tmp_sd_k_dang_ky_khoa_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_nhan_su_chuyen_mon` SELECT p.`id` FROM `nhan_su_chuyen_mon` p JOIN `de_xuat_nhan_su` c ON c.`nhan_su_id`=p.`id` JOIN `tmp_sd_k_de_xuat_nhan_su` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_nhan_vien` SELECT p.`id` FROM `nhan_vien` p JOIN `de_xuat_nhan_su` c ON c.`nhan_vien_id`=p.`id` JOIN `tmp_sd_k_de_xuat_nhan_su` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `de_xuat_nhan_su` c ON c.`phu_huynh_phan_hoi_boi`=p.`user_id` JOIN `tmp_sd_k_de_xuat_nhan_su` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_yeu_cau_hoc` SELECT p.`id` FROM `yeu_cau_hoc` p JOIN `de_xuat_nhan_su` c ON c.`yeu_cau_id`=p.`id` JOIN `tmp_sd_k_de_xuat_nhan_su` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `dieu_chinh_cong` c ON c.`nguoi_duyet_id`=p.`user_id` JOIN `tmp_sd_k_dieu_chinh_cong` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_phan_cong_buoi_hoc` SELECT p.`id` FROM `phan_cong_buoi_hoc` p JOIN `dieu_chinh_cong` c ON c.`phan_cong_id`=p.`id` JOIN `tmp_sd_k_dieu_chinh_cong` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `dieu_chinh_cong` c ON c.`nguoi_yeu_cau_id`=p.`user_id` JOIN `tmp_sd_k_dieu_chinh_cong` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_buoi_hoc` SELECT p.`id` FROM `buoi_hoc` p JOIN `don_xin_nghi` c ON c.`buoi_bu_id`=p.`id` JOIN `tmp_sd_k_don_xin_nghi` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_buoi_hoc` SELECT p.`id` FROM `buoi_hoc` p JOIN `don_xin_nghi` c ON c.`buoi_hoc_id`=p.`id` JOIN `tmp_sd_k_don_xin_nghi` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `don_xin_nghi` c ON c.`nguoi_duyet_id`=p.`user_id` JOIN `tmp_sd_k_don_xin_nghi` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `don_xin_nghi` c ON c.`nguoi_gui_id`=p.`user_id` JOIN `tmp_sd_k_don_xin_nghi` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_hoc_vien` SELECT p.`id` FROM `hoc_vien` p JOIN `don_xin_nghi` c ON c.`hoc_vien_id`=p.`id` JOIN `tmp_sd_k_don_xin_nghi` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `giao_dich_truc_tuyen` c ON c.`xac_nhan_boi`=p.`user_id` JOIN `tmp_sd_k_giao_dich_truc_tuyen` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_hoa_don` SELECT p.`id` FROM `hoa_don` p JOIN `giao_dich_truc_tuyen` c ON c.`hoa_don_id`=p.`id` JOIN `tmp_sd_k_giao_dich_truc_tuyen` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `giao_dich_truc_tuyen` c ON c.`phu_huynh_id`=p.`user_id` JOIN `tmp_sd_k_giao_dich_truc_tuyen` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_thanh_toan` SELECT p.`id` FROM `thanh_toan` p JOIN `giao_dich_truc_tuyen` c ON c.`thanh_toan_id`=p.`id` JOIN `tmp_sd_k_giao_dich_truc_tuyen` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_dang_ky_khoa_hoc` SELECT p.`id` FROM `dang_ky_khoa_hoc` p JOIN `hoa_don` c ON c.`dang_ky_khoa_hoc_id`=p.`id` JOIN `tmp_sd_k_hoa_don` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `hoa_don` c ON c.`huy_boi`=p.`user_id` JOIN `tmp_sd_k_hoa_don` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_lop_hoc_vien` SELECT p.`id` FROM `lop_hoc_vien` p JOIN `hoa_don` c ON c.`lop_hoc_vien_id`=p.`id` JOIN `tmp_sd_k_hoa_don` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `hoa_don` c ON c.`phat_hanh_boi`=p.`user_id` JOIN `tmp_sd_k_hoa_don` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `hoa_don` c ON c.`nguoi_lap_id`=p.`user_id` JOIN `tmp_sd_k_hoa_don` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_buoi_hoc` SELECT p.`id` FROM `buoi_hoc` p JOIN `hoa_don_chi_tiet` c ON c.`buoi_hoc_id`=p.`id` JOIN `tmp_sd_k_hoa_don_chi_tiet` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_hoa_don` SELECT p.`id` FROM `hoa_don` p JOIN `hoa_don_chi_tiet` c ON c.`hoa_don_id`=p.`id` JOIN `tmp_sd_k_hoa_don_chi_tiet` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_buoi_hoc` SELECT p.`id` FROM `buoi_hoc` p JOIN `hoc_bu_hoc_vien` c ON c.`buoi_bu_id`=p.`id` JOIN `tmp_sd_k_hoc_bu_hoc_vien` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_don_xin_nghi` SELECT p.`id` FROM `don_xin_nghi` p JOIN `hoc_bu_hoc_vien` c ON c.`don_xin_nghi_id`=p.`id` JOIN `tmp_sd_k_hoc_bu_hoc_vien` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_hoc_vien` SELECT p.`id` FROM `hoc_vien` p JOIN `hoc_bu_hoc_vien` c ON c.`hoc_vien_id`=p.`id` JOIN `tmp_sd_k_hoc_bu_hoc_vien` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `hoc_bu_hoc_vien` c ON c.`xep_boi`=p.`user_id` JOIN `tmp_sd_k_hoc_bu_hoc_vien` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_cap_lop` SELECT p.`id` FROM `cap_lop` p JOIN `hoc_vien` c ON c.`cap_lop_id`=p.`id` JOIN `tmp_sd_k_hoc_vien` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_khu_vuc` SELECT p.`id` FROM `khu_vuc` p JOIN `hoc_vien` c ON c.`khu_vuc_id`=p.`id` JOIN `tmp_sd_k_hoc_vien` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `hoc_vien` c ON c.`phu_huynh_user_id`=p.`user_id` JOIN `tmp_sd_k_hoc_vien` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_hoc_vien` SELECT p.`id` FROM `hoc_vien` p JOIN `ket_qua_hoc_tap` c ON c.`hoc_vien_id`=p.`id` JOIN `tmp_sd_k_ket_qua_hoc_tap` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_lop_hoc` SELECT p.`id` FROM `lop_hoc` p JOIN `ket_qua_hoc_tap` c ON c.`lop_id`=p.`id` JOIN `tmp_sd_k_ket_qua_hoc_tap` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `ket_qua_hoc_tap` c ON c.`nguoi_nhap_id`=p.`user_id` JOIN `tmp_sd_k_ket_qua_hoc_tap` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_lop_hoc` SELECT p.`id` FROM `lop_hoc` p JOIN `lich_hoc` c ON c.`lop_id`=p.`id` JOIN `tmp_sd_k_lich_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `lich_su_don_gia_gio` c ON c.`nguoi_dat_id`=p.`user_id` JOIN `tmp_sd_k_lich_su_don_gia_gio` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_nhan_su_chuyen_mon` SELECT p.`id` FROM `nhan_su_chuyen_mon` p JOIN `lich_su_don_gia_gio` c ON c.`nhan_su_id`=p.`id` JOIN `tmp_sd_k_lich_su_don_gia_gio` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_cap_lop` SELECT p.`id` FROM `cap_lop` p JOIN `lop_hoc` c ON c.`cap_lop_id`=p.`id` JOIN `tmp_sd_k_lop_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_co_so` SELECT p.`id` FROM `co_so` p JOIN `lop_hoc` c ON c.`co_so_id`=p.`id` JOIN `tmp_sd_k_lop_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_de_xuat_nhan_su` SELECT p.`id` FROM `de_xuat_nhan_su` p JOIN `lop_hoc` c ON c.`de_xuat_id`=p.`id` JOIN `tmp_sd_k_lop_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_khoa_hoc` SELECT p.`id` FROM `khoa_hoc` p JOIN `lop_hoc` c ON c.`khoa_hoc_id`=p.`id` JOIN `tmp_sd_k_lop_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_khu_vuc` SELECT p.`id` FROM `khu_vuc` p JOIN `lop_hoc` c ON c.`khu_vuc_id`=p.`id` JOIN `tmp_sd_k_lop_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_mon_hoc` SELECT p.`id` FROM `mon_hoc` p JOIN `lop_hoc` c ON c.`mon_hoc_id`=p.`id` JOIN `tmp_sd_k_lop_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_nhan_su_chuyen_mon` SELECT p.`id` FROM `nhan_su_chuyen_mon` p JOIN `lop_hoc` c ON c.`nguoi_day_chinh_id`=p.`id` JOIN `tmp_sd_k_lop_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_nhan_vien` SELECT p.`id` FROM `nhan_vien` p JOIN `lop_hoc` c ON c.`nhan_vien_id`=p.`id` JOIN `tmp_sd_k_lop_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `lop_hoc_vien` c ON c.`xep_boi`=p.`user_id` JOIN `tmp_sd_k_lop_hoc_vien` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_dang_ky_khoa_hoc` SELECT p.`id` FROM `dang_ky_khoa_hoc` p JOIN `lop_hoc_vien` c ON c.`dang_ky_khoa_hoc_id`=p.`id` JOIN `tmp_sd_k_lop_hoc_vien` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_hoc_vien` SELECT p.`id` FROM `hoc_vien` p JOIN `lop_hoc_vien` c ON c.`hoc_vien_id`=p.`id` JOIN `tmp_sd_k_lop_hoc_vien` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_lop_hoc` SELECT p.`id` FROM `lop_hoc` p JOIN `lop_hoc_vien` c ON c.`lop_id`=p.`id` JOIN `tmp_sd_k_lop_hoc_vien` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `nhan_su_chuyen_mon` c ON c.`user_id`=p.`user_id` JOIN `tmp_sd_k_nhan_su_chuyen_mon` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_khu_vuc` SELECT p.`id` FROM `khu_vuc` p JOIN `nhan_su_khu_vuc` c ON c.`khu_vuc_id`=p.`id` JOIN `tmp_sd_k_nhan_su_khu_vuc` k ON c.`nhan_su_id`=k.`nhan_su_id` AND c.`khu_vuc_id`=k.`khu_vuc_id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_nhan_su_chuyen_mon` SELECT p.`id` FROM `nhan_su_chuyen_mon` p JOIN `nhan_su_khu_vuc` c ON c.`nhan_su_id`=p.`id` JOIN `tmp_sd_k_nhan_su_khu_vuc` k ON c.`nhan_su_id`=k.`nhan_su_id` AND c.`khu_vuc_id`=k.`khu_vuc_id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_nhan_su_chuyen_mon` SELECT p.`id` FROM `nhan_su_chuyen_mon` p JOIN `nhan_su_lich_ranh` c ON c.`nhan_su_id`=p.`id` JOIN `tmp_sd_k_nhan_su_lich_ranh` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_cap_lop` SELECT p.`id` FROM `cap_lop` p JOIN `nhan_su_mon` c ON c.`cap_lop_den`=p.`id` JOIN `tmp_sd_k_nhan_su_mon` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_mon_hoc` SELECT p.`id` FROM `mon_hoc` p JOIN `nhan_su_mon` c ON c.`mon_hoc_id`=p.`id` JOIN `tmp_sd_k_nhan_su_mon` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_nhan_su_chuyen_mon` SELECT p.`id` FROM `nhan_su_chuyen_mon` p JOIN `nhan_su_mon` c ON c.`nhan_su_id`=p.`id` JOIN `tmp_sd_k_nhan_su_mon` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_cap_lop` SELECT p.`id` FROM `cap_lop` p JOIN `nhan_su_mon` c ON c.`cap_lop_tu`=p.`id` JOIN `tmp_sd_k_nhan_su_mon` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_co_so` SELECT p.`id` FROM `co_so` p JOIN `nhan_vien` c ON c.`co_so_id`=p.`id` JOIN `tmp_sd_k_nhan_vien` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `nhan_vien` c ON c.`user_id`=p.`user_id` JOIN `tmp_sd_k_nhan_vien` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_bang_cong_thang` SELECT p.`id` FROM `bang_cong_thang` p JOIN `phan_cong_buoi_hoc` c ON c.`bang_cong_id`=p.`id` JOIN `tmp_sd_k_phan_cong_buoi_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_buoi_hoc` SELECT p.`id` FROM `buoi_hoc` p JOIN `phan_cong_buoi_hoc` c ON c.`buoi_hoc_id`=p.`id` JOIN `tmp_sd_k_phan_cong_buoi_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `phan_cong_buoi_hoc` c ON c.`go_boi`=p.`user_id` JOIN `tmp_sd_k_phan_cong_buoi_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `phan_cong_buoi_hoc` c ON c.`luong_sua_boi`=p.`user_id` JOIN `tmp_sd_k_phan_cong_buoi_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `phan_cong_buoi_hoc` c ON c.`phan_cong_boi`=p.`user_id` JOIN `tmp_sd_k_phan_cong_buoi_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `phan_cong_buoi_hoc` c ON c.`user_id`=p.`user_id` JOIN `tmp_sd_k_phan_cong_buoi_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_function` SELECT p.`function_id` FROM `function` p JOIN `role_group_function` c ON c.`function_id`=p.`function_id` JOIN `tmp_sd_k_role_group_function` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_role_group` SELECT p.`group_id` FROM `role_group` p JOIN `role_group_function` c ON c.`group_id`=p.`group_id` JOIN `tmp_sd_k_role_group_function` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_bai_tap` SELECT p.`id` FROM `bai_tap` p JOIN `so_theo_doi_buoi_hoc` c ON c.`bai_tap_id`=p.`id` JOIN `tmp_sd_k_so_theo_doi_buoi_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_buoi_hoc` SELECT p.`id` FROM `buoi_hoc` p JOIN `so_theo_doi_buoi_hoc` c ON c.`buoi_hoc_id`=p.`id` JOIN `tmp_sd_k_so_theo_doi_buoi_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_hoc_vien` SELECT p.`id` FROM `hoc_vien` p JOIN `so_theo_doi_buoi_hoc` c ON c.`hoc_vien_id`=p.`id` JOIN `tmp_sd_k_so_theo_doi_buoi_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `so_theo_doi_buoi_hoc` c ON c.`nguoi_nhap_id`=p.`user_id` JOIN `tmp_sd_k_so_theo_doi_buoi_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_giao_dich_truc_tuyen` SELECT p.`id` FROM `giao_dich_truc_tuyen` p JOIN `su_kien_thanh_toan` c ON c.`giao_dich_id`=p.`id` AND c.`nha_cung_cap`=p.`nha_cung_cap` JOIN `tmp_sd_k_su_kien_thanh_toan` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_hoa_don` SELECT p.`id` FROM `hoa_don` p JOIN `thanh_toan` c ON c.`hoa_don_id`=p.`id` JOIN `tmp_sd_k_thanh_toan` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `thanh_toan` c ON c.`huy_boi`=p.`user_id` JOIN `tmp_sd_k_thanh_toan` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `thanh_toan` c ON c.`nguoi_thu_id`=p.`user_id` JOIN `tmp_sd_k_thanh_toan` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `thong_bao` c ON c.`user_id`=p.`user_id` JOIN `tmp_sd_k_thong_bao` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_khu_vuc` SELECT p.`id` FROM `khu_vuc` p JOIN `users` c ON c.`khu_vuc_id`=p.`id` JOIN `tmp_sd_k_users` k ON c.`user_id`=k.`user_id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_cap_lop` SELECT p.`id` FROM `cap_lop` p JOIN `yeu_cau_hoc` c ON c.`cap_lop_id`=p.`id` JOIN `tmp_sd_k_yeu_cau_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_co_so` SELECT p.`id` FROM `co_so` p JOIN `yeu_cau_hoc` c ON c.`co_so_mong_muon_id`=p.`id` JOIN `tmp_sd_k_yeu_cau_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `yeu_cau_hoc` c ON c.`nguoi_gui_id`=p.`user_id` JOIN `tmp_sd_k_yeu_cau_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_hoc_vien` SELECT p.`id` FROM `hoc_vien` p JOIN `yeu_cau_hoc` c ON c.`hoc_vien_id`=p.`id` JOIN `tmp_sd_k_yeu_cau_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_khu_vuc` SELECT p.`id` FROM `khu_vuc` p JOIN `yeu_cau_hoc` c ON c.`khu_vuc_id`=p.`id` JOIN `tmp_sd_k_yeu_cau_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_mon_hoc` SELECT p.`id` FROM `mon_hoc` p JOIN `yeu_cau_hoc` c ON c.`mon_hoc_id`=p.`id` JOIN `tmp_sd_k_yeu_cau_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_nhan_vien` SELECT p.`id` FROM `nhan_vien` p JOIN `yeu_cau_hoc` c ON c.`nhan_vien_id`=p.`id` JOIN `tmp_sd_k_yeu_cau_hoc` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_yeu_cau_hoc` SELECT p.`id` FROM `yeu_cau_hoc` p JOIN `yeu_cau_lich` c ON c.`yeu_cau_id`=p.`id` JOIN `tmp_sd_k_yeu_cau_lich` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_buoi_hoc` SELECT p.`id` FROM `buoi_hoc` p JOIN `yeu_cau_nghi_lop` c ON c.`buoi_hoc_id`=p.`id` JOIN `tmp_sd_k_yeu_cau_nghi_lop` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `yeu_cau_nghi_lop` c ON c.`nguoi_duyet_id`=p.`user_id` JOIN `tmp_sd_k_yeu_cau_nghi_lop` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
    INSERT IGNORE INTO `tmp_sd_k_users` SELECT p.`user_id` FROM `users` p JOIN `yeu_cau_nghi_lop` c ON c.`nguoi_gui_id`=p.`user_id` JOIN `tmp_sd_k_yeu_cau_nghi_lop` k ON c.`id`=k.`id`;
    SET v_thay_doi=v_thay_doi+ROW_COUNT();
END WHILE;
-- B4. Bo cac ID can giu khoi danh sach xoa, sau do xoa con truoc cha.
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'users',COUNT(*) FROM `tmp_sd_x_users`;
DELETE x FROM `tmp_sd_x_users` x JOIN `tmp_sd_k_users` k ON x.`user_id`=k.`user_id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_users`) WHERE bang='users';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'role_group',COUNT(*) FROM `tmp_sd_x_role_group`;
DELETE x FROM `tmp_sd_x_role_group` x JOIN `tmp_sd_k_role_group` k ON x.`group_id`=k.`group_id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_role_group`) WHERE bang='role_group';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'account',COUNT(*) FROM `tmp_sd_x_account`;
DELETE x FROM `tmp_sd_x_account` x JOIN `tmp_sd_k_account` k ON x.`account_id`=k.`account_id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_account`) WHERE bang='account';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'function',COUNT(*) FROM `tmp_sd_x_function`;
DELETE x FROM `tmp_sd_x_function` x JOIN `tmp_sd_k_function` k ON x.`function_id`=k.`function_id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_function`) WHERE bang='function';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'role_group_function',COUNT(*) FROM `tmp_sd_x_role_group_function`;
DELETE x FROM `tmp_sd_x_role_group_function` x JOIN `tmp_sd_k_role_group_function` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_role_group_function`) WHERE bang='role_group_function';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'mon_hoc',COUNT(*) FROM `tmp_sd_x_mon_hoc`;
DELETE x FROM `tmp_sd_x_mon_hoc` x JOIN `tmp_sd_k_mon_hoc` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_mon_hoc`) WHERE bang='mon_hoc';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'cap_lop',COUNT(*) FROM `tmp_sd_x_cap_lop`;
DELETE x FROM `tmp_sd_x_cap_lop` x JOIN `tmp_sd_k_cap_lop` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_cap_lop`) WHERE bang='cap_lop';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'khu_vuc',COUNT(*) FROM `tmp_sd_x_khu_vuc`;
DELETE x FROM `tmp_sd_x_khu_vuc` x JOIN `tmp_sd_k_khu_vuc` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_khu_vuc`) WHERE bang='khu_vuc';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'co_so',COUNT(*) FROM `tmp_sd_x_co_so`;
DELETE x FROM `tmp_sd_x_co_so` x JOIN `tmp_sd_k_co_so` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_co_so`) WHERE bang='co_so';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'cau_hinh',COUNT(*) FROM `tmp_sd_x_cau_hinh`;
DELETE x FROM `tmp_sd_x_cau_hinh` x JOIN `tmp_sd_k_cau_hinh` k ON x.`khoa`=k.`khoa`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_cau_hinh`) WHERE bang='cau_hinh';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'nhan_vien',COUNT(*) FROM `tmp_sd_x_nhan_vien`;
DELETE x FROM `tmp_sd_x_nhan_vien` x JOIN `tmp_sd_k_nhan_vien` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_nhan_vien`) WHERE bang='nhan_vien';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'nhan_su_chuyen_mon',COUNT(*) FROM `tmp_sd_x_nhan_su_chuyen_mon`;
DELETE x FROM `tmp_sd_x_nhan_su_chuyen_mon` x JOIN `tmp_sd_k_nhan_su_chuyen_mon` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_nhan_su_chuyen_mon`) WHERE bang='nhan_su_chuyen_mon';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'hoc_vien',COUNT(*) FROM `tmp_sd_x_hoc_vien`;
DELETE x FROM `tmp_sd_x_hoc_vien` x JOIN `tmp_sd_k_hoc_vien` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_hoc_vien`) WHERE bang='hoc_vien';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'nhan_su_mon',COUNT(*) FROM `tmp_sd_x_nhan_su_mon`;
DELETE x FROM `tmp_sd_x_nhan_su_mon` x JOIN `tmp_sd_k_nhan_su_mon` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_nhan_su_mon`) WHERE bang='nhan_su_mon';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'nhan_su_khu_vuc',COUNT(*) FROM `tmp_sd_x_nhan_su_khu_vuc`;
DELETE x FROM `tmp_sd_x_nhan_su_khu_vuc` x JOIN `tmp_sd_k_nhan_su_khu_vuc` k ON x.`nhan_su_id`=k.`nhan_su_id` AND x.`khu_vuc_id`=k.`khu_vuc_id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_nhan_su_khu_vuc`) WHERE bang='nhan_su_khu_vuc';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'nhan_su_lich_ranh',COUNT(*) FROM `tmp_sd_x_nhan_su_lich_ranh`;
DELETE x FROM `tmp_sd_x_nhan_su_lich_ranh` x JOIN `tmp_sd_k_nhan_su_lich_ranh` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_nhan_su_lich_ranh`) WHERE bang='nhan_su_lich_ranh';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'yeu_cau_hoc',COUNT(*) FROM `tmp_sd_x_yeu_cau_hoc`;
DELETE x FROM `tmp_sd_x_yeu_cau_hoc` x JOIN `tmp_sd_k_yeu_cau_hoc` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_yeu_cau_hoc`) WHERE bang='yeu_cau_hoc';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'yeu_cau_lich',COUNT(*) FROM `tmp_sd_x_yeu_cau_lich`;
DELETE x FROM `tmp_sd_x_yeu_cau_lich` x JOIN `tmp_sd_k_yeu_cau_lich` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_yeu_cau_lich`) WHERE bang='yeu_cau_lich';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'de_xuat_nhan_su',COUNT(*) FROM `tmp_sd_x_de_xuat_nhan_su`;
DELETE x FROM `tmp_sd_x_de_xuat_nhan_su` x JOIN `tmp_sd_k_de_xuat_nhan_su` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_de_xuat_nhan_su`) WHERE bang='de_xuat_nhan_su';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'khoa_hoc',COUNT(*) FROM `tmp_sd_x_khoa_hoc`;
DELETE x FROM `tmp_sd_x_khoa_hoc` x JOIN `tmp_sd_k_khoa_hoc` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_khoa_hoc`) WHERE bang='khoa_hoc';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'dang_ky_khoa_hoc',COUNT(*) FROM `tmp_sd_x_dang_ky_khoa_hoc`;
DELETE x FROM `tmp_sd_x_dang_ky_khoa_hoc` x JOIN `tmp_sd_k_dang_ky_khoa_hoc` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_dang_ky_khoa_hoc`) WHERE bang='dang_ky_khoa_hoc';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'lich_su_don_gia_gio',COUNT(*) FROM `tmp_sd_x_lich_su_don_gia_gio`;
DELETE x FROM `tmp_sd_x_lich_su_don_gia_gio` x JOIN `tmp_sd_k_lich_su_don_gia_gio` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_lich_su_don_gia_gio`) WHERE bang='lich_su_don_gia_gio';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'lop_hoc',COUNT(*) FROM `tmp_sd_x_lop_hoc`;
DELETE x FROM `tmp_sd_x_lop_hoc` x JOIN `tmp_sd_k_lop_hoc` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_lop_hoc`) WHERE bang='lop_hoc';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'lop_hoc_vien',COUNT(*) FROM `tmp_sd_x_lop_hoc_vien`;
DELETE x FROM `tmp_sd_x_lop_hoc_vien` x JOIN `tmp_sd_k_lop_hoc_vien` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_lop_hoc_vien`) WHERE bang='lop_hoc_vien';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'lich_hoc',COUNT(*) FROM `tmp_sd_x_lich_hoc`;
DELETE x FROM `tmp_sd_x_lich_hoc` x JOIN `tmp_sd_k_lich_hoc` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_lich_hoc`) WHERE bang='lich_hoc';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'buoi_hoc',COUNT(*) FROM `tmp_sd_x_buoi_hoc`;
DELETE x FROM `tmp_sd_x_buoi_hoc` x JOIN `tmp_sd_k_buoi_hoc` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_buoi_hoc`) WHERE bang='buoi_hoc';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'phan_cong_buoi_hoc',COUNT(*) FROM `tmp_sd_x_phan_cong_buoi_hoc`;
DELETE x FROM `tmp_sd_x_phan_cong_buoi_hoc` x JOIN `tmp_sd_k_phan_cong_buoi_hoc` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_phan_cong_buoi_hoc`) WHERE bang='phan_cong_buoi_hoc';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'dieu_chinh_cong',COUNT(*) FROM `tmp_sd_x_dieu_chinh_cong`;
DELETE x FROM `tmp_sd_x_dieu_chinh_cong` x JOIN `tmp_sd_k_dieu_chinh_cong` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_dieu_chinh_cong`) WHERE bang='dieu_chinh_cong';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'don_xin_nghi',COUNT(*) FROM `tmp_sd_x_don_xin_nghi`;
DELETE x FROM `tmp_sd_x_don_xin_nghi` x JOIN `tmp_sd_k_don_xin_nghi` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_don_xin_nghi`) WHERE bang='don_xin_nghi';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'bai_tap',COUNT(*) FROM `tmp_sd_x_bai_tap`;
DELETE x FROM `tmp_sd_x_bai_tap` x JOIN `tmp_sd_k_bai_tap` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_bai_tap`) WHERE bang='bai_tap';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'so_theo_doi_buoi_hoc',COUNT(*) FROM `tmp_sd_x_so_theo_doi_buoi_hoc`;
DELETE x FROM `tmp_sd_x_so_theo_doi_buoi_hoc` x JOIN `tmp_sd_k_so_theo_doi_buoi_hoc` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_so_theo_doi_buoi_hoc`) WHERE bang='so_theo_doi_buoi_hoc';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'ket_qua_hoc_tap',COUNT(*) FROM `tmp_sd_x_ket_qua_hoc_tap`;
DELETE x FROM `tmp_sd_x_ket_qua_hoc_tap` x JOIN `tmp_sd_k_ket_qua_hoc_tap` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_ket_qua_hoc_tap`) WHERE bang='ket_qua_hoc_tap';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'bang_cong_thang',COUNT(*) FROM `tmp_sd_x_bang_cong_thang`;
DELETE x FROM `tmp_sd_x_bang_cong_thang` x JOIN `tmp_sd_k_bang_cong_thang` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_bang_cong_thang`) WHERE bang='bang_cong_thang';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'hoa_don',COUNT(*) FROM `tmp_sd_x_hoa_don`;
DELETE x FROM `tmp_sd_x_hoa_don` x JOIN `tmp_sd_k_hoa_don` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_hoa_don`) WHERE bang='hoa_don';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'hoa_don_chi_tiet',COUNT(*) FROM `tmp_sd_x_hoa_don_chi_tiet`;
DELETE x FROM `tmp_sd_x_hoa_don_chi_tiet` x JOIN `tmp_sd_k_hoa_don_chi_tiet` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_hoa_don_chi_tiet`) WHERE bang='hoa_don_chi_tiet';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'thanh_toan',COUNT(*) FROM `tmp_sd_x_thanh_toan`;
DELETE x FROM `tmp_sd_x_thanh_toan` x JOIN `tmp_sd_k_thanh_toan` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_thanh_toan`) WHERE bang='thanh_toan';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'thong_bao',COUNT(*) FROM `tmp_sd_x_thong_bao`;
DELETE x FROM `tmp_sd_x_thong_bao` x JOIN `tmp_sd_k_thong_bao` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_thong_bao`) WHERE bang='thong_bao';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'audit_log',COUNT(*) FROM `tmp_sd_x_audit_log`;
DELETE x FROM `tmp_sd_x_audit_log` x JOIN `tmp_sd_k_audit_log` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_audit_log`) WHERE bang='audit_log';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'schema_migrations',COUNT(*) FROM `tmp_sd_x_schema_migrations`;
DELETE x FROM `tmp_sd_x_schema_migrations` x JOIN `tmp_sd_k_schema_migrations` k ON x.`version`=k.`version`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_schema_migrations`) WHERE bang='schema_migrations';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'yeu_cau_nghi_lop',COUNT(*) FROM `tmp_sd_x_yeu_cau_nghi_lop`;
DELETE x FROM `tmp_sd_x_yeu_cau_nghi_lop` x JOIN `tmp_sd_k_yeu_cau_nghi_lop` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_yeu_cau_nghi_lop`) WHERE bang='yeu_cau_nghi_lop';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'hoc_bu_hoc_vien',COUNT(*) FROM `tmp_sd_x_hoc_bu_hoc_vien`;
DELETE x FROM `tmp_sd_x_hoc_bu_hoc_vien` x JOIN `tmp_sd_k_hoc_bu_hoc_vien` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_hoc_bu_hoc_vien`) WHERE bang='hoc_bu_hoc_vien';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'giao_dich_truc_tuyen',COUNT(*) FROM `tmp_sd_x_giao_dich_truc_tuyen`;
DELETE x FROM `tmp_sd_x_giao_dich_truc_tuyen` x JOIN `tmp_sd_k_giao_dich_truc_tuyen` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_giao_dich_truc_tuyen`) WHERE bang='giao_dich_truc_tuyen';
INSERT INTO tmp_sd_ket_qua(bang,chon_xoa) SELECT 'su_kien_thanh_toan',COUNT(*) FROM `tmp_sd_x_su_kien_thanh_toan`;
DELETE x FROM `tmp_sd_x_su_kien_thanh_toan` x JOIN `tmp_sd_k_su_kien_thanh_toan` k ON x.`id`=k.`id`;
UPDATE tmp_sd_ket_qua SET giu_lai=chon_xoa-(SELECT COUNT(*) FROM `tmp_sd_x_su_kien_thanh_toan`) WHERE bang='su_kien_thanh_toan';
DELETE r FROM `thong_bao` r JOIN `tmp_sd_x_thong_bao` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='thong_bao';
DELETE r FROM `hoc_bu_hoc_vien` r JOIN `tmp_sd_x_hoc_bu_hoc_vien` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='hoc_bu_hoc_vien';
DELETE r FROM `dieu_chinh_cong` r JOIN `tmp_sd_x_dieu_chinh_cong` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='dieu_chinh_cong';
DELETE r FROM `don_xin_nghi` r JOIN `tmp_sd_x_don_xin_nghi` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='don_xin_nghi';
DELETE r FROM `yeu_cau_nghi_lop` r JOIN `tmp_sd_x_yeu_cau_nghi_lop` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='yeu_cau_nghi_lop';
DELETE r FROM `so_theo_doi_buoi_hoc` r JOIN `tmp_sd_x_so_theo_doi_buoi_hoc` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='so_theo_doi_buoi_hoc';
DELETE r FROM `ket_qua_hoc_tap` r JOIN `tmp_sd_x_ket_qua_hoc_tap` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='ket_qua_hoc_tap';
DELETE r FROM `bai_tap` r JOIN `tmp_sd_x_bai_tap` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='bai_tap';
DELETE r FROM `phan_cong_buoi_hoc` r JOIN `tmp_sd_x_phan_cong_buoi_hoc` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='phan_cong_buoi_hoc';
DELETE r FROM buoi_hoc r JOIN `tmp_sd_x_buoi_hoc` x ON r.id=x.id WHERE r.loai='bu';
UPDATE tmp_sd_ket_qua SET da_xoa=ROW_COUNT() WHERE bang='buoi_hoc';
DELETE r FROM `buoi_hoc` r JOIN `tmp_sd_x_buoi_hoc` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='buoi_hoc';
DELETE r FROM `lich_hoc` r JOIN `tmp_sd_x_lich_hoc` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='lich_hoc';
DELETE r FROM `lop_hoc_vien` r JOIN `tmp_sd_x_lop_hoc_vien` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='lop_hoc_vien';
DELETE r FROM `lop_hoc` r JOIN `tmp_sd_x_lop_hoc` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='lop_hoc';
DELETE r FROM `de_xuat_nhan_su` r JOIN `tmp_sd_x_de_xuat_nhan_su` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='de_xuat_nhan_su';
DELETE r FROM `yeu_cau_lich` r JOIN `tmp_sd_x_yeu_cau_lich` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='yeu_cau_lich';
DELETE r FROM `yeu_cau_hoc` r JOIN `tmp_sd_x_yeu_cau_hoc` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='yeu_cau_hoc';
DELETE r FROM `dang_ky_khoa_hoc` r JOIN `tmp_sd_x_dang_ky_khoa_hoc` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='dang_ky_khoa_hoc';
DELETE r FROM `khoa_hoc` r JOIN `tmp_sd_x_khoa_hoc` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='khoa_hoc';
DELETE r FROM `nhan_su_lich_ranh` r JOIN `tmp_sd_x_nhan_su_lich_ranh` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='nhan_su_lich_ranh';
DELETE r FROM `nhan_su_mon` r JOIN `tmp_sd_x_nhan_su_mon` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='nhan_su_mon';
DELETE r FROM `nhan_su_khu_vuc` r JOIN `tmp_sd_x_nhan_su_khu_vuc` x ON r.`nhan_su_id`=x.`nhan_su_id` AND r.`khu_vuc_id`=x.`khu_vuc_id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='nhan_su_khu_vuc';
DELETE r FROM `nhan_su_chuyen_mon` r JOIN `tmp_sd_x_nhan_su_chuyen_mon` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='nhan_su_chuyen_mon';
DELETE r FROM `nhan_vien` r JOIN `tmp_sd_x_nhan_vien` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='nhan_vien';
DELETE r FROM `account` r JOIN `tmp_sd_x_account` x ON r.`account_id`=x.`account_id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='account';
DELETE r FROM `co_so` r JOIN `tmp_sd_x_co_so` x ON r.`id`=x.`id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='co_so';
DELETE r FROM `users` r JOIN `tmp_sd_x_users` x ON r.`user_id`=x.`user_id`;
UPDATE tmp_sd_ket_qua SET da_xoa=da_xoa+ROW_COUNT() WHERE bang='users';
COMMIT;
SELECT bang,chon_xoa,giu_lai,da_xoa FROM tmp_sd_ket_qua WHERE chon_xoa>0 ORDER BY bang;
DROP TEMPORARY TABLE `tmp_sd_x_users`;
DROP TEMPORARY TABLE `tmp_sd_k_users`;
DROP TEMPORARY TABLE `tmp_sd_x_role_group`;
DROP TEMPORARY TABLE `tmp_sd_k_role_group`;
DROP TEMPORARY TABLE `tmp_sd_x_account`;
DROP TEMPORARY TABLE `tmp_sd_k_account`;
DROP TEMPORARY TABLE `tmp_sd_x_function`;
DROP TEMPORARY TABLE `tmp_sd_k_function`;
DROP TEMPORARY TABLE `tmp_sd_x_role_group_function`;
DROP TEMPORARY TABLE `tmp_sd_k_role_group_function`;
DROP TEMPORARY TABLE `tmp_sd_x_mon_hoc`;
DROP TEMPORARY TABLE `tmp_sd_k_mon_hoc`;
DROP TEMPORARY TABLE `tmp_sd_x_cap_lop`;
DROP TEMPORARY TABLE `tmp_sd_k_cap_lop`;
DROP TEMPORARY TABLE `tmp_sd_x_khu_vuc`;
DROP TEMPORARY TABLE `tmp_sd_k_khu_vuc`;
DROP TEMPORARY TABLE `tmp_sd_x_co_so`;
DROP TEMPORARY TABLE `tmp_sd_k_co_so`;
DROP TEMPORARY TABLE `tmp_sd_x_cau_hinh`;
DROP TEMPORARY TABLE `tmp_sd_k_cau_hinh`;
DROP TEMPORARY TABLE `tmp_sd_x_nhan_vien`;
DROP TEMPORARY TABLE `tmp_sd_k_nhan_vien`;
DROP TEMPORARY TABLE `tmp_sd_x_nhan_su_chuyen_mon`;
DROP TEMPORARY TABLE `tmp_sd_k_nhan_su_chuyen_mon`;
DROP TEMPORARY TABLE `tmp_sd_x_hoc_vien`;
DROP TEMPORARY TABLE `tmp_sd_k_hoc_vien`;
DROP TEMPORARY TABLE `tmp_sd_x_nhan_su_mon`;
DROP TEMPORARY TABLE `tmp_sd_k_nhan_su_mon`;
DROP TEMPORARY TABLE `tmp_sd_x_nhan_su_khu_vuc`;
DROP TEMPORARY TABLE `tmp_sd_k_nhan_su_khu_vuc`;
DROP TEMPORARY TABLE `tmp_sd_x_nhan_su_lich_ranh`;
DROP TEMPORARY TABLE `tmp_sd_k_nhan_su_lich_ranh`;
DROP TEMPORARY TABLE `tmp_sd_x_yeu_cau_hoc`;
DROP TEMPORARY TABLE `tmp_sd_k_yeu_cau_hoc`;
DROP TEMPORARY TABLE `tmp_sd_x_yeu_cau_lich`;
DROP TEMPORARY TABLE `tmp_sd_k_yeu_cau_lich`;
DROP TEMPORARY TABLE `tmp_sd_x_de_xuat_nhan_su`;
DROP TEMPORARY TABLE `tmp_sd_k_de_xuat_nhan_su`;
DROP TEMPORARY TABLE `tmp_sd_x_khoa_hoc`;
DROP TEMPORARY TABLE `tmp_sd_k_khoa_hoc`;
DROP TEMPORARY TABLE `tmp_sd_x_dang_ky_khoa_hoc`;
DROP TEMPORARY TABLE `tmp_sd_k_dang_ky_khoa_hoc`;
DROP TEMPORARY TABLE `tmp_sd_x_lich_su_don_gia_gio`;
DROP TEMPORARY TABLE `tmp_sd_k_lich_su_don_gia_gio`;
DROP TEMPORARY TABLE `tmp_sd_x_lop_hoc`;
DROP TEMPORARY TABLE `tmp_sd_k_lop_hoc`;
DROP TEMPORARY TABLE `tmp_sd_x_lop_hoc_vien`;
DROP TEMPORARY TABLE `tmp_sd_k_lop_hoc_vien`;
DROP TEMPORARY TABLE `tmp_sd_x_lich_hoc`;
DROP TEMPORARY TABLE `tmp_sd_k_lich_hoc`;
DROP TEMPORARY TABLE `tmp_sd_x_buoi_hoc`;
DROP TEMPORARY TABLE `tmp_sd_k_buoi_hoc`;
DROP TEMPORARY TABLE `tmp_sd_x_phan_cong_buoi_hoc`;
DROP TEMPORARY TABLE `tmp_sd_k_phan_cong_buoi_hoc`;
DROP TEMPORARY TABLE `tmp_sd_x_dieu_chinh_cong`;
DROP TEMPORARY TABLE `tmp_sd_k_dieu_chinh_cong`;
DROP TEMPORARY TABLE `tmp_sd_x_don_xin_nghi`;
DROP TEMPORARY TABLE `tmp_sd_k_don_xin_nghi`;
DROP TEMPORARY TABLE `tmp_sd_x_bai_tap`;
DROP TEMPORARY TABLE `tmp_sd_k_bai_tap`;
DROP TEMPORARY TABLE `tmp_sd_x_so_theo_doi_buoi_hoc`;
DROP TEMPORARY TABLE `tmp_sd_k_so_theo_doi_buoi_hoc`;
DROP TEMPORARY TABLE `tmp_sd_x_ket_qua_hoc_tap`;
DROP TEMPORARY TABLE `tmp_sd_k_ket_qua_hoc_tap`;
DROP TEMPORARY TABLE `tmp_sd_x_bang_cong_thang`;
DROP TEMPORARY TABLE `tmp_sd_k_bang_cong_thang`;
DROP TEMPORARY TABLE `tmp_sd_x_hoa_don`;
DROP TEMPORARY TABLE `tmp_sd_k_hoa_don`;
DROP TEMPORARY TABLE `tmp_sd_x_hoa_don_chi_tiet`;
DROP TEMPORARY TABLE `tmp_sd_k_hoa_don_chi_tiet`;
DROP TEMPORARY TABLE `tmp_sd_x_thanh_toan`;
DROP TEMPORARY TABLE `tmp_sd_k_thanh_toan`;
DROP TEMPORARY TABLE `tmp_sd_x_thong_bao`;
DROP TEMPORARY TABLE `tmp_sd_k_thong_bao`;
DROP TEMPORARY TABLE `tmp_sd_x_audit_log`;
DROP TEMPORARY TABLE `tmp_sd_k_audit_log`;
DROP TEMPORARY TABLE `tmp_sd_x_schema_migrations`;
DROP TEMPORARY TABLE `tmp_sd_k_schema_migrations`;
DROP TEMPORARY TABLE `tmp_sd_x_yeu_cau_nghi_lop`;
DROP TEMPORARY TABLE `tmp_sd_k_yeu_cau_nghi_lop`;
DROP TEMPORARY TABLE `tmp_sd_x_hoc_bu_hoc_vien`;
DROP TEMPORARY TABLE `tmp_sd_k_hoc_bu_hoc_vien`;
DROP TEMPORARY TABLE `tmp_sd_x_giao_dich_truc_tuyen`;
DROP TEMPORARY TABLE `tmp_sd_k_giao_dich_truc_tuyen`;
DROP TEMPORARY TABLE `tmp_sd_x_su_kien_thanh_toan`;
DROP TEMPORARY TABLE `tmp_sd_k_su_kien_thanh_toan`;
DROP TEMPORARY TABLE tmp_sd_buoi_copy;
DROP TEMPORARY TABLE tmp_sd_ket_qua;
END$$
DELIMITER ;

-- ĐỔI DGTEST THÀNH SQLDEMO HOẶC CA_HAI TÙY BỘ MUỐN DỌN.
CALL sp_don_du_lieu_demo('DGTEST');
DROP PROCEDURE sp_don_du_lieu_demo;
*/
