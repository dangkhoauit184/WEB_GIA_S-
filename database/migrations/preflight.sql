-- [THÊM] Báo cáo CHỈ ĐỌC trước nâng cấp schema 35 bảng gốc. Không có dòng kết quả nghĩa là không phát hiện lỗi dưới đây.
-- [LƯU Ý] Không tự sửa/xóa dữ liệu sai. Nếu cấu trúc không đúng bản gốc, truy vấn sẽ lỗi và script dừng.
-- [LƯU Ý] Đây không phải bằng chứng dữ liệu lịch sử đúng nghiệp vụ; vẫn phải đối chiếu sao lưu/hợp đồng.
SELECT 'Không đủ 35 bảng nghiệp vụ gốc' AS van_de, COUNT(*) AS so_luong
FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE'
HAVING COUNT(*)<>35
UNION ALL
SELECT 'Lịch tuần có hiệu lực ngược',COUNT(*) FROM lich_hoc WHERE hieu_luc_den<hieu_luc_tu HAVING COUNT(*)>0
UNION ALL
SELECT 'Ngân sách âm',COUNT(*) FROM yeu_cau_hoc WHERE ngan_sach_min<0 OR ngan_sach_max<0 HAVING COUNT(*)>0
UNION ALL
SELECT 'Học phí đề xuất âm',COUNT(*) FROM de_xuat_nhan_su WHERE hoc_phi_de_xuat<0 HAVING COUNT(*)>0
UNION ALL
SELECT 'Đơn giá chuẩn âm',COUNT(*) FROM nhan_su_chuyen_mon WHERE don_gia_gio_chuan<0 HAVING COUNT(*)>0
UNION ALL
SELECT 'Số buổi dự kiến bằng 0',COUNT(*) FROM lop_hoc WHERE so_buoi_du_kien=0 HAVING COUNT(*)>0
UNION ALL
SELECT 'Đơn giá phân công âm',COUNT(*) FROM phan_cong_buoi_hoc WHERE don_gia<0 HAVING COUNT(*)>0
UNION ALL
SELECT 'Công hợp lệ thiếu giờ',COUNT(*) FROM phan_cong_buoi_hoc WHERE trang_thai_cong='hop_le' AND (checkin_luc IS NULL OR checkout_luc IS NULL) HAVING COUNT(*)>0
UNION ALL
SELECT 'GPS thiếu cặp hoặc sai miền',COUNT(*) FROM phan_cong_buoi_hoc
WHERE (checkin_lat IS NULL)<>(checkin_lng IS NULL) OR checkin_lat NOT BETWEEN -90 AND 90 OR checkin_lng NOT BETWEEN -180 AND 180 HAVING COUNT(*)>0
UNION ALL
SELECT 'Thang điểm không dương',COUNT(*) FROM ket_qua_hoc_tap WHERE diem_toi_da<=0 HAVING COUNT(*)>0
UNION ALL
SELECT 'Thu nhập/thưởng/khấu trừ âm',COUNT(*) FROM bang_cong_thang WHERE tong_thu_nhap<0 OR thuong<0 OR khau_tru<0 HAVING COUNT(*)>0
UNION ALL
SELECT 'Số dư hóa đơn vượt phải thu',COUNT(*) FROM hoa_don WHERE da_thu>phai_thu HAVING COUNT(*)>0
UNION ALL
SELECT 'Chi tiết học phí âm hoặc thiếu buổi',COUNT(*) FROM hoa_don_chi_tiet WHERE don_gia<0 OR (loai_khoan='buoi_hoc' AND buoi_hoc_id IS NULL) HAVING COUNT(*)>0
UNION ALL
SELECT 'Giờ điều chỉnh ngược/thiếu checkin',COUNT(*) FROM dieu_chinh_cong WHERE checkout_moi IS NOT NULL AND (checkin_moi IS NULL OR checkout_moi<=checkin_moi) HAVING COUNT(*)>0
UNION ALL
SELECT 'Số dư hóa đơn lệch chứng từ',COUNT(*) FROM hoa_don hd WHERE hd.da_thu<>(SELECT COALESCE(SUM(IF(t.loai='thu',t.so_tien,-t.so_tien)),0) FROM thanh_toan t WHERE t.hoa_don_id=hd.id AND t.trang_thai='hop_le') HAVING COUNT(*)>0
UNION ALL
SELECT 'Phân công sai người/kỳ bảng công',COUNT(*) FROM phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id JOIN bang_cong_thang bc ON bc.id=pc.bang_cong_id
WHERE bc.user_id<>pc.user_id OR bc.thang<>MONTH(b.ngay) OR bc.nam<>YEAR(b.ngay) HAVING COUNT(*)>0
UNION ALL
SELECT 'Lớp đã quá sĩ số',COUNT(*) FROM lop_hoc l WHERE l.si_so_toi_da<(SELECT COUNT(*) FROM lop_hoc_vien h WHERE h.lop_id=l.id AND h.trang_thai IN ('dang_hoc','hoc_thu')) HAVING COUNT(*)>0
UNION ALL
SELECT 'Học bù sai lớp/gốc/trạng thái',COUNT(*) FROM buoi_hoc b LEFT JOIN buoi_hoc g ON g.id=b.buoi_goc_id
WHERE (b.loai='bu' AND (g.id IS NULL OR g.lop_id<>b.lop_id OR g.id=b.id OR g.loai<>'thuong' OR g.trang_thai NOT IN ('huy','doi')))
OR (b.loai='thuong' AND b.buoi_goc_id IS NOT NULL) HAVING COUNT(*)>0
UNION ALL
SELECT 'Buổi học không cùng lớp lịch tuần',COUNT(*) FROM buoi_hoc b JOIN lich_hoc lh ON lh.id=b.lich_hoc_id WHERE b.lop_id<>lh.lop_id HAVING COUNT(*)>0
UNION ALL
SELECT 'Trùng phòng/cơ sở/ngày',COUNT(*) FROM buoi_hoc a JOIN lop_hoc la ON la.id=a.lop_id JOIN buoi_hoc b ON b.id>a.id JOIN lop_hoc lb ON lb.id=b.lop_id
WHERE la.co_so_id=lb.co_so_id AND a.phong_hoc=b.phong_hoc AND TRIM(a.phong_hoc)<>'' AND a.ngay=b.ngay
AND a.trang_thai NOT IN ('huy','doi') AND b.trang_thai NOT IN ('huy','doi') AND a.gio_bat_dau<b.gio_ket_thuc AND a.gio_ket_thuc>b.gio_bat_dau HAVING COUNT(*)>0
UNION ALL
SELECT 'Trùng lịch người dạy',COUNT(*) FROM phan_cong_buoi_hoc a JOIN phan_cong_buoi_hoc b ON b.id>a.id AND b.user_id=a.user_id
JOIN buoi_hoc x ON x.id=a.buoi_hoc_id JOIN buoi_hoc y ON y.id=b.buoi_hoc_id
WHERE x.id<>y.id AND x.ngay=y.ngay AND x.gio_bat_dau<y.gio_ket_thuc AND x.gio_ket_thuc>y.gio_bat_dau
AND a.trang_thai<>'da_go' AND b.trang_thai<>'da_go' AND x.trang_thai NOT IN ('huy','doi') AND y.trang_thai NOT IN ('huy','doi') HAVING COUNT(*)>0
UNION ALL
SELECT 'Điểm danh không thuộc lớp/ngày học',COUNT(*) FROM so_theo_doi_buoi_hoc s JOIN buoi_hoc b ON b.id=s.buoi_hoc_id
WHERE NOT EXISTS (SELECT 1 FROM lop_hoc_vien l WHERE l.lop_id=b.lop_id AND l.hoc_vien_id=s.hoc_vien_id AND l.ngay_vao<=b.ngay AND (l.ngay_roi IS NULL OR l.ngay_roi>=b.ngay)) HAVING COUNT(*)>0
UNION ALL
SELECT 'Bài tập sổ học vụ khác lớp',COUNT(*) FROM so_theo_doi_buoi_hoc s JOIN buoi_hoc b ON b.id=s.buoi_hoc_id
JOIN bai_tap bt ON bt.id=s.bai_tap_id JOIN buoi_hoc g ON g.id=bt.buoi_hoc_id WHERE b.lop_id<>g.lop_id HAVING COUNT(*)>0
UNION ALL
SELECT 'Chi tiết hóa đơn khác học viên/lớp',COUNT(*) FROM hoa_don_chi_tiet ct JOIN hoa_don hd ON hd.id=ct.hoa_don_id JOIN lop_hoc_vien l ON l.id=hd.lop_hoc_vien_id
LEFT JOIN buoi_hoc b ON b.id=ct.buoi_hoc_id WHERE ct.hoc_vien_id<>l.hoc_vien_id OR (b.id IS NOT NULL AND b.lop_id<>l.lop_id) HAVING COUNT(*)>0
UNION ALL
SELECT 'Đơn nghỉ tham chiếu ca bù sai gốc',COUNT(*) FROM don_xin_nghi d JOIN buoi_hoc b ON b.id=d.buoi_bu_id WHERE b.loai<>'bu' OR NOT(b.buoi_goc_id<=>d.buoi_hoc_id) HAVING COUNT(*)>0;
