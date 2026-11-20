-- EDUCENTER ERP · DỮ LIỆU DEMO ĐỦ 6 VAI TRÒ · MYSQL 8.4 / SCHEMA 005
-- Chạy cả file bằng SQL Scripts > Run SQL Script trong DataGrip, SQL dialect MySQL.
-- File tạo một thủ tục và gọi THEM. Chạy lại THEM giữ bộ đã tạo, tránh chèn trùng.
-- Sau lần đầu, chỉ cần chạy một trong ba lệnh CALL ở cuối file.
-- XOA xóa TOÀN BỘ dữ liệu nghiệp vụ trong DB local demo, kể cả dòng nhập tay; giữ tài khoản/mật khẩu và vai trò.
-- Giữ trạng thái bảo mật tài khoản, danh mục hệ thống và schema_migrations. Dừng backend và sao lưu DB trước khi gọi XOA.
-- Mật khẩu của các tài khoản demo là Demo@123, chỉ dùng cho DB local demo.

USE trung_tam_gia_su;
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET SESSION time_zone = '+07:00';
SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED;

-- Dọn hai thủ tục demo cũ để dùng thống nhất một lệnh bên dưới.
DROP PROCEDURE IF EXISTS sp_them_du_lieu_sqldemo;
DROP PROCEDURE IF EXISTS sp_don_du_lieu_demo;
DELIMITER $$
DROP PROCEDURE IF EXISTS sp_demo_educenter$$
CREATE PROCEDURE sp_demo_educenter(IN p_hanh_dong VARCHAR(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci)
SQL SECURITY INVOKER
demo: BEGIN
    DECLARE v_action VARCHAR(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_lock_name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    DECLARE v_fk_checks INT DEFAULT 1;
    DECLARE v_tag VARCHAR(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'SQLDEMO01';
    DECLARE v_password_hash VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '$2y$12$3Kl2ij6XsDRxK.o4INzAAOV6BYkn55b5VECylB226AfMae4NkBkom';
    DECLARE v_admin BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_afternoon1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_afternoon2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_afternoon3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_afternoon4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_afternoon5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_assignment_afternoon1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_assignment_afternoon2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_assignment_afternoon3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_assignment_afternoon4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_assignment_afternoon5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_assignment_morning1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_assignment_morning2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_assignment_morning3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_assignment_morning4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_assignment_morning5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_assignment_past1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_assignment_past2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_assignment_past3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_assignment_past4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_assignment_past5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_campus1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_campus2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_class1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_class2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_class3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_class4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_class5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_course1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_course2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_course3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_course4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_course5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_employee BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_enrollment1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_enrollment10 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_enrollment2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_enrollment3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_enrollment4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_enrollment5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_enrollment6 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_enrollment7 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_enrollment8 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_enrollment9 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_event1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_event2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_fn1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_fn2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_fn3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_fn4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_fn5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_fn6 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_fn7 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_fn8 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_giaovu BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_group1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_group2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_group3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_group4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_group5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_homework1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_homework2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_homework3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_homework4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_homework5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_invoice1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_invoice10 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_invoice2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_invoice3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_invoice4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_invoice5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_invoice6 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_invoice7 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_invoice8 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_invoice9 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_leave1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_leave2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_leave3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_leave4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_leave5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_makeup1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_makeup2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_makeup3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_makeup4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_makeup5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_mon_ANH BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_mon_HOA BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_mon_LY BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_mon_SINH BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_mon_TOAN BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_mon_VAN BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_morning1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_morning2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_morning3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_morning4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_morning5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_ns1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_ns2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_ns3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_ns4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_ns5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_ns6 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_ns7 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_ns8 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_parent1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_parent2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_parent3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_parent4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_parent5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_past1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_past2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_past3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_past4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_past5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_payment1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_payment2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_payroll1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_payroll2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_payroll3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_payroll4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_payroll5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_proposal1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_proposal2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_proposal3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_proposal4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_proposal5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_receipt2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_region1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_region2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_registration1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_registration10 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_registration11 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_registration2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_registration3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_registration4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_registration5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_registration6 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_registration7 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_registration8 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_registration9 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_request1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_request2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_request3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_request4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_request5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_student1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_student10 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_student11 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_student12 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_student2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_student3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_student4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_student5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_student6 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_student7 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_student8 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_student9 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_teacher1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_teacher2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_teacher3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_teacher4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_teacher5 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_teacher6 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_teacher7 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_teacher8 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_weekly1 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_weekly2 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_weekly3 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_weekly4 BIGINT UNSIGNED DEFAULT NULL;
    DECLARE v_weekly5 BIGINT UNSIGNED DEFAULT NULL;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET FOREIGN_KEY_CHECKS = v_fk_checks;
        DO RELEASE_LOCK(v_lock_name);
        RESIGNAL;
    END;
    SET v_fk_checks = @@FOREIGN_KEY_CHECKS;
    SET v_action = UPPER(TRIM(p_hanh_dong));
    SET v_lock_name = CONCAT('educenter_demo:',DATABASE());
    IF v_action IS NULL OR v_action NOT IN ('THEM','XEM','XOA') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Dung THEM, XEM hoac XOA.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM schema_migrations WHERE version='005_operations_ready' AND status='applied') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Can cai schema 005 truoc.';
    END IF;
    IF COALESCE(GET_LOCK(v_lock_name,10),0)<>1 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Co thao tac demo dang chay. Thu lai sau.';
    END IF;

    -- PHẦN XÓA. TRUNCATE tự COMMIT, chạy sau khi dừng backend và kiểm chứng backup.
    -- Giữ tài khoản, mật khẩu, phân quyền, danh mục chuẩn và định danh vai trò.
    IF v_action='XOA' THEN
        UPDATE nhan_vien SET co_so_id=NULL;
        SET FOREIGN_KEY_CHECKS=0;
        TRUNCATE TABLE `co_so`;
        TRUNCATE TABLE `hoc_vien`;
        TRUNCATE TABLE `nhan_su_mon`;
        TRUNCATE TABLE `nhan_su_khu_vuc`;
        TRUNCATE TABLE `nhan_su_lich_ranh`;
        TRUNCATE TABLE `yeu_cau_hoc`;
        TRUNCATE TABLE `yeu_cau_lich`;
        TRUNCATE TABLE `de_xuat_nhan_su`;
        TRUNCATE TABLE `khoa_hoc`;
        TRUNCATE TABLE `dang_ky_khoa_hoc`;
        TRUNCATE TABLE `lich_su_don_gia_gio`;
        TRUNCATE TABLE `lop_hoc`;
        TRUNCATE TABLE `lop_hoc_vien`;
        TRUNCATE TABLE `lich_hoc`;
        TRUNCATE TABLE `buoi_hoc`;
        TRUNCATE TABLE `phan_cong_buoi_hoc`;
        TRUNCATE TABLE `dieu_chinh_cong`;
        TRUNCATE TABLE `don_xin_nghi`;
        TRUNCATE TABLE `bai_tap`;
        TRUNCATE TABLE `so_theo_doi_buoi_hoc`;
        TRUNCATE TABLE `ket_qua_hoc_tap`;
        TRUNCATE TABLE `bang_cong_thang`;
        TRUNCATE TABLE `hoa_don`;
        TRUNCATE TABLE `hoa_don_chi_tiet`;
        TRUNCATE TABLE `thanh_toan`;
        TRUNCATE TABLE `thong_bao`;
        TRUNCATE TABLE `yeu_cau_nghi_lop`;
        TRUNCATE TABLE `hoc_bu_hoc_vien`;
        TRUNCATE TABLE `giao_dich_truc_tuyen`;
        TRUNCATE TABLE `su_kien_thanh_toan`;
        SET FOREIGN_KEY_CHECKS=v_fk_checks;
        -- Nhật ký trạng thái/thu hồi phiên và OTP là trạng thái bảo mật của tài khoản.
        DELETE FROM audit_log WHERE NOT (bang='account' AND COALESCE(
            JSON_UNQUOTE(JSON_EXTRACT(du_lieu_moi,'$.action')) IN
                ('account_status_changed','dang_xuat','forgot_password_otp'),0));
        DELETE FROM nhan_su_chuyen_mon WHERE user_id NOT IN (SELECT user_id FROM account);
        DELETE FROM nhan_vien WHERE user_id NOT IN (SELECT user_id FROM account);
        DELETE FROM users WHERE user_id NOT IN (SELECT user_id FROM account)
            AND user_id NOT IN (SELECT user_id FROM audit_log WHERE user_id IS NOT NULL);
        UPDATE users SET sdt=NULL,ngaysinh=NULL,diachi=NULL,nghe_nghiep=NULL,khu_vuc_id=NULL;
        UPDATE nhan_vien SET chuc_vu=NULL,ngay_vao_lam=NULL;
        UPDATE nhan_su_chuyen_mon SET gioi_tinh=NULL,cccd=NULL,don_vi_cong_tac=NULL,gioi_thieu=NULL,
            kinh_nghiem_nam=0,don_gia_gio_chuan=0,ngan_hang=NULL,so_tai_khoan=NULL,chu_tai_khoan=NULL;
        DO RELEASE_LOCK(v_lock_name);
        SELECT 'Đã xóa dữ liệu nghiệp vụ demo, giữ tài khoản/mật khẩu và vai trò đăng nhập.' AS ket_qua;
        LEAVE demo;
    END IF;

    -- PHẦN THÊM. Các ID là biến cục bộ trong cùng một giao dịch.
    IF v_action='THEM' AND NOT EXISTS (
        SELECT 1 FROM audit_log
        WHERE JSON_UNQUOTE(JSON_EXTRACT(du_lieu_moi,'$.nguon'))='manual_sql_fixture'
          AND JSON_UNQUOTE(JSON_EXTRACT(du_lieu_moi,'$.version'))='all_roles_v2'
    ) THEN
        -- Bổ sung theo namespace SQLDEMO01. Không xóa nghiệp vụ đang có.
        -- Nếu trùng mã fixture mà thiếu marker, UNIQUE sẽ dừng và rollback toàn bộ phần thêm.
        START TRANSACTION;
-- A1. Danh muc chuan. Moi dong mot INSERT, giu nguyen dong da ton tai.
INSERT INTO `role_group` (`group_id`, `group_name`, `description`, `created_at`, `is_deleted`)
VALUES (NULL, 'Admin', 'Quản trị viên', DEFAULT, 0) ON DUPLICATE KEY UPDATE `group_id` = `role_group`.`group_id`;
SET v_group1 = (SELECT group_id FROM role_group WHERE group_name='Admin');
INSERT INTO `role_group` (`group_id`, `group_name`, `description`, `created_at`, `is_deleted`)
VALUES (NULL, 'NhanVien', 'Giáo vụ / Điều phối', DEFAULT, 0) ON DUPLICATE KEY UPDATE `group_id` = `role_group`.`group_id`;
SET v_group2 = (SELECT group_id FROM role_group WHERE group_name='NhanVien');
INSERT INTO `role_group` (`group_id`, `group_name`, `description`, `created_at`, `is_deleted`)
VALUES (NULL, 'GiangVien', 'Giáo viên / Gia sư', DEFAULT, 0) ON DUPLICATE KEY UPDATE `group_id` = `role_group`.`group_id`;
SET v_group3 = (SELECT group_id FROM role_group WHERE group_name='GiangVien');
INSERT INTO `role_group` (`group_id`, `group_name`, `description`, `created_at`, `is_deleted`)
VALUES (NULL, 'TroGiang', 'Trợ giảng', DEFAULT, 0) ON DUPLICATE KEY UPDATE `group_id` = `role_group`.`group_id`;
SET v_group4 = (SELECT group_id FROM role_group WHERE group_name='TroGiang');
INSERT INTO `role_group` (`group_id`, `group_name`, `description`, `created_at`, `is_deleted`)
VALUES (NULL, 'PhuHuynh', 'Phụ huynh', DEFAULT, 0) ON DUPLICATE KEY UPDATE `group_id` = `role_group`.`group_id`;
SET v_group5 = (SELECT group_id FROM role_group WHERE group_name='PhuHuynh');
INSERT INTO `function` (`function_id`, `name_function`, `created_at`, `is_deleted`)
VALUES (NULL, 'QuanLyTaiKhoan', DEFAULT, 0) ON DUPLICATE KEY UPDATE `function_id` = `function`.`function_id`;
SET v_fn1 = (SELECT function_id FROM `function` WHERE name_function='QuanLyTaiKhoan');
INSERT INTO `function` (`function_id`, `name_function`, `created_at`, `is_deleted`)
VALUES (NULL, 'QuanLyNhanSu', DEFAULT, 0) ON DUPLICATE KEY UPDATE `function_id` = `function`.`function_id`;
SET v_fn2 = (SELECT function_id FROM `function` WHERE name_function='QuanLyNhanSu');
INSERT INTO `function` (`function_id`, `name_function`, `created_at`, `is_deleted`)
VALUES (NULL, 'TiepNhanYeuCau', DEFAULT, 0) ON DUPLICATE KEY UPDATE `function_id` = `function`.`function_id`;
SET v_fn3 = (SELECT function_id FROM `function` WHERE name_function='TiepNhanYeuCau');
INSERT INTO `function` (`function_id`, `name_function`, `created_at`, `is_deleted`)
VALUES (NULL, 'QuanLyLopHoc', DEFAULT, 0) ON DUPLICATE KEY UPDATE `function_id` = `function`.`function_id`;
SET v_fn4 = (SELECT function_id FROM `function` WHERE name_function='QuanLyLopHoc');
INSERT INTO `function` (`function_id`, `name_function`, `created_at`, `is_deleted`)
VALUES (NULL, 'VanHanhBuoiHoc', DEFAULT, 0) ON DUPLICATE KEY UPDATE `function_id` = `function`.`function_id`;
SET v_fn5 = (SELECT function_id FROM `function` WHERE name_function='VanHanhBuoiHoc');
INSERT INTO `function` (`function_id`, `name_function`, `created_at`, `is_deleted`)
VALUES (NULL, 'ChamCongLuong', DEFAULT, 0) ON DUPLICATE KEY UPDATE `function_id` = `function`.`function_id`;
SET v_fn6 = (SELECT function_id FROM `function` WHERE name_function='ChamCongLuong');
INSERT INTO `function` (`function_id`, `name_function`, `created_at`, `is_deleted`)
VALUES (NULL, 'HocPhiThanhToan', DEFAULT, 0) ON DUPLICATE KEY UPDATE `function_id` = `function`.`function_id`;
SET v_fn7 = (SELECT function_id FROM `function` WHERE name_function='HocPhiThanhToan');
INSERT INTO `function` (`function_id`, `name_function`, `created_at`, `is_deleted`)
VALUES (NULL, 'BaoCaoThongKe', DEFAULT, 0) ON DUPLICATE KEY UPDATE `function_id` = `function`.`function_id`;
SET v_fn8 = (SELECT function_id FROM `function` WHERE name_function='BaoCaoThongKe');
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group1, v_fn1, 1, 1, 1, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group1, v_fn2, 1, 1, 1, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group1, v_fn3, 1, 1, 1, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group1, v_fn4, 1, 1, 1, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group1, v_fn5, 1, 1, 1, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group1, v_fn6, 1, 1, 1, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group1, v_fn7, 1, 1, 1, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group1, v_fn8, 1, 1, 1, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group2, v_fn3, 1, 1, 0, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group2, v_fn4, 1, 1, 0, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group2, v_fn5, 1, 1, 0, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group2, v_fn7, 1, 1, 0, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group2, v_fn8, 0, 0, 0, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group3, v_fn5, 1, 1, 0, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group3, v_fn6, 0, 0, 0, 0, 0, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group4, v_fn5, 0, 1, 0, 0, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group4, v_fn6, 0, 0, 0, 0, 0, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group5, v_fn5, 0, 0, 0, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `role_group_function` (`id`, `group_id`, `function_id`, `add`, `edit`, `delete`, `download`, `view`, `created_at`, `is_deleted`)
VALUES (NULL, v_group5, v_fn7, 0, 0, 0, 1, 1, DEFAULT, 0) ON DUPLICATE KEY UPDATE `id` = `role_group_function`.`id`;
INSERT INTO `mon_hoc` (`id`, `ma`, `ten`, `is_active`)
VALUES (NULL, 'TOAN', 'Toán', 1) ON DUPLICATE KEY UPDATE `id` = `mon_hoc`.`id`;
SET v_mon_TOAN = (SELECT id FROM mon_hoc WHERE ma='TOAN');
INSERT INTO `mon_hoc` (`id`, `ma`, `ten`, `is_active`)
VALUES (NULL, 'VAN', 'Ngữ văn', 1) ON DUPLICATE KEY UPDATE `id` = `mon_hoc`.`id`;
SET v_mon_VAN = (SELECT id FROM mon_hoc WHERE ma='VAN');
INSERT INTO `mon_hoc` (`id`, `ma`, `ten`, `is_active`)
VALUES (NULL, 'ANH', 'Tiếng Anh', 1) ON DUPLICATE KEY UPDATE `id` = `mon_hoc`.`id`;
SET v_mon_ANH = (SELECT id FROM mon_hoc WHERE ma='ANH');
INSERT INTO `mon_hoc` (`id`, `ma`, `ten`, `is_active`)
VALUES (NULL, 'LY', 'Vật lý', 1) ON DUPLICATE KEY UPDATE `id` = `mon_hoc`.`id`;
SET v_mon_LY = (SELECT id FROM mon_hoc WHERE ma='LY');
INSERT INTO `mon_hoc` (`id`, `ma`, `ten`, `is_active`)
VALUES (NULL, 'HOA', 'Hóa học', 1) ON DUPLICATE KEY UPDATE `id` = `mon_hoc`.`id`;
SET v_mon_HOA = (SELECT id FROM mon_hoc WHERE ma='HOA');
INSERT INTO `mon_hoc` (`id`, `ma`, `ten`, `is_active`)
VALUES (NULL, 'SINH', 'Sinh học', 1) ON DUPLICATE KEY UPDATE `id` = `mon_hoc`.`id`;
SET v_mon_SINH = (SELECT id FROM mon_hoc WHERE ma='SINH');
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
SET v_region1 = (SELECT id FROM khu_vuc WHERE ten='Thủ Đức' AND thanh_pho='TP. Hồ Chí Minh');
INSERT INTO `khu_vuc` (`id`, `ten`, `thanh_pho`, `is_active`)
VALUES (NULL, 'Bình Thạnh', 'TP. Hồ Chí Minh', 1) ON DUPLICATE KEY UPDATE `id` = `khu_vuc`.`id`;
SET v_region2 = (SELECT id FROM khu_vuc WHERE ten='Bình Thạnh' AND thanh_pho='TP. Hồ Chí Minh');
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

-- Tài khoản Admin mẫu được tạo cùng giao dịch với bộ demo.
SET v_admin = (SELECT user_id FROM account WHERE username='demo_admin');
IF v_admin IS NULL THEN
INSERT INTO users (user_id,hoten,sdt,email,ngaysinh,diachi,nghe_nghiep,khu_vuc_id,created_at,is_deleted,updated_at)
VALUES (NULL,'Quản trị viên Demo',NULL,'sqldemo01.admin@example.test',NULL,'EduCenter','Quản trị viên',v_region1,DEFAULT,0,NULL);
SET v_admin = LAST_INSERT_ID();
INSERT INTO account (account_id,user_id,group_id,username,password_hash,status,created_at,is_deleted,updated_at)
VALUES (NULL,v_admin,v_group1,'demo_admin',v_password_hash,'active',DEFAULT,0,NULL);
END IF;

-- A2. Giao vu, co so va tai khoan demo. Dung Admin hien co de ghi dau vet.
SET v_giaovu = (SELECT user_id FROM account WHERE username='demo_giaovu');
IF v_giaovu IS NULL THEN
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Lê Minh Thư [SQLDEMO]', NULL, CONCAT(LOWER(v_tag),'.giaovu@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Giáo vụ', v_region1, DEFAULT, 0, NULL);
SET v_giaovu = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, v_giaovu, v_group2, 'demo_giaovu', v_password_hash, 'active', DEFAULT, 0, NULL);
END IF;
INSERT INTO `co_so` (`id`, `ma_co_so`, `ten_co_so`, `dia_chi`, `so_dien_thoai`, `email`, `khu_vuc_id`, `quan_ly_id`, `is_active`, `created_at`)
VALUES (NULL, CONCAT(v_tag,'-CS01'), 'EduCenter Thủ Đức [SQLDEMO]', 'Cơ sở minh họa Thủ Đức', '02800000000', CONCAT(LOWER(v_tag),'.cs1@example.test'), v_region1, v_giaovu, 1, DEFAULT);
SET v_campus1 = LAST_INSERT_ID();
INSERT INTO `co_so` (`id`, `ma_co_so`, `ten_co_so`, `dia_chi`, `so_dien_thoai`, `email`, `khu_vuc_id`, `quan_ly_id`, `is_active`, `created_at`)
VALUES (NULL, CONCAT(v_tag,'-CS02'), 'EduCenter Bình Thạnh [SQLDEMO]', 'Cơ sở minh họa Bình Thạnh', '02800000000', CONCAT(LOWER(v_tag),'.cs2@example.test'), v_region2, v_giaovu, 1, DEFAULT);
SET v_campus2 = LAST_INSERT_ID();
IF EXISTS (SELECT 1 FROM nhan_vien WHERE ma_nv=CONCAT(v_tag,'-NV01') COLLATE utf8mb4_unicode_ci AND user_id<>v_giaovu) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ma ho so demo da duoc tai khoan khac su dung.';
END IF;
INSERT INTO `nhan_vien` (`id`, `user_id`, `ma_nv`, `chuc_vu`, `co_so_id`, `ngay_vao_lam`, `trang_thai`, `created_at`)
VALUES (NULL, v_giaovu, CONCAT(v_tag,'-NV01'), 'Giáo vụ', NULL, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 'dang_lam', DEFAULT)
ON DUPLICATE KEY UPDATE
    `id`=LAST_INSERT_ID(`id`),`chuc_vu`=VALUES(`chuc_vu`),`co_so_id`=VALUES(`co_so_id`),
    `ngay_vao_lam`=VALUES(`ngay_vao_lam`),`trang_thai`=VALUES(`trang_thai`);
SET v_employee = LAST_INSERT_ID();
SET v_teacher1 = (SELECT user_id FROM account WHERE username='demo_giaovien');
IF v_teacher1 IS NULL THEN
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Nguyễn Minh Anh [SQLDEMO]', NULL, CONCAT(LOWER(v_tag),'.teacher1@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Người dạy', v_region1, DEFAULT, 0, NULL);
SET v_teacher1 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, v_teacher1, v_group3, 'demo_giaovien', v_password_hash, 'active', DEFAULT, 0, NULL);
END IF;
IF EXISTS (SELECT 1 FROM nhan_su_chuyen_mon WHERE ma_ns=CONCAT(v_tag,'-NS01') COLLATE utf8mb4_unicode_ci AND user_id<>v_teacher1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ma ho so demo da duoc tai khoan khac su dung.';
END IF;
INSERT INTO `nhan_su_chuyen_mon` (`id`, `user_id`, `ma_ns`, `phan_loai`, `gioi_tinh`, `cccd`, `nghe_nghiep`, `don_vi_cong_tac`, `gioi_thieu`, `kinh_nghiem_nam`, `hinh_thuc_day`, `don_gia_gio_chuan`, `ngan_hang`, `so_tai_khoan`, `chu_tai_khoan`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_teacher1, CONCAT(v_tag,'-NS01'), 'giao_vien', 'nu', NULL, 'giao_vien', 'EduCenter', 'Hồ sơ giả lập phục vụ kiểm thử giao diện', 3, 'online,offline', 160000, 'Ngân hàng minh họa', 'DEMO1', 'Nguyễn Minh Anh', 'hoat_dong', DEFAULT, DEFAULT, NULL)
ON DUPLICATE KEY UPDATE
    `id`=LAST_INSERT_ID(`id`),`phan_loai`=VALUES(`phan_loai`),`gioi_tinh`=VALUES(`gioi_tinh`),
    `cccd`=VALUES(`cccd`),`nghe_nghiep`=VALUES(`nghe_nghiep`),`don_vi_cong_tac`=VALUES(`don_vi_cong_tac`),
    `gioi_thieu`=VALUES(`gioi_thieu`),`kinh_nghiem_nam`=VALUES(`kinh_nghiem_nam`),`hinh_thuc_day`=VALUES(`hinh_thuc_day`),
    `don_gia_gio_chuan`=VALUES(`don_gia_gio_chuan`),`ngan_hang`=VALUES(`ngan_hang`),`so_tai_khoan`=VALUES(`so_tai_khoan`),
    `chu_tai_khoan`=VALUES(`chu_tai_khoan`),`trang_thai`=VALUES(`trang_thai`),`updated_at`=VALUES(`updated_at`),
    `deleted_at`=VALUES(`deleted_at`);
SET v_ns1 = LAST_INSERT_ID();
INSERT INTO `lich_su_don_gia_gio` (`id`, `nhan_su_id`, `don_gia_gio`, `hieu_luc_tu`, `nguoi_dat_id`)
VALUES (NULL, v_ns1, 160000, TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 7 DAY)), v_admin);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (v_ns1, v_region1);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (v_ns1, v_region2);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns1, v_mon_TOAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns1, v_mon_VAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns1, v_mon_ANH, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns1, v_mon_LY, 6, 12, 250000);
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns1, 2, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns1, 3, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns1, 4, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns1, 5, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns1, 6, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns1, 7, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns1, 8, '08:00', '21:00');
SET v_teacher2 = (SELECT user_id FROM account WHERE username='demo_gv02');
IF v_teacher2 IS NULL THEN
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Trần Quốc Huy [SQLDEMO]', NULL, CONCAT(LOWER(v_tag),'.teacher2@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Người dạy', v_region1, DEFAULT, 0, NULL);
SET v_teacher2 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, v_teacher2, v_group3, 'demo_gv02', v_password_hash, 'active', DEFAULT, 0, NULL);
END IF;
IF EXISTS (SELECT 1 FROM nhan_su_chuyen_mon WHERE ma_ns=CONCAT(v_tag,'-NS02') COLLATE utf8mb4_unicode_ci AND user_id<>v_teacher2) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ma ho so demo da duoc tai khoan khac su dung.';
END IF;
INSERT INTO `nhan_su_chuyen_mon` (`id`, `user_id`, `ma_ns`, `phan_loai`, `gioi_tinh`, `cccd`, `nghe_nghiep`, `don_vi_cong_tac`, `gioi_thieu`, `kinh_nghiem_nam`, `hinh_thuc_day`, `don_gia_gio_chuan`, `ngan_hang`, `so_tai_khoan`, `chu_tai_khoan`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_teacher2, CONCAT(v_tag,'-NS02'), 'giao_vien', 'nam', NULL, 'giao_vien', 'EduCenter', 'Hồ sơ giả lập phục vụ kiểm thử giao diện', 3, 'online,offline', 170000, 'Ngân hàng minh họa', 'DEMO2', 'Trần Quốc Huy', 'hoat_dong', DEFAULT, DEFAULT, NULL)
ON DUPLICATE KEY UPDATE
    `id`=LAST_INSERT_ID(`id`),`phan_loai`=VALUES(`phan_loai`),`gioi_tinh`=VALUES(`gioi_tinh`),
    `cccd`=VALUES(`cccd`),`nghe_nghiep`=VALUES(`nghe_nghiep`),`don_vi_cong_tac`=VALUES(`don_vi_cong_tac`),
    `gioi_thieu`=VALUES(`gioi_thieu`),`kinh_nghiem_nam`=VALUES(`kinh_nghiem_nam`),`hinh_thuc_day`=VALUES(`hinh_thuc_day`),
    `don_gia_gio_chuan`=VALUES(`don_gia_gio_chuan`),`ngan_hang`=VALUES(`ngan_hang`),`so_tai_khoan`=VALUES(`so_tai_khoan`),
    `chu_tai_khoan`=VALUES(`chu_tai_khoan`),`trang_thai`=VALUES(`trang_thai`),`updated_at`=VALUES(`updated_at`),
    `deleted_at`=VALUES(`deleted_at`);
