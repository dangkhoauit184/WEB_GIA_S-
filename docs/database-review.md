# Đối chiếu database và nghiệp vụ EduCenter ERP

## Nguồn đối chiếu

Đã đọc toàn bộ `database/trung_tam_gia_su_35_tables.sql`, bản SQL cùng tên ở gốc, hai tài liệu Word và `docs/architecture.md`. Hai bản SQL gốc có cùng SHA-256. Hai tài liệu Word có cùng nội dung nghiệp vụ: 5 nhóm quyền, 8 phân hệ quyền và 9 use case. Không thay thế tài liệu gốc.

SQL gốc dùng MySQL, InnoDB, `utf8mb4`, khóa số nguyên UNSIGNED và `DECIMAL(12,0)` cho VND. Giữ các lựa chọn này. Yêu cầu MySQL 8.0.16 trở lên để CHECK được thực thi; ưu tiên nhánh 8.4 LTS. Không coi MariaDB hoặc SQL Server là tương đương.

## Ánh xạ feature với 35 bảng nghiệp vụ

| Feature | Bảng chính |
| --- | --- |
| auth, permissions | users, account, role_group, function, role_group_function |
| dashboard | Truy vấn tổng hợp theo quyền, không cần bảng dashboard |
| staff | nhan_vien, nhan_su_chuyen_mon, nhan_su_mon, nhan_su_khu_vuc, nhan_su_lich_ranh |
| academic-affairs | yeu_cau_hoc, yeu_cau_lich, de_xuat_nhan_su |
| scheduling | lich_hoc, buoi_hoc, phan_cong_buoi_hoc |
| classes | lop_hoc, lop_hoc_vien |
| students | hoc_vien |
| teachers, tutors | nhan_su_chuyen_mon và các bảng chuyên môn; không sao chép hồ sơ |
| parents | users và hoc_vien.phu_huynh_user_id; một phụ huynh có nhiều con |
| attendance | so_theo_doi_buoi_hoc, bai_tap, ket_qua_hoc_tap, buoi_hoc |
| leave-requests | don_xin_nghi |
| makeup-classes | buoi_hoc.loai/buoi_goc_id, don_xin_nghi.buoi_bu_id |
| tuition | hoa_don, hoa_don_chi_tiet, thanh_toan |
| timekeeping | phan_cong_buoi_hoc, dieu_chinh_cong |
| payroll | bang_cong_thang |
| notifications | thong_bao, audit_log |
| Danh mục dùng chung | mon_hoc, cap_lop, khu_vuc, co_so, cau_hinh |

Không tạo bảng Giáo viên/Gia sư/Phụ huynh riêng khi hồ sơ dùng chung đã đủ. Thêm duy nhất bảng kỹ thuật `schema_migrations`, tổng 36 bảng; không tạo bảng điểm danh thứ hai với tên `DIEM_DANH` như tài liệu Markdown viết tắt.

## Các thay đổi đã thực hiện

Mỗi vị trí thay đổi trong schema, migration, routine, trigger, seeder và cấu hình đều có chú thích `[SỬA]`, `[THÊM]` hoặc `[LƯU Ý]` bằng tiếng Việt.

