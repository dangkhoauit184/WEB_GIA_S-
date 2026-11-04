-- [SỬA] Dữ liệu minh họa từ SQL gốc tách riêng, chỉ chạy theo lựa chọn rõ ràng trên database thử nghiệm.
-- [LƯU Ý] Chạy 001_reference.sql trước; chỉ chạy một lần, không dùng trên database đang vận hành.
-- [LƯU Ý] Không đặt giá tháng hoặc chính sách tính phí vắng mặt khi tài liệu chưa xác định.
-- 12.5. Người dùng mẫu & Cơ sở
INSERT INTO users (user_id, hoten, sdt, email, ngaysinh, diachi, nghe_nghiep, khu_vuc_id) VALUES
 (1, 'Quản Trị Viên', '0900000001', 'admin@giasu.local', '1988-01-01', 'Quận 1, TP.HCM', 'Quản lý', 6),
 (2, 'Lê Thị Mai (Điều phối)', '0900000002', 'nhanvien1@giasu.local', '1995-05-10', 'Bình Thạnh, TP.HCM', 'Nhân viên giáo vụ', 2),
 (3, 'Nguyễn Minh Anh (Gia sư)', '0900000003', 'gs.minhanh@giasu.local', '2002-03-15', 'Thủ Đức, TP.HCM', 'Sinh viên', 1),
 (4, 'Trần Quốc Bảo (Gia sư)', '0900000004', 'gs.quocbao@giasu.local', '2003-07-20', 'Thủ Đức, TP.HCM', 'Sinh viên', 1),
 (5, 'Cô Lê Thu Cúc (Giáo viên)', '0900000005', 'gv.thucuc@giasu.local', '1990-11-02', 'Gò Vấp, TP.HCM', 'Giáo viên', 3),
 (6, 'Phạm Gia Huy (Trợ giảng)', '0900000006', 'tg.giahuy@giasu.local', '2005-04-18', 'Thủ Đức, TP.HCM', 'Sinh viên', 1),
 (7, 'Nguyễn Thị Hoa (Phụ huynh)', '0900000007', 'ph.hoa@giasu.local', '1982-08-25', '12 Võ Văn Ngân, Thủ Đức', 'Kế toán', 1),
 (8, 'Trần Văn Nam (Phụ huynh)', '0900000008', 'ph.nam@giasu.local', '1980-12-14', '45 Phan Văn Trị, Gò Vấp', 'Kinh doanh', 3);

