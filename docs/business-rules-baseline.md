# Nghiệp vụ EduCenter — quyết định hiện hành

Nguồn: các xác nhận trực tiếp của người dùng ngày 08/11/2026. Khi khác với Word v2 hoặc architecture.md cũ, dùng các quyết định mới ở đây. Chưa đánh lại mã BR vì tài liệu cũ dùng mã khác nhau.

## Đã chốt

| Phần | Quy tắc |
| --- | --- |
| Khóa học | Các sản phẩm đăng ký riêng như “Học kỳ 1 cơ bản”, “Giải đề nâng cao”. Học viên chọn khóa muốn học. |
| Học phí | Thu trọn từng khóa, không chia kỳ/đợt thu. Tự thôi học không hoàn học phí. |
| Học bù | BỎ hoàn toàn hạn mức 10 buổi, đếm số buổi được bù và tính/thu tiền bù. Phụ huynh và trung tâm tự liên hệ thỏa thuận; giáo vụ chỉ xếp lịch kèm/bù và lưu người học, thời gian, ghi chú. |
| Nghỉ cả lớp | Admin duyệt; giáo vụ không tự duyệt lớp nghỉ. |
| Nghỉ cá nhân | Giáo vụ duyệt. Admin có quyền quản trị; API phải xác thực đúng người thực hiện. |
| Xếp lịch | Giáo vụ xếp lịch, không tự loại ngày lễ. |
| Công/lương | Giáo vụ không chốt công hoặc trả lương. Chỉ Admin duyệt điều chỉnh công, chốt, trả lương và mở khóa. |
| Đơn giá | Tiền/giờ, gán theo từng người. Admin xem và thay đổi. Mức mới áp dụng từ lúc thay đổi; giữ nguyên công đã dạy. |
| Công thực trả | Chỉ tính phần thời gian có mặt nằm trong khung giờ lịch; đến sớm/về muộn không cộng thêm công. Check-in sau giờ lịch phải đánh dấu trễ. |
| Gia đình | Mỗi học viên một phụ huynh; một phụ huynh có thể có nhiều con. |
| Chấm bài | Một điểm bài chấm mỗi buổi cho từng học viên. |

Ví dụ lịch 18:00–20:00:

| Check-in | Check-out | Phút công | Trễ |
| --- | --- | --- | --- |
| 17:50 | 20:10 | 120 | Không |
| 17:50 | 19:30 | 90 | Không |
| 18:10 | 20:05 | 110 | Có |
| 18:10 | 19:30 | 80 | Có |

Tiền công theo đơn giá giờ × phút công / 60. SQL tiếp tục dùng làm tròn VND của fn_tien_cong hiện có; chưa bổ sung thưởng/phạt do trễ. Giữ giờ check-in/out gốc riêng với phút được tính lương.

## Đã cập nhật ở DB 004

- Thêm khoa_hoc, dang_ky_khoa_hoc và liên kết lớp/ghi danh. Snapshot học phí theo lúc đăng ký; hóa đơn khóa không cần giả tháng/năm hoặc ghi danh lớp.
- Thêm hoc_bu_hoc_vien, pham_vi_bu để buổi bù cá nhân không làm hủy buổi gốc của lớp. Không có hạn mức hay giá bù.
- Thêm yeu_cau_nghi_lop, kiểm tra Admin khi duyệt và cập nhật buổi nghỉ. Nghỉ cá nhân kiểm tra giáo vụ/Admin.
- Thêm phút công theo khung lịch, cờ trễ và snapshot lịch. Công 003 giữ chế độ lịch sử; không tính lại dữ liệu cũ khi nâng cấp.
- Thêm lịch sử đơn giá và procedure sp_dat_don_gia_gio. Thay đổi cập nhật mức giờ người dạy và các ca tương lai chưa check-in, giữ ca đã dạy/đang dạy. Nếu ca tương lai nằm trong kỳ đã khóa, thao tác bị từ chối và rollback để Admin xử lý khóa trước.
- Chỉ Admin được chốt/trả/mở công; cấm cấp quyền ChamCongLuong cho nhóm khác. Một user_id hợp lệ không thay thế xác thực web.
- DB đang dùng đã được sao lưu, khôi phục thử và nâng lên 004; các giá trị nghiệp vụ cũ được đối chiếu bảo toàn. Chi tiết [database-004.md](database-004.md).

## Còn cần đặc tả trước khi code tiếp

- GPS hay ảnh check-in: chưa chọn. Nếu chọn ảnh, cần chốt chụp trực tiếp hay tải ảnh, chỉ check-in hay cả check-out và ai xem được ảnh.
- Giờ mở check-in, quên check-out và quy trình Admin sửa công; thời lượng khác buổi chuẩn 2 giờ cần kịch bản nghiệm thu riêng.
- Nghỉ người dạy được thay người hay xin nghỉ cả lớp; lịch bù hủy/đổi và cách thông báo hai bên.
- Danh sách khóa, giá cụ thể, thời lượng và cách gán các lớp cũ vào khóa. Không tự tạo khóa/giá giả từ dữ liệu cũ.
- Phân biệt bài chấm mỗi buổi, bài giao về nhà và bài thi định kỳ trong phần học vụ.
- Thông tin lương chỉ Admin biết: API cũ vẫn trả một số trường lương; phải lọc đọc/ghi ở backend khi được yêu cầu viết tiếp. Thay DB không tự che được JSON của API cũ.

## Check-in bằng GPS hay ảnh

Chỉ lấy tọa độ GPS cần ít lưu trữ hơn ảnh, nhưng xác minh địa điểm cần bán kính, độ chính xác và xử lý lỗi. Ảnh minh chứng để Admin xem thủ công thuận tiện trình diễn; không mặc định có nhận diện khuôn mặt. input capture không bảo đảm chặn ảnh cũ trên mọi trình duyệt. Camera trực tiếp và GPS cần quyền thiết bị và secure context khi triển khai.

Tài liệu: [Geolocation](https://developer.mozilla.org/en-US/docs/Web/API/Geolocation/getCurrentPosition), [HTML capture](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Attributes/capture), [camera](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia).