| Vị trí | Thay đổi và lý do |
| --- | --- |
| Đầu schema | Bỏ DROP DATABASE, CREATE DATABASE/USE tên cố định; chọn database từ kết nối; tách seed/routine/trigger |
| lop_hoc.hoc_phi_thang | Thêm giá tháng nullable; hỗ trợ phí tháng cố định có trong tài liệu, không suy ra từ giá buổi |
| buoi_hoc.co_so_id | Thêm FK cơ sở và chỉ mục phòng/cơ sở/ngày để kiểm tra trùng phòng; giữ phòng dạng chuỗi theo thiết kế gốc |
| phan_cong_buoi_hoc.cach_tinh_luong | Snapshot cách tính lương theo buổi/giờ; giá cũ đã có snapshot don_gia. Không dùng cách tính hiện tại của lớp để tính lại quá khứ |
| fk_pc_bct | Đổi ON DELETE SET NULL thành RESTRICT mặc định để không gỡ khóa ca bằng việc xóa bảng công |
| fk_hdct_buoi | Đổi SET NULL thành RESTRICT để giữ buổi của chứng từ và cho phép CHECK khoản theo buổi |
| CHECK mới | Thêm kiểm tra học phí tháng, số buổi, ngân sách/đơn giá/thu nhập không âm, thang điểm dương, hiệu lực lịch, cặp GPS, công hợp lệ đủ giờ, giờ điều chỉnh, số dư hóa đơn và chi tiết thu |
| trg_pc_* | Kiểm tra xung đột phân công cả hai thao tác; chặn sửa/xóa ca đã khóa; bảng công phải cùng người/cùng kỳ; không tự gắn bảng công khi INSERT |
| trg_lhv_* | Kiểm tra sĩ số khi INSERT lẫn UPDATE/chuyển lớp; khóa dòng lớp trước khi đếm |
| trg_buoi_* | Kiểm tra đổi giờ gây trùng người/phòng; lịch tuần, buổi bù phải cùng lớp; gốc của ca bù phải là buổi thường hủy/dời |
| trg_sotd_*, trg_dxn_* | Kiểm tra học viên thuộc lớp tại ngày học; bài tập có thể của buổi trước cùng lớp; ca bù của đơn phải đúng buổi gốc |
| trg_tt_* | Khóa hóa đơn trước kiểm tra thu/hoàn/hủy phiếu; số dư trong [0, phai_thu]; bảo toàn chứng từ, không xóa phiếu |
| trg_hd_*, trg_hdct_* | Số dư hóa đơn khớp chứng từ; khoản thu đúng học viên và lớp |
| sp_sinh_buoi_hoc | Đúng tên cột schema; đúng quy ước thứ; kiểm tra ngày; giới hạn bởi ngày lớp; transaction; bỏ INSERT IGNORE che lỗi; lặp không phụ thuộc giới hạn recursive CTE |
| sp_chot_cong | Đúng người/kỳ; transaction và khóa; chặn thiếu checkout/công chờ duyệt/đơn điều chỉnh chờ duyệt/snapshot chưa xác minh; tính từ snapshot; audit |
| sp_mo_khoa_cong | Bổ sung mở khóa bảng da_chot bởi Admin với lý do và audit, giữ liên kết ca để tái tổng hợp |
| Seed tham chiếu | Giữ nguyên 5 nhóm, 8 phân hệ và ma trận quyền gốc; không bổ sung quyền khi chưa xác nhận |
| Seed demo | Tách tùy chọn, giữ dữ liệu mẫu; khóa các tài khoản dùng hash mật khẩu công khai |
| Migration | Chỉ ALTER/thêm bảng kỹ thuật, không drop bảng/cột, không đổi ID hay tự sửa dữ liệu vi phạm; lưu trạng thái applying/applied |

## Những điểm cần xác nhận trước phát triển nghiệp vụ