-- [SỬA] Tài khoản demo bị khóa vì hash password công khai; chỉ mở khi đã đổi mật khẩu. Không dùng production.
INSERT INTO account (account_id, user_id, group_id, username, password_hash, status) VALUES
 (1, 1, 1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'locked'),
 (2, 2, 2, 'nhanvien1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'locked'),
 (3, 3, 3, 'gs_minhanh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'locked'),
 (4, 4, 3, 'gs_quocbao', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'locked'),
 (5, 5, 3, 'gv_thucuc', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'locked'),
 (6, 6, 4, 'tg_giahuy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'locked'),
 (7, 7, 5, 'ph_hoa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'locked'),
 (8, 8, 5, 'ph_nam', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'locked');

-- Cơ sở đào tạo (QUAN_LY_ID trỏ tới USERS)
INSERT INTO co_so (id, ma_co_so, ten_co_so, dia_chi, so_dien_thoai, khu_vuc_id, quan_ly_id) VALUES
 (1, 'CS-THUDUC', 'Cơ sở 1 - TP. Thủ Đức', '12 Võ Văn Ngân, P. Linh Chiểu, TP. Thủ Đức', '02838960001', 1, 1),
 (2, 'CS-GOVAP', 'Cơ sở 2 - Quận Gò Vấp', '45 Phan Văn Trị, Phường 5, Quận Gò Vấp', '02838960002', 3, 1);

-- Nhân viên
INSERT INTO nhan_vien (id, user_id, ma_nv, chuc_vu, co_so_id, ngay_vao_lam) VALUES
 (1, 2, 'NV001', 'Điều phối viên', 1, '2025-01-10');

-- Người dạy
INSERT INTO nhan_su_chuyen_mon (id, user_id, ma_ns, phan_loai, gioi_tinh, cccd, nghe_nghiep, don_vi_cong_tac, kinh_nghiem_nam, hinh_thuc_day, don_gia_gio_chuan, ngan_hang, so_tai_khoan, chu_tai_khoan) VALUES
 (1, 3, 'NS001', 'gia_su', 'nu', '079202000001', 'sinh_vien', 'ĐH Sư phạm TP.HCM', 3, 'online,offline', 70000, 'Vietcombank', '0011000000001', 'NGUYEN MINH ANH'),
 (2, 4, 'NS002', 'gia_su', 'nam', '079203000002', 'sinh_vien', 'ĐH Khoa học Tự nhiên', 2, 'offline', 80000, 'Techcombank', '19030000000002', 'TRAN QUOC BAO'),
 (3, 5, 'NS003', 'giao_vien', 'nu', '079190000003', 'giao_vien', 'THCS Nguyễn Du', 10, 'online,offline', 150000, 'ACB', '24000000003', 'LE THU CUC'),
 (4, 6, 'NS004', 'tro_giang', 'nam', '079205000004', 'sinh_vien', 'ĐH Công nghệ Thông tin', 1, 'online,offline', 35000, 'MBBank', '09000000006', 'PHAM GIA HUY');

-- Học viên
INSERT INTO hoc_vien (id, phu_huynh_user_id, ma_hv, ho_ten, ngay_sinh, gioi_tinh, truong, cap_lop_id, dia_chi, khu_vuc_id) VALUES
 (1, 7, 'HV001', 'Nguyễn Văn A', '2011-05-12', 'nam', 'THCS Linh Trung', 9, '12 Võ Văn Ngân, Thủ Đức', 1),
 (2, 7, 'HV002', 'Nguyễn Thị B', '2014-09-01', 'nu', 'TH Linh Chiểu', 6, '12 Võ Văn Ngân, Thủ Đức', 1),
 (3, 8, 'HV003', 'Trần Minh C', '2014-02-20', 'nam', 'THCS Gò Vấp', 6, '45 Phan Văn Trị, Gò Vấp', 3);

-- Chuyên môn môn học & Lịch rảnh
INSERT INTO nhan_su_mon (nhan_su_id, mon_hoc_id, cap_lop_tu, cap_lop_den, hoc_phi_buoi_de_xuat) VALUES
 (1, 1, 6, 9, 180000), (2, 1, 6, 12, 200000), (3, 3, 1, 9, 200000), (3, 2, 6, 9, 190000);

INSERT INTO nhan_su_khu_vuc VALUES (1,1),(1,2),(2,1),(2,3),(3,1),(3,3),(4,1);

INSERT INTO nhan_su_lich_ranh (nhan_su_id, thu, gio_bat_dau, gio_ket_thuc) VALUES
 (1, 3, '18:00', '21:00'), (1, 5, '18:00', '21:00'), (1, 7, '08:00', '12:00'),
 (2, 3, '18:00', '21:00'), (2, 5, '18:00', '21:00'),
 (3, 7, '08:00', '12:00'), (3, 8, '08:00', '12:00'),
 (4, 7, '08:00', '12:00');

-- Tuyển sinh: Yêu cầu học & Yêu cầu lịch
INSERT INTO yeu_cau_hoc (id, ma_yc, hoc_vien_id, mon_hoc_id, cap_lop_id, loai_lop_mong_muon, hinh_thuc, khu_vuc_id, ngan_sach_min, ngan_sach_max, muc_tieu, nhan_vien_id, nguoi_gui_id, don_vi_ngan_sach) VALUES
 (1, 'YC-2026-0001', 1, 1, 9, 'kem_1_1', 'offline', 1, 150000, 200000, 'Ôn thi vào lớp 10', 1, 7, 'theo_buoi');

INSERT INTO yeu_cau_lich (yeu_cau_id, thu, gio_bat_dau, gio_ket_thuc) VALUES
 (1, 3, '18:00', '20:00'), (1, 5, '18:00', '20:00');

INSERT INTO de_xuat_nhan_su (id, yeu_cau_id, nhan_su_id, nhan_vien_id, hoc_phi_de_xuat, tt_nhan_su, tt_phu_huynh, cach_thu_phi_de_xuat, nhan_su_phan_hoi_luc, phu_huynh_phan_hoi_luc, phu_huynh_phan_hoi_boi) VALUES
 (1, 1, 1, 1, 180000, 'dong_y', 'dong_y', 'theo_buoi', '2026-09-30 09:00:00', '2026-09-30 10:00:00', 7);

-- Khởi tạo Lớp học
INSERT INTO lop_hoc (id, ma_lop, ten_lop, loai_lop, de_xuat_id, mon_hoc_id, cap_lop_id, nguoi_day_chinh_id, co_so_id, phong_hoc, hinh_thuc, dia_diem, si_so_toi_thieu, si_so_toi_da, cach_thu_phi, hoc_phi_buoi, hoc_phi_khoa, muc_tra_chinh, muc_tra_tro_giang, cach_tinh_luong, so_buoi_du_kien, ngay_bat_dau, trang_thai, nhan_vien_id) VALUES
 (1, 'TOAN9-KEM11', 'Toán 9 kèm 1-1 em Nguyễn Văn A', 'kem_1_1', 1, 1, 9, 1, 1, NULL, 'offline', '12 Võ Văn Ngân, Thủ Đức', 1, 1, 'theo_buoi', 180000, 0, 140000, 0, 'theo_buoi', 24, '2026-10-01', 'dang_hoc', 1),
 (2, 'ANH6-NHOM01', 'Tiếng Anh 6 - Lớp nhóm chất lượng cao', 'nhom', NULL, 3, 6, 3, 1, 'Phòng 201', 'offline', 'Cơ sở 1 Thủ Đức', 3, 10, 'theo_thang', 120000, 0, 200000, 60000, 'theo_buoi', 20, '2026-10-03', 'dang_hoc', 1);

INSERT INTO lop_hoc_vien (lop_id, hoc_vien_id, ngay_vao, trang_thai) VALUES
 (1, 1, '2026-10-01', 'dang_hoc'),
 (2, 2, '2026-10-03', 'dang_hoc'),
 (2, 3, '2026-10-03', 'dang_hoc');

INSERT INTO lich_hoc (lop_id, thu, gio_bat_dau, gio_ket_thuc, phong_hoc, hieu_luc_tu) VALUES
 (1, 3, '18:00', '20:00', NULL, '2026-10-01'), (1, 5, '18:00', '20:00', NULL, '2026-10-01'),
 (2, 7, '09:00', '11:00', 'Phòng 201', '2026-10-03');

-- Tự động sinh lịch học tháng 10
CALL sp_sinh_buoi_hoc(1, '2026-10-01', '2026-10-31');
CALL sp_sinh_buoi_hoc(2, '2026-10-03', '2026-10-31');

-- Phân công thêm Trợ giảng cho buổi đầu của lớp 2
INSERT INTO phan_cong_buoi_hoc (buoi_hoc_id, user_id, vai_tro, don_gia, phan_cong_boi)
SELECT id, 6, 'tro_giang', 60000, 2 FROM buoi_hoc WHERE lop_id=2 AND ngay='2026-10-03';

-- Cập nhật buổi đã học
UPDATE buoi_hoc SET trang_thai='da_day' WHERE (lop_id=1 AND ngay='2026-10-01') OR (lop_id=2 AND ngay='2026-10-03');
UPDATE phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
   SET pc.checkin_luc  = CONCAT(b.ngay,' ', SUBTIME(b.gio_bat_dau,'00:05:00')),
       pc.checkout_luc = CONCAT(b.ngay,' ', ADDTIME(b.gio_ket_thuc,'00:02:00')),
       pc.trang_thai='da_day', pc.trang_thai_cong='hop_le'
 WHERE b.trang_thai='da_day';

-- Bài tập về nhà
INSERT INTO bai_tap (id, buoi_hoc_id, tieu_de, noi_dung, file_de_bai_url, han_nop, nguoi_tao_id)
SELECT 1, id, 'Phiếu bài tập số 01 - Hệ phương trình', 'Làm bài tập 1 đến 8 trong phiếu giấy phát tại lớp.', '/uploads/bt/phieu_01_toan9.pdf', '2026-10-06 18:00:00', 3
  FROM buoi_hoc WHERE lop_id=1 AND ngay='2026-10-01';

-- Sổ theo dõi buổi học: Điểm danh + Điểm bài tập giấy + Nhận xét
INSERT INTO so_theo_doi_buoi_hoc (buoi_hoc_id, hoc_vien_id, trang_thai_diem_danh, bai_tap_id, diem_bai_tap_giay, nhan_xet_bai_tap, muc_tiep_thu, thai_do, nhan_xet_buoi_hoc, nguoi_nhap_id)
SELECT b.id, 1, 'co_mat', 1, 8.5, 'Làm tốt bài 1-7, bài 8 nhầm dấu', 4, 'tot', 'Tiếp thu bài nhanh, tích cực phát biểu.', 3
  FROM buoi_hoc b WHERE b.lop_id=1 AND b.ngay='2026-10-01';

INSERT INTO so_theo_doi_buoi_hoc (buoi_hoc_id, hoc_vien_id, trang_thai_diem_danh, muc_tiep_thu, thai_do, nhan_xet_buoi_hoc, nguoi_nhap_id)
SELECT b.id, 2, 'co_mat', 4, 'tot', 'Nắm vững từ vựng Unit 1.', 5
  FROM buoi_hoc b WHERE b.lop_id=2 AND b.ngay='2026-10-03';

INSERT INTO so_theo_doi_buoi_hoc (buoi_hoc_id, hoc_vien_id, trang_thai_diem_danh, nhan_xet_buoi_hoc, nguoi_nhap_id)
SELECT b.id, 3, 'vang_co_phep', 'Gia đình có việc bận xin phép nghỉ.', 5
  FROM buoi_hoc b WHERE b.lop_id=2 AND b.ngay='2026-10-03';

-- Hóa đơn & chi tiết
INSERT INTO hoa_don (id, ma_hd, lop_hoc_vien_id, thang, nam, tong_tien, giam_gia, trang_thai, han_thanh_toan, nguoi_lap_id, phat_hanh_luc, phat_hanh_boi)
VALUES (1, 'HD-202610-0001', 1, 10, 2026, 180000, 0, 'chua_thanh_toan', '2026-10-15', 2, '2026-10-02 09:00:00', 2);

INSERT INTO hoa_don_chi_tiet (hoa_don_id, buoi_hoc_id, loai_khoan, mo_ta, don_gia)
SELECT 1, id, 'buoi_hoc', 'Học phí buổi 01/10/2026', 180000 FROM buoi_hoc WHERE lop_id=1 AND ngay='2026-10-01';

INSERT INTO thanh_toan (hoa_don_id, loai, so_tien, phuong_thuc, nguoi_thu_id)
VALUES (1, 'thu', 180000, 'chuyen_khoan', 2);

-- Thông báo
INSERT INTO thong_bao (user_id, loai, tieu_de, noi_dung, doi_tuong_loai, doi_tuong_id, khoa_chong_trung)
VALUES (3, 'phan_cong', 'Bạn được phân công dạy lớp TOAN9-KEM11', 'Lịch thứ 3 & thứ 5 lúc 18h', 'lop_hoc', 1, 'pc:lop1:u3');