SET v_ns2 = LAST_INSERT_ID();
INSERT INTO `lich_su_don_gia_gio` (`id`, `nhan_su_id`, `don_gia_gio`, `hieu_luc_tu`, `nguoi_dat_id`)
VALUES (NULL, v_ns2, 170000, TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 7 DAY)), v_admin);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (v_ns2, v_region1);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (v_ns2, v_region2);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns2, v_mon_TOAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns2, v_mon_VAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns2, v_mon_ANH, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns2, v_mon_LY, 6, 12, 250000);
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns2, 2, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns2, 3, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns2, 4, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns2, 5, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns2, 6, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns2, 7, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns2, 8, '08:00', '21:00');
SET v_teacher3 = (SELECT user_id FROM account WHERE username='demo_gv03');
IF v_teacher3 IS NULL THEN
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Lê Thu Hà [SQLDEMO]', NULL, CONCAT(LOWER(v_tag),'.teacher3@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Người dạy', v_region1, DEFAULT, 0, NULL);
SET v_teacher3 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, v_teacher3, v_group3, 'demo_gv03', v_password_hash, 'active', DEFAULT, 0, NULL);
END IF;
IF EXISTS (SELECT 1 FROM nhan_su_chuyen_mon WHERE ma_ns=CONCAT(v_tag,'-NS03') COLLATE utf8mb4_unicode_ci AND user_id<>v_teacher3) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ma ho so demo da duoc tai khoan khac su dung.';
END IF;
INSERT INTO `nhan_su_chuyen_mon` (`id`, `user_id`, `ma_ns`, `phan_loai`, `gioi_tinh`, `cccd`, `nghe_nghiep`, `don_vi_cong_tac`, `gioi_thieu`, `kinh_nghiem_nam`, `hinh_thuc_day`, `don_gia_gio_chuan`, `ngan_hang`, `so_tai_khoan`, `chu_tai_khoan`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_teacher3, CONCAT(v_tag,'-NS03'), 'giao_vien', 'nu', NULL, 'giao_vien', 'EduCenter', 'Hồ sơ giả lập phục vụ kiểm thử giao diện', 3, 'online,offline', 180000, 'Ngân hàng minh họa', 'DEMO3', 'Lê Thu Hà', 'hoat_dong', DEFAULT, DEFAULT, NULL)
ON DUPLICATE KEY UPDATE
    `id`=LAST_INSERT_ID(`id`),`phan_loai`=VALUES(`phan_loai`),`gioi_tinh`=VALUES(`gioi_tinh`),
    `cccd`=VALUES(`cccd`),`nghe_nghiep`=VALUES(`nghe_nghiep`),`don_vi_cong_tac`=VALUES(`don_vi_cong_tac`),
    `gioi_thieu`=VALUES(`gioi_thieu`),`kinh_nghiem_nam`=VALUES(`kinh_nghiem_nam`),`hinh_thuc_day`=VALUES(`hinh_thuc_day`),
    `don_gia_gio_chuan`=VALUES(`don_gia_gio_chuan`),`ngan_hang`=VALUES(`ngan_hang`),`so_tai_khoan`=VALUES(`so_tai_khoan`),
    `chu_tai_khoan`=VALUES(`chu_tai_khoan`),`trang_thai`=VALUES(`trang_thai`),`updated_at`=VALUES(`updated_at`),
    `deleted_at`=VALUES(`deleted_at`);
