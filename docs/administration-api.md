# API danh mục và phân quyền

Các route cần `Authorization: Bearer <token>`. Response dùng `{success:true,data:...}`; lỗi có `code` và `message`. Backend dùng PHP/CodeIgniter, không cần migration SQL mới.

## Danh mục

Prefix: `/api/administration/catalog`.

| Route | Ý nghĩa | Quyền |
| --- | --- | --- |
| GET prefix | Đọc bốn danh mục, chỉ mục active cho form chọn | Quyền xem lớp/vận hành |
| GET /subjects, /grade-levels, /regions, /campuses | Danh sách; `q` tìm kiếm, `isActive=0` hoặc `1` để lọc (trừ cấp lớp) | Quyền xem lớp/vận hành |
| GET /resource/:id | Chi tiết, gồm cả mục đã ngừng dùng | Quyền xem lớp/vận hành |
| POST /subjects, /regions, /campuses | Tạo mục mới, HTTP 201 | Admin |
| PUT /resource/:id | Cập nhật một phần trường | Admin |
| DELETE /subjects/:id, /regions/:id, /campuses/:id | Ngừng sử dụng: isActive=false, giữ liên kết/dữ liệu | Admin |
| GET /settings | Danh sách cấu hình nghiệp vụ | Admin |
| PUT /settings/:key | Sửa giá trị cấu hình được hỗ trợ | Admin |

`resource` chỉ nhận subjects, grade-levels, regions, campuses. Môn dùng `ma`, `ten`, `isActive`; khu vực dùng `ten`, `thanhPho`, `isActive`; cơ sở dùng `maCoSo`, `tenCoSo`, `diaChi`, `soDienThoai`, `email`, `khuVucId`, `quanLyId`, `isActive`. `id` và `createdAt` không sửa qua request. Boolean JSON nhận true/false hoặc số 0/1, không nhận chuỗi "false".

Ví dụ tạo môn:

```json
{"ma":"TIN","ten":"Tin học","isActive":true}
```

Tạo cơ sở bắt buộc mã/tên/địa chỉ. Khu vực nếu gán phải đang active. Quản lý nếu gán phải có hồ sơ nhân viên đang làm hoặc tài khoản Admin active. Các trường tùy chọn như email, khuVucId, quanLyId có thể truyền null để xóa liên kết; không truyền thì giữ nguyên khi PUT.

Cấp lớp giữ ID/cấp học chuẩn 1–12 vì thuật toán chuyên môn dùng khoảng cấp lớp. Chỉ PUT `{"ten":"Lớp Chín"}` để đổi tên hiển thị. POST/DELETE cấp lớp trả 405. Danh mục ngừng dùng không xuất hiện trong bootstrap, nhưng không tự hủy lớp/hợp đồng đang tham chiếu.

Settings hỗ trợ:

- checkin_truoc_phut, checkin_tre_sau_phut: số nguyên 0–1440 phút.
- han_nhap_bao_cao_gio: số nguyên 1–8760 giờ.

Request ví dụ `{"giaTri":30}`. Không dùng API này để ghi JWT_SECRET, mật khẩu DB hoặc biến môi trường. Khóa cấu hình ngoài danh sách trả 404. Mọi thay đổi danh mục/cấu hình ghi audit trong cùng transaction.

## Phân quyền

Prefix: `/api/administration/permissions`. Toàn bộ route chỉ dành cho Admin.

| Route | Ý nghĩa |
| --- | --- |
| GET /groups | 5 nhóm quyền chuẩn và số tài khoản |
| GET /functions | Chức năng active trong DB |
| GET /groups/:id/permissions | Ma trận đầy đủ, quyền thiếu được xem là false, kèm version |
| PUT /groups/:id/permissions | Thay ma trận, yêu cầu version hiện tại và đủ từng chức năng |
| PUT /groups/:id | Sửa description; không đổi tên/ID nhóm |
| GET /accounts | Tìm tài khoản theo q/groupId/status; phân trang page, perPage (1–100, mặc định 25) |
| PUT /accounts/:id/group | Gán nhóm: `{"groupId":2}` |

GET ma trận trả `data.group`, `data.permissions`, `data.version`. Mỗi dòng gồm functionId, functionName và 5 cờ view/add/edit/delete/download. Khi PUT, bỏ functionName và gửi đủ các dòng đang active:

```json
{
  "version":"<64 ký tự hex từ GET mới nhất>",
  "permissions":[
    {"functionId":1,"view":false,"add":false,"edit":false,"delete":false,"download":false}
  ]
}
```

Ví dụ trên chỉ minh họa một dòng; request thực phải chứa đủ chức năng từ GET. Thiếu/trùng chức năng hoặc bật add/edit/delete/download khi view=false trả 400. Nếu version cũ, trả 409 `PERMISSIONS_VERSION_CONFLICT`; frontend phải tải lại rồi để người dùng xem và lưu lại. Nhóm Admin luôn toàn quyền, sửa ma trận Admin trả 400 `ADMIN_PERMISSIONS_FIXED`.

API giữ 5 nhóm Admin, NhanVien, GiangVien, TroGiang, PhuHuynh; chưa có chức năng tạo nhóm tùy ý. GiangVien tách vai trò Giáo viên/Gia sư theo phân loại chuyên môn. Muốn gán NhanVien phải có hồ sơ nhân viên đang làm; GiangVien/TroGiang phải có hồ sơ chuyên môn phù hợp đang hoạt động. Staff chưa có API tạo hồ sơ nhân viên đầy đủ, sẽ phát triển ở feature Staff.

Không tự đổi nhóm của chính mình. Các thay đổi nhóm Admin được tuần tự hóa và kiểm tra lại quyền người thao tác trong transaction, tránh hai admin đồng thời hạ quyền nhau làm mất toàn bộ quản trị. Danh sách tài khoản không trả password_hash. API này chưa tạo/xóa tài khoản hoặc đổi mật khẩu người khác.

Quyền/nhóm mới có hiệu lực ở request kế tiếp, kể cả khi JWT còn chứa vai trò cũ. Việc sửa ma trận/gán nhóm đều có audit trước/sau và actor. Chưa có màn hình/API tra cứu audit tổng quát.

## Kiểm thử

```powershell
pwsh -File backend/scripts/test.ps1
```

Script tạo MySQL riêng và chạy core, luồng cũ, suite administration và SQL regression. Không chạy integration trên database đang dùng. Muốn chạy riêng suite mới trên DB thử đã init, cấu hình TEST_WITH_DB=1 và DB_NAME kết thúc `_test`, sau đó `composer test:admin`.
