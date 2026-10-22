-- [LƯU Ý] CHỈ nâng cấp đúng schema 35 bảng gốc, chạy một lần sau preflight.sql.
-- [LƯU Ý] Dừng ghi dữ liệu, sao lưu trước khi chạy; DDL MySQL tự COMMIT, không rollback toàn bộ.
-- [LƯU Ý] Không chạy với --force. Không tự đổi dữ liệu cũ để vượt ràng buộc.
-- [THÊM] Nhật ký phiên bản kỹ thuật; không lưu hay thay đổi dữ liệu nghiệp vụ.
CREATE TABLE schema_migrations (
  version VARCHAR(80) NOT NULL PRIMARY KEY,
  status ENUM('applying','applied') NOT NULL,
  applied_at DATETIME NULL
) ENGINE=InnoDB;
INSERT INTO schema_migrations(version, status) VALUES ('001_feature_based','applying');
-- [THÊM] Mức học phí tháng cố định cho cach_thu_phi=theo_thang; NULL nghĩa là chưa cấu hình, không tự lấy giá buổi.
ALTER TABLE lop_hoc ADD COLUMN hoc_phi_thang DECIMAL(12,0) NULL;

-- [THÊM] Chụp cơ sở của buổi học để kiểm tra phòng theo cơ sở, không thay đổi tên phòng dạng chuỗi đã quy định.
ALTER TABLE buoi_hoc ADD COLUMN co_so_id INT UNSIGNED NULL;

-- [THÊM] Ràng buộc cơ sở của buổi học; không xóa lịch sử theo cơ sở.
ALTER TABLE buoi_hoc ADD CONSTRAINT fk_buoi_coso FOREIGN KEY (co_so_id) REFERENCES co_so(id);

-- [THÊM] Bổ sung chỉ mục kiểm tra xung đột phòng trong cùng cơ sở/ngày.
ALTER TABLE buoi_hoc ADD KEY idx_buoi_phong (co_so_id, phong_hoc, ngay, trang_thai);

-- [THÊM] Snapshot cách tính lương cùng đơn giá; NULL cho dữ liệu lịch sử chưa xác minh, chặn chốt công nếu còn NULL.
ALTER TABLE phan_cong_buoi_hoc ADD COLUMN cach_tinh_luong ENUM('theo_buoi','theo_gio') NULL;

-- [THÊM] Đơn giá ca dạy không được âm.
ALTER TABLE phan_cong_buoi_hoc ADD CONSTRAINT ck_pc_don_gia CHECK (don_gia >= 0);

-- [THÊM] Công hợp lệ phải có cả check-in và check-out, không ép mọi ca dự kiến phải check-in.
ALTER TABLE phan_cong_buoi_hoc ADD CONSTRAINT ck_pc_hop_le CHECK (trang_thai_cong <> 'hop_le' OR (checkin_luc IS NOT NULL AND checkout_luc IS NOT NULL));

-- [THÊM] GPS phải đủ cặp và đúng miền giá trị. [LƯU Ý] Chưa ép bán kính khi tài liệu Word chỉ yêu cầu GPS nếu có.
ALTER TABLE phan_cong_buoi_hoc ADD CONSTRAINT ck_pc_gps CHECK ((checkin_lat IS NULL AND checkin_lng IS NULL) OR (checkin_lat IS NOT NULL AND checkin_lng IS NOT NULL AND checkin_lat BETWEEN -90 AND 90 AND checkin_lng BETWEEN -180 AND 180));

-- [SỬA] Bỏ FK SET NULL có thể làm mất khóa công khi xóa bảng công.
ALTER TABLE phan_cong_buoi_hoc DROP FOREIGN KEY fk_pc_bct;

-- [SỬA] Chặn xóa bảng công đang liên kết ca dạy, giữ lịch sử quyết toán.
ALTER TABLE phan_cong_buoi_hoc ADD CONSTRAINT fk_pc_bct FOREIGN KEY (bang_cong_id) REFERENCES bang_cong_thang(id);

-- [THÊM] Khoảng hiệu lực lịch tuần phải hợp lệ.
ALTER TABLE lich_hoc ADD CONSTRAINT ck_lich_hieu_luc CHECK (hieu_luc_den IS NULL OR hieu_luc_den >= hieu_luc_tu);

