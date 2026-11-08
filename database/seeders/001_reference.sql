-- [SỬA] Tách danh mục/quyền gốc ra khỏi schema, giữ 5 nhóm và 8 phân hệ theo tài liệu Word.
-- [LƯU Ý] Chỉ chạy trên database mới, không ghi đè ma trận phân quyền đang dùng.
START TRANSACTION;
-- 12.1. Nhóm quyền
INSERT INTO role_group (group_id, group_name, description) VALUES
 (1, 'Admin', 'Ban Giám Đốc, Quản trị viên tối cao'),
 (2, 'NhanVien', 'Điều phối viên, Giáo vụ, Kế toán trung tâm'),
 (3, 'GiangVien', 'Giáo viên cơ hữu và Gia sư đứng lớp chính'),
 (4, 'TroGiang', 'Trợ giảng hỗ trợ lớp nhóm'),
 (5, 'PhuHuynh', 'Phụ huynh học viên đại diện gia đình');

-- 12.2. Phân hệ chức năng
INSERT INTO `function` (function_id, name_function) VALUES
 (1, 'QuanLyTaiKhoan'), (2, 'QuanLyNhanSu'), (3, 'TiepNhanYeuCau'),
 (4, 'QuanLyLopHoc'), (5, 'VanHanhBuoiHoc'), (6, 'ChamCongLuong'),
 (7, 'HocPhiThanhToan'), (8, 'BaoCaoThongKe');

-- 12.3. Ma trận phân quyền nhóm (ROLE_GROUP_FUNCTION)
INSERT INTO role_group_function (group_id, function_id, `add`, `edit`, `delete`, download, `view`) VALUES
 -- Admin toàn quyền trên cả 8 phân hệ
 (1, 1, 1, 1, 1, 1, 1), (1, 2, 1, 1, 1, 1, 1), (1, 3, 1, 1, 1, 1, 1), (1, 4, 1, 1, 1, 1, 1),
 (1, 5, 1, 1, 1, 1, 1), (1, 6, 1, 1, 1, 1, 1), (1, 7, 1, 1, 1, 1, 1), (1, 8, 1, 1, 1, 1, 1),
 -- Nhân viên
 (2, 3, 1, 1, 0, 1, 1), (2, 4, 1, 1, 0, 1, 1), (2, 5, 1, 1, 0, 1, 1), (2, 7, 1, 1, 0, 1, 1), (2, 8, 0, 0, 0, 1, 1),
 -- Giảng viên (Giáo viên / Gia sư chính)
 (3, 5, 1, 1, 0, 1, 1), (3, 6, 0, 0, 0, 0, 0),
 -- Trợ giảng (chỉ hỗ trợ điểm danh & check-in công của mình)
 (4, 5, 0, 1, 0, 0, 1), (4, 6, 0, 0, 0, 0, 0),
 -- Phụ huynh
 (5, 5, 0, 0, 0, 1, 1), (5, 7, 0, 0, 0, 1, 1);

-- 12.4. Danh mục cơ bản
INSERT INTO mon_hoc (id, ma, ten) VALUES
 (1,'TOAN','Toán'),(2,'VAN','Ngữ văn'),(3,'ANH','Tiếng Anh'),(4,'LY','Vật lý'),(5,'HOA','Hóa học'),(6,'SINH','Sinh học');

INSERT INTO cap_lop (id, ten, cap_hoc) VALUES
 (1,'Lớp 1','tieu_hoc'),(2,'Lớp 2','tieu_hoc'),(3,'Lớp 3','tieu_hoc'),(4,'Lớp 4','tieu_hoc'),(5,'Lớp 5','tieu_hoc'),
 (6,'Lớp 6','thcs'),(7,'Lớp 7','thcs'),(8,'Lớp 8','thcs'),(9,'Lớp 9','thcs'),
 (10,'Lớp 10','thpt'),(11,'Lớp 11','thpt'),(12,'Lớp 12','thpt');

INSERT INTO khu_vuc (id, ten) VALUES
 (1,'Thủ Đức'),(2,'Bình Thạnh'),(3,'Gò Vấp'),(4,'Phú Nhuận'),(5,'Tân Bình'),(6,'Quận 1'),(7,'Quận 3');

INSERT INTO cau_hinh (khoa, gia_tri, mo_ta) VALUES
 ('checkin_truoc_phut','-1','Không giới hạn check-in sớm; phút công chỉ tính trong lịch phân công'),
 ('checkin_tre_sau_phut','0','Đến sau giờ lịch là trễ'),
 ('han_nhap_bao_cao_gio','24','Thời hạn người dạy nhập nhận xét & bài tập');


COMMIT;
