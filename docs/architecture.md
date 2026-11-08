# TÀI LIỆU KIẾN TRÚC & QUY TẮC NGHIỆP VỤ (V2.0)

> Hiện hành: [baseline](business-rules-baseline.md) và [DB/server005](db-server-readiness.md). Các mốc/schema/quy tắc cũ bên dưới là lịch sử; ưu tiên xác nhận mới.

> Cập nhật 08/11/2026: [nghiệp vụ mới](business-rules-baseline.md) và [DB 004](database-004.md) được ưu tiên nếu khác nội dung lịch sử dưới đây. Chỉ Admin quản trị công-lương, thu trọn khóa, học bù theo thỏa thuận không hạn mức/phí.

Hệ thống Quản trị Trung tâm Gia sư & Đào tạo EduCenter ERP tuân thủ nghiêm ngặt 20 Business Rules (`BR01` - `BR20`) được đặc tả chi tiết dưới đây:

---

## I. CÁC QUY TẮC NGHIỆP VỤ CỐT LÕI (BR01 - BR20)

### 1. Phân hệ Tiếp nhận, Ghép lớp & Xếp lịch
- **BR01 (Chống trùng lịch):** Một giáo viên hoặc một phòng học không được có 2 ca học có khung giờ giao nhau trong cùng một ngày. Hệ thống sẽ cảnh báo đỏ và ngăn chặn lưu lịch nếu phát hiện xung đột.
- **BR02 (Ghép người dạy):** Lọc ứng viên dựa trên chuyên môn môn học, cấp lớp, địa bàn cơ sở, khung giờ rảnh và ngân sách phụ huynh đề xuất.
- **BR03 (Sinh chuỗi buổi học `sp_sinh_buoi_hoc`):** Từ lịch cố định hàng tuần và số buổi dự kiến, hệ thống tự động sinh chuỗi các buổi học tương ứng trong tương lai.

### 2. Phân hệ Vận hành ca dạy & Chấm công
- **BR04 (Check-in GPS):** Người dạy phải check-in qua thiết bị di động trong bán kính cho phép của cơ sở hoặc nhà học viên.
- **BR05 (Cảnh báo đi trễ):** Nếu check-in trễ quá 15 phút so với giờ bắt đầu ca học, hệ thống tự động gắn cờ cảnh báo vào nhật ký kiểm toán.
- **BR06 (Bắt buộc Check-out):** Ca dạy chỉ được tính là hoàn thành hợp lệ khi có đủ thời gian Check-in và Check-out thực tế.

### 3. Phân hệ Đơn từ & Dạy bù
- **BR08 (Đơn xin nghỉ học của học viên):** 
  - Với lớp nhóm: Việc một học viên xin nghỉ phép không làm hủy buổi học chung của các bạn còn lại trong lớp.
  - Với lớp kèm 1-1: Giáo vụ sẽ xử lý dời lịch hoặc xếp buổi học bù tương ứng.
- **BR09 (Xếp lịch dạy bù):** Ca học bù phải được liên kết với ca học gốc bị hủy hoặc hoãn để đảm bảo đủ số buổi theo hợp đồng khóa học.
- **BR10 (Chấm điểm & Sổ học vụ):** Giáo viên ghi nhận điểm danh theo 4 trạng thái (Có mặt, Đi trễ, Nghỉ có phép, Nghỉ không phép), chấm điểm bài tập giấy và giao bài tập về nhà đính kèm PDF.

### 4. Phân hệ Khóa sổ công lương & Học phí
- **BR11 (Kiểm tra điều kiện chốt công):** Giáo vụ không thể chốt bảng công tháng (`sp_chot_cong`) nếu còn bất kỳ ca dạy nào đã check-in nhưng chưa check-out hoặc còn đơn điều chỉnh giờ công chưa xử lý.
- **BR12 (Khóa sổ bảng công):** Sau khi giáo vụ bấm "Chốt công", bảng công tháng sẽ chuyển sang trạng thái Khóa sổ (Locked). Giáo vụ không thể tự ý sửa đổi.
- **BR04/BR12 (Đặc quyền Admin mở khóa):** Chỉ có Quản trị viên tối cao (Admin) mới có quyền mở khóa bảng công tháng đã chốt khi có khiếu nại từ nhân sự.
- **BR14 (Phương thức tính phí):** Hỗ trợ tính học phí theo buổi thực học hoặc trọn gói cả khóa.
- **BR15 (Hóa đơn học phí):** Xuất hóa đơn kèm theo dõi 3 trạng thái: Chưa đóng, Đóng một phần, Đã thanh toán đủ.

---

## II. THIẾT KẾ CSDL & PROCEDURES LIÊN QUAN

- `LICH_HOC`: Lưu lịch tuần cố định
- `BUOI_HOC` / `PHAN_CONG_BUOI_HOC`: Lưu từng ca dạy cụ thể
- `DIEM_DANH`: Điểm danh và điểm bài tập giấy của học viên
- `DON_XIN_NGHI` / `DIEU_CHINH_CONG`: Lưu vết đơn từ của GV và PH
- `BANG_CONG_THANG`: Lưu quyết toán thù lao và trạng thái khóa sổ

