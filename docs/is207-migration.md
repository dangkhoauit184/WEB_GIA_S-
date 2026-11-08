# Đối chiếu và chuyển đổi theo IS207

Căn cứ file đề cương IS207 (1).pdf người dùng cung cấp, trang 3 và 6–8 đề cập PHP/MySQL, MVC, CodeIgniter, Ajax; phần bài tập 3 trang 17–19 yêu cầu PHP framework/MVC. Đồ án A1 trang 21 còn yêu cầu chức năng và tài liệu hoàn chỉnh. Chọn CodeIgniter 4 để bám sát framework được học; không đổi đề tài EduCenter thành đề bán hàng của phần bài tập khác.

## Đã chuyển trong đợt này

| Trước | Sau |
| --- | --- |
| Node.js/Express/MySQL2 | PHP 8.4/CodeIgniter 4/MySQLi |
| server.js và npm | public/index.php, spark và Composer lock |
| src/modules/.../*.js | app/Modules/.../Features/.../*.php theo MVC |
| Middleware JWT/RBAC | Access, feature Filter, trạng thái tài khoản/quyền từ DB |
| API auth, people, classes, scheduling | Các API PHP tương ứng, giữ URL và tên trường camelCase |
| Logout chỉ ghi audit | Hash token bị thu hồi trong audit_log; đổi mật khẩu hủy phiên cũ |
| Danh sách/detail chưa đồng đều về phạm vi | Scope phụ huynh/người dạy trên hồ sơ, lớp, ghi danh và lịch |
| Test Node | PHP core, HTTP/MySQL test; CI vẫn chạy SQL regression |
| Cấu hình Node local | Apache/PHP Docker, script khởi động và bootstrap |

Giữ nguyên SQL/schema/dữ liệu, thiết kế Figma, Word và demo tham khảo. Không cần dựng lại database do chuyển PHP. Mật khẩu ứng dụng bcrypt cũ dùng lại được; JWT Node cũ cần đăng nhập lại. `config/server.env` vẫn được nạp, môi trường Docker ưu tiên ghi đè host/port DB. Không tự reset admin hoặc cấp thêm quyền giáo vụ.

Giao diện PHP hiện có: đăng nhập, đổi mật khẩu, xem/tìm học viên/lớp và lịch qua Ajax. Đây là màn hình khởi đầu MVC, chưa thay toàn bộ thiết kế Figma. Frontend module/feature giữ nguyên khung để triển khai tiếp.

## Những phần còn phải làm để nộp đồ án hoàn chỉnh

| Tiêu chí/phần việc | Tình trạng và bước tiếp theo |
| --- | --- |
| PHP framework, MVC, MySQL, Ajax | Đã có nền tảng chạy được và các luồng API nêu trên |
| Khảo sát và phân tích | Có Word nghiệp vụ; cần hoàn thiện báo cáo, use case và mô tả theo mẫu giảng viên |
| Tài khoản/nhóm quyền/danh mục/audit | Có auth, API Catalog và Permissions (ma trận, gán nhóm tài khoản), ghi audit khi sửa; Staff và API tra cứu Audit còn khung. Màn hình quản trị chưa làm |
| Tuyển sinh, học vụ, điểm danh, xin nghỉ, học bù | Admissions/AcademicAffairs/Attendance/LeaveRequests/MakeupClasses chưa có API PHP nghiệp vụ |
| Học phí, công, lương | Tuition/Timekeeping/Payroll còn khung; routines DB đã có nhưng cần service/API, quyền, giao diện và test |
| Thêm/sửa/xóa/tìm kiếm | Có cho một số hồ sơ/lớp; cần hoàn thiện đúng nghiệp vụ từng feature, không xóa cứng dữ liệu tài chính |
| Thống kê, hiển thị | Dashboard và báo cáo tổng hợp chưa triển khai; trang PHP hiện chỉ là tra cứu cơ bản |
| In ấn, xuất dữ liệu | Chưa có in hóa đơn/báo cáo hoặc xuất file thực tế |
| Gửi mail/thông báo | Notifications còn khung; cần SMTP, template và kiểm thử gửi/ghi nhận trạng thái |
| Giao diện | Cần triển khai các màn Figma, responsive và liên kết API theo vai trò |
| Deploy và nghiệm thu | Có image Apache/PHP và hướng dẫn; cần server/domain/HTTPS thực tế, dữ liệu demo chuẩn và kiểm thử đầu-cuối |
| Báo cáo/nộp bài | Cần ảnh minh chứng, hướng dẫn tài khoản demo, phân công nhóm, kịch bản trình diễn và tài liệu theo mẫu môn học |

Thứ tự phát triển đề xuất: Staff và màn hình quản trị cho Catalog/Permissions đã có API → hồ sơ/lớp/lịch đang có → điểm danh/học vụ → xin nghỉ/học bù → công/lương/học phí → báo cáo/in/mail → deploy và hoàn thiện báo cáo. Hợp đồng API quản trị ở [administration-api.md](administration-api.md).

Các route `/status` chỉ xác nhận feature được đăng ký. Giá trị `planned` không đồng nghĩa đã hoàn thành feature. Health 200 chỉ xác nhận PHP và MySQL đang kết nối.
