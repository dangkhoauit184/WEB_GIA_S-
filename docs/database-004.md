# Database 004 — nghiệp vụ đã chốt

> Hiện hành: [baseline](business-rules-baseline.md) và [DB/server005](db-server-readiness.md). Các mốc/schema/quy tắc cũ bên dưới là lịch sử; ưu tiên xác nhận mới.

Phiên bản 004_business_rules ngày 08/11/2026: 41 bảng, 4 function, 13 procedure, 47 trigger. Giữ 35 bảng nghiệp vụ gốc và bảng lịch sử migration; bổ sung 5 bảng để hỗ trợ nghiệp vụ mới.

## Thay đổi

1. khoa_hoc: sản phẩm khóa đăng ký độc lập, tên/mã/học phí; không có khái niệm kỳ thu hay hạn mức/phí học bù.
2. dang_ky_khoa_hoc: học viên đăng ký một khóa và snapshot giá. lop_hoc.khoa_hoc_id và lop_hoc_vien.dang_ky_khoa_hoc_id kiểm tra khớp khóa/học viên.
3. Hóa đơn trọn khóa gắn đăng ký khóa, không yêu cầu tháng/năm hoặc lớp giả; một hóa đơn học phí cho một đăng ký. Nguồn hóa đơn khóa và lớp cũ loại trừ nhau. Giá trọn khóa khớp snapshot, không tự cộng phí bù. Ghi nhận thanh toán vẫn có thể phản ánh nhiều giao dịch, không tự biến thành gói trả góp hay kỳ đăng ký.
4. Tự thôi học ở đăng ký khóa không cho tạo/kích hoạt chứng từ hoàn. Chứng từ cũ không bị xóa, cơ chế sửa sai thu trùng chưa được mở rộng thành chính sách mới.
5. hoc_bu_hoc_vien: lưu học viên được giáo vụ xếp vào buổi bù cá nhân; không đếm hạn mức và không sinh học phí. Buổi gốc lớp nhóm vẫn có thể diễn ra. Học vụ chỉ ghi cho người được xếp bù.
6. yeu_cau_nghi_lop: Admin duyệt nghỉ nguyên buổi; nghỉ cá nhân qua giáo vụ/Admin. Duyệt nghỉ lớp cập nhật dấu vết hủy buổi, vẫn tôn trọng kỳ công đã khóa.
7. phan_cong_buoi_hoc giữ so_phut_thuc_te (thời gian hiện diện), thêm so_phut_tinh_cong (phần giao với giờ lịch), snapshot lịch và quy_tac_cong. Ca mới theo 004, đến trễ tự gắn cờ; không cộng thời gian đến sớm/về muộn. Ca 003 giữ lịch sử để không đổi số liệu cũ; khi sửa giờ sẽ chuyển sang quy tắc mới theo thao tác có kiểm soát.
8. sp_sinh_buoi_hoc và phân công mới lấy đơn giá giờ từng người thay vì giá từ lớp. sp_dat_don_gia_gio(nhan_su_id,don_gia_gio,admin_user_id) có transaction, lịch sử và audit, áp dụng từ hiện tại cho ca tương lai chưa bắt đầu. Không sửa ca đã dạy/đã check-in. UPDATE snapshot giá ngoài procedure cũng phải có luong_sua_boi là Admin đang hoạt động.
9. sp_chot_cong chỉ nhận Admin; bảng công dùng phút trả lương. Trả/mở khóa/duyệt điều chỉnh công kiểm tra Admin và dấu vết. Ma trận công-lương ngoài Admin được thu hồi đúng phân hệ, không đổi quyền khác.

## Nâng cấp an toàn

Không import schema.sql vào DB đang có dữ liệu. Dừng ghi ứng dụng, sao lưu cả dữ liệu/routine/trigger và thử khôi phục trước. MySQL DDL tự COMMIT; gặp applying phải dừng kiểm tra, không xóa cờ để chạy lại.

Với Docker đang dùng (mật khẩu root chỉ được đọc bên trong container):

```powershell
pwsh -File database/scripts/run.ps1 -Action preflight -Container educenter-mysql -EnvFile config/server.env
pwsh -File database/scripts/run.ps1 -Action migrate -Container educenter-mysql -EnvFile config/server.env
pwsh -File database/scripts/run.ps1 -Action check -Container educenter-mysql -EnvFile config/server.env
```

Đường MySQL CLI/tài khoản migrator cũ vẫn dùng được với database/.env. Máy mới dùng Action init trên database rỗng. Script init/migrate nạp modules theo install-order.json và policy 004, không nạp lại toàn bộ quyền seed lên database cũ.

Các lớp 003 chưa có thông tin khóa giữ khoa_hoc_id=NULL; check báo CAN_XAC_NHAN. Hóa đơn/công cũ giữ nguyên nguồn và giá trị. Người dùng cần cung cấp khóa/giá và đối chiếu lớp trước khi gán; migration không suy đoán dữ liệu đó.

## Kết quả kiểm chứng ngày 08/11/2026

- 51 tình huống SQL cũ vẫn đạt; cập nhật actor và kỳ vọng phù hợp chính sách Admin/tiền giờ.
- 27 tình huống mới đạt: 120/110/90/80 phút và cờ trễ, lương theo người, tăng giá không đổi ca đã dạy, đăng ký/giá khóa/hóa đơn, không hoàn khi thôi học, bù hơn 10 lần không giới hạn/không phí, quyền duyệt nghỉ, một bài chấm và cấm giáo vụ chốt công.
- 71 PHP core + 81 API cũ + 98 API quản trị đạt với schema 004; không sửa mã ứng dụng backend/frontend.
- Script cài đặt được thử cài mới, từ chối init DB không rỗng, nâng 003→004, chạy migrate lần hai không đổi dữ liệu, so cấu trúc cài mới/nâng cấp trùng nhau.
- Nâng cấp từ 003 kiểm tra hash các cột cũ của mọi bảng nghiệp vụ; chỉ quyền công-lương và ngưỡng trễ được cập nhật theo quyết định mới. Các mốc applied_at cũ được giữ.
- DB thực tế đã backup, restore vào DB thử, đối chiếu dữ liệu, nâng bản sao rồi mới nâng DB đang dùng và kiểm tra lại; không có VI_PHAM. Bản backup chứa dữ liệu riêng, nằm ngoài Git trong .cache/db004/backups/.

Chạy lại toàn bộ kiểm thử trên MySQL dùng một lần:

```powershell
pwsh -File backend/scripts/test.ps1
```

verify_upgrade.py lấy baseline 003 từ commit 64e4f18328658c86a92875aa8ffeb07c46206f50 trong lịch sử Git; clone nông cần fetch lịch sử trước. Công cụ chỉ chấp nhận container test có tên educenter-test-* hoặc educenter-db004-*.

## Giới hạn ứng dụng còn lại

DB không xác thực được người dùng web thông qua user_id do caller truyền. API phải xác thực actor, giới hạn dữ liệu và chuyển sang luồng khóa học mới. API cũ còn các trường mức lương trong JSON; cần sửa khi bắt đầu lại backend. 004 chưa làm GPS/ảnh, giao diện, API khóa học/đăng ký hay gửi thông báo. Không áp dụng hạn mức/phí bù đã bị người dùng bỏ.
