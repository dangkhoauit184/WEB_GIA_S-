-- [THÊM] Chính sách đã chốt: chỉ bấm nút, không giới hạn sớm/muộn. -1 nghĩa không giới hạn.
INSERT INTO cau_hinh(khoa,gia_tri,mo_ta) VALUES
 ('checkin_truoc_phut','-1','Không giới hạn check-in sớm; phút công vẫn giới hạn bởi lịch phân công'),
 ('checkout_sau_phut','-1','Không giới hạn check-out muộn; không cộng giờ ngoài lịch'),
 ('checkin_minh_chung','khong','Chỉ bấm check-in/out, không GPS hoặc ảnh')
ON DUPLICATE KEY UPDATE gia_tri=VALUES(gia_tri),mo_ta=VALUES(mo_ta);
