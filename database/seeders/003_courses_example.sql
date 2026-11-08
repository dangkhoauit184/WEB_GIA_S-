-- [THÊM] 5 tên khóa mẫu theo yêu cầu. Giá minh họa; khóa TẮT đăng ký để Admin sửa/bật qua CRUD sau.
-- Chạy riêng theo Action seed-courses; không tạo đăng ký, hóa đơn, thanh toán hay ghi đè khóa đã có.
INSERT INTO khoa_hoc(ma,ten,mo_ta,hoc_phi,is_active)
SELECT 'MAU-CB-HK1','Khóa cơ bản học kỳ 1','Mẫu tham khảo, Admin sửa giá/nội dung rồi bật đăng ký.',1200000,0
WHERE NOT EXISTS(SELECT 1 FROM khoa_hoc WHERE ma='MAU-CB-HK1');
INSERT INTO khoa_hoc(ma,ten,mo_ta,hoc_phi,is_active)
SELECT 'MAU-CB-NAM','Khóa cơ bản năm học','Mẫu tham khảo, giá trọn khóa.',2400000,0
WHERE NOT EXISTS(SELECT 1 FROM khoa_hoc WHERE ma='MAU-CB-NAM');
INSERT INTO khoa_hoc(ma,ten,mo_ta,hoc_phi,is_active)
SELECT 'MAU-NC-HK2','Khóa nâng cao học kỳ 2','Mẫu tham khảo, giá trọn khóa.',1800000,0
WHERE NOT EXISTS(SELECT 1 FROM khoa_hoc WHERE ma='MAU-NC-HK2');
INSERT INTO khoa_hoc(ma,ten,mo_ta,hoc_phi,is_active)
SELECT 'MAU-GS-11','Khóa gia sư 1 kèm 1','Mẫu tham khảo, giá trọn khóa.',3000000,0
WHERE NOT EXISTS(SELECT 1 FROM khoa_hoc WHERE ma='MAU-GS-11');
INSERT INTO khoa_hoc(ma,ten,mo_ta,hoc_phi,is_active)
SELECT 'MAU-ON-CHUYEN','Khóa ôn luyện chuyên','Mẫu tham khảo, giá trọn khóa.',2000000,0
WHERE NOT EXISTS(SELECT 1 FROM khoa_hoc WHERE ma='MAU-ON-CHUYEN');