SET v_ns3 = LAST_INSERT_ID();
INSERT INTO `lich_su_don_gia_gio` (`id`, `nhan_su_id`, `don_gia_gio`, `hieu_luc_tu`, `nguoi_dat_id`)
VALUES (NULL, v_ns3, 180000, TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 7 DAY)), v_admin);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (v_ns3, v_region1);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (v_ns3, v_region2);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns3, v_mon_TOAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns3, v_mon_VAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns3, v_mon_ANH, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns3, v_mon_LY, 6, 12, 250000);
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns3, 2, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns3, 3, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns3, 4, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns3, 5, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns3, 6, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns3, 7, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns3, 8, '08:00', '21:00');
SET v_teacher4 = (SELECT user_id FROM account WHERE username='demo_gv04');
IF v_teacher4 IS NULL THEN
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Phạm Quốc Bảo [SQLDEMO]', NULL, CONCAT(LOWER(v_tag),'.teacher4@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Người dạy', v_region1, DEFAULT, 0, NULL);
SET v_teacher4 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, v_teacher4, v_group3, 'demo_gv04', v_password_hash, 'active', DEFAULT, 0, NULL);
END IF;
IF EXISTS (SELECT 1 FROM nhan_su_chuyen_mon WHERE ma_ns=CONCAT(v_tag,'-NS04') COLLATE utf8mb4_unicode_ci AND user_id<>v_teacher4) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ma ho so demo da duoc tai khoan khac su dung.';
END IF;
INSERT INTO `nhan_su_chuyen_mon` (`id`, `user_id`, `ma_ns`, `phan_loai`, `gioi_tinh`, `cccd`, `nghe_nghiep`, `don_vi_cong_tac`, `gioi_thieu`, `kinh_nghiem_nam`, `hinh_thuc_day`, `don_gia_gio_chuan`, `ngan_hang`, `so_tai_khoan`, `chu_tai_khoan`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_teacher4, CONCAT(v_tag,'-NS04'), 'gia_su', 'nam', NULL, 'giao_vien', 'EduCenter', 'Hồ sơ giả lập phục vụ kiểm thử giao diện', 3, 'online,offline', 190000, 'Ngân hàng minh họa', 'DEMO4', 'Phạm Quốc Bảo', 'hoat_dong', DEFAULT, DEFAULT, NULL)
ON DUPLICATE KEY UPDATE
    `id`=LAST_INSERT_ID(`id`),`phan_loai`=VALUES(`phan_loai`),`gioi_tinh`=VALUES(`gioi_tinh`),
    `cccd`=VALUES(`cccd`),`nghe_nghiep`=VALUES(`nghe_nghiep`),`don_vi_cong_tac`=VALUES(`don_vi_cong_tac`),
    `gioi_thieu`=VALUES(`gioi_thieu`),`kinh_nghiem_nam`=VALUES(`kinh_nghiem_nam`),`hinh_thuc_day`=VALUES(`hinh_thuc_day`),
    `don_gia_gio_chuan`=VALUES(`don_gia_gio_chuan`),`ngan_hang`=VALUES(`ngan_hang`),`so_tai_khoan`=VALUES(`so_tai_khoan`),
    `chu_tai_khoan`=VALUES(`chu_tai_khoan`),`trang_thai`=VALUES(`trang_thai`),`updated_at`=VALUES(`updated_at`),
    `deleted_at`=VALUES(`deleted_at`);
SET v_ns4 = LAST_INSERT_ID();
INSERT INTO `lich_su_don_gia_gio` (`id`, `nhan_su_id`, `don_gia_gio`, `hieu_luc_tu`, `nguoi_dat_id`)
VALUES (NULL, v_ns4, 190000, TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 7 DAY)), v_admin);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (v_ns4, v_region1);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (v_ns4, v_region2);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns4, v_mon_TOAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns4, v_mon_VAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns4, v_mon_ANH, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns4, v_mon_LY, 6, 12, 250000);
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns4, 2, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns4, 3, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns4, 4, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns4, 5, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns4, 6, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns4, 7, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns4, 8, '08:00', '21:00');
SET v_teacher5 = (SELECT user_id FROM account WHERE username='demo_gv05');
IF v_teacher5 IS NULL THEN
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Võ Thanh Tâm [SQLDEMO]', NULL, CONCAT(LOWER(v_tag),'.teacher5@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Người dạy', v_region1, DEFAULT, 0, NULL);
SET v_teacher5 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, v_teacher5, v_group3, 'demo_gv05', v_password_hash, 'active', DEFAULT, 0, NULL);
END IF;
IF EXISTS (SELECT 1 FROM nhan_su_chuyen_mon WHERE ma_ns=CONCAT(v_tag,'-NS05') COLLATE utf8mb4_unicode_ci AND user_id<>v_teacher5) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ma ho so demo da duoc tai khoan khac su dung.';
END IF;
INSERT INTO `nhan_su_chuyen_mon` (`id`, `user_id`, `ma_ns`, `phan_loai`, `gioi_tinh`, `cccd`, `nghe_nghiep`, `don_vi_cong_tac`, `gioi_thieu`, `kinh_nghiem_nam`, `hinh_thuc_day`, `don_gia_gio_chuan`, `ngan_hang`, `so_tai_khoan`, `chu_tai_khoan`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_teacher5, CONCAT(v_tag,'-NS05'), 'giao_vien', 'nu', NULL, 'giao_vien', 'EduCenter', 'Hồ sơ giả lập phục vụ kiểm thử giao diện', 3, 'online,offline', 200000, 'Ngân hàng minh họa', 'DEMO5', 'Võ Thanh Tâm', 'hoat_dong', DEFAULT, DEFAULT, NULL)
ON DUPLICATE KEY UPDATE
    `id`=LAST_INSERT_ID(`id`),`phan_loai`=VALUES(`phan_loai`),`gioi_tinh`=VALUES(`gioi_tinh`),
    `cccd`=VALUES(`cccd`),`nghe_nghiep`=VALUES(`nghe_nghiep`),`don_vi_cong_tac`=VALUES(`don_vi_cong_tac`),
    `gioi_thieu`=VALUES(`gioi_thieu`),`kinh_nghiem_nam`=VALUES(`kinh_nghiem_nam`),`hinh_thuc_day`=VALUES(`hinh_thuc_day`),
    `don_gia_gio_chuan`=VALUES(`don_gia_gio_chuan`),`ngan_hang`=VALUES(`ngan_hang`),`so_tai_khoan`=VALUES(`so_tai_khoan`),
    `chu_tai_khoan`=VALUES(`chu_tai_khoan`),`trang_thai`=VALUES(`trang_thai`),`updated_at`=VALUES(`updated_at`),
    `deleted_at`=VALUES(`deleted_at`);
SET v_ns5 = LAST_INSERT_ID();
INSERT INTO `lich_su_don_gia_gio` (`id`, `nhan_su_id`, `don_gia_gio`, `hieu_luc_tu`, `nguoi_dat_id`)
VALUES (NULL, v_ns5, 200000, TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 7 DAY)), v_admin);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (v_ns5, v_region1);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (v_ns5, v_region2);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns5, v_mon_TOAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns5, v_mon_VAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns5, v_mon_ANH, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns5, v_mon_LY, 6, 12, 250000);
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns5, 2, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns5, 3, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns5, 4, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns5, 5, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns5, 6, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns5, 7, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns5, 8, '08:00', '21:00');
SET v_teacher6 = (SELECT user_id FROM account WHERE username='demo_giasu');
IF v_teacher6 IS NULL THEN
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Đỗ Ngọc Ánh [SQLDEMO]', NULL, CONCAT(LOWER(v_tag),'.teacher6@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Người dạy', v_region1, DEFAULT, 0, NULL);
SET v_teacher6 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, v_teacher6, v_group3, 'demo_giasu', v_password_hash, 'active', DEFAULT, 0, NULL);
END IF;
IF EXISTS (SELECT 1 FROM nhan_su_chuyen_mon WHERE ma_ns=CONCAT(v_tag,'-NS06') COLLATE utf8mb4_unicode_ci AND user_id<>v_teacher6) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ma ho so demo da duoc tai khoan khac su dung.';
END IF;
INSERT INTO `nhan_su_chuyen_mon` (`id`, `user_id`, `ma_ns`, `phan_loai`, `gioi_tinh`, `cccd`, `nghe_nghiep`, `don_vi_cong_tac`, `gioi_thieu`, `kinh_nghiem_nam`, `hinh_thuc_day`, `don_gia_gio_chuan`, `ngan_hang`, `so_tai_khoan`, `chu_tai_khoan`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_teacher6, CONCAT(v_tag,'-NS06'), 'gia_su', 'nam', NULL, 'giao_vien', 'EduCenter', 'Hồ sơ giả lập phục vụ kiểm thử giao diện', 3, 'online,offline', 210000, 'Ngân hàng minh họa', 'DEMO6', 'Đỗ Ngọc Ánh', 'hoat_dong', DEFAULT, DEFAULT, NULL)
ON DUPLICATE KEY UPDATE
    `id`=LAST_INSERT_ID(`id`),`phan_loai`=VALUES(`phan_loai`),`gioi_tinh`=VALUES(`gioi_tinh`),
    `cccd`=VALUES(`cccd`),`nghe_nghiep`=VALUES(`nghe_nghiep`),`don_vi_cong_tac`=VALUES(`don_vi_cong_tac`),
    `gioi_thieu`=VALUES(`gioi_thieu`),`kinh_nghiem_nam`=VALUES(`kinh_nghiem_nam`),`hinh_thuc_day`=VALUES(`hinh_thuc_day`),
    `don_gia_gio_chuan`=VALUES(`don_gia_gio_chuan`),`ngan_hang`=VALUES(`ngan_hang`),`so_tai_khoan`=VALUES(`so_tai_khoan`),
    `chu_tai_khoan`=VALUES(`chu_tai_khoan`),`trang_thai`=VALUES(`trang_thai`),`updated_at`=VALUES(`updated_at`),
    `deleted_at`=VALUES(`deleted_at`);
SET v_ns6 = LAST_INSERT_ID();
INSERT INTO `lich_su_don_gia_gio` (`id`, `nhan_su_id`, `don_gia_gio`, `hieu_luc_tu`, `nguoi_dat_id`)
VALUES (NULL, v_ns6, 210000, TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 7 DAY)), v_admin);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (v_ns6, v_region1);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (v_ns6, v_region2);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns6, v_mon_TOAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns6, v_mon_VAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns6, v_mon_ANH, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns6, v_mon_LY, 6, 12, 250000);
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns6, 2, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns6, 3, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns6, 4, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns6, 5, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns6, 6, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns6, 7, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns6, 8, '08:00', '21:00');
SET v_teacher7 = (SELECT user_id FROM account WHERE username='demo_trogiang');
IF v_teacher7 IS NULL THEN
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Nguyễn Thảo Vy [SQLDEMO]', NULL, CONCAT(LOWER(v_tag),'.teacher7@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Trợ giảng', v_region1, DEFAULT, 0, NULL);
SET v_teacher7 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, v_teacher7, v_group4, 'demo_trogiang', v_password_hash, 'active', DEFAULT, 0, NULL);
END IF;
IF EXISTS (SELECT 1 FROM nhan_su_chuyen_mon WHERE ma_ns=CONCAT(v_tag,'-NS07') COLLATE utf8mb4_unicode_ci AND user_id<>v_teacher7) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ma ho so demo da duoc tai khoan khac su dung.';
END IF;
INSERT INTO `nhan_su_chuyen_mon` (`id`, `user_id`, `ma_ns`, `phan_loai`, `gioi_tinh`, `cccd`, `nghe_nghiep`, `don_vi_cong_tac`, `gioi_thieu`, `kinh_nghiem_nam`, `hinh_thuc_day`, `don_gia_gio_chuan`, `ngan_hang`, `so_tai_khoan`, `chu_tai_khoan`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_teacher7, CONCAT(v_tag,'-NS07'), 'tro_giang', 'nu', NULL, 'sinh_vien', 'EduCenter', 'Hồ sơ giả lập phục vụ kiểm thử giao diện', 3, 'online,offline', 80000, 'Ngân hàng minh họa', 'DEMO7', 'Nguyễn Thảo Vy', 'hoat_dong', DEFAULT, DEFAULT, NULL)
ON DUPLICATE KEY UPDATE
    `id`=LAST_INSERT_ID(`id`),`phan_loai`=VALUES(`phan_loai`),`gioi_tinh`=VALUES(`gioi_tinh`),
    `cccd`=VALUES(`cccd`),`nghe_nghiep`=VALUES(`nghe_nghiep`),`don_vi_cong_tac`=VALUES(`don_vi_cong_tac`),
    `gioi_thieu`=VALUES(`gioi_thieu`),`kinh_nghiem_nam`=VALUES(`kinh_nghiem_nam`),`hinh_thuc_day`=VALUES(`hinh_thuc_day`),
    `don_gia_gio_chuan`=VALUES(`don_gia_gio_chuan`),`ngan_hang`=VALUES(`ngan_hang`),`so_tai_khoan`=VALUES(`so_tai_khoan`),
    `chu_tai_khoan`=VALUES(`chu_tai_khoan`),`trang_thai`=VALUES(`trang_thai`),`updated_at`=VALUES(`updated_at`),
    `deleted_at`=VALUES(`deleted_at`);
SET v_ns7 = LAST_INSERT_ID();
INSERT INTO `lich_su_don_gia_gio` (`id`, `nhan_su_id`, `don_gia_gio`, `hieu_luc_tu`, `nguoi_dat_id`)
VALUES (NULL, v_ns7, 80000, TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 7 DAY)), v_admin);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (v_ns7, v_region1);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (v_ns7, v_region2);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns7, v_mon_TOAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns7, v_mon_VAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns7, v_mon_ANH, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns7, v_mon_LY, 6, 12, 250000);
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns7, 2, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns7, 3, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns7, 4, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns7, 5, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns7, 6, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns7, 7, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns7, 8, '08:00', '21:00');
SET v_teacher8 = (SELECT user_id FROM account WHERE username='demo_tg02');
IF v_teacher8 IS NULL THEN
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Trần Đức Minh [SQLDEMO]', NULL, CONCAT(LOWER(v_tag),'.teacher8@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Trợ giảng', v_region1, DEFAULT, 0, NULL);
SET v_teacher8 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, v_teacher8, v_group4, 'demo_tg02', v_password_hash, 'active', DEFAULT, 0, NULL);
END IF;
IF EXISTS (SELECT 1 FROM nhan_su_chuyen_mon WHERE ma_ns=CONCAT(v_tag,'-NS08') COLLATE utf8mb4_unicode_ci AND user_id<>v_teacher8) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ma ho so demo da duoc tai khoan khac su dung.';
END IF;
INSERT INTO `nhan_su_chuyen_mon` (`id`, `user_id`, `ma_ns`, `phan_loai`, `gioi_tinh`, `cccd`, `nghe_nghiep`, `don_vi_cong_tac`, `gioi_thieu`, `kinh_nghiem_nam`, `hinh_thuc_day`, `don_gia_gio_chuan`, `ngan_hang`, `so_tai_khoan`, `chu_tai_khoan`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_teacher8, CONCAT(v_tag,'-NS08'), 'tro_giang', 'nam', NULL, 'sinh_vien', 'EduCenter', 'Hồ sơ giả lập phục vụ kiểm thử giao diện', 3, 'online,offline', 80000, 'Ngân hàng minh họa', 'DEMO8', 'Trần Đức Minh', 'hoat_dong', DEFAULT, DEFAULT, NULL)
ON DUPLICATE KEY UPDATE
    `id`=LAST_INSERT_ID(`id`),`phan_loai`=VALUES(`phan_loai`),`gioi_tinh`=VALUES(`gioi_tinh`),
    `cccd`=VALUES(`cccd`),`nghe_nghiep`=VALUES(`nghe_nghiep`),`don_vi_cong_tac`=VALUES(`don_vi_cong_tac`),
    `gioi_thieu`=VALUES(`gioi_thieu`),`kinh_nghiem_nam`=VALUES(`kinh_nghiem_nam`),`hinh_thuc_day`=VALUES(`hinh_thuc_day`),
    `don_gia_gio_chuan`=VALUES(`don_gia_gio_chuan`),`ngan_hang`=VALUES(`ngan_hang`),`so_tai_khoan`=VALUES(`so_tai_khoan`),
    `chu_tai_khoan`=VALUES(`chu_tai_khoan`),`trang_thai`=VALUES(`trang_thai`),`updated_at`=VALUES(`updated_at`),
    `deleted_at`=VALUES(`deleted_at`);