1. **Quyền:** Word ghi 5 nhóm; README cũ/giao diện có 6 cổng. Giữ 5 nhóm ở database, Gia sư/Giáo viên phân biệt bằng `phan_loai`. Ma trận quyền gốc thiếu quyền chốt công cho Nhân viên ở phân hệ 6 trong khi tài liệu Markdown giao giáo vụ chốt. Chưa tự thay ma trận. Routine có kiểm tra nhóm; API sau này phải kiểm tra thêm quyền thao tác và phạm vi cơ sở.
2. **Mã BR:** Word BR02 là một người dạy chính, BR03 là sĩ số; Markdown BR02/03 lại nói ghép người dạy/sinh lịch. Không dùng số BR đơn lẻ để suy ra nghiệp vụ. README cũ tuyên bố 20 quy tắc nhưng tài liệu không định nghĩa đủ 20; không bịa phần còn thiếu.
3. **GPS:** Word ghi GPS nếu có, Markdown yêu cầu bán kính bắt buộc. Chỉ kiểm tra cặp tọa độ và miền số; chưa thêm địa điểm GPS hoặc bán kính mặc định, chưa coi tọa độ client gửi là đã xác thực vị trí.
4. **Học phí nghỉ:** Chưa có quy tắc khi nào vắng được tính phí, cách bù học viên riêng trong lớp nhóm và mức phí tháng. Không tự hủy lớp nhóm hoặc sinh hóa đơn từ việc nghỉ. `hoc_phi_thang=NULL` phải được cấu hình trước khi phát hành hóa đơn tháng.
5. **Lương cũ:** Migration để `cach_tinh_luong=NULL` cho ca lịch sử, vì lớp có thể đã đổi cách tính. Phải đối chiếu hợp đồng để cập nhật từng ca trước khi chốt lại. Ca đã chốt không tự sửa. Bảng `da_tra` cần quy trình điều chỉnh được xác nhận riêng.
6. **Gói học:** Giữ kỳ tháng/năm và UNIQUE hóa đơn gốc. Chưa mở rộng nhiều hóa đơn cùng kỳ/khóa hoặc tự tính số buổi, ngày lễ, công bù/vắng, thưởng/phạt khi chưa rõ hợp đồng.
7. **Đơn từ:** Word chỉ xác định đơn nghỉ và điều chỉnh công; giao diện có thêm loại đơn đổi lịch/đổi người. Chưa thêm bảng hoặc trạng thái cho các loại chưa được tài liệu quy định.
8. **Phòng:** Giữ VARCHAR theo yêu cầu gốc. Cần thống nhất cách nhập tên phòng theo cơ sở; không tự suy luận phòng tại nhà từ tên địa chỉ. Buổi cũ đã chốt để cơ sở NULL và báo cáo riêng, không đoán cơ sở quá khứ.
9. **Quy trình chấm công:** Trigger không tự coi checkout là được duyệt công; backend sau này phải kiểm tra giờ check-in, tính cờ trễ theo cấu hình, phê duyệt công và gửi thông báo theo đúng quyền. Database đã có trường lưu, chưa triển khai API.
10. **An toàn kết nối:** Tài khoản DB không xác thực danh tính người dùng ứng dụng. Không cấp UPDATE trực tiếp `bang_cong_thang` hoặc quyền chạy mở khóa cho kết nối thông thường; chỉ cấp EXECUTE procedure chốt/mở khóa cho kết nối phù hợp và xác thực role ở API. DBA có quyền DDL luôn có thể vượt trigger. Không dùng tài khoản triển khai làm tài khoản API.

## Vận hành và giới hạn kiểm tra

Chạy thao tác ghi bằng READ COMMITTED và InnoDB, dùng transaction cho một thay đổi nghiệp vụ. Trigger khóa dòng người dạy/lớp/cơ sở/hóa đơn để giảm tranh chấp. Backend sau này phải giữ thứ tự khóa ổn định, retry toàn giao dịch khi deadlock, và khóa người dạy khi duyệt điều chỉnh công để không tranh chấp với chốt kỳ. Không coi kiểm tra một kết nối là kiểm chứng tải đồng thời.

Không tự đồng bộ tổng hóa đơn theo chi tiết khi tài liệu chưa chỉ rõ các khoản/giảm giá. Khi phát hành, service sau này phải đối chiếu tổng chi tiết và `tong_tien`, kiểm tra quyền phụ huynh chỉ xem con mình, trợ giảng không chấm điểm/đổi giáo án, và phạm vi chi nhánh. Các điều kiện nhiều bảng này không thể thay bằng CHECK đơn giản.

Nguồn kỹ thuật: [MySQL CHECK constraints](https://dev.mysql.com/doc/refman/8.0/en/create-table-check-constraints.html), [giới hạn stored programs](https://dev.mysql.com/doc/mysql-reslimits-excerpt/8.0/en/stored-program-restrictions.html), [trigger](https://dev.mysql.com/doc/refman/8.0/en/create-trigger.html).
