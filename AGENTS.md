# Quy ước làm việc với EduCenter ERP

- Backend **PHP 8.4 / CodeIgniter 4**, MVC/Ajax, MySQL 8.4; không thêm Node backend song song. Lựa chọn bám đề cương IS207: PHP/MySQL/MVC/CodeIgniter, yêu cầu đồ án A1.
- Source BE tại backend/app/Modules/Module/Features/Feature, FE tại frontend/src/modules/module/features/feature; manifest shared/modules.json có 6 module/21 feature. Feature chưa làm giữ TODO/status planned, không báo hoàn tất.
- `docs` chỉ có **2 file chính thức** theo yêu cầu09/11/2026: [hướng dẫn cài/chạy cho đồng đội](docs/huong-dan-cai-dat-va-su-dung.md) và [thông tin/đặc tả đồ án](docs/thong-tin-do-an.md). Mọi cập nhật tài liệu thêm vào đúng file, không tạo Markdown thứ ba trong docs.
- Giữ4 README (gốc/backend/frontend/database) làm chỉ dẫn ngắn và 1 mẫu PR. Word/demo/SQL gốc nằm tại reference/, bảo toàn nội dung/hash theo reference/preserved-files.json; bản cũ chỉ tham khảo khi khác đặc tả hiện hành.
- Quyết định trực tiếp mới của người dùng ưu tiên hơn Word/Figma/tài liệu lịch sử. Không tự biến đề xuất/câu hỏi thành quy tắc hoặc áp migration chỉ vì có bản rà soát.
- Người dùng ưu tiên nghiệp vụ/DB/server trước BE/FE; câu hỏi “nên code chưa” không phải lệnh viết tính năng. Chỉ triển khai trong phạm vi đã giao rõ.
- DB hiện hành005_operations_ready. Giữ container educenter-mysql, volume educenter_mysql_data, dữ liệu và env/secret riêng. Local Windows gọi127.0.0.1:3307; PHP container gọiDB:3306. Không ghi mật khẩu/token/backup vào repo.
- Integration/SQL suite chỉ chạy DB riêng tên `_test`, PHP integration thêm TEST_WITH_DB=1. MySQL DDL tự COMMIT: backup/restore thử→preflight→migrate→check, không xóa marker applying hoặc dùng init đè DB cũ.
- Nghiệp vụ: thu trọn khóa; không kỳ thu, hạn mức/phí bù; giáo vụ nhập bù theo thỏa thuận ngoài hệ thống, không bắt buộc đơn. Chỉ Admin xếp lớp, độc lập tiền; nghỉ cả lớp do Admin, nghỉ cá nhân giáo vụ.
- Check-in/out chỉ bấm nút/giờ server, không GPS/ảnh và không giới hạn sớm/muộn. Phút công là giao thời gian có mặt với lịch; lịch18–20, vào18:10/ra20:05=110 phút/trễ, ra19:30=80 phút/trễ. Không cộng giờ ngoài lịch.
- Lương tiền/giờ theo người, chỉ Admin xem/sửa/duyệt/chốt/trả/mở. Tăng giá từ lúc đổi, giữ ca đã bắt đầu. Kỳ đã trả chưa có quy trình mở/sửa tự động. Một học viên một phụ huynh, một phụ huynh nhiều con; một bài chấm mỗi buổi/học viên.
- DB 005 có intent/event chống trùng, Admin đối soát phiếu thu và thông báo học phí, chưa có tích hợp gateway/worker/màn FE thật. API cũ còn cần che lương và chỉ Admin xếp lớp; đọc mục Tiến độ trong đặc tả trước khi giao việc. Không báo đã deploy public/thanh toán thật khi chỉ có DB/config.
