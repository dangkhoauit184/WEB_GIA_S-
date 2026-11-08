-- [SỬA] Chính sách đã chốt: ngoài Admin không được cấp quyền xem/sửa phân hệ công-lương.
-- Chỉ cập nhật đúng phân hệ liên quan, giữ các nhóm và quyền khác đang dùng.
UPDATE role_group_function p JOIN role_group g ON g.group_id=p.group_id
JOIN `function` f ON f.function_id=p.function_id
SET p.view=0,p.`add`=0,p.edit=0,p.`delete`=0,p.download=0
WHERE f.name_function='ChamCongLuong' AND g.group_name<>'Admin';
-- [SỬA] Đến sau giờ lịch là trễ, không còn ngưỡng miễn 15 phút.
UPDATE cau_hinh SET gia_tri='0',mo_ta='Check-in sau giờ bắt đầu trên lịch được đánh dấu trễ'
WHERE khoa='checkin_tre_sau_phut';