-- [THÊM] Học phí tháng không âm.
ALTER TABLE lop_hoc ADD CONSTRAINT ck_lop_phi_thang CHECK (hoc_phi_thang IS NULL OR hoc_phi_thang >= 0);

-- [THÊM] Số buổi dự kiến nếu khai báo phải dương.
ALTER TABLE lop_hoc ADD CONSTRAINT ck_lop_so_buoi CHECK (so_buoi_du_kien IS NULL OR so_buoi_du_kien > 0);

-- [THÊM] Ngân sách hai đầu không âm.
ALTER TABLE yeu_cau_hoc ADD CONSTRAINT ck_yc_ns_duong CHECK ((ngan_sach_min IS NULL OR ngan_sach_min >= 0) AND (ngan_sach_max IS NULL OR ngan_sach_max >= 0));

-- [THÊM] Học phí đề xuất không âm.
ALTER TABLE de_xuat_nhan_su ADD CONSTRAINT ck_dx_phi CHECK (hoc_phi_de_xuat IS NULL OR hoc_phi_de_xuat >= 0);

-- [THÊM] Đơn giá giờ chuẩn không âm.
ALTER TABLE nhan_su_chuyen_mon ADD CONSTRAINT ck_ns_don_gia CHECK (don_gia_gio_chuan >= 0);

-- [THÊM] Thang điểm phải dương, giữ nguyên điểm tối đa có thể tùy chỉnh.
ALTER TABLE ket_qua_hoc_tap ADD CONSTRAINT ck_kq_thang_diem CHECK (diem_toi_da > 0);

-- [THÊM] Lũy kế thu không được lớn hơn phải thu; hoàn tiền không được làm số dư âm.
ALTER TABLE hoa_don ADD CONSTRAINT ck_hd_so_du CHECK (da_thu <= phai_thu);

-- [THÊM] Đơn giá từng khoản học phí không âm.
ALTER TABLE hoa_don_chi_tiet ADD CONSTRAINT ck_hdct_gia CHECK (don_gia >= 0);

-- [THÊM] Khoản theo buổi phải có buổi học; khoản tháng/khóa vẫn được NULL.
-- [SỬA] Giữ buổi học của chứng từ; SET NULL không tương thích CHECK buoi_hoc_id và làm mất dấu vết khoản thu.
ALTER TABLE hoa_don_chi_tiet DROP FOREIGN KEY fk_hdct_buoi;
ALTER TABLE hoa_don_chi_tiet ADD CONSTRAINT fk_hdct_buoi FOREIGN KEY (buoi_hoc_id) REFERENCES buoi_hoc(id);
ALTER TABLE hoa_don_chi_tiet ADD CONSTRAINT ck_hdct_buoi CHECK (loai_khoan <> 'buoi_hoc' OR buoi_hoc_id IS NOT NULL);

-- [THÊM] Thu nhập, thưởng và khấu trừ không âm; không tự cấm thuc_nhan âm khi chưa có quy định.
ALTER TABLE bang_cong_thang ADD CONSTRAINT ck_bc_tien CHECK (tong_thu_nhap >= 0 AND thuong >= 0 AND khau_tru >= 0);

-- [THÊM] Giờ công đề nghị mới phải có thứ tự hợp lệ.
ALTER TABLE dieu_chinh_cong ADD CONSTRAINT ck_dcc_gio CHECK (checkout_moi IS NULL OR (checkin_moi IS NOT NULL AND checkout_moi > checkin_moi));

-- [THÊM] Chỉ sao chép cơ sở hiện tại cho các buổi CHƯA chốt, không động tới giá trị tài chính cũ.
-- [LƯU Ý] Phải kiểm tra cơ sở lịch sử trong báo cáo trước khi nâng cấp; buổi đã khóa vẫn để NULL.
UPDATE buoi_hoc b JOIN lop_hoc l ON l.id=b.lop_id
SET b.co_so_id=l.co_so_id
WHERE NOT EXISTS (SELECT 1 FROM phan_cong_buoi_hoc pc WHERE pc.buoi_hoc_id=b.id AND pc.bang_cong_id IS NOT NULL);
-- [LƯU Ý] Không backfill cách tính lương quá khứ từ lớp hiện tại vì có thể làm sai tiền lương.
-- [LƯU Ý] Sau khi cài procedures.sql và triggers.sql thành công mới đánh dấu phiên bản applied.