SET v_ns8 = LAST_INSERT_ID();
INSERT INTO `lich_su_don_gia_gio` (`id`, `nhan_su_id`, `don_gia_gio`, `hieu_luc_tu`, `nguoi_dat_id`)
VALUES (NULL, v_ns8, 80000, TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 7 DAY)), v_admin);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (v_ns8, v_region1);
INSERT INTO `nhan_su_khu_vuc` (`nhan_su_id`, `khu_vuc_id`)
VALUES (v_ns8, v_region2);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns8, v_mon_TOAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns8, v_mon_VAN, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns8, v_mon_ANH, 6, 12, 250000);
INSERT INTO `nhan_su_mon` (`id`, `nhan_su_id`, `mon_hoc_id`, `cap_lop_tu`, `cap_lop_den`, `hoc_phi_buoi_de_xuat`)
VALUES (NULL, v_ns8, v_mon_LY, 6, 12, 250000);
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns8, 2, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns8, 3, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns8, 4, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns8, 5, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns8, 6, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns8, 7, '08:00', '21:00');
INSERT INTO `nhan_su_lich_ranh` (`id`, `nhan_su_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_ns8, 8, '08:00', '21:00');

-- A3. Phu huynh, hoc vien va khoa hoc. Mot phu huynh co nhieu con.
SET v_parent1 = (SELECT user_id FROM account WHERE username='demo_phuhuynh');
IF v_parent1 IS NULL THEN
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Nguyễn Thị Lan [SQLDEMO]', NULL, CONCAT(LOWER(v_tag),'.parent1@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Phụ huynh', v_region1, DEFAULT, 0, NULL);
SET v_parent1 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, v_parent1, v_group5, 'demo_phuhuynh', v_password_hash, 'active', DEFAULT, 0, NULL);
END IF;
SET v_parent2 = (SELECT user_id FROM account WHERE username='demo_ph02');
IF v_parent2 IS NULL THEN
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Trần Văn Hùng [SQLDEMO]', NULL, CONCAT(LOWER(v_tag),'.parent2@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Phụ huynh', v_region1, DEFAULT, 0, NULL);
SET v_parent2 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, v_parent2, v_group5, 'demo_ph02', v_password_hash, 'active', DEFAULT, 0, NULL);
END IF;
SET v_parent3 = (SELECT user_id FROM account WHERE username='demo_ph03');
IF v_parent3 IS NULL THEN
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Lê Thị Hạnh [SQLDEMO]', NULL, CONCAT(LOWER(v_tag),'.parent3@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Phụ huynh', v_region1, DEFAULT, 0, NULL);
SET v_parent3 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, v_parent3, v_group5, 'demo_ph03', v_password_hash, 'active', DEFAULT, 0, NULL);
END IF;
SET v_parent4 = (SELECT user_id FROM account WHERE username='demo_ph04');
IF v_parent4 IS NULL THEN
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Phạm Văn Nam [SQLDEMO]', NULL, CONCAT(LOWER(v_tag),'.parent4@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Phụ huynh', v_region1, DEFAULT, 0, NULL);
SET v_parent4 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, v_parent4, v_group5, 'demo_ph04', v_password_hash, 'active', DEFAULT, 0, NULL);
END IF;
SET v_parent5 = (SELECT user_id FROM account WHERE username='demo_ph05');
IF v_parent5 IS NULL THEN
INSERT INTO `users` (`user_id`, `hoten`, `sdt`, `email`, `ngaysinh`, `diachi`, `nghe_nghiep`, `khu_vuc_id`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, 'Võ Thị Mai [SQLDEMO]', NULL, CONCAT(LOWER(v_tag),'.parent5@example.test'), '1995-04-12', 'Địa chỉ minh họa tại TP. Hồ Chí Minh', 'Phụ huynh', v_region1, DEFAULT, 0, NULL);
SET v_parent5 = LAST_INSERT_ID();
INSERT INTO `account` (`account_id`, `user_id`, `group_id`, `username`, `password_hash`, `status`, `created_at`, `is_deleted`, `updated_at`)
VALUES (NULL, v_parent5, v_group5, 'demo_ph05', v_password_hash, 'active', DEFAULT, 0, NULL);
END IF;
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_parent1, CONCAT(v_tag,'-HV01'), 'Nguyễn Minh An [SQLDEMO]', '2011-05-15', 'nam', 'Trường minh họa', 9, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', v_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET v_student1 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_parent1, CONCAT(v_tag,'-HV02'), 'Nguyễn Ngọc Bảo [SQLDEMO]', '2011-05-15', 'nu', 'Trường minh họa', 9, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', v_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET v_student2 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_parent2, CONCAT(v_tag,'-HV03'), 'Trần Gia Hân [SQLDEMO]', '2008-05-15', 'nam', 'Trường minh họa', 12, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', v_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET v_student3 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_parent2, CONCAT(v_tag,'-HV04'), 'Trần Minh Khang [SQLDEMO]', '2008-05-15', 'nu', 'Trường minh họa', 12, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', v_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET v_student4 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_parent3, CONCAT(v_tag,'-HV05'), 'Lê Ngọc Chi [SQLDEMO]', '2009-05-15', 'nam', 'Trường minh họa', 11, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', v_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET v_student5 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_parent3, CONCAT(v_tag,'-HV06'), 'Lê Hoàng Nam [SQLDEMO]', '2009-05-15', 'nu', 'Trường minh họa', 11, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', v_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET v_student6 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_parent4, CONCAT(v_tag,'-HV07'), 'Phạm Anh Duy [SQLDEMO]', '2014-05-15', 'nam', 'Trường minh họa', 6, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', v_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET v_student7 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_parent4, CONCAT(v_tag,'-HV08'), 'Phạm Bảo Ngọc [SQLDEMO]', '2014-05-15', 'nu', 'Trường minh họa', 6, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', v_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET v_student8 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_parent5, CONCAT(v_tag,'-HV09'), 'Võ Khánh Linh [SQLDEMO]', '2011-05-15', 'nam', 'Trường minh họa', 9, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', v_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET v_student9 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_parent5, CONCAT(v_tag,'-HV10'), 'Võ Minh Tuấn [SQLDEMO]', '2011-05-15', 'nu', 'Trường minh họa', 9, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', v_region1, 'Dữ liệu giả lập đang học', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET v_student10 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_parent1, CONCAT(v_tag,'-HV11'), 'Nguyễn Hải Yến [SQLDEMO]', '2011-05-15', 'nam', 'Trường minh họa', 9, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', v_region1, 'Dữ liệu giả lập chờ xếp lớp', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET v_student11 = LAST_INSERT_ID();
INSERT INTO `hoc_vien` (`id`, `phu_huynh_user_id`, `ma_hv`, `ho_ten`, `ngay_sinh`, `gioi_tinh`, `truong`, `cap_lop_id`, `dia_chi`, `khu_vuc_id`, `ghi_chu`, `trang_thai`, `created_at`, `updated_at`, `deleted_at`)
VALUES (NULL, v_parent2, CONCAT(v_tag,'-HV12'), 'Trần Nhật Minh [SQLDEMO]', '2011-05-15', 'nu', 'Trường minh họa', 9, 'Địa chỉ minh họa tại TP. Hồ Chí Minh', v_region1, 'Dữ liệu giả lập chưa đăng ký khóa', 'dang_hoc', DEFAULT, DEFAULT, NULL);
SET v_student12 = LAST_INSERT_ID();
INSERT INTO `khoa_hoc` (`id`, `ma`, `ten`, `mo_ta`, `hoc_phi`, `is_active`, `created_at`)
VALUES (NULL, CONCAT(v_tag,'-KH01'), 'Toán 9 cơ bản học kỳ 1 [SQLDEMO]', 'Thu trọn khóa học. Lịch học bù do giáo vụ xếp theo thỏa thuận.', 1600000, 1, DEFAULT);
SET v_course1 = LAST_INSERT_ID();
INSERT INTO `khoa_hoc` (`id`, `ma`, `ten`, `mo_ta`, `hoc_phi`, `is_active`, `created_at`)
VALUES (NULL, CONCAT(v_tag,'-KH02'), 'Toán 12 ôn luyện chuyên [SQLDEMO]', 'Thu trọn khóa học. Lịch học bù do giáo vụ xếp theo thỏa thuận.', 1700000, 1, DEFAULT);
SET v_course2 = LAST_INSERT_ID();
INSERT INTO `khoa_hoc` (`id`, `ma`, `ten`, `mo_ta`, `hoc_phi`, `is_active`, `created_at`)
VALUES (NULL, CONCAT(v_tag,'-KH03'), 'Vật lý 11 nâng cao học kỳ 2 [SQLDEMO]', 'Thu trọn khóa học. Lịch học bù do giáo vụ xếp theo thỏa thuận.', 1800000, 1, DEFAULT);
SET v_course3 = LAST_INSERT_ID();
INSERT INTO `khoa_hoc` (`id`, `ma`, `ten`, `mo_ta`, `hoc_phi`, `is_active`, `created_at`)
VALUES (NULL, CONCAT(v_tag,'-KH04'), 'Tiếng Anh 6 nền tảng cả năm [SQLDEMO]', 'Thu trọn khóa học. Lịch học bù do giáo vụ xếp theo thỏa thuận.', 1900000, 1, DEFAULT);
SET v_course4 = LAST_INSERT_ID();
INSERT INTO `khoa_hoc` (`id`, `ma`, `ten`, `mo_ta`, `hoc_phi`, `is_active`, `created_at`)
VALUES (NULL, CONCAT(v_tag,'-KH05'), 'Ngữ văn 9 luyện đề [SQLDEMO]', 'Thu trọn khóa học. Lịch học bù do giáo vụ xếp theo thỏa thuận.', 2000000, 1, DEFAULT);
SET v_course5 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, v_course1, v_student1, 1600000, 'dang_hoc', TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)));
SET v_registration1 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, v_course1, v_student2, 1600000, 'dang_hoc', TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)));
SET v_registration2 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, v_course2, v_student3, 1700000, 'dang_hoc', TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)));
SET v_registration3 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, v_course2, v_student4, 1700000, 'dang_hoc', TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)));
SET v_registration4 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, v_course3, v_student5, 1800000, 'dang_hoc', TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)));
SET v_registration5 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, v_course3, v_student6, 1800000, 'dang_hoc', TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)));
SET v_registration6 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, v_course4, v_student7, 1900000, 'dang_hoc', TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)));
SET v_registration7 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, v_course4, v_student8, 1900000, 'dang_hoc', TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)));
SET v_registration8 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, v_course5, v_student9, 2000000, 'dang_hoc', TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)));
SET v_registration9 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, v_course5, v_student10, 2000000, 'dang_hoc', TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)));
SET v_registration10 = LAST_INSERT_ID();
INSERT INTO `dang_ky_khoa_hoc` (`id`, `khoa_hoc_id`, `hoc_vien_id`, `hoc_phi_snapshot`, `trang_thai`, `ngay_dang_ky`)
VALUES (NULL, v_course1, v_student11, 1600000, 'cho_xep_lop', TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)));
SET v_registration11 = LAST_INSERT_ID();

-- A4. Nhu cau, de xuat, lop va xep lop. Admin xep lop doc lap hoc phi.
INSERT INTO `yeu_cau_hoc` (`id`, `ma_yc`, `hoc_vien_id`, `ho_ten_lien_he`, `sdt_lien_he`, `email_lien_he`, `mon_hoc_id`, `cap_lop_id`, `loai_lop_mong_muon`, `co_so_mong_muon_id`, `hinh_thuc`, `khu_vuc_id`, `dia_chi_hoc`, `ngan_sach_min`, `ngan_sach_max`, `muc_tieu`, `ghi_chu`, `nguon`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `nguoi_gui_id`, `don_vi_ngan_sach`)
VALUES (NULL, CONCAT(v_tag,'-YC01'), v_student1, 'Phụ huynh minh họa', '0900000000', CONCAT(LOWER(v_tag),'.parent1@example.test'), v_mon_TOAN, 9, 'nhom', v_campus1, 'offline', v_region1, 'Tại cơ sở EduCenter', 1000000, 3000000, 'Củng cố kiến thức và luyện bài tập', 'Nhu cầu mẫu', 'truc_tiep', 'da_chot', v_employee, DEFAULT, DEFAULT, v_giaovu, 'theo_khoa');
SET v_request1 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_lich` (`id`, `yeu_cau_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_request1, WEEKDAY(DATE_ADD(CURDATE(), INTERVAL 1 DAY))+2, '10:00', '12:00');
INSERT INTO `de_xuat_nhan_su` (`id`, `yeu_cau_id`, `nhan_su_id`, `nhan_vien_id`, `hoc_phi_de_xuat`, `tt_nhan_su`, `tt_phu_huynh`, `ghi_chu`, `created_at`, `updated_at`, `cach_thu_phi_de_xuat`, `nhan_su_phan_hoi_luc`, `phu_huynh_phan_hoi_luc`, `phu_huynh_phan_hoi_boi`, `ly_do_tu_choi_nhan_su`, `ly_do_tu_choi_phu_huynh`)
VALUES (NULL, v_request1, v_ns1, v_employee, 1600000, 'dong_y', 'dong_y', 'Hai bên đã xác nhận', DEFAULT, DEFAULT, 'theo_khoa', TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)), TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)), v_parent1, NULL, NULL);
SET v_proposal1 = LAST_INSERT_ID();
INSERT INTO `lop_hoc` (`id`, `ma_lop`, `ten_lop`, `loai_lop`, `de_xuat_id`, `mon_hoc_id`, `cap_lop_id`, `nguoi_day_chinh_id`, `co_so_id`, `phong_hoc`, `hinh_thuc`, `dia_diem`, `link_hoc`, `khu_vuc_id`, `si_so_toi_thieu`, `si_so_toi_da`, `cach_thu_phi`, `hoc_phi_buoi`, `hoc_phi_khoa`, `muc_tra_chinh`, `muc_tra_tro_giang`, `cach_tinh_luong`, `so_buoi_du_kien`, `ngay_bat_dau`, `ngay_ket_thuc`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `deleted_at`, `hoc_phi_thang`, `khoa_hoc_id`)
VALUES (NULL, CONCAT(v_tag,'-LOP01'), 'Toán 9 cơ bản học kỳ 1 [SQLDEMO]', 'nhom', v_proposal1, v_mon_TOAN, 9, v_ns1, v_campus1, 'P01', 'offline', 'Cơ sở EduCenter', NULL, v_region1, 1, 12, 'theo_khoa', 0, 1600000, 0, 0, 'theo_gio', 24, DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(),INTERVAL 90 DAY), 'dang_hoc', v_employee, DEFAULT, DEFAULT, NULL, NULL, v_course1);
SET v_class1 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, v_class1, v_student1, DATE_SUB(CURDATE(), INTERVAL 14 DAY), NULL, 'dang_hoc', DEFAULT, NULL, v_registration1, v_admin, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY)));
SET v_enrollment1 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, v_class1, v_student2, DATE_SUB(CURDATE(), INTERVAL 14 DAY), NULL, 'dang_hoc', DEFAULT, NULL, v_registration2, v_admin, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY)));
SET v_enrollment2 = LAST_INSERT_ID();
INSERT INTO `lich_hoc` (`id`, `lop_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `hieu_luc_tu`, `hieu_luc_den`, `is_active`)
VALUES (NULL, v_class1, WEEKDAY(DATE_ADD(CURDATE(), INTERVAL 1 DAY))+2, '10:00', '12:00', 'P01', DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(),INTERVAL 90 DAY), 1);
SET v_weekly1 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_hoc` (`id`, `ma_yc`, `hoc_vien_id`, `ho_ten_lien_he`, `sdt_lien_he`, `email_lien_he`, `mon_hoc_id`, `cap_lop_id`, `loai_lop_mong_muon`, `co_so_mong_muon_id`, `hinh_thuc`, `khu_vuc_id`, `dia_chi_hoc`, `ngan_sach_min`, `ngan_sach_max`, `muc_tieu`, `ghi_chu`, `nguon`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `nguoi_gui_id`, `don_vi_ngan_sach`)
VALUES (NULL, CONCAT(v_tag,'-YC02'), v_student3, 'Phụ huynh minh họa', '0900000000', CONCAT(LOWER(v_tag),'.parent2@example.test'), v_mon_TOAN, 12, 'nhom', v_campus1, 'offline', v_region1, 'Tại cơ sở EduCenter', 1000000, 3000000, 'Củng cố kiến thức và luyện bài tập', 'Nhu cầu mẫu', 'truc_tiep', 'da_chot', v_employee, DEFAULT, DEFAULT, v_giaovu, 'theo_khoa');
SET v_request2 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_lich` (`id`, `yeu_cau_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_request2, WEEKDAY(DATE_ADD(CURDATE(), INTERVAL 1 DAY))+2, '10:00', '12:00');
INSERT INTO `de_xuat_nhan_su` (`id`, `yeu_cau_id`, `nhan_su_id`, `nhan_vien_id`, `hoc_phi_de_xuat`, `tt_nhan_su`, `tt_phu_huynh`, `ghi_chu`, `created_at`, `updated_at`, `cach_thu_phi_de_xuat`, `nhan_su_phan_hoi_luc`, `phu_huynh_phan_hoi_luc`, `phu_huynh_phan_hoi_boi`, `ly_do_tu_choi_nhan_su`, `ly_do_tu_choi_phu_huynh`)
VALUES (NULL, v_request2, v_ns2, v_employee, 1700000, 'dong_y', 'dong_y', 'Hai bên đã xác nhận', DEFAULT, DEFAULT, 'theo_khoa', TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)), TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)), v_parent2, NULL, NULL);
SET v_proposal2 = LAST_INSERT_ID();
INSERT INTO `lop_hoc` (`id`, `ma_lop`, `ten_lop`, `loai_lop`, `de_xuat_id`, `mon_hoc_id`, `cap_lop_id`, `nguoi_day_chinh_id`, `co_so_id`, `phong_hoc`, `hinh_thuc`, `dia_diem`, `link_hoc`, `khu_vuc_id`, `si_so_toi_thieu`, `si_so_toi_da`, `cach_thu_phi`, `hoc_phi_buoi`, `hoc_phi_khoa`, `muc_tra_chinh`, `muc_tra_tro_giang`, `cach_tinh_luong`, `so_buoi_du_kien`, `ngay_bat_dau`, `ngay_ket_thuc`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `deleted_at`, `hoc_phi_thang`, `khoa_hoc_id`)
VALUES (NULL, CONCAT(v_tag,'-LOP02'), 'Toán 12 ôn luyện chuyên [SQLDEMO]', 'nhom', v_proposal2, v_mon_TOAN, 12, v_ns2, v_campus1, 'P02', 'offline', 'Cơ sở EduCenter', NULL, v_region1, 1, 12, 'theo_khoa', 0, 1700000, 0, 0, 'theo_gio', 24, DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(),INTERVAL 90 DAY), 'dang_hoc', v_employee, DEFAULT, DEFAULT, NULL, NULL, v_course2);
SET v_class2 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, v_class2, v_student3, DATE_SUB(CURDATE(), INTERVAL 14 DAY), NULL, 'dang_hoc', DEFAULT, NULL, v_registration3, v_admin, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY)));
SET v_enrollment3 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, v_class2, v_student4, DATE_SUB(CURDATE(), INTERVAL 14 DAY), NULL, 'dang_hoc', DEFAULT, NULL, v_registration4, v_admin, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY)));
SET v_enrollment4 = LAST_INSERT_ID();
INSERT INTO `lich_hoc` (`id`, `lop_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `hieu_luc_tu`, `hieu_luc_den`, `is_active`)
VALUES (NULL, v_class2, WEEKDAY(DATE_ADD(CURDATE(), INTERVAL 1 DAY))+2, '10:00', '12:00', 'P02', DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(),INTERVAL 90 DAY), 1);
SET v_weekly2 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_hoc` (`id`, `ma_yc`, `hoc_vien_id`, `ho_ten_lien_he`, `sdt_lien_he`, `email_lien_he`, `mon_hoc_id`, `cap_lop_id`, `loai_lop_mong_muon`, `co_so_mong_muon_id`, `hinh_thuc`, `khu_vuc_id`, `dia_chi_hoc`, `ngan_sach_min`, `ngan_sach_max`, `muc_tieu`, `ghi_chu`, `nguon`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `nguoi_gui_id`, `don_vi_ngan_sach`)
VALUES (NULL, CONCAT(v_tag,'-YC03'), v_student5, 'Phụ huynh minh họa', '0900000000', CONCAT(LOWER(v_tag),'.parent3@example.test'), v_mon_LY, 11, 'nhom', v_campus1, 'offline', v_region1, 'Tại cơ sở EduCenter', 1000000, 3000000, 'Củng cố kiến thức và luyện bài tập', 'Nhu cầu mẫu', 'truc_tiep', 'da_chot', v_employee, DEFAULT, DEFAULT, v_giaovu, 'theo_khoa');
SET v_request3 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_lich` (`id`, `yeu_cau_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_request3, WEEKDAY(DATE_ADD(CURDATE(), INTERVAL 1 DAY))+2, '10:00', '12:00');
INSERT INTO `de_xuat_nhan_su` (`id`, `yeu_cau_id`, `nhan_su_id`, `nhan_vien_id`, `hoc_phi_de_xuat`, `tt_nhan_su`, `tt_phu_huynh`, `ghi_chu`, `created_at`, `updated_at`, `cach_thu_phi_de_xuat`, `nhan_su_phan_hoi_luc`, `phu_huynh_phan_hoi_luc`, `phu_huynh_phan_hoi_boi`, `ly_do_tu_choi_nhan_su`, `ly_do_tu_choi_phu_huynh`)
VALUES (NULL, v_request3, v_ns3, v_employee, 1800000, 'dong_y', 'dong_y', 'Hai bên đã xác nhận', DEFAULT, DEFAULT, 'theo_khoa', TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)), TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)), v_parent3, NULL, NULL);
SET v_proposal3 = LAST_INSERT_ID();
INSERT INTO `lop_hoc` (`id`, `ma_lop`, `ten_lop`, `loai_lop`, `de_xuat_id`, `mon_hoc_id`, `cap_lop_id`, `nguoi_day_chinh_id`, `co_so_id`, `phong_hoc`, `hinh_thuc`, `dia_diem`, `link_hoc`, `khu_vuc_id`, `si_so_toi_thieu`, `si_so_toi_da`, `cach_thu_phi`, `hoc_phi_buoi`, `hoc_phi_khoa`, `muc_tra_chinh`, `muc_tra_tro_giang`, `cach_tinh_luong`, `so_buoi_du_kien`, `ngay_bat_dau`, `ngay_ket_thuc`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `deleted_at`, `hoc_phi_thang`, `khoa_hoc_id`)
VALUES (NULL, CONCAT(v_tag,'-LOP03'), 'Vật lý 11 nâng cao học kỳ 2 [SQLDEMO]', 'nhom', v_proposal3, v_mon_LY, 11, v_ns3, v_campus1, 'P03', 'offline', 'Cơ sở EduCenter', NULL, v_region1, 1, 12, 'theo_khoa', 0, 1800000, 0, 0, 'theo_gio', 24, DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(),INTERVAL 90 DAY), 'dang_hoc', v_employee, DEFAULT, DEFAULT, NULL, NULL, v_course3);
SET v_class3 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, v_class3, v_student5, DATE_SUB(CURDATE(), INTERVAL 14 DAY), NULL, 'dang_hoc', DEFAULT, NULL, v_registration5, v_admin, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY)));
SET v_enrollment5 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, v_class3, v_student6, DATE_SUB(CURDATE(), INTERVAL 14 DAY), NULL, 'dang_hoc', DEFAULT, NULL, v_registration6, v_admin, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY)));
SET v_enrollment6 = LAST_INSERT_ID();
INSERT INTO `lich_hoc` (`id`, `lop_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `hieu_luc_tu`, `hieu_luc_den`, `is_active`)
VALUES (NULL, v_class3, WEEKDAY(DATE_ADD(CURDATE(), INTERVAL 1 DAY))+2, '10:00', '12:00', 'P03', DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(),INTERVAL 90 DAY), 1);
SET v_weekly3 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_hoc` (`id`, `ma_yc`, `hoc_vien_id`, `ho_ten_lien_he`, `sdt_lien_he`, `email_lien_he`, `mon_hoc_id`, `cap_lop_id`, `loai_lop_mong_muon`, `co_so_mong_muon_id`, `hinh_thuc`, `khu_vuc_id`, `dia_chi_hoc`, `ngan_sach_min`, `ngan_sach_max`, `muc_tieu`, `ghi_chu`, `nguon`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `nguoi_gui_id`, `don_vi_ngan_sach`)
VALUES (NULL, CONCAT(v_tag,'-YC04'), v_student7, 'Phụ huynh minh họa', '0900000000', CONCAT(LOWER(v_tag),'.parent4@example.test'), v_mon_ANH, 6, 'nhom', v_campus2, 'offline', v_region1, 'Tại cơ sở EduCenter', 1000000, 3000000, 'Củng cố kiến thức và luyện bài tập', 'Nhu cầu mẫu', 'truc_tiep', 'da_chot', v_employee, DEFAULT, DEFAULT, v_giaovu, 'theo_khoa');
SET v_request4 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_lich` (`id`, `yeu_cau_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_request4, WEEKDAY(DATE_ADD(CURDATE(), INTERVAL 1 DAY))+2, '10:00', '12:00');
INSERT INTO `de_xuat_nhan_su` (`id`, `yeu_cau_id`, `nhan_su_id`, `nhan_vien_id`, `hoc_phi_de_xuat`, `tt_nhan_su`, `tt_phu_huynh`, `ghi_chu`, `created_at`, `updated_at`, `cach_thu_phi_de_xuat`, `nhan_su_phan_hoi_luc`, `phu_huynh_phan_hoi_luc`, `phu_huynh_phan_hoi_boi`, `ly_do_tu_choi_nhan_su`, `ly_do_tu_choi_phu_huynh`)
VALUES (NULL, v_request4, v_ns4, v_employee, 1900000, 'dong_y', 'dong_y', 'Hai bên đã xác nhận', DEFAULT, DEFAULT, 'theo_khoa', TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)), TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)), v_parent4, NULL, NULL);
SET v_proposal4 = LAST_INSERT_ID();
INSERT INTO `lop_hoc` (`id`, `ma_lop`, `ten_lop`, `loai_lop`, `de_xuat_id`, `mon_hoc_id`, `cap_lop_id`, `nguoi_day_chinh_id`, `co_so_id`, `phong_hoc`, `hinh_thuc`, `dia_diem`, `link_hoc`, `khu_vuc_id`, `si_so_toi_thieu`, `si_so_toi_da`, `cach_thu_phi`, `hoc_phi_buoi`, `hoc_phi_khoa`, `muc_tra_chinh`, `muc_tra_tro_giang`, `cach_tinh_luong`, `so_buoi_du_kien`, `ngay_bat_dau`, `ngay_ket_thuc`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `deleted_at`, `hoc_phi_thang`, `khoa_hoc_id`)
VALUES (NULL, CONCAT(v_tag,'-LOP04'), 'Tiếng Anh 6 nền tảng cả năm [SQLDEMO]', 'nhom', v_proposal4, v_mon_ANH, 6, v_ns4, v_campus2, 'P04', 'offline', 'Cơ sở EduCenter', NULL, v_region1, 1, 12, 'theo_khoa', 0, 1900000, 0, 0, 'theo_gio', 24, DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(),INTERVAL 90 DAY), 'dang_hoc', v_employee, DEFAULT, DEFAULT, NULL, NULL, v_course4);
SET v_class4 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, v_class4, v_student7, DATE_SUB(CURDATE(), INTERVAL 14 DAY), NULL, 'dang_hoc', DEFAULT, NULL, v_registration7, v_admin, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY)));
SET v_enrollment7 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, v_class4, v_student8, DATE_SUB(CURDATE(), INTERVAL 14 DAY), NULL, 'dang_hoc', DEFAULT, NULL, v_registration8, v_admin, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY)));
SET v_enrollment8 = LAST_INSERT_ID();
INSERT INTO `lich_hoc` (`id`, `lop_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `hieu_luc_tu`, `hieu_luc_den`, `is_active`)
VALUES (NULL, v_class4, WEEKDAY(DATE_ADD(CURDATE(), INTERVAL 1 DAY))+2, '10:00', '12:00', 'P04', DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(),INTERVAL 90 DAY), 1);
SET v_weekly4 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_hoc` (`id`, `ma_yc`, `hoc_vien_id`, `ho_ten_lien_he`, `sdt_lien_he`, `email_lien_he`, `mon_hoc_id`, `cap_lop_id`, `loai_lop_mong_muon`, `co_so_mong_muon_id`, `hinh_thuc`, `khu_vuc_id`, `dia_chi_hoc`, `ngan_sach_min`, `ngan_sach_max`, `muc_tieu`, `ghi_chu`, `nguon`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `nguoi_gui_id`, `don_vi_ngan_sach`)
VALUES (NULL, CONCAT(v_tag,'-YC05'), v_student9, 'Phụ huynh minh họa', '0900000000', CONCAT(LOWER(v_tag),'.parent5@example.test'), v_mon_VAN, 9, 'nhom', v_campus2, 'offline', v_region1, 'Tại cơ sở EduCenter', 1000000, 3000000, 'Củng cố kiến thức và luyện bài tập', 'Nhu cầu mẫu', 'truc_tiep', 'da_chot', v_employee, DEFAULT, DEFAULT, v_giaovu, 'theo_khoa');
SET v_request5 = LAST_INSERT_ID();
INSERT INTO `yeu_cau_lich` (`id`, `yeu_cau_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`)
VALUES (NULL, v_request5, WEEKDAY(DATE_ADD(CURDATE(), INTERVAL 1 DAY))+2, '10:00', '12:00');
INSERT INTO `de_xuat_nhan_su` (`id`, `yeu_cau_id`, `nhan_su_id`, `nhan_vien_id`, `hoc_phi_de_xuat`, `tt_nhan_su`, `tt_phu_huynh`, `ghi_chu`, `created_at`, `updated_at`, `cach_thu_phi_de_xuat`, `nhan_su_phan_hoi_luc`, `phu_huynh_phan_hoi_luc`, `phu_huynh_phan_hoi_boi`, `ly_do_tu_choi_nhan_su`, `ly_do_tu_choi_phu_huynh`)
VALUES (NULL, v_request5, v_ns6, v_employee, 2000000, 'dong_y', 'dong_y', 'Hai bên đã xác nhận', DEFAULT, DEFAULT, 'theo_khoa', TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)), TIMESTAMP(DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 14 DAY), INTERVAL 1 DAY)), v_parent5, NULL, NULL);
SET v_proposal5 = LAST_INSERT_ID();
INSERT INTO `lop_hoc` (`id`, `ma_lop`, `ten_lop`, `loai_lop`, `de_xuat_id`, `mon_hoc_id`, `cap_lop_id`, `nguoi_day_chinh_id`, `co_so_id`, `phong_hoc`, `hinh_thuc`, `dia_diem`, `link_hoc`, `khu_vuc_id`, `si_so_toi_thieu`, `si_so_toi_da`, `cach_thu_phi`, `hoc_phi_buoi`, `hoc_phi_khoa`, `muc_tra_chinh`, `muc_tra_tro_giang`, `cach_tinh_luong`, `so_buoi_du_kien`, `ngay_bat_dau`, `ngay_ket_thuc`, `trang_thai`, `nhan_vien_id`, `created_at`, `updated_at`, `deleted_at`, `hoc_phi_thang`, `khoa_hoc_id`)
VALUES (NULL, CONCAT(v_tag,'-LOP05'), 'Ngữ văn 9 luyện đề [SQLDEMO]', 'kem_1_2', v_proposal5, v_mon_VAN, 9, v_ns6, v_campus2, 'P05', 'offline', 'Cơ sở EduCenter', NULL, v_region1, 1, 2, 'theo_khoa', 0, 2000000, 0, 0, 'theo_gio', 24, DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(),INTERVAL 90 DAY), 'dang_hoc', v_employee, DEFAULT, DEFAULT, NULL, NULL, v_course5);
SET v_class5 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, v_class5, v_student9, DATE_SUB(CURDATE(), INTERVAL 14 DAY), NULL, 'dang_hoc', DEFAULT, NULL, v_registration9, v_admin, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY)));
SET v_enrollment9 = LAST_INSERT_ID();
INSERT INTO `lop_hoc_vien` (`id`, `lop_id`, `hoc_vien_id`, `ngay_vao`, `ngay_roi`, `trang_thai`, `created_at`, `updated_at`, `dang_ky_khoa_hoc_id`, `xep_boi`, `xep_luc`)
VALUES (NULL, v_class5, v_student10, DATE_SUB(CURDATE(), INTERVAL 14 DAY), NULL, 'dang_hoc', DEFAULT, NULL, v_registration10, v_admin, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY)));
SET v_enrollment10 = LAST_INSERT_ID();
INSERT INTO `lich_hoc` (`id`, `lop_id`, `thu`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `hieu_luc_tu`, `hieu_luc_den`, `is_active`)
VALUES (NULL, v_class5, WEEKDAY(DATE_ADD(CURDATE(), INTERVAL 1 DAY))+2, '10:00', '12:00', 'P05', DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(),INTERVAL 90 DAY), 1);
SET v_weekly5 = LAST_INSERT_ID();

-- A5. Moi giao vien co ca 10-12 va 14-16 ngay mai, phong khac nhau.
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class1, NULL, 'thuong', NULL, DATE_SUB(CURDATE(), INTERVAL 14 DAY), '18:00', '20:00', 'P01', 'Ôn tập kiến thức nền', 'Ôn lý thuyết và chữa bài tập', NULL, 'da_day', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus1, 'ca_lop');
SET v_past1 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_past1, v_teacher1, 'day_chinh', 'da_day', 0, NULL, v_giaovu, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY),'18:10:00'), NULL, NULL, 0, 'Bản ghi minh họa giờ server', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY),'20:05:00'), DEFAULT, 'hop_le', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET v_assignment_past1 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class1, v_weekly1, 'thuong', NULL, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00', '12:00', 'P01', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus1, 'ca_lop');
SET v_morning1 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_morning1, v_teacher1, 'day_chinh', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET v_assignment_morning1 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class1, NULL, 'thuong', NULL, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00', '16:00', 'P01', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus1, 'ca_lop');
SET v_afternoon1 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_afternoon1, v_teacher1, 'day_chinh', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET v_assignment_afternoon1 = LAST_INSERT_ID();
INSERT INTO `don_xin_nghi` (`id`, `buoi_hoc_id`, `doi_tuong`, `hoc_vien_id`, `nguoi_gui_id`, `ly_do`, `trang_thai`, `nguoi_duyet_id`, `duyet_luc`, `ly_do_tu_choi`, `buoi_bu_id`, `created_at`)
VALUES (NULL, v_past1, 'hoc_vien', v_student2, v_parent1, 'Gia đình đã trao đổi với giáo vụ', 'da_duyet', v_giaovu, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY)), NULL, NULL, DEFAULT);
SET v_leave1 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class1, NULL, 'bu', v_past1, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '18:00', '20:00', 'P01', 'Học bù theo lịch đã thỏa thuận', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus1, 'ca_nhan');
SET v_makeup1 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_makeup1, v_teacher1, 'day_chinh', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `hoc_bu_hoc_vien` (`id`, `buoi_bu_id`, `hoc_vien_id`, `don_xin_nghi_id`, `xep_boi`, `ghi_chu`)
VALUES (NULL, v_makeup1, v_student2, v_leave1, v_giaovu, 'Phụ huynh và giáo vụ đã thống nhất ngày học');
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class2, NULL, 'thuong', NULL, DATE_SUB(CURDATE(), INTERVAL 14 DAY), '18:00', '20:00', 'P02', 'Ôn tập kiến thức nền', 'Ôn lý thuyết và chữa bài tập', NULL, 'da_day', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus1, 'ca_lop');
SET v_past2 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_past2, v_teacher2, 'day_chinh', 'da_day', 0, NULL, v_giaovu, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY),'18:10:00'), NULL, NULL, 0, 'Bản ghi minh họa giờ server', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY),'19:30:00'), DEFAULT, 'hop_le', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET v_assignment_past2 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class2, v_weekly2, 'thuong', NULL, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00', '12:00', 'P02', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus1, 'ca_lop');
SET v_morning2 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_morning2, v_teacher2, 'day_chinh', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET v_assignment_morning2 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class2, NULL, 'thuong', NULL, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00', '16:00', 'P02', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus1, 'ca_lop');
SET v_afternoon2 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_afternoon2, v_teacher2, 'day_chinh', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET v_assignment_afternoon2 = LAST_INSERT_ID();
INSERT INTO `don_xin_nghi` (`id`, `buoi_hoc_id`, `doi_tuong`, `hoc_vien_id`, `nguoi_gui_id`, `ly_do`, `trang_thai`, `nguoi_duyet_id`, `duyet_luc`, `ly_do_tu_choi`, `buoi_bu_id`, `created_at`)
VALUES (NULL, v_past2, 'hoc_vien', v_student4, v_parent2, 'Gia đình đã trao đổi với giáo vụ', 'da_duyet', v_giaovu, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY)), NULL, NULL, DEFAULT);
SET v_leave2 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class2, NULL, 'bu', v_past2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '18:00', '20:00', 'P02', 'Học bù theo lịch đã thỏa thuận', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus1, 'ca_nhan');
SET v_makeup2 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_makeup2, v_teacher2, 'day_chinh', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `hoc_bu_hoc_vien` (`id`, `buoi_bu_id`, `hoc_vien_id`, `don_xin_nghi_id`, `xep_boi`, `ghi_chu`)
VALUES (NULL, v_makeup2, v_student4, v_leave2, v_giaovu, 'Phụ huynh và giáo vụ đã thống nhất ngày học');
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class3, NULL, 'thuong', NULL, DATE_SUB(CURDATE(), INTERVAL 14 DAY), '18:00', '20:00', 'P03', 'Ôn tập kiến thức nền', 'Ôn lý thuyết và chữa bài tập', NULL, 'da_day', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus1, 'ca_lop');
SET v_past3 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_past3, v_teacher3, 'day_chinh', 'da_day', 0, NULL, v_giaovu, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY),'18:10:00'), NULL, NULL, 0, 'Bản ghi minh họa giờ server', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY),'20:05:00'), DEFAULT, 'hop_le', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET v_assignment_past3 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class3, v_weekly3, 'thuong', NULL, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00', '12:00', 'P03', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus1, 'ca_lop');
SET v_morning3 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_morning3, v_teacher3, 'day_chinh', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET v_assignment_morning3 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class3, NULL, 'thuong', NULL, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00', '16:00', 'P03', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus1, 'ca_lop');
SET v_afternoon3 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_afternoon3, v_teacher3, 'day_chinh', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET v_assignment_afternoon3 = LAST_INSERT_ID();
INSERT INTO `don_xin_nghi` (`id`, `buoi_hoc_id`, `doi_tuong`, `hoc_vien_id`, `nguoi_gui_id`, `ly_do`, `trang_thai`, `nguoi_duyet_id`, `duyet_luc`, `ly_do_tu_choi`, `buoi_bu_id`, `created_at`)
VALUES (NULL, v_past3, 'hoc_vien', v_student6, v_parent3, 'Gia đình đã trao đổi với giáo vụ', 'da_duyet', v_giaovu, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY)), NULL, NULL, DEFAULT);
SET v_leave3 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class3, NULL, 'bu', v_past3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '18:00', '20:00', 'P03', 'Học bù theo lịch đã thỏa thuận', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus1, 'ca_nhan');
SET v_makeup3 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_makeup3, v_teacher3, 'day_chinh', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `hoc_bu_hoc_vien` (`id`, `buoi_bu_id`, `hoc_vien_id`, `don_xin_nghi_id`, `xep_boi`, `ghi_chu`)
VALUES (NULL, v_makeup3, v_student6, v_leave3, v_giaovu, 'Phụ huynh và giáo vụ đã thống nhất ngày học');
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class4, NULL, 'thuong', NULL, DATE_SUB(CURDATE(), INTERVAL 14 DAY), '18:00', '20:00', 'P04', 'Ôn tập kiến thức nền', 'Ôn lý thuyết và chữa bài tập', NULL, 'da_day', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus2, 'ca_lop');
SET v_past4 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_past4, v_teacher4, 'day_chinh', 'da_day', 0, NULL, v_giaovu, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY),'18:10:00'), NULL, NULL, 0, 'Bản ghi minh họa giờ server', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY),'20:05:00'), DEFAULT, 'hop_le', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET v_assignment_past4 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class4, v_weekly4, 'thuong', NULL, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00', '12:00', 'P04', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus2, 'ca_lop');
SET v_morning4 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_morning4, v_teacher4, 'day_chinh', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET v_assignment_morning4 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class4, NULL, 'thuong', NULL, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00', '16:00', 'P04', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus2, 'ca_lop');
SET v_afternoon4 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_afternoon4, v_teacher4, 'day_chinh', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET v_assignment_afternoon4 = LAST_INSERT_ID();
INSERT INTO `don_xin_nghi` (`id`, `buoi_hoc_id`, `doi_tuong`, `hoc_vien_id`, `nguoi_gui_id`, `ly_do`, `trang_thai`, `nguoi_duyet_id`, `duyet_luc`, `ly_do_tu_choi`, `buoi_bu_id`, `created_at`)
VALUES (NULL, v_past4, 'hoc_vien', v_student8, v_parent4, 'Gia đình đã trao đổi với giáo vụ', 'da_duyet', v_giaovu, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY)), NULL, NULL, DEFAULT);
SET v_leave4 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class4, NULL, 'bu', v_past4, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '18:00', '20:00', 'P04', 'Học bù theo lịch đã thỏa thuận', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus2, 'ca_nhan');
SET v_makeup4 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_makeup4, v_teacher4, 'day_chinh', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `hoc_bu_hoc_vien` (`id`, `buoi_bu_id`, `hoc_vien_id`, `don_xin_nghi_id`, `xep_boi`, `ghi_chu`)
VALUES (NULL, v_makeup4, v_student8, v_leave4, v_giaovu, 'Phụ huynh và giáo vụ đã thống nhất ngày học');
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class5, NULL, 'thuong', NULL, DATE_SUB(CURDATE(), INTERVAL 14 DAY), '18:00', '20:00', 'P05', 'Ôn tập kiến thức nền', 'Ôn lý thuyết và chữa bài tập', NULL, 'da_day', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus2, 'ca_lop');
SET v_past5 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_past5, v_teacher6, 'day_chinh', 'da_day', 0, NULL, v_giaovu, NULL, NULL, NULL, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY),'18:10:00'), NULL, NULL, 0, 'Bản ghi minh họa giờ server', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY),'20:05:00'), DEFAULT, 'hop_le', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET v_assignment_past5 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class5, v_weekly5, 'thuong', NULL, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00', '12:00', 'P05', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus2, 'ca_lop');
SET v_morning5 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_morning5, v_teacher6, 'day_chinh', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET v_assignment_morning5 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class5, NULL, 'thuong', NULL, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '14:00', '16:00', 'P05', 'Ôn tập kiến thức nền', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus2, 'ca_lop');
SET v_afternoon5 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_afternoon5, v_teacher6, 'day_chinh', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
SET v_assignment_afternoon5 = LAST_INSERT_ID();
INSERT INTO `don_xin_nghi` (`id`, `buoi_hoc_id`, `doi_tuong`, `hoc_vien_id`, `nguoi_gui_id`, `ly_do`, `trang_thai`, `nguoi_duyet_id`, `duyet_luc`, `ly_do_tu_choi`, `buoi_bu_id`, `created_at`)
VALUES (NULL, v_past5, 'hoc_vien', v_student10, v_parent5, 'Gia đình đã trao đổi với giáo vụ', 'da_duyet', v_giaovu, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY)), NULL, NULL, DEFAULT);
SET v_leave5 = LAST_INSERT_ID();
INSERT INTO `buoi_hoc` (`id`, `lop_id`, `lich_hoc_id`, `loai`, `buoi_goc_id`, `ngay`, `gio_bat_dau`, `gio_ket_thuc`, `phong_hoc`, `chu_de_bai_hoc`, `noi_dung_bai_hoc`, `file_bai_giang_url`, `trang_thai`, `huy_boi`, `ly_do_huy_doi`, `created_at`, `updated_at`, `nguoi_huy_id`, `huy_luc`, `co_so_id`, `pham_vi_bu`)
VALUES (NULL, v_class5, NULL, 'bu', v_past5, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '18:00', '20:00', 'P05', 'Học bù theo lịch đã thỏa thuận', NULL, NULL, 'du_kien', NULL, NULL, DEFAULT, DEFAULT, NULL, NULL, v_campus2, 'ca_nhan');
SET v_makeup5 = LAST_INSERT_ID();
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_makeup5, v_teacher6, 'day_chinh', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `hoc_bu_hoc_vien` (`id`, `buoi_bu_id`, `hoc_vien_id`, `don_xin_nghi_id`, `xep_boi`, `ghi_chu`)
VALUES (NULL, v_makeup5, v_student10, v_leave5, v_giaovu, 'Phụ huynh và giáo vụ đã thống nhất ngày học');
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_morning1, v_teacher7, 'tro_giang', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_afternoon1, v_teacher7, 'tro_giang', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_morning2, v_teacher8, 'tro_giang', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `phan_cong_buoi_hoc` (`id`, `buoi_hoc_id`, `user_id`, `vai_tro`, `trang_thai`, `don_gia`, `bang_cong_id`, `phan_cong_boi`, `go_boi`, `go_luc`, `ly_do_go`, `checkin_luc`, `checkin_lat`, `checkin_lng`, `checkin_tre`, `checkin_ghi_chu`, `checkout_luc`, `so_phut_thuc_te`, `trang_thai_cong`, `day_chinh_key`, `created_at`, `updated_at`, `cach_tinh_luong`, `quy_tac_cong`, `gio_lich_bat_dau`, `gio_lich_ket_thuc`, `so_phut_tinh_cong`, `luong_sua_boi`)
VALUES (NULL, v_afternoon2, v_teacher8, 'tro_giang', 'da_phan_cong', 0, NULL, v_giaovu, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, DEFAULT, 'chua_check', DEFAULT, DEFAULT, DEFAULT, 'theo_gio', 4, NULL, NULL, DEFAULT, NULL);
INSERT INTO `yeu_cau_nghi_lop` (`id`, `buoi_hoc_id`, `nguoi_gui_id`, `ly_do`, `trang_thai`, `nguoi_duyet_id`, `duyet_luc`, `ghi_chu`)
VALUES (NULL, v_morning1, v_teacher1, 'Đề nghị nghỉ cả lớp để Admin xem xét', 'cho_duyet', NULL, NULL, 'Đơn chờ duyệt, chưa hủy buổi');
INSERT INTO `dieu_chinh_cong` (`id`, `phan_cong_id`, `loai`, `checkin_cu`, `checkout_cu`, `checkin_moi`, `checkout_moi`, `ly_do`, `nguoi_yeu_cau_id`, `trang_thai`, `nguoi_duyet_id`, `duyet_luc`, `ghi_chu_duyet`, `created_at`)
VALUES (NULL, v_assignment_past1, 'sua_gio', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY),'18:10:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY),'20:05:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY),'18:00:00'), TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 14 DAY),'20:05:00'), 'Đề nghị Admin kiểm tra lại giờ vào lớp', v_teacher1, 'cho_duyet', NULL, NULL, NULL, DEFAULT);

-- A6. Hoc vu, bai cham, ket qua va bang cong mo. Cot generated dung DEFAULT.
INSERT INTO `bai_tap` (`id`, `buoi_hoc_id`, `tieu_de`, `noi_dung`, `file_de_bai_url`, `han_nop`, `nguoi_tao_id`, `created_at`, `updated_at`)
VALUES (NULL, v_past1, 'Bài ôn tập số 1', 'Làm các câu hỏi đã giao trên lớp', NULL, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY)), v_teacher1, DEFAULT, NULL);
SET v_homework1 = LAST_INSERT_ID();
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, v_past1, v_student1, 'co_mat', v_homework1, 8.5, 'Nắm kiến thức cơ bản', 4, 'tot', 'Tích cực phát biểu', 'Luyện thêm bài vận dụng', v_teacher1, DEFAULT);
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, v_past1, v_student2, 'vang_co_phep', NULL, NULL, NULL, NULL, NULL, 'Vắng có phép, đã thỏa thuận học bù', NULL, v_teacher1, DEFAULT);
INSERT INTO `ket_qua_hoc_tap` (`id`, `lop_id`, `hoc_vien_id`, `loai`, `ten`, `diem`, `diem_toi_da`, `ngay`, `nhan_xet`, `nguoi_nhap_id`, `created_at`, `updated_at`)
VALUES (NULL, v_class1, v_student1, 'kiem_tra', 'Kiểm tra đầu khóa', 8, 10, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'Đạt yêu cầu', v_teacher1, DEFAULT, NULL);
INSERT INTO `bang_cong_thang` (`id`, `user_id`, `thang`, `nam`, `so_buoi`, `tong_phut`, `so_buoi_bu`, `so_buoi_vang`, `so_lan_tre`, `tong_thu_nhap`, `thuong`, `khau_tru`, `thuc_nhan`, `trang_thai`, `chot_boi`, `chot_luc`, `ngay_tra`, `ghi_chu`, `created_at`, `updated_at`, `tra_boi`, `tra_luc`, `ma_giao_dich_chi`, `mo_khoa_boi`, `mo_khoa_luc`, `ly_do_mo_khoa`)
VALUES (NULL, v_teacher1, MONTH(DATE_SUB(CURDATE(), INTERVAL 14 DAY)), YEAR(DATE_SUB(CURDATE(), INTERVAL 14 DAY)), 1, 110, 0, 0, 1, ROUND(110 / 60 * 160000), 0, 0, DEFAULT, 'mo', NULL, NULL, NULL, 'Bảng công mẫu đang mở, chưa chốt/trả', DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, NULL);
SET v_payroll1 = LAST_INSERT_ID();

UPDATE phan_cong_buoi_hoc SET bang_cong_id=v_payroll1 WHERE id=v_assignment_past1;
INSERT INTO `bai_tap` (`id`, `buoi_hoc_id`, `tieu_de`, `noi_dung`, `file_de_bai_url`, `han_nop`, `nguoi_tao_id`, `created_at`, `updated_at`)
VALUES (NULL, v_past2, 'Bài ôn tập số 1', 'Làm các câu hỏi đã giao trên lớp', NULL, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY)), v_teacher2, DEFAULT, NULL);
SET v_homework2 = LAST_INSERT_ID();
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, v_past2, v_student3, 'co_mat', v_homework2, 8.5, 'Nắm kiến thức cơ bản', 4, 'tot', 'Tích cực phát biểu', 'Luyện thêm bài vận dụng', v_teacher2, DEFAULT);
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, v_past2, v_student4, 'vang_co_phep', NULL, NULL, NULL, NULL, NULL, 'Vắng có phép, đã thỏa thuận học bù', NULL, v_teacher2, DEFAULT);
INSERT INTO `ket_qua_hoc_tap` (`id`, `lop_id`, `hoc_vien_id`, `loai`, `ten`, `diem`, `diem_toi_da`, `ngay`, `nhan_xet`, `nguoi_nhap_id`, `created_at`, `updated_at`)
VALUES (NULL, v_class2, v_student3, 'kiem_tra', 'Kiểm tra đầu khóa', 8, 10, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'Đạt yêu cầu', v_teacher2, DEFAULT, NULL);
INSERT INTO `bang_cong_thang` (`id`, `user_id`, `thang`, `nam`, `so_buoi`, `tong_phut`, `so_buoi_bu`, `so_buoi_vang`, `so_lan_tre`, `tong_thu_nhap`, `thuong`, `khau_tru`, `thuc_nhan`, `trang_thai`, `chot_boi`, `chot_luc`, `ngay_tra`, `ghi_chu`, `created_at`, `updated_at`, `tra_boi`, `tra_luc`, `ma_giao_dich_chi`, `mo_khoa_boi`, `mo_khoa_luc`, `ly_do_mo_khoa`)
VALUES (NULL, v_teacher2, MONTH(DATE_SUB(CURDATE(), INTERVAL 14 DAY)), YEAR(DATE_SUB(CURDATE(), INTERVAL 14 DAY)), 1, 80, 0, 0, 1, ROUND(80 / 60 * 170000), 0, 0, DEFAULT, 'mo', NULL, NULL, NULL, 'Bảng công mẫu đang mở, chưa chốt/trả', DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, NULL);
SET v_payroll2 = LAST_INSERT_ID();

UPDATE phan_cong_buoi_hoc SET bang_cong_id=v_payroll2 WHERE id=v_assignment_past2;
INSERT INTO `bai_tap` (`id`, `buoi_hoc_id`, `tieu_de`, `noi_dung`, `file_de_bai_url`, `han_nop`, `nguoi_tao_id`, `created_at`, `updated_at`)
VALUES (NULL, v_past3, 'Bài ôn tập số 1', 'Làm các câu hỏi đã giao trên lớp', NULL, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY)), v_teacher3, DEFAULT, NULL);
SET v_homework3 = LAST_INSERT_ID();
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, v_past3, v_student5, 'co_mat', v_homework3, 8.5, 'Nắm kiến thức cơ bản', 4, 'tot', 'Tích cực phát biểu', 'Luyện thêm bài vận dụng', v_teacher3, DEFAULT);
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, v_past3, v_student6, 'vang_co_phep', NULL, NULL, NULL, NULL, NULL, 'Vắng có phép, đã thỏa thuận học bù', NULL, v_teacher3, DEFAULT);
INSERT INTO `ket_qua_hoc_tap` (`id`, `lop_id`, `hoc_vien_id`, `loai`, `ten`, `diem`, `diem_toi_da`, `ngay`, `nhan_xet`, `nguoi_nhap_id`, `created_at`, `updated_at`)
VALUES (NULL, v_class3, v_student5, 'kiem_tra', 'Kiểm tra đầu khóa', 8, 10, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'Đạt yêu cầu', v_teacher3, DEFAULT, NULL);
INSERT INTO `bang_cong_thang` (`id`, `user_id`, `thang`, `nam`, `so_buoi`, `tong_phut`, `so_buoi_bu`, `so_buoi_vang`, `so_lan_tre`, `tong_thu_nhap`, `thuong`, `khau_tru`, `thuc_nhan`, `trang_thai`, `chot_boi`, `chot_luc`, `ngay_tra`, `ghi_chu`, `created_at`, `updated_at`, `tra_boi`, `tra_luc`, `ma_giao_dich_chi`, `mo_khoa_boi`, `mo_khoa_luc`, `ly_do_mo_khoa`)
VALUES (NULL, v_teacher3, MONTH(DATE_SUB(CURDATE(), INTERVAL 14 DAY)), YEAR(DATE_SUB(CURDATE(), INTERVAL 14 DAY)), 1, 110, 0, 0, 1, ROUND(110 / 60 * 180000), 0, 0, DEFAULT, 'mo', NULL, NULL, NULL, 'Bảng công mẫu đang mở, chưa chốt/trả', DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, NULL);
SET v_payroll3 = LAST_INSERT_ID();

UPDATE phan_cong_buoi_hoc SET bang_cong_id=v_payroll3 WHERE id=v_assignment_past3;
INSERT INTO `bai_tap` (`id`, `buoi_hoc_id`, `tieu_de`, `noi_dung`, `file_de_bai_url`, `han_nop`, `nguoi_tao_id`, `created_at`, `updated_at`)
VALUES (NULL, v_past4, 'Bài ôn tập số 1', 'Làm các câu hỏi đã giao trên lớp', NULL, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY)), v_teacher4, DEFAULT, NULL);
SET v_homework4 = LAST_INSERT_ID();
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, v_past4, v_student7, 'co_mat', v_homework4, 8.5, 'Nắm kiến thức cơ bản', 4, 'tot', 'Tích cực phát biểu', 'Luyện thêm bài vận dụng', v_teacher4, DEFAULT);
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, v_past4, v_student8, 'vang_co_phep', NULL, NULL, NULL, NULL, NULL, 'Vắng có phép, đã thỏa thuận học bù', NULL, v_teacher4, DEFAULT);
INSERT INTO `ket_qua_hoc_tap` (`id`, `lop_id`, `hoc_vien_id`, `loai`, `ten`, `diem`, `diem_toi_da`, `ngay`, `nhan_xet`, `nguoi_nhap_id`, `created_at`, `updated_at`)
VALUES (NULL, v_class4, v_student7, 'kiem_tra', 'Kiểm tra đầu khóa', 8, 10, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'Đạt yêu cầu', v_teacher4, DEFAULT, NULL);
INSERT INTO `bang_cong_thang` (`id`, `user_id`, `thang`, `nam`, `so_buoi`, `tong_phut`, `so_buoi_bu`, `so_buoi_vang`, `so_lan_tre`, `tong_thu_nhap`, `thuong`, `khau_tru`, `thuc_nhan`, `trang_thai`, `chot_boi`, `chot_luc`, `ngay_tra`, `ghi_chu`, `created_at`, `updated_at`, `tra_boi`, `tra_luc`, `ma_giao_dich_chi`, `mo_khoa_boi`, `mo_khoa_luc`, `ly_do_mo_khoa`)
VALUES (NULL, v_teacher4, MONTH(DATE_SUB(CURDATE(), INTERVAL 14 DAY)), YEAR(DATE_SUB(CURDATE(), INTERVAL 14 DAY)), 1, 110, 0, 0, 1, ROUND(110 / 60 * 190000), 0, 0, DEFAULT, 'mo', NULL, NULL, NULL, 'Bảng công mẫu đang mở, chưa chốt/trả', DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, NULL);
SET v_payroll4 = LAST_INSERT_ID();

UPDATE phan_cong_buoi_hoc SET bang_cong_id=v_payroll4 WHERE id=v_assignment_past4;
INSERT INTO `bai_tap` (`id`, `buoi_hoc_id`, `tieu_de`, `noi_dung`, `file_de_bai_url`, `han_nop`, `nguoi_tao_id`, `created_at`, `updated_at`)
VALUES (NULL, v_past5, 'Bài ôn tập số 1', 'Làm các câu hỏi đã giao trên lớp', NULL, TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY)), v_teacher6, DEFAULT, NULL);
SET v_homework5 = LAST_INSERT_ID();
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, v_past5, v_student9, 'co_mat', v_homework5, 8.5, 'Nắm kiến thức cơ bản', 4, 'tot', 'Tích cực phát biểu', 'Luyện thêm bài vận dụng', v_teacher6, DEFAULT);
INSERT INTO `so_theo_doi_buoi_hoc` (`id`, `buoi_hoc_id`, `hoc_vien_id`, `trang_thai_diem_danh`, `bai_tap_id`, `diem_bai_tap_giay`, `nhan_xet_bai_tap`, `muc_tiep_thu`, `thai_do`, `nhan_xet_buoi_hoc`, `huong_cai_thien`, `nguoi_nhap_id`, `updated_at`)
VALUES (NULL, v_past5, v_student10, 'vang_co_phep', NULL, NULL, NULL, NULL, NULL, 'Vắng có phép, đã thỏa thuận học bù', NULL, v_teacher6, DEFAULT);
INSERT INTO `ket_qua_hoc_tap` (`id`, `lop_id`, `hoc_vien_id`, `loai`, `ten`, `diem`, `diem_toi_da`, `ngay`, `nhan_xet`, `nguoi_nhap_id`, `created_at`, `updated_at`)
VALUES (NULL, v_class5, v_student9, 'kiem_tra', 'Kiểm tra đầu khóa', 8, 10, DATE_SUB(CURDATE(), INTERVAL 14 DAY), 'Đạt yêu cầu', v_teacher6, DEFAULT, NULL);
INSERT INTO `bang_cong_thang` (`id`, `user_id`, `thang`, `nam`, `so_buoi`, `tong_phut`, `so_buoi_bu`, `so_buoi_vang`, `so_lan_tre`, `tong_thu_nhap`, `thuong`, `khau_tru`, `thuc_nhan`, `trang_thai`, `chot_boi`, `chot_luc`, `ngay_tra`, `ghi_chu`, `created_at`, `updated_at`, `tra_boi`, `tra_luc`, `ma_giao_dich_chi`, `mo_khoa_boi`, `mo_khoa_luc`, `ly_do_mo_khoa`)
VALUES (NULL, v_teacher6, MONTH(DATE_SUB(CURDATE(), INTERVAL 14 DAY)), YEAR(DATE_SUB(CURDATE(), INTERVAL 14 DAY)), 1, 110, 0, 0, 1, ROUND(110 / 60 * 210000), 0, 0, DEFAULT, 'mo', NULL, NULL, NULL, 'Bảng công mẫu đang mở, chưa chốt/trả', DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, NULL);
SET v_payroll5 = LAST_INSERT_ID();

UPDATE phan_cong_buoi_hoc SET bang_cong_id=v_payroll5 WHERE id=v_assignment_past5;

-- A7. Hoa don tron khoa, phieu thu, giao dich va su kien gia lap.
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(v_tag,'-HD01'), NULL, NULL, NULL, 1600000, 0, DEFAULT, 0, 'chua_thanh_toan', DATE_ADD(CURDATE(), INTERVAL 14 DAY), v_giaovu, DEFAULT, DEFAULT, NOW(), v_giaovu, NULL, NULL, NULL, v_registration1);
SET v_invoice1 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, v_invoice1, NULL, 'khoa_hoc', 'Toán 9 cơ bản học kỳ 1', 1600000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(v_tag,'-HD02'), NULL, NULL, NULL, 1600000, 0, DEFAULT, 0, 'chua_thanh_toan', DATE_ADD(CURDATE(), INTERVAL 14 DAY), v_giaovu, DEFAULT, DEFAULT, NOW(), v_giaovu, NULL, NULL, NULL, v_registration2);
SET v_invoice2 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, v_invoice2, NULL, 'khoa_hoc', 'Toán 9 cơ bản học kỳ 1', 1600000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(v_tag,'-HD03'), NULL, NULL, NULL, 1700000, 0, DEFAULT, 0, 'chua_thanh_toan', DATE_ADD(CURDATE(), INTERVAL 14 DAY), v_giaovu, DEFAULT, DEFAULT, NOW(), v_giaovu, NULL, NULL, NULL, v_registration3);
SET v_invoice3 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, v_invoice3, NULL, 'khoa_hoc', 'Toán 12 ôn luyện chuyên', 1700000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(v_tag,'-HD04'), NULL, NULL, NULL, 1700000, 0, DEFAULT, 0, 'chua_thanh_toan', DATE_ADD(CURDATE(), INTERVAL 14 DAY), v_giaovu, DEFAULT, DEFAULT, NOW(), v_giaovu, NULL, NULL, NULL, v_registration4);
SET v_invoice4 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, v_invoice4, NULL, 'khoa_hoc', 'Toán 12 ôn luyện chuyên', 1700000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(v_tag,'-HD05'), NULL, NULL, NULL, 1800000, 0, DEFAULT, 0, 'chua_thanh_toan', DATE_ADD(CURDATE(), INTERVAL 14 DAY), v_giaovu, DEFAULT, DEFAULT, NOW(), v_giaovu, NULL, NULL, NULL, v_registration5);
SET v_invoice5 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, v_invoice5, NULL, 'khoa_hoc', 'Vật lý 11 nâng cao học kỳ 2', 1800000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(v_tag,'-HD06'), NULL, NULL, NULL, 1800000, 0, DEFAULT, 0, 'chua_thanh_toan', DATE_ADD(CURDATE(), INTERVAL 14 DAY), v_giaovu, DEFAULT, DEFAULT, NOW(), v_giaovu, NULL, NULL, NULL, v_registration6);
SET v_invoice6 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, v_invoice6, NULL, 'khoa_hoc', 'Vật lý 11 nâng cao học kỳ 2', 1800000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(v_tag,'-HD07'), NULL, NULL, NULL, 1900000, 0, DEFAULT, 0, 'nhap', DATE_ADD(CURDATE(), INTERVAL 14 DAY), v_giaovu, DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, v_registration7);
SET v_invoice7 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, v_invoice7, NULL, 'khoa_hoc', 'Tiếng Anh 6 nền tảng cả năm', 1900000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(v_tag,'-HD08'), NULL, NULL, NULL, 1900000, 0, DEFAULT, 0, 'nhap', DATE_ADD(CURDATE(), INTERVAL 14 DAY), v_giaovu, DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, v_registration8);
SET v_invoice8 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, v_invoice8, NULL, 'khoa_hoc', 'Tiếng Anh 6 nền tảng cả năm', 1900000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(v_tag,'-HD09'), NULL, NULL, NULL, 2000000, 0, DEFAULT, 0, 'nhap', DATE_ADD(CURDATE(), INTERVAL 14 DAY), v_giaovu, DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, v_registration9);
SET v_invoice9 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, v_invoice9, NULL, 'khoa_hoc', 'Ngữ văn 9 luyện đề', 2000000);
INSERT INTO `hoa_don` (`id`, `ma_hd`, `lop_hoc_vien_id`, `thang`, `nam`, `tong_tien`, `giam_gia`, `phai_thu`, `da_thu`, `trang_thai`, `han_thanh_toan`, `nguoi_lap_id`, `created_at`, `updated_at`, `phat_hanh_luc`, `phat_hanh_boi`, `huy_luc`, `huy_boi`, `ly_do_huy`, `dang_ky_khoa_hoc_id`)
VALUES (NULL, CONCAT(v_tag,'-HD10'), NULL, NULL, NULL, 2000000, 0, DEFAULT, 0, 'nhap', DATE_ADD(CURDATE(), INTERVAL 14 DAY), v_giaovu, DEFAULT, DEFAULT, NULL, NULL, NULL, NULL, NULL, v_registration10);
SET v_invoice10 = LAST_INSERT_ID();
INSERT INTO `hoa_don_chi_tiet` (`id`, `hoa_don_id`, `buoi_hoc_id`, `loai_khoan`, `mo_ta`, `don_gia`)
VALUES (NULL, v_invoice10, NULL, 'khoa_hoc', 'Ngữ văn 9 luyện đề', 2000000);
INSERT INTO `thanh_toan` (`id`, `hoa_don_id`, `loai`, `so_tien`, `phuong_thuc`, `ma_giao_dich`, `ngay_thanh_toan`, `nguoi_thu_id`, `trang_thai`, `ghi_chu`, `huy_luc`, `huy_boi`, `ly_do_huy`)
VALUES (NULL, v_invoice1, 'thu', 500000, 'tien_mat', CONCAT(v_tag,'-PT01'), DEFAULT, v_admin, 'hop_le', 'Phiếu thu giả lập, chưa thu đủ trọn khóa', NULL, NULL, NULL);
INSERT INTO `giao_dich_truc_tuyen` (`id`, `ma_yeu_cau`, `hoa_don_id`, `phu_huynh_id`, `nha_cung_cap`, `ma_giao_dich_cong`, `so_tien`, `tien_te`, `trang_thai`, `thanh_toan_id`, `xac_nhan_boi`, `xac_nhan_luc`, `het_han_luc`, `created_at`, `updated_at`)
VALUES (NULL, CONCAT(v_tag,'-PAY01'), v_invoice2, v_parent1, 'demo_no_gateway', CONCAT(v_tag,'-BANK01'), 1600000, 'VND', 'khoi_tao', NULL, NULL, NULL, DATE_ADD(NOW(), INTERVAL 1 DAY), DEFAULT, DEFAULT);
SET v_payment1 = LAST_INSERT_ID();
INSERT INTO `su_kien_thanh_toan` (`id`, `giao_dich_id`, `nha_cung_cap`, `ma_su_kien`, `payload_sha256`, `trang_thai`, `loi`, `nhan_luc`, `xu_ly_luc`)
VALUES (NULL, v_payment1, 'demo_no_gateway', CONCAT(v_tag,'-EVENT01'), SHA2(CONCAT(v_tag,'-FAKE-EVENT-1'),256), 'cho_xu_ly', NULL, DEFAULT, NULL);
SET v_event1 = LAST_INSERT_ID();

UPDATE giao_dich_truc_tuyen SET trang_thai='cho_xac_nhan' WHERE id=v_payment1;
INSERT INTO `giao_dich_truc_tuyen` (`id`, `ma_yeu_cau`, `hoa_don_id`, `phu_huynh_id`, `nha_cung_cap`, `ma_giao_dich_cong`, `so_tien`, `tien_te`, `trang_thai`, `thanh_toan_id`, `xac_nhan_boi`, `xac_nhan_luc`, `het_han_luc`, `created_at`, `updated_at`)
VALUES (NULL, CONCAT(v_tag,'-PAY02'), v_invoice3, v_parent2, 'demo_no_gateway', CONCAT(v_tag,'-BANK02'), 1700000, 'VND', 'khoi_tao', NULL, NULL, NULL, DATE_ADD(NOW(), INTERVAL 1 DAY), DEFAULT, DEFAULT);
SET v_payment2 = LAST_INSERT_ID();
INSERT INTO `su_kien_thanh_toan` (`id`, `giao_dich_id`, `nha_cung_cap`, `ma_su_kien`, `payload_sha256`, `trang_thai`, `loi`, `nhan_luc`, `xu_ly_luc`)
VALUES (NULL, v_payment2, 'demo_no_gateway', CONCAT(v_tag,'-EVENT02'), SHA2(CONCAT(v_tag,'-FAKE-EVENT-2'),256), 'cho_xu_ly', NULL, DEFAULT, NULL);
SET v_event2 = LAST_INSERT_ID();

UPDATE giao_dich_truc_tuyen SET trang_thai='cho_xac_nhan' WHERE id=v_payment2;
INSERT INTO `thanh_toan` (`id`, `hoa_don_id`, `loai`, `so_tien`, `phuong_thuc`, `ma_giao_dich`, `ngay_thanh_toan`, `nguoi_thu_id`, `trang_thai`, `ghi_chu`, `huy_luc`, `huy_boi`, `ly_do_huy`)
VALUES (NULL, v_invoice3, 'thu', 1700000, 'chuyen_khoan', CONCAT(v_tag,'-PT02'), DEFAULT, v_admin, 'hop_le', 'Đối soát giả lập dữ liệu, không gọi ngân hàng', NULL, NULL, NULL);
SET v_receipt2 = LAST_INSERT_ID();

UPDATE giao_dich_truc_tuyen SET trang_thai='thanh_cong',thanh_toan_id=v_receipt2,xac_nhan_boi=v_admin,xac_nhan_luc=NOW() WHERE id=v_payment2;
UPDATE su_kien_thanh_toan SET trang_thai='da_xu_ly',xu_ly_luc=NOW() WHERE id=v_event2;
INSERT INTO `thong_bao` (`id`, `user_id`, `loai`, `tieu_de`, `noi_dung`, `doi_tuong_loai`, `doi_tuong_id`, `khoa_chong_trung`, `da_doc`, `doc_luc`, `co_gui_email`, `so_lan_thu_email`, `trang_thai_email`, `loi_email`, `created_at`, `email_gui_luc`, `email_thu_gan_nhat_luc`, `email_thu_tiep_luc`)
VALUES (NULL, v_giaovu, 'he_thong', 'Bộ dữ liệu SQLDEMO đã sẵn sàng', 'Kiểm tra lớp, sĩ số, ca dạy, học vụ và hóa đơn.', 'lop_hoc', v_class1, CONCAT(v_tag,'-NOTICE'), DEFAULT, NULL, 0, 0, 'chua_gui', NULL, DEFAULT, NULL, NULL, NULL);
INSERT INTO `audit_log` (`id`, `user_id`, `hanh_dong`, `bang`, `ban_ghi_id`, `du_lieu_cu`, `du_lieu_moi`, `ip`, `created_at`)
VALUES (NULL, v_admin, 'them', 'lop_hoc', v_class1, NULL, JSON_OBJECT('nguon','manual_sql_fixture','ma_bo',v_tag,'version','all_roles_v2'), '127.0.0.1', DEFAULT);


        COMMIT;
    END IF;

    -- PHẦN TRUY VẤN. XEM và THEM đều trả cùng các bảng kết quả để đối chiếu.
    SELECT (SELECT COUNT(*) FROM account) AS tai_khoan,
           (SELECT COUNT(*) FROM hoc_vien) AS hoc_vien,
           (SELECT COUNT(*) FROM lop_hoc) AS lop_hoc,
           (SELECT COUNT(*) FROM buoi_hoc) AS ca_hoc,
           (SELECT COUNT(*) FROM hoa_don) AS hoa_don;
    SELECT a.username AS tai_khoan, IF(BINARY a.password_hash=BINARY v_password_hash,'Demo@123','Mật khẩu hiện có') AS mat_khau_demo, g.group_name AS nhom,
           ns.phan_loai AS loai_nguoi_day, u.hoten AS ho_ten, a.status AS trang_thai
    FROM account a JOIN users u ON u.user_id=a.user_id
    JOIN role_group g ON g.group_id=a.group_id
    LEFT JOIN nhan_su_chuyen_mon ns ON ns.user_id=a.user_id
    WHERE LEFT(a.username,5)='demo_'
    ORDER BY a.account_id;
    SELECT h.ma_hv,h.ho_ten,p.hoten AS phu_huynh,l.ma_lop,l.ten_lop
    FROM hoc_vien h JOIN users p ON p.user_id=h.phu_huynh_user_id
    LEFT JOIN lop_hoc_vien x ON x.hoc_vien_id=h.id
    LEFT JOIN lop_hoc l ON l.id=x.lop_id ORDER BY h.id;
    SELECT b.ngay,l.ma_lop,b.gio_bat_dau,b.gio_ket_thuc,u.hoten AS nguoi_day,
           p.so_phut_tinh_cong,p.checkin_tre
    FROM buoi_hoc b JOIN lop_hoc l ON l.id=b.lop_id
    JOIN phan_cong_buoi_hoc p ON p.buoi_hoc_id=b.id JOIN users u ON u.user_id=p.user_id
    ORDER BY b.ngay,b.gio_bat_dau,l.ma_lop,p.vai_tro;
    SELECT ma_hd,tong_tien,da_thu,phai_thu-da_thu AS con_lai,trang_thai FROM hoa_don ORDER BY id;
    DO RELEASE_LOCK(v_lock_name);
END$$
DELIMITER ;

-- Chạy cả file lần đầu sẽ thêm bộ demo. Chạy lại giữ dữ liệu đã có và trả kết quả.
CALL sp_demo_educenter('THEM');

-- Tài khoản minh họa, không tạo QR chuyển tiền thật. Không ghi đè tài khoản thật đã cấu hình.
INSERT IGNORE INTO cau_hinh(khoa,gia_tri,mo_ta) VALUES
('payment.bank.demo-vcb','{"bin":"970436","bank":"Vietcombank (DEMO)","number":"DEMOEDU01","holder":"EDUCENTER DEMO","active":true,"demo":true}','Tài khoản minh họa, không chuyển tiền');
INSERT IGNORE INTO cau_hinh(khoa,gia_tri,mo_ta) VALUES
('payment.bank.demo-mb','{"bin":"970422","bank":"MB (DEMO)","number":"DEMOEDU02","holder":"EDUCENTER DEMO","active":true,"demo":true}','Tài khoản minh họa thứ hai');
INSERT IGNORE INTO cau_hinh(khoa,gia_tri,mo_ta) VALUES
('payment.bank.default','demo-vcb','Tài khoản nhận học phí mặc định');
UPDATE cau_hinh SET gia_tri='demo-vcb' WHERE khoa='payment.bank.default' AND gia_tri='';

/*
===================== CÁC LỆNH TỰ THAO TÁC TRONG DATAGRIP =====================
-- Chọn và chạy đúng một lệnh CALL trong cùng schema trung_tam_gia_su.
-- THÊM DỮ LIỆU
CALL sp_demo_educenter('THEM');

-- XEM / ĐỐI CHIẾU DỮ LIỆU
CALL sp_demo_educenter('XEM');
SELECT ma,ten,hoc_phi,is_active FROM khoa_hoc;
SELECT ma_lop,ten_lop,loai_lop,trang_thai FROM lop_hoc;
SELECT ma_hd,phai_thu,da_thu,trang_thai FROM hoa_don;

-- XÓA DỮ LIỆU NGHIỆP VỤ DEMO, GIỮ TÀI KHOẢN/MẬT KHẨU VÀ VAI TRÒ. Sao lưu trước.
CALL sp_demo_educenter('XOA');

-- SAU KHI XÓA, THÊM LẠI DEMO BẰNG CÁC TÀI KHOẢN ĐÃ GIỮ
CALL sp_demo_educenter('THEM');
*/
