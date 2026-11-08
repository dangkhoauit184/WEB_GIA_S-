# Nghiệp vụ EduCenter — quyết định hiện hành

Nguồn: xác nhận trực tiếp ngày 08/11/2026, gồm phần trả lời 1–10 sau khi merge PR #7. Các quyết định này thay thế phần khác trong Word v2/architecture cũ. Không cần hỏi lại tên khóa hoặc chọn GPS/ảnh.

| Phần | Quy tắc đã chốt |
| --- | --- |
| Khóa học | Sản phẩm CRUD độc lập: cơ bản học kỳ 1, cơ bản năm học, nâng cao học kỳ 2, gia sư 1 kèm 1, ôn luyện chuyên. Admin sửa tên/nội dung/giá, bật/tắt đăng ký. |
| Học phí | Thu trọn từng khóa, không kỳ/đợt. Tự thôi học không hoàn tiền. Snapshot giá khi đăng ký. |
| Thu tiền | Đăng ký → giáo vụ chốt học phí/phát hành hóa đơn → phụ huynh nhận thông báo → thanh toán trên web → đối soát tiền → Admin và giáo vụ thấy kết quả. Bấm “đã chuyển” không đồng nghĩa đã nhận tiền. |
| Xếp lớp | Chỉ Admin xếp lớp, độc lập với tình trạng tiền. Có thể xếp khi chưa đóng hoặc chưa có hóa đơn. Chuỗi trả tiền rồi xếp lớp là ví dụ, không phải điều kiện bắt buộc. |
| Xếp lịch | Giáo vụ/điều phối xếp, không tự loại ngày lễ. |
| Học bù | Phụ huynh và điều phối tự liên hệ chốt ngoài hệ thống. Giáo vụ chỉ lưu lịch/học viên, đánh loại “bù”, có thể ghi chú. Không cần đơn xin bù, quy trình thương lượng/duyệt bù, đếm hạn mức, số buổi còn lại hoặc phí bù. |
| Nghỉ | Nghỉ cả lớp do Admin duyệt. Nghỉ cá nhân theo đặc tả do giáo vụ duyệt. Đơn nghỉ cũ có thể liên kết lịch bù, không phải điều kiện xếp bù. |
| Check-in/out | Chỉ bấm nút, ghi giờ server đúng ca/người xác thực. Không GPS/ảnh; không truy cập camera/vị trí. Quản lý có tại nơi dạy; minh chứng để phát triển sau. |
| Giờ check-in/out | Không giới hạn đến sớm/về muộn, bỏ cửa sổ 30 phút. Lưu giờ thực và giới hạn phút công trong lịch phân công. |
| Công | Giao giữa thời gian có mặt và khung lịch; sớm/muộn không thêm công, đến sau giờ lịch là trễ, về sớm giảm phút. Thiếu check-out không tự tính đủ; dùng điều chỉnh công do Admin xử lý. |
| Lương | Tiền/giờ theo từng người, chỉ Admin xem/sửa. Mức mới từ lúc thay đổi, giữ ca đã dạy/đang dạy. Chỉ Admin duyệt điều chỉnh/chốt/trả/mở công. |
| Gia đình/chấm bài | Một học viên một phụ huynh, một phụ huynh nhiều con. Một bài chấm/điểm mỗi buổi cho từng học viên. |
| Quyền khác | Theo đặc tả và ma trận hiện có; ưu tiên các xác nhận mới về xếp lớp/công/lương ở trên. |

Lịch 18:00–20:00:

| Vào | Ra | Phút công | Trễ |
| --- | --- | --- | --- |
| 06:00 | 23:00 | 120 | Không |
| 17:50 | 19:30 | 90 | Không |
| 18:10 | 20:05 | 110 | Có |
| 18:10 | 19:30 | 80 | Có |

Không mặc định mọi ca luôn 2 giờ: lấy giờ được phân công. Tiền = đơn giá giờ × phút công / 60, làm tròn VND theo fn_tien_cong. Chưa thêm thưởng/phạt trễ.

DB004 đã có trọn khóa, lịch bù, snapshot lương và công theo lịch. DB005 bổ sung giao dịch/webhook chống trùng, thông báo học phí/nhận tiền, dấu vết Admin xếp lớp và chính sách check-in mới. Không xóa GPS nullable/đơn nghỉ lịch sử; luồng mới không yêu cầu chúng.

Bàn giao DB/server và thứ tự code: [db-server-readiness.md](db-server-readiness.md). Các API cũ còn cần cập nhật quyền xếp lớp/lọc lương khi code BE; DB không tự thay JSON hoặc xác thực người bấm nút.
