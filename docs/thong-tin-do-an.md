# Thông tin và đặc tả tổng hợp đồ án EduCenter ERP

Cập nhật 09/11/2026, căn cứ code trên main sau PR #8, schema `005_operations_ready`, đặc tả gốc và các xác nhận trực tiếp của chủ đồ án đến ngày 09/11/2026. Đây là nguồn đặc tả hiện hành duy nhất. Cách tải/cài/chạy/chia sẻ môi trường ở [Hướng dẫn cài đặt và sử dụng](huong-dan-cai-dat-va-su-dung.md).

Tài liệu mô tả cả thiết kế mục tiêu và phần đã triển khai; các nhãn **đã có**, **còn phải làm**, **lịch sử** dùng để tránh nhầm DB/prototype với một website hoàn chỉnh. Không áp dụng lại quy tắc cũ từ Word, demo hoặc Figma khi khác quyết định hiện hành.

## Mục lục

1. [Đề tài, công nghệ và tiêu chí IS207](#tong-quan)
2. [Tác nhân và quyền](#vai-tro)
3. [Các quy tắc đã chốt](#quy-tac)
4. [Quy trình chi tiết và ngoại lệ](#luong-nghiep-vu)
5. [Cấu trúc module và MVC](#kien-truc)
6. [Mô hình dữ liệu và quan hệ](#database)
7. [Luồng cài/nâng cấp và luồng xử lý SQL](#sql-lifecycle)
8. [API đang có và trách nhiệm BE](#api)
9. [Figma và giao diện còn thiếu](#figma)
10. [Tiến độ, kiểm chứng và thứ tự làm tiếp](#tien-do)
11. [Lịch sử và tài liệu nguồn](#lich-su)
12. [Chi tiết API quản trị](#api-quan-tri)
13. [Từ điển dữ liệu toàn bộ 43 bảng](#tu-dien-du-lieu)
14. [Danh mục 20 routine và 57 trigger](#routine-trigger)

<a id="tong-quan"></a>
## 1. Đề tài và công nghệ

EduCenter ERP quản lý trung tâm gia sư/đào tạo từ tiếp nhận nhu cầu, đăng ký khóa, ghép người dạy, xếp lớp/lịch, vận hành buổi học đến học phí, công/lương, thông báo và báo cáo. Có lớp kèm1:1, kèm1:2, lớp nhóm; hình thức online/offline theo dữ liệu lớp.

| Thành phần | Lựa chọn hiện hành |
| --- | --- |
| Backend | PHP 8.4, CodeIgniter 4.7, MVC, Composer lock, MySQLi |
| Frontend | HTML/CSS/JavaScript, Ajax; chia module→feature; không có Node backend song song |
| DB | MySQL 8.4, InnoDB, utf8mb4_unicode_ci, giờ+07:00, READ COMMITTED |
| Phiên | JWT HS256, bcrypt, kiểm tra lại tài khoản/quyền từ DB ở mỗi request |
| Local | Docker Desktop/Compose, Apache document root backend/public |
| Mail thử | Mailpit, cấu hình Email CodeIgniter qua MAIL_* |
| Release | Docker image application, Caddy HTTPS, writable volume, cấu hình riêng |

Đề cương IS207 do chủ đồ án cung cấp: trang 3 và 6–8 đề cập PHP/MySQL, MVC, CodeIgniter, Ajax; trang 17–19 yêu cầu framework PHP/MVC ở bài tập3. Đồ án A1 trang 21 cần khảo sát/phân quyền/thiết kế/nhập dữ liệu, giao diện, tìm kiếm, thêm/xóa/sửa, in/thống kê/hiển thị/gửi mail, triển khai server và báo cáo. Đề tài giữ EduCenter; ví dụ bán hàng/giỏ hàng/SB-Admin của bài tập khác không tự trở thành yêu cầu đồ án này.

Backend đã chuyển Node/Express→PHP, giữ dữ liệu và URL/tên trường phù hợp để tránh viết hai hệ thống song song. Việc chuyển PHP không yêu cầu xóa DB hoặc hash lại mật khẩu cũ.

<a id="vai-tro"></a>
## 2. Tác nhân và quyền

Có **5 nhóm DB**, suy ra **6 vai trò giao diện**. Giáo viên và Gia sư cùng nhóm GiangVien, phân biệt theo nhan_su_chuyen_mon; không tạo nhóm Gia sư thứ sáu chỉ vì có một cổng UI riêng.

| Nhóm DB | Vai trò | Nhiệm vụ và phạm vi |
| --- | --- | --- |
| Admin | ADMIN | Tài khoản/quyền/danh mục/cấu hình; xếp lớp; duyệt nghỉ cả lớp; đối soát nhận tiền online; duyệt điều chỉnh/chốt/trả/mở công; quản lý đơn giá/lương; báo cáo |
| NhanVien | GIAO_VU | Giáo vụ/điều phối/kế toán: tiếp nhận, ghép người dạy, hồ sơ theo quyền, xếp lịch/bù, duyệt nghỉ cá nhân, chốt học phí/phát hành hóa đơn, theo dõi tiền đã nhận |
| GiangVien | GIAO_VIEN/GIA_SU | Hồ sơ/lịch rảnh của mình, đề xuất nhận lớp, ca được phân công, check-in/out, nội dung học, điểm danh/chấm bài/giao bài, gửi nghỉ/điều chỉnh công |
| TroGiang | TRO_GIANG | Ca hỗ trợ của mình, check-in/out, hỗ trợ điểm danh; không mặc định được sửa giáo án/chấm điểm |
| PhuHuynh | PHU_HUYNH | Chọn con; đăng ký khóa, phản hồi đề xuất, xem lịch/học vụ/đề bài/điểm/học phí, xin nghỉ cá nhân, thanh toán và nhận thông báo |

Khách chưa đăng nhập có thể để lại yêu cầu học/thông tin liên hệ theo đặc tả; API tiếp nhận đầy đủ còn phải triển khai.

Ma trận role_group_function có 5 cờ **view/add/edit/delete/download** trên8 phân hệ: QuanLyTaiKhoan, QuanLyNhanSu, TiepNhanYeuCau, QuanLyLopHoc, VanHanhBuoiHoc, ChamCongLuong, HocPhiThanhToan, BaoCaoThongKe. Đây là mã phân quyền, khác với6 module/21 feature tổ chức source.

Quyền chức năng phải đi cùng phạm vi dữ liệu: phụ huynh chỉ con mình; người dạy chỉ hồ sơ/phân công liên quan. Không để thay id trong URL để xem người khác. Nhân viên không mặc nhiên xem toàn bộ đơn giá/lương vì có quyền xem lớp. QuanLyNhanSu hiện chưa được seed cho giáo vụ; Admin cấp quyền phù hợp qua ma trận, code không tự cấp lại.

**Chỉ Admin được xếp lớp và quản trị tiền lương.** Đây là xác nhận mới thay phần giao giáo vụ xếp lớp/chốt lương trong Word/Figma cũ. Các API legacy còn cần cập nhật theo mục 10; trigger/ID actor không thay cho xác thực web. Phạm vi theo cơ sở cần BE áp dụng ở từng truy vấn theo đặc tả và phân công, không tuyên bố mọi API cũ đã có đủ scope cơ sở.

<a id="quy-tac"></a>
## 3. Quy tắc nghiệp vụ đã chốt

### 3.1. Khóa học, học phí và gia đình

- Khóa là sản phẩm CRUD đăng ký độc lập: cơ bản học kỳ1, cơ bản năm học, nâng cao học kỳ2, gia sư1 kèm 1, ôn luyện chuyên. Tên chỉ là ví dụ; Admin sửa tên/nội dung/giá và bật/tắt đăng ký.
- Thu **trọn từng khóa**, không học phí tháng, kỳ thu hoặc gói trả góp trong luồng mới. “Học kỳ1 cơ bản” là tên khóa, không phải một đợt thu.
- Giá được snapshot lúc đăng ký, sửa giá khóa sau đó không đổi đăng ký/hóa đơn cũ. Một hóa đơn trọn khóa cho một đăng ký.
- Tự thôi học **không hoàn học phí**. Chứng từ lịch sử/sửa sai tài chính được bảo toàn; không biến cơ chế phiếu hoàn legacy thành quyền hoàn do thôi học.
- Giáo vụ chốt học phí/phát hành hóa đơn → phụ huynh nhận thông báo và thanh toán → Admin xác nhận tiền, Admin/giáo vụ cùng thấy kết quả.
- Admin xếp lớp **độc lập việc đóng tiền**, kể cả chưa có hóa đơn. Không đặt điều kiện “đã thanh toán mới xếp lớp”.
- Một học viên có đúng một phụ huynh đại diện; một phụ huynh có nhiều con. Chọn con phải thay cả phạm vi lịch, khóa, học phí và học vụ.

### 3.2. Lịch học, nghỉ và bù

- Giáo vụ/điều phối xếp lịch; không tự bỏ ngày lễ. Sinh buổi theo khoảng ngày được chọn và hiệu lực lịch tuần, không tự suy số buổi từ tên khóa.
- Người dạy/trợ giảng không trùng ca; phòng không trùng trong cùng cơ sở/ngày/khung giờ. Hai ca nối tiếp không bị coi là chồng giờ.
- Mỗi buổi tối đa một người dạy chính có hiệu lực; có thể phân công nhiều trợ giảng. Thêm/ghi danh không vượt sĩ số tối đa.
- Nghỉ cả lớp cần Admin duyệt. Nghỉ cá nhân theo đặc tả do giáo vụ duyệt. Nghỉ một học viên lớp nhóm không hủy buổi của những người khác.
- Phụ huynh và trung tâm tự liên hệ, thống nhất việc bù ngoài hệ thống. Giáo vụ nhập ngày/giờ/người dạy/học viên và đánh loại **bù**; không cần quy trình thương lượng/xin duyệt bù trên web.
- Không có hạn mức10 buổi, đếm số buổi bù còn lại hoặc phí bù. Đơn nghỉ có thể được liên kết nhưng không bắt buộc để xếp bù.
- Buổi bù cá nhân chỉ điểm danh/học vụ học viên được xếp; buổi gốc của lớp vẫn có thể diễn ra. Bù cả lớp vẫn tham chiếu buổi gốc đã hủy/dời theo ràng buộc DB.

### 3.3. Check-in/out và tiền công

- Chỉ bấm nút; lấy **giờ server**, đúng ca/người xác thực. Không GPS/ảnh, không quyền camera/vị trí, không nhận diện khuôn mặt. Có quản lý tại nơi dạy; minh chứng để mở rộng sau.
- Check-in sớm/checkout muộn không giới hạn, nhưng không tăng công ngoài giờ phân công. Giá trị cấu hình -1 nghĩa không giới hạn; cờ trễ tính ngay khi vào sau giờ bắt đầu, không miễn15 phút.
- Lưu riêng thời gian hiện diện và phút được tính công. Cần đủ check-in/out; quên checkout không tự tính đủ giờ, người dạy đề nghị điều chỉnh, Admin xử lý có dấu vết.

Với lịch18:00–20:00:

| Vào | Ra | Phút hiện diện | Phút công | Trễ |
| --- | --- | ---: | ---: | --- |
| 06:00 | 23:00 | 1020 | 120 | Không |
| 17:50 | 20:10 | 140 | 120 | Không |
| 17:50 | 19:30 | 100 | 90 | Không |
| 18:10 | 20:05 | 115 | 110 | Có |
| 18:10 | 19:30 | 80 | 80 | Có |

Phút công = max(0, phút giữa max(checkin, lịch_bắt_đầu) và min(checkout, lịch_kết_thúc)). TIMESTAMPDIFF(MINUTE) lấy số phút nguyên theo DB. Không mặc định mọi ca luôn2 giờ; dùng thời gian thực của lịch phân công. Không tự thêm phạt/thưởng do trễ.

- Admin gán đơn giá **tiền/giờ theo từng người**, chỉ Admin xem/sửa thông tin lương. Tiền từng ca = ROUND(đơn_giá_giờ×phút_công/60,0) VND theo fn_tien_cong.
- Tăng giá có hiệu lực từ lúc thay đổi; giữ ca đã dạy/đang dạy, cập nhật ca tương lai chưa bắt đầu. Lưu lịch sử, người đặt, thời điểm và audit.
- Chỉ Admin duyệt điều chỉnh, chốt công, trả lương và mở khóa. Giáo vụ không có quyền đó.
- Công đã chốt bị khóa. Cơ chế hiện có chỉ mở bảng đã chốt **chưa trả**, cần lý do; kỳ đã trả giữ chứng từ, chưa có quy trình điều chỉnh sau trả. Không tự mở hoặc sửa chứng từ đã trả.

### 3.4. Học vụ và bài tập

Một bài chấm/điểm mỗi buổi cho từng học viên. Điểm bài giấy0–10, mức tiếp thu1–5 nếu nhập. Bốn trạng thái điểm danh: có mặt, đi trễ, vắng có phép, vắng không phép. Bài tập làm trên giấy; phụ huynh tải đề PDF/Word để in, giáo viên chấm tại lớp và nhập điểm. Không có yêu cầu học sinh nộp bài online.

Nội dung buổi, bài giảng, đề giao về nhà và điểm bài chấm là dữ liệu khác nhau. ket_qua_hoc_tap giữ kết quả đánh giá theo thiết kế hiện có; không dùng nó để tạo thêm nhiều điểm bài chấm cho cùng buổi/học viên trái quy tắc trên.

<a id="luong-nghiep-vu"></a>
## 4. Quy trình chi tiết

### 4.1. Hồ sơ, tài khoản và đăng nhập

Admin tạo/quản lý tài khoản và nhóm quyền. users là hồ sơ con người; account có username, hash mật khẩu, nhóm và trạng thái. Hồ sơ nhân viên, chuyên môn và học viên liên kết riêng; user_id không đồng nghĩa nhan_su_id/hoc_vien_id/account_id.

Người dùng đăng nhập → kiểm tra bcrypt/tài khoản active → phát JWT → mỗi request kiểm tra lại account/nhóm/quyền/scope → điều hướng đúng cổng. Logout lưu hash token bị thu hồi; đổi mật khẩu làm phiên cũ mất hiệu lực. Không trả password_hash trong API. Không dùng màn chọn vai trò của prototype làm cơ chế phân quyền thật.

### 4.2. Đăng ký khóa và nhu cầu ghép người dạy

1. Admin quản lý khóa/mức phí. Khóa inactive không nhận đăng ký mới; việc đổi giá không viết lại lịch sử.
2. Phụ huynh chọn con và khóa. BE xác minh quan hệ phụ huynh–học viên, ghi dang_ky_khoa_hoc và snapshot giá, ban đầu cho_xep_lop.
3. Nếu cần tìm người dạy, khách/phụ huynh để lại nhu cầu, môn/khối/khu vực/khung giờ/ngân sách; lưu yeu_cau_hoc + yeu_cau_lich. Yêu cầu mới có thể chưa có nhân viên phụ trách.
4. Giáo vụ lọc chuyên môn môn/khối, khu vực, lịch rảnh, xung đột lịch và ngân sách có đơn vị rõ ràng. Không để lộ giá lương cá nhân qua màn ghép cho người ngoài Admin; giá học phí đề xuất là dữ liệu khác.
5. Giáo vụ tạo de_xuat_nhan_su; người dạy và phụ huynh phản hồi riêng cho/dong_y/tu_choi, lưu lý do đúng bên/thời điểm/actor. Hai bên đồng ý mới chuyển sang bước chốt người dạy theo đặc tả.
6. Admin xếp lớp/ghi danh khóa. Giáo vụ tiếp tục xếp lịch. Không tự tạo lớp bằng quyền Giáo vụ chỉ vì hai bên đã đồng ý đề xuất.

API lọc người dạy đã có; toàn bộ workflow tiếp nhận/đề xuất/khóa/đăng ký chưa có đủ API. Không coi đường endpoint minh họa trong Word là route đã triển khai.

### 4.3. Chốt học phí, thông báo và thanh toán

1. Giáo vụ đối chiếu khóa/đăng ký, lập hoa_don nhap gắn dang_ky_khoa_hoc_id, tổng bằng hoc_phi_snapshot, không cần giả tháng/năm hoặc lớp để lập hóa đơn.
2. Phát hành kèm phat_hanh_boi/luc. DB tạo thong_bao cho phụ huynh, chống trùng theo hóa đơn; FE/worker sẽ hiển thị/gửi khi được viết.
3. Phụ huynh mở hóa đơn của đúng con. BE tạo giao_dich_truc_tuyen với số dư phải thu, VND, mã yêu cầu duy nhất, provider/order id khi có; khởi tạo chưa phải tiền đã nhận.
4. Phụ huynh thanh toán qua adapter ngân hàng/cổng thật ở bước tích hợp. Redirect từ trình duyệt hoặc bấm “đã chuyển” chỉ để hiển thị/chờ đối soát, không được tự tăng da_thu.
5. Webhook phải kiểm chữ ký, merchant, order, amount, currency và kết quả với provider trước xử lý. su_kien_thanh_toan lưu provider/event unique + hash payload; lưu event không tự sinh phiếu thu.
6. Admin đối soát tiền và xác nhận giao dịch cho_xac_nhan. sp_xac_nhan_giao_dich khóa hóa đơn rồi giao dịch, tạo một phiếu thu, cập nhật số dư/trạng thái, audit trong transaction. Gọi lại cùng giao dịch trả phiếu cũ, không thu hai lần.
7. DB tạo thông báo cho phụ huynh, Admin và giáo vụ active. Các bên xem phiếu thu/kết quả; trạng thái hóa đơn theo tiền thật đã ghi nhận.

Hóa đơn có nhap/chua_thanh_toan/mot_phan/da_thanh_toan/huy. Chứng từ legacy vẫn có thể gồm nhiều giao dịch/thu một phần; đó không phải chính sách chia kỳ mới. Yêu cầu online mới phải bằng số dư còn phải thu. Giao dịch có khoi_tao/cho_xac_nhan/thanh_cong/that_bai/het_han/huy; giao dịch kết thúc không sửa tiền hoặc kích hoạt lại. Thử lại tạo yêu cầu mới có kiểm tra số dư.

Không thu vượt phải thu; hoàn/hủy phiếu không làm số dư âm. Phiếu không xóa cứng hoặc sửa danh tính/số tiền; dùng trạng thái hủy, có người/thời điểm/lý do. Hóa đơn đã có chứng từ không chuyển sang học viên/kỳ khác. Tự thôi học không phát sinh hoàn khóa. Cơ chế đối soát sửa sai khác không tự thêm thành chính sách hoàn mới.

### 4.4. Admin xếp lớp và giáo vụ xếp lịch

Admin chọn lớp đúng khóa, học viên/đăng ký, ngày vào/trạng thái → kiểm tra khóa–học viên, trùng ghi danh và sĩ số → ghi lop_hoc_vien cùng xep_boi/xep_luc. Cho phép thực hiện trước/sau thanh toán.

Giáo vụ chọn lịch tuần, người dạy, cơ sở/phòng, ngày hiệu lực → lưu lich_hoc → xem khoảng sinh → gọi sp_sinh_buoi_hoc(lop_id,tu,den). DB sinh buoi_hoc và phân công chính từ người dạy của lớp, chụp đơn giá giờ theo người. Không sinh trước ngày khai giảng/sau ngày kết thúc/ngoài hiệu lực lịch; sinh lặp không tạo buổi trùng khóa lớp/ngày/giờ. Thứ2=2…Chủ nhật=8.

Trùng phòng kiểm theo cơ sở/phòng/ngày/khung giờ; phòng NULL không coi là một phòng chung của các lớp tại nhà/online. Phân công trùng người bị chặn cả vai trò chính và trợ giảng. Không tự gán lại người đã bị gỡ hoặc tự bỏ lễ. Sửa lịch sau check-in hoặc dời vào kỳ công khóa bị bảo vệ; phải dùng thao tác điều chỉnh hợp lệ, không sửa lén snapshot.

### 4.5. Một buổi học từ đầu đến cuối

1. Người dạy/trợ giảng mở ca của mình, bấm check-in; BE dùng giờ server, ghi checkin_luc và đánh trễ theo lịch. Không ảnh/GPS.
2. Người dạy giảng bài, chấm bài giấy; cuối buổi nhập chung trên một màn hình: chủ đề/nội dung/bài giảng của buoi_hoc, điểm danh/điểm/nhận xét của so_theo_doi_buoi_hoc và đề giao về nhà ở bai_tap.
3. Trợ giảng chỉ thao tác phạm vi hỗ trợ điểm danh; quyền sửa nội dung/điểm phải được chặn ở BE.
4. Bấm checkout, ghi giờ server. DB giữ phút hiện diện và phút công trong lịch. BE phải chuyển trạng thái buổi/phân công/công nhất quán; chỉ có timestamp chưa đủ coi ca hợp lệ đã hoàn thành.
5. Phụ huynh chọn con → xem “Hôm nay học gì”, lịch, điểm/nhận xét, tải đề bài/bài giảng. Không xem học viên khác bằng đổi id.

Học vụ phải đúng học viên thuộc lớp trong ngày học. Buổi bù cá nhân chỉ ghi cho học viên đã được xếp. Bài tập chấm nếu liên kết phải cùng lớp; UNIQUE buổi/học viên ngăn hai bản bài chấm.

### 4.6. Nghỉ cá nhân, nghỉ cả lớp và học bù

Nghỉ cá nhân: phụ huynh gửi cho con hoặc người dạy gửi cho ca của mình → don_xin_nghi cho_duyet → giáo vụ/Admin duyệt hoặc từ chối có dấu vết. Không hủy buổi nhóm vì một học viên nghỉ.

Nghỉ cả lớp: tạo yeu_cau_nghi_lop → chỉ Admin duyệt/từ chối → nếu duyệt, cập nhật buổi hủy và metadata; vẫn tôn trọng công khóa. Giáo vụ không dùng màn xác nhận hủy để tự duyệt nghỉ lớp.

Học bù: sau khi hai bên liên hệ/chốt ngoài web, giáo vụ tạo buoi_hoc loai=bu, buoi_goc_id, ngày/giờ/phân công; bù cá nhân pham_vi_bu=ca_nhan và ghi hoc_bu_hoc_vien. Có thể để don_xin_nghi_id NULL. Bù cả lớp dùng pham_vi_bu=ca_lop và buổi gốc đã hủy/dời. Không tự hủy buổi gốc lớp nhóm cho bù cá nhân, không sinh hóa đơn/thu phí/hạn mức. Nếu đổi/hủy lịch, cập nhật theo quyền và ràng buộc lịch/công, thông báo thay đổi khi BE thông báo được triển khai.

### 4.7. Điều chỉnh công, tăng giá giờ, chốt và trả lương

Người dạy gửi dieu_chinh_cong kèm ca, giờ cũ/mới, lý do → Admin duyệt/từ chối. Một đơn đã duyệt không tự thay giờ chỉ nhờ có trạng thái; service BE phải áp dụng thay đổi giờ, trạng thái và audit trong thao tác nhất quán, xử lý kỳ khóa trước.

Admin đổi đơn giá qua sp_dat_don_gia_gio(nhan_su_id,gia,admin_id): khóa người, ghi lịch sử hiện tại, sửa giá chuẩn và phân công tương lai chưa bắt đầu. Ca đã dạy/đã check-in giữ giá. Nếu ca tương lai thuộc kỳ khóa, rollback để Admin xử lý khóa trước, không cập nhật nửa chừng.

Chốt tháng: Admin chọn người/tháng/năm → sp_chot_cong kiểm khoảng kỳ, thiếu checkout, công chờ/đơn điều chỉnh chờ, snapshot NULL và trạng thái hợp lệ → tổng hợp so_phut_tinh_cong, tiền theo snapshot, số buổi/bù/trễ/vắng → gắn bang_cong_id vào ca hợp lệ → da_chot + người/thời điểm/audit. Không âm thầm bỏ công hợp lệ có trạng thái ca/buổi chưa khớp.

Chi trả: Admin ghi da_tra từ da_chot, tra_boi/luc/ngay_tra và mã đối chiếu nếu có; lưu số tiền quyết toán. Mô hình hiện có một lần trả/bảng công, chưa có chứng từ trả lương nhiều đợt. Kỳ đã trả không sửa số tiền/actor/kỳ hoặc tự mở lại.

Mở khóa: sp_mo_khoa_cong(id,admin,ly_do) chỉ nhận da_chot chưa trả, chuyển mo và ghi dấu vết. Ca được giữ liên kết để tổng hợp lại; khi chốt lại, procedure chọn lại các ca hợp lệ, không cộng trùng. Quyền mở khóa thuộc Admin không có nghĩa được bỏ ràng buộc kỳ đã trả.

### 4.8. Thông báo, email, audit và báo cáo

Thông báo web lưu thong_bao; trạng thái đọc tự tính từ doc_luc, không ghi trực tiếp da_doc. Có khóa chống trùng theo người/nguồn; DB 005 sinh thông báo phát hành hóa đơn/phiếu thu. Các sự kiện phân công/đổi lịch/đơn nghỉ/học vụ sẽ do BE tạo phù hợp.

Email có cờ gửi, số lần thử, trạng thái/lỗi, thời điểm gửi/thử gần nhất/thử tiếp. Worker còn phải viết: chọn thông báo cần gửi, gửi SMTP, lưu trạng thái/retry có kiểm soát; không gửi mạng trong transaction trigger. Mailpit hiện chỉ là SMTP thử.

audit_log ghi actor, hành động, bảng/id, dữ liệu trước/sau, metadata; dùng cả thu hồi token. Không xóa audit token còn hiệu lực. Không ghi mật khẩu/token nguyên văn vào log. Dashboard/report cần học viên/lớp, học phí/công nợ, công dạy/lương theo quyền; báo cáo lương chỉ Admin. In hóa đơn/báo cáo, xuất dữ liệu và mail thật là phần còn phải làm.

<a id="kien-truc"></a>
## 5. Cấu trúc mã nguồn và MVC

Manifest [shared/modules.json](../shared/modules.json) là danh sách chuẩn6 module/21 feature:

| Module | Feature |
| --- | --- |
| Administration | Auth, Permissions, Staff, Catalog, Audit |
| People | Students, Teachers, Tutors, Parents |
| Academic | Admissions, AcademicAffairs, Classes, Scheduling, Attendance, LeaveRequests, MakeupClasses |
| Finance | Tuition, Timekeeping, Payroll |
| Communication | Notifications |
| Reporting | Dashboard |

Backend: `backend/app/Modules/<Module>/Features/<Feature>/`; frontend: `frontend/src/modules/<module>/features/<feature>/`. PHP PascalCase theo PSR-4, URL lowercase/kebab-case, ví dụ /api/academic/leave-requests. Chưa triển khai feature thì giữ TODO/status planned; không viết nội dung giả để báo hoàn thành.

Trong feature: Controller nhận HTTP/MVC; Service điều phối nghiệp vụ/transaction; Repository truy vấn bind tham số; Model ánh xạ trường camelCase; Validator kiểm tra input; Filter kiểm tra quyền; Routes.php khai báo route; Views/ khi có màn MVC. Không đặt SQL trong view, không nhân đôi logic Teachers/Tutors.

app/Shared gồm Access (JWT/RBAC/scope), Store (session DB/transaction/procedure), Input, ApiController/ApiException/ApiFilter. app/Config ghép routes từ manifest, tắt auto-route. backend/public là document root; Composer/config/env nằm ngoài public. Frontend shared giữ component/style/service dùng chung; shared/ gốc có manifest và hằng số trình duyệt.

Compose local bind repo và dùng DB hiện có; image release chứa backend/shared và vendor, không đưa DB/Word/demo/secret vào public image. Phần reference đã được giữ nguyên byte để tham khảo, không phải chương trình triển khai.

<a id="database"></a>
## 6. Thiết kế database

Nguồn thực thi: [schema.sql](../database/schema.sql), [install-order.json](../database/install-order.json), routines/, triggers/, migrations/, seeders/, checks.sql. Hiện có **43 bảng,5 function,15 procedure,57 trigger**. Cột/PK/FK/check/index đầy đủ ở [mục 13](#tu-dien-du-lieu); code SQL là căn cứ khi thay đổi.

| Nhóm dữ liệu | Bảng |
| --- | --- |
| Danh tính/quyền | users, account, role_group, function, role_group_function |
| Danh mục/cơ sở/cấu hình | mon_hoc, cap_lop, khu_vuc, co_so, cau_hinh |
| Hồ sơ | nhan_vien, nhan_su_chuyen_mon, hoc_vien |
| Năng lực/địa bàn/lịch rảnh | nhan_su_mon, nhan_su_khu_vuc, nhan_su_lich_ranh |
| Tuyển sinh/ghép | yeu_cau_hoc, yeu_cau_lich, de_xuat_nhan_su |
| Khóa/đăng ký/giá giờ | khoa_hoc, dang_ky_khoa_hoc, lich_su_don_gia_gio |
| Lớp/lịch/ca | lop_hoc, lop_hoc_vien, lich_hoc, buoi_hoc, phan_cong_buoi_hoc |
| Đơn và bù | don_xin_nghi, yeu_cau_nghi_lop, hoc_bu_hoc_vien, dieu_chinh_cong |
| Học vụ | bai_tap, so_theo_doi_buoi_hoc, ket_qua_hoc_tap |
| Tiền | bang_cong_thang, hoa_don, hoa_don_chi_tiet, thanh_toan, giao_dich_truc_tuyen, su_kien_thanh_toan |
| Hệ thống | thong_bao, audit_log, schema_migrations |

Quan hệ chính:

```text
users(phụ huynh) 1 ── n hoc_vien
hoc_vien 1 ── n dang_ky_khoa_hoc n ── 1 khoa_hoc
khoa_hoc 1 ── n lop_hoc 1 ── n lop_hoc_vien ── dang_ky_khoa_hoc
lop_hoc 1 ── n lich_hoc 1 ── n buoi_hoc
buoi_hoc 1 ── n phan_cong_buoi_hoc ── users(người dạy)
buoi_hoc 1 ── n so_theo_doi_buoi_hoc ── hoc_vien
buoi_hoc(loại bù cá nhân) 1 ── n hoc_bu_hoc_vien ── hoc_vien
dang_ky_khoa_hoc 1 ── 0..1 hoa_don 1 ── n thanh_toan
hoa_don 1 ── n giao_dich_truc_tuyen 1 ── n su_kien_thanh_toan
giao_dich_truc_tuyen ── 0..1 thanh_toan (mỗi phiếu chỉ gắn một giao dịch)
bang_cong_thang 1 ── n phan_cong_buoi_hoc (khi chốt)
```

user/account là1:1 khi có account; không chia bảng người dạy theo từng cổng. Khóa học khác lớp học: mua khóa ghi giá đăng ký, Admin xếp vào lớp phù hợp, không chờ đóng tiền. Một lớp có nhiều học viên; lịch tuần khác từng buổi cụ thể; ca của từng người nằm ở bảng phân công.

### 6.1. Snapshot và dữ liệu legacy

- dang_ky_khoa_hoc.hoc_phi_snapshot giữ giá đăng ký; phan_cong_buoi_hoc giữ đơn giá/cách tính và khung giờ lịch.
- so_phut_thuc_te là hiện diện; so_phut_tinh_cong là phút trả lương. quy_tac_cong=3 giữ lịch sử003; ca mới=4. Sửa giờ theo thao tác kiểm soát sẽ dùng quy tắc mới, không chạy migration để tính lại mọi ca cũ.
- Hóa đơn trọn khóa dùng dang_ky_khoa_hoc_id, không lớp/tháng/năm giả. Hóa đơn legacy vẫn có lop_hoc_vien_id/thang/nam. CHECK buộc đúng một nguồn; gán cả hai hoặc bỏ cả hai bị chặn.
- Trường theo tháng/buổi/GPS còn trong schema để bảo toàn dữ liệu gốc, không phải chính sách mới. Lớp cũ chưa gắn khóa giữ NULL để đối chiếu, không tự suy tên/giá.
- Dấu vết lịch sử chưa biết giữ NULL; check báo CAN_XAC_NHAN. Không điền giá/actor/thời gian giả bằng giá hiện tại hoặc người lập.
- Cột tự tính gồm so_phut_thuc_te, so_phut_tinh_cong, phai_thu, thuc_nhan, day_chinh_key, da_doc. Không để API ghi đè kết quả tự tính.

### 6.2. Toàn vẹn và trách nhiệm ứng dụng

DB bảo vệ khóa ngoại, miền giá trị, số dư, sĩ số, chồng lịch, một người dạy chính, một bài chấm, snapshot và kỳ khóa. API vẫn phải xác thực actor, phạm vi con/ca/cơ sở, chống IDOR, kiểm tra input, lọc dữ liệu lương và xác minh thanh toán thật. Truyền user_id của Admin vào procedure không chứng minh người gọi là Admin.

Kết nối ghi READ COMMITTED, transaction ngắn, khóa đúng thứ tự, retry toàn giao dịch khi deadlock. Procedure có START TRANSACTION/COMMIT riêng phải CALL độc lập ngoài transaction caller: sinh buổi, đổi giá giờ, chốt/mở công, xác nhận giao dịch. Các helper được gọi trong trigger dùng transaction hiện tại. Bộ test hiện không thay kiểm chứng tải đồng thời hoặc diễn tập hạ tầng production.

<a id="sql-lifecycle"></a>
## 7. Luồng cài đặt, nâng cấp và xử lý SQL

### 7.1. Thứ tự cài DB mới

run.ps1 -Action init chỉ nhận database rỗng: tạo DB nếu thiếu → schema.sql → ghi001–005 applying → nạp **đúng manifest** → seeders/001_reference.sql →004_business_policy.sql →005_operations_policy.sql → đánh dấu applied. Sau đó Action check chạy checks.sql. Không chạy ALTER migration trên schema mới đã005.

Manifest hiện hành theo thứ tự:

1. routines/functions.sql
2. routines/helpers.sql
3. routines/business_rules.sql
4. routines/payments.sql
5. routines/procedures.sql
6. triggers/timekeeping.sql
7. triggers/classes.sql
8. triggers/scheduling.sql
9. triggers/academic.sql
10. triggers/tuition.sql
11. triggers/payroll.sql
12. triggers/business_rules.sql
13. triggers/payments.sql

Routine/helper phải có trước trigger gọi nó. Không tạo thêm file SQL gộp trùng để bảo trì hai nguồn khác nhau.

### 7.2. Nâng DB cũ

Backup/restore thử → preflight theo baseline → migrations còn thiếu001→002→003→004→005 → nạp module mới → policy giới hạn đúng phần cần đổi → applied chỉ với bản ghi applying → checks. Không reseed toàn bộ ma trận quyền vào DB đã có người dùng. Đã005 thì migrate chỉ kiểm tra, giữ dữ liệu và applied_at; version lạ hoặc applying thì dừng.

MySQL DDL tự COMMIT, không có cam kết rollback cả migration. Lỗi giữa chừng cần đọc tiến độ/backup và sửa có kế hoạch, không xóa marker, --force hoặc import schema.sql đè DB cũ. SQL gốc ở reference/database/schema-original.sql có DROP DATABASE, chỉ tham khảo.

### 7.3. Seeder và cấu hình

| File | Mục đích/điều kiện |
| --- | --- |
| 001_reference.sql | Danh mục/quyền chuẩn, chạy DB mới; không ghi đè quyền DB cũ |
| 002_demo.sql | Dữ liệu8 users cho DB `_test`, account locked; không nạp vào DB làm việc |
| 003_courses_example.sql | Action seed-courses:5 khóa giá minh họa/inactive; không ghi đè mã đã có, không tự tạo học viên/hóa đơn |
| 004_business_policy.sql | Chỉ Admin công/lương, ngưỡng trễ0 |
| 005_operations_policy.sql | checkin_truoc_phut=-1, checkout_sau_phut=-1, checkin_minh_chung=khong |

han_nhap_bao_cao_gio hiện24 theo reference; các khóa cấu hình không phải chỗ lưu password/JWT. Giá mẫu1200000/2400000/1800000/3000000/2000000 VND chỉ để Admin sửa trước mở khóa đăng ký, không phải bảng giá đã ký với phụ huynh.

### 7.4. Luồng SQL khi ứng dụng hoạt động

Đăng ký → trigger lấy giá active của khoa_hoc và snapshot → hóa đơn đúng nguồn/giá → phát hành tạo thông báo. Online intent → kiểm phụ huynh/số dư/khóa chống trùng → event được xác minh ở BE → Admin confirm → phiếu thu → trigger cập nhật da_thu/trạng thái và thông báo các bên.

Xếp lớp → khớp khóa/học viên + Admin actor + sĩ số → lịch tuần → sinh buổi/phân công → chụp giá giờ/lịch → check-in/out tạo phút công/cờ trễ → học vụ đúng học viên → Admin chốt gắn khóa công. Đơn nghỉ/bù đi quan hệ riêng, không tự sinh phí hoặc đổi trạng thái tài chính.

Action check đọc số object/version và dữ liệu vi phạm: lệch số dư, sai kỳ/người công, vượt sĩ số, học vụ khác lớp, thiếu snapshot, quyền lương ngoài Admin, giao dịch lệch phiếu. CAN_XAC_NHAN là dữ liệu cần đối chiếu, như lớp cũ chưa gắn khóa/actor chưa rõ hoặc phụ huynh hiện tại khác người thanh toán lịch sử; không mặc định sửa/xóa chúng.

<a id="api"></a>
## 8. API hiện có và quy ước BE

| Nhóm | Route/thao tác đang có |
| --- | --- |
| Health/manifest | GET /api/health, /health, /api |
| Auth | /api/auth hoặc /api/administration/auth: POST login/logout, GET me, PUT/POST change-password |
| Phụ huynh | /api/people/parents: GET/POST, GET/PUT /:id |
| Học viên | /api/people/students: GET/POST, GET/PUT/DELETE /:id; DELETE soft delete |
| Người dạy | /api/people/teachers, /api/people/tutors: GET/POST, GET/PUT /:id, GET /match |
| Lớp/ghi danh | /api/academic/classes: GET/POST, GET/PUT /:id, GET/POST /:id/enrollments, PUT /:id/enrollments/:enrollmentId |
| Lịch | /api/academic/scheduling: GET/POST weekly, GET sessions, POST generate-sessions, POST sessions/:id/assign, PUT sessions/:id |
| Danh mục/quyền | /api/administration/catalog và /permissions, chi tiết mục 12 |
| Status | /api/:module/:feature/status, ready/planned theo feature đăng ký |

API nghiệp vụ dùng Authorization: Bearer, ngoại trừ login/health/manifest/status và route public được khai báo rõ. Thành công `{success:true,data:...}`, tên trường camelCase; lỗi success=false, code/message; 401 phiên sai,403 thiếu quyền,404 không thấy/ngoài scope,409 trùng/xung đột,400 vi phạm nghiệp vụ. SQL SIGNAL45000 được ánh xạ BUSINESS_RULE_VIOLATION, lỗi server không trả SQL/password cho client.

Routes.php/module và app/Config/Routes.php mới là căn cứ route đã triển khai. Word có endpoint minh họa và tên controller cũ; không dùng chúng như tài liệu API chạy được. Chưa có đủ API đăng ký khóa, checkout online, check-in/out, nghỉ/bù, học vụ, payroll hay worker thông báo. status planned không phải chức năng đã xong; health200 chỉ xác nhận PHP/MySQL.

<a id="figma"></a>
## 9. Figma và giao diện

[Thiết kế chỉnh sửa được](https://www.figma.com/design/O6RLoIjTYy72BU8OmWN7kz/EduCenter-ERP?node-id=33-870) · [Prototype](https://www.figma.com/proto/O6RLoIjTYy72BU8OmWN7kz/EduCenter-ERP?node-id=33-870&page-id=0%3A1&starting-point-node-id=33%3A870&scaling=scale-down&content-scaling=fixed).

Bản thiết kế có các cổng Admin/Giáo vụ/Giáo viên/Gia sư/Trợ giảng/Phụ huynh. Lần hoàn thiện04/11 đã thêm162 frame gồm màn, form, trạng thái và mục lục, không phải162 chức năng độc lập. Rà soát08/11 thấy177 frame cấp đầu, gồm cả15 frame gốc/components. Phong cách Inter, teal#0d9488, sidebar#171717, nền#f5f5f5, desktop1440.

Phần cũ dùng đặc tả trước004/005, cần cập nhật:

- Thêm CRUD khóa, phụ huynh chọn con/khóa/đăng ký và lịch sử đăng ký.
- Thêm thanh toán online, chờ xác nhận/thành công/thất bại/hết hạn/thử lại; Admin đối soát và giáo vụ xem kết quả.
- Thêm form/lịch sử đơn giá giờ và duyệt nghỉ cả lớp của Admin; báo cáo chi tiết có lọc/in/xuất.
- Chuyển xếp lớp/ghi danh, duyệt/chốt/trả công từ Giáo vụ sang Admin; ẩn đơn giá/tiền lương khỏi cổng khác.
- Chuyển học phí tháng/buổi sang trọn khóa; không tự hoàn khi thôi học.
- Check-in bỏ GPS/ảnh/cửa sổ30 phút/ngưỡng trễ15 phút, phân biệt phút hiện diện/phút công; lịch bù không bắt buộc đơn đã duyệt, không phí/hạn mức.
- Thêm nội dung thông báo học phí khóa/nhận tiền/xếp lớp; nối prototype theo luồng mới và kiểm tra responsive khi triển khai.

Lần rà soát đọc trực tiếp danh mục và nhóm màn khóa/học phí; nội dung công/lương/check-in/bù đối chiếu thêm bản thiết kế đã lưu do công cụ Figma chạm hạn mức. Chưa chỉnh canvas theo005. Không coi Figma là FE đã kết nối DB. Demo HTML lưu ở reference/ dùng localStorage và dữ liệu mô phỏng; giữ để tham khảo giao diện, không dùng làm backend.

<a id="tien-do"></a>
## 10. Tiến độ, kiểm chứng và phần tiếp theo

| Phần | Đã có | Còn phải làm |
| --- | --- | --- |
| DB/server | Schema005, migration/seed/check, routine/trigger, Docker PHP/MySQL, SMTP local, backup/restore/doctor, release config | Vận hành public, dịch vụ ngoài và kiểm thử tải/thực tế |
| PHP nền | Auth/JWT/scope, hồ sơ, lớp/lịch, matching, Catalog/Permissions | Cập nhật quyền legacy và che lương; không coi bộ cũ đã khớp toàn bộ 005 |
| Administration | Auth, danh mục/quyền, audit khi sửa | Staff API đầy đủ, tra cứu Audit, các màn quản trị |
| Khóa/tuyển sinh | Bảng/quan hệ và API matching | CRUD khóa/đăng ký, tiếp nhận/đề xuất/phản hồi/chốt đầy đủ |
| Học vụ/nghỉ/bù | Bảng, ràng buộc | API/MVC/FE và kịch bản người dùng |
| Tài chính/công | Hóa đơn/intent/event/phiếu, phút công/lương, procedure | Gateway/webhook xác thực, API chốt học phí/thu/công/lương, FE |
| Thông báo | Dữ liệu, trigger học phí, SMTP thử | API đọc/thao tác, worker/retry/template/mail thật |
| FE | Khung module rỗng, portal PHP đăng nhập/tra cứu cơ bản, Figma/demo | Các màn thực, responsive, kết nối API, loading/empty/error/success |
| Báo cáo/nộp bài | Nguồn nghiệp vụ và nền kỹ thuật | Dashboard/thống kê/in/xuất, báo cáo môn học, ảnh minh chứng/phân công/kịch bản trình diễn |

**Hai việc BE đầu tiên:** chỉ Admin xếp lớp kể cả legacy, che đơn giá/tiền lương khỏi API ngoài Admin; sau đó CRUD khóa và đăng ký. Hiện TeachersModel/ClassesModel còn trả trường lương trong JSON cũ. DB không tự sửa các response này; cần hoàn thành trước mở web cho các vai trò khác sử dụng thực tế.

Tiếp theo: phát hành hóa đơn/thông báo/thanh toán/đối soát → Admin xếp lớp và giáo vụ xếp lịch/bù → check-in/học vụ/nghỉ → lương/báo cáo/mail → FE từng luồng nối API và kiểm thử ngay khi phần tương ứng xong → deploy/nghiệm thu. Không cần chờ toàn bộ Figma mới bắt đầu BE. Chỉ viết tiếp chức năng khi được giao; câu hỏi “nên code chưa” không phải lệnh triển khai.

Kết quả đã ghi nhận08/11/2026 trên nền005:

| Bộ kiểm tra | Số đạt |
| --- | ---: |
| PHP core | 71 |
| API/HTTP/MySQL nền | 81 |
| API quản trị | 103 |
| SQL001–003 | 51 |
| SQL nghiệp vụ004 | 27 |
| SQL vận hành005 | 20 |
| Tổng | 353 |

Còn có 220 file PHP lint, Composer lock, structure/hash, cài mới và nâng003/004→005, giữ giá trị/applied_at cũ, migration lặp không đổi dữ liệu, từ chối init DB không rỗng. Backup dữ liệu thật đã khôi phục/nâng thử, đối chiếu hash rồi mới áp dụng005; có bản backup sau005 đã verify. DB local thời điểm kiểm tra43 bảng/57 trigger/20 routine, không VI_PHAM và CAN_XAC_NHAN khác0.

Script MySQL đã thử trên container/volume riêng: tạo mới, dừng/bật giữ dữ liệu, từ chối tự khởi tạo khi chỉ còn volume. Apache configtest, Caddy validate, Compose release, image application build và doctor PHP/MySQL/SMTP/HTTP đạt. Không thử thanh toán ngân hàng thật, gửi mail người thật, cấp chứng chỉ public hoặc kiểm chứng tải đồng thời. Các con số là bằng chứng của phiên bản/thời điểm đó; chạy lại kiểm tra khi source thay đổi, không xem là chứng nhận mọi feature đã hoàn thiện.

<a id="lich-su"></a>
## 11. Lịch sử và tài liệu nguồn

| Mốc | Thay đổi được giữ trong thiết kế |
| --- | --- |
| SQL gốc | 35 bảng, bản tham khảo có lệnh xóa DB, không dùng triển khai |
| 001_feature_based | Tách schema/seed/routine/trigger, thêm schema_migrations, khóa/ràng buộc và công cụ cài an toàn |
| 002_routine_review | Sửa helper/khóa/thu tiền/chốt công,8 hồi quy; không ALTER dữ liệu nghiệp vụ |
| 003_attribute_review | Bỏ học viên dư ở chi tiết hóa đơn, da_doc tự tính, tách lý do từ chối, metadata/phản hồi/timestamp,51 SQL cases |
| 004_business_rules | Thêm5 bảng khóa/đăng ký/giá giờ/nghỉ lớp/bù cá nhân,41 bảng; phút công giao lịch, Admin lương, thu trọn khóa |
| 005_operations_ready | Thêm intent/event thanh toán,43 bảng; thông báo, dấu vết xếp lớp, check-in đơn giản không giới hạn |
| PHP migration | Thay Node/Express bằng CodeIgniter 4, giữ bcrypt/DB, auth/API/portal/CI; PR #5 |
| Quản trị API | Catalog/Permissions, version ma trận/audit, PR #6 |
| DB 004 và DB/server005 | PR #7 và PR #8 đã merge; hai file tài liệu hiện hành thay nhóm Markdown rời cũ |

Rà soát003 ngày 03/11 dùng MySQL 8.4.13 riêng,51/51 ca và nâng từ gốc/001/002, bảo toàn dữ liệu. Các con số15 hash/18 feature/198 khung ở báo cáo cũ chỉ là cấu trúc lúc đó, không phải repo hiện nay. Các mục “chưa rõ GPS/phí bù/quyền chốt/nhà có nhiều phụ huynh” đã được xác nhận như mục 3; không đưa trở lại danh sách chờ chốt. Không tự gán đủ BR01–BR20 vì Word/Markdown cũ dùng mã khác nhau; dùng tên nghiệp vụ hiện hành.

Nguồn giữ nguyên byte:

- [Word nghiệp vụ gốc](../reference/quy_trinh_nghiep_vu_v2.docx): chỉ tham khảo lịch sử khi khác quyết định mới.
- [SQL gốc](../reference/database/schema-original.sql): không chạy vào DB hiện có.
- [Demo HTML](demo/index.html): mô phỏng, không gọi API thật.
- [Manifest hash](../reference/preserved-files.json): kiểm tra bảo toàn3 nguồn trên.

Đề cương IS207 PDF là file chủ đồ án cung cấp trong cuộc trao đổi, không được tự thêm bản vào repo. Mật khẩu web/DB/JWT, file env cá nhân, backup và tài khoản merchant không nằm trong hai tài liệu hoặc lịch sử code được chủ động bổ sung.

<a id="api-quan-tri"></a>
## 12. Chi tiết API quản trị hiện có

Các route cần `Authorization: Bearer <token>`. Response dùng `{success:true,data:...}`; lỗi có `code` và `message`. Mô tả API hiện có trên nền PHP/CodeIgniter và schema 005.

### Danh mục

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

- checkin_truoc_phut, checkout_sau_phut: số nguyên -1–1440 phút, -1 = không giới hạn (mặc định005).
- checkin_tre_sau_phut: 0–1440, mặc định0.
- checkin_minh_chung: chỉ nhận chuỗi khong theo nghiệp vụ hiện hành, không GPS/ảnh.
- han_nhap_bao_cao_gio: số nguyên 1–8760 giờ.

Request ví dụ `{"giaTri":30}`. Không dùng API này để ghi JWT_SECRET, mật khẩu DB hoặc biến môi trường. Khóa cấu hình ngoài danh sách trả 404. Mọi thay đổi danh mục/cấu hình ghi audit trong cùng transaction.

### Phân quyền

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

### Kiểm thử

```powershell
pwsh -File backend/scripts/test.ps1
```

Script tạo MySQL riêng và chạy core, luồng cũ, suite administration và SQL regression. Không chạy integration trên database đang dùng. Muốn chạy riêng suite mới trên DB thử đã init, cấu hình TEST_WITH_DB=1 và DB_NAME kết thúc `_test`, sau đó `composer test:admin`.

<a id="tu-dien-du-lieu"></a>
## 13. Từ điển dữ liệu toàn bộ 43 bảng

Phần này đối chiếu trực tiếp schema 005: toàn bộ cột, kiểu/nullable/default/generated và PK/FK/UNIQUE/index/CHECK. NULL nghĩa chưa có/không áp dụng theo ngữ cảnh; snapshot và trường legacy giải thích tại mục 6. Khai báo SQL đầy đủ trong source vẫn là căn cứ thực thi. Không sao chép các định nghĩa dưới đây để ALTER DB đang có dữ liệu.

[users](#bang-users) · [role_group](#bang-role-group) · [account](#bang-account) · [function](#bang-function) · [role_group_function](#bang-role-group-function) · [mon_hoc](#bang-mon-hoc) · [cap_lop](#bang-cap-lop) · [khu_vuc](#bang-khu-vuc) · [co_so](#bang-co-so) · [cau_hinh](#bang-cau-hinh) · [nhan_vien](#bang-nhan-vien) · [nhan_su_chuyen_mon](#bang-nhan-su-chuyen-mon) · [hoc_vien](#bang-hoc-vien) · [nhan_su_mon](#bang-nhan-su-mon) · [nhan_su_khu_vuc](#bang-nhan-su-khu-vuc) · [nhan_su_lich_ranh](#bang-nhan-su-lich-ranh) · [yeu_cau_hoc](#bang-yeu-cau-hoc) · [yeu_cau_lich](#bang-yeu-cau-lich) · [de_xuat_nhan_su](#bang-de-xuat-nhan-su) · [khoa_hoc](#bang-khoa-hoc) · [dang_ky_khoa_hoc](#bang-dang-ky-khoa-hoc) · [lich_su_don_gia_gio](#bang-lich-su-don-gia-gio) · [lop_hoc](#bang-lop-hoc) · [lop_hoc_vien](#bang-lop-hoc-vien) · [lich_hoc](#bang-lich-hoc) · [buoi_hoc](#bang-buoi-hoc) · [phan_cong_buoi_hoc](#bang-phan-cong-buoi-hoc) · [dieu_chinh_cong](#bang-dieu-chinh-cong) · [don_xin_nghi](#bang-don-xin-nghi) · [bai_tap](#bang-bai-tap) · [so_theo_doi_buoi_hoc](#bang-so-theo-doi-buoi-hoc) · [ket_qua_hoc_tap](#bang-ket-qua-hoc-tap) · [bang_cong_thang](#bang-bang-cong-thang) · [hoa_don](#bang-hoa-don) · [hoa_don_chi_tiet](#bang-hoa-don-chi-tiet) · [thanh_toan](#bang-thanh-toan) · [thong_bao](#bang-thong-bao) · [audit_log](#bang-audit-log) · [schema_migrations](#bang-schema-migrations) · [yeu_cau_nghi_lop](#bang-yeu-cau-nghi-lop) · [hoc_bu_hoc_vien](#bang-hoc-bu-hoc-vien) · [giao_dich_truc_tuyen](#bang-giao-dich-truc-tuyen) · [su_kien_thanh_toan](#bang-su-kien-thanh-toan)

<a id="bang-users"></a>
### 13.1. users

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `user_id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `hoten` | `VARCHAR(150) NOT NULL` |
| `sdt` | `VARCHAR(20) NULL` |
| `email` | `VARCHAR(150) NOT NULL` |
| `ngaysinh` | `DATE NULL` |
| `diachi` | `VARCHAR(255) NULL` |
| `nghe_nghiep` | `VARCHAR(100) NULL` |
| `khu_vuc_id` | `INT UNSIGNED NULL` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `is_deleted` | `TINYINT(1) NOT NULL DEFAULT 0` |
| `updated_at` | `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP` |

Khóa và ràng buộc:

- `PRIMARY KEY (user_id)`
- `UNIQUE KEY uq_users_email (email)`
- `UNIQUE KEY uq_users_sdt (sdt)`

<a id="bang-role-group"></a>
### 13.2. role_group

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `group_id` | `INT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `group_name` | `VARCHAR(100) NOT NULL` |
| `description` | `VARCHAR(255) NULL` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `is_deleted` | `TINYINT(1) NOT NULL DEFAULT 0` |

Khóa và ràng buộc:

- `PRIMARY KEY (group_id)`
- `UNIQUE KEY uq_group_name (group_name)`

<a id="bang-account"></a>
### 13.3. account

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `account_id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `user_id` | `BIGINT UNSIGNED NOT NULL` |
| `group_id` | `INT UNSIGNED NOT NULL` |
| `username` | `VARCHAR(100) NOT NULL` |
| `password_hash` | `VARCHAR(255) NOT NULL` |
| `status` | `ENUM('active','locked','pending') NOT NULL DEFAULT 'active'` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `is_deleted` | `TINYINT(1) NOT NULL DEFAULT 0` |
| `updated_at` | `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP` |

Khóa và ràng buộc:

- `PRIMARY KEY (account_id)`
- `UNIQUE KEY uq_account_user (user_id)`
- `UNIQUE KEY uq_account_username (username)`
- `KEY idx_acc_group (group_id)`
- `CONSTRAINT fk_account_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE`
- `CONSTRAINT fk_account_group FOREIGN KEY (group_id) REFERENCES role_group(group_id)`

<a id="bang-function"></a>
### 13.4. function

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `function_id` | `INT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `name_function` | `VARCHAR(100) NOT NULL` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `is_deleted` | `TINYINT(1) NOT NULL DEFAULT 0` |

Khóa và ràng buộc:

- `PRIMARY KEY (function_id)`
- `UNIQUE KEY uq_func_name (name_function)`

<a id="bang-role-group-function"></a>
### 13.5. role_group_function

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `group_id` | `INT UNSIGNED NOT NULL` |
| `function_id` | `INT UNSIGNED NOT NULL` |
| `add` | `TINYINT(1) NOT NULL DEFAULT 0` |
| `edit` | `TINYINT(1) NOT NULL DEFAULT 0` |
| `delete` | `TINYINT(1) NOT NULL DEFAULT 0` |
| `download` | `TINYINT(1) NOT NULL DEFAULT 0` |
| `view` | `TINYINT(1) NOT NULL DEFAULT 1` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `is_deleted` | `TINYINT(1) NOT NULL DEFAULT 0` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_rgf (group_id, function_id)`
- `CONSTRAINT fk_rgf_group FOREIGN KEY (group_id) REFERENCES role_group(group_id) ON DELETE CASCADE`
- ``CONSTRAINT fk_rgf_function FOREIGN KEY (function_id) REFERENCES `function`(function_id) ON DELETE CASCADE``

<a id="bang-mon-hoc"></a>
### 13.6. mon_hoc

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `INT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `ma` | `VARCHAR(20) NOT NULL` |
| `ten` | `VARCHAR(100) NOT NULL` |
| `is_active` | `TINYINT(1) NOT NULL DEFAULT 1` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_mon_ma (ma)`
- `UNIQUE KEY uq_mon_ten (ten)`

<a id="bang-cap-lop"></a>
### 13.7. cap_lop

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `TINYINT UNSIGNED NOT NULL` |
| `ten` | `VARCHAR(30) NOT NULL` |
| `cap_hoc` | `ENUM('tieu_hoc','thcs','thpt') NOT NULL` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_caplop_ten (ten)`

<a id="bang-khu-vuc"></a>
### 13.8. khu_vuc

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `INT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `ten` | `VARCHAR(100) NOT NULL` |
| `thanh_pho` | `VARCHAR(100) NOT NULL DEFAULT 'TP. Hồ Chí Minh'` |
| `is_active` | `TINYINT(1) NOT NULL DEFAULT 1` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_kv (ten, thanh_pho)`

<a id="bang-co-so"></a>
### 13.9. co_so

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `INT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `ma_co_so` | `VARCHAR(20) NOT NULL` |
| `ten_co_so` | `VARCHAR(150) NOT NULL` |
| `dia_chi` | `VARCHAR(255) NOT NULL` |
| `so_dien_thoai` | `VARCHAR(20) NULL` |
| `email` | `VARCHAR(150) NULL` |
| `khu_vuc_id` | `INT UNSIGNED NULL` |
| `quan_ly_id` | `BIGINT UNSIGNED NULL` |
| `is_active` | `TINYINT(1) NOT NULL DEFAULT 1` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_coso_ma (ma_co_so)`
- `CONSTRAINT fk_coso_kv FOREIGN KEY (khu_vuc_id) REFERENCES khu_vuc(id) ON DELETE SET NULL`
- `CONSTRAINT fk_coso_ql FOREIGN KEY (quan_ly_id) REFERENCES users(user_id) ON DELETE SET NULL`

<a id="bang-cau-hinh"></a>
### 13.10. cau_hinh

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `khoa` | `VARCHAR(60) NOT NULL` |
| `gia_tri` | `VARCHAR(255) NOT NULL` |
| `mo_ta` | `VARCHAR(255) NULL` |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |

Khóa và ràng buộc:

- `PRIMARY KEY (khoa)`

<a id="bang-nhan-vien"></a>
### 13.11. nhan_vien

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `user_id` | `BIGINT UNSIGNED NOT NULL` |
| `ma_nv` | `VARCHAR(20) NOT NULL` |
| `chuc_vu` | `VARCHAR(100) NULL` |
| `co_so_id` | `INT UNSIGNED NULL` |
| `ngay_vao_lam` | `DATE NULL` |
| `trang_thai` | `ENUM('dang_lam','nghi_viec') NOT NULL DEFAULT 'dang_lam'` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_nv_user (user_id)`
- `UNIQUE KEY uq_nv_ma (ma_nv)`
- `CONSTRAINT fk_nv_user FOREIGN KEY (user_id) REFERENCES users(user_id)`
- `CONSTRAINT fk_nv_coso FOREIGN KEY (co_so_id) REFERENCES co_so(id) ON DELETE SET NULL`

<a id="bang-nhan-su-chuyen-mon"></a>
### 13.12. nhan_su_chuyen_mon

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `user_id` | `BIGINT UNSIGNED NOT NULL` |
| `ma_ns` | `VARCHAR(20) NOT NULL` |
| `phan_loai` | `SET('giao_vien','gia_su','tro_giang') NOT NULL DEFAULT 'gia_su'` |
| `gioi_tinh` | `ENUM('nam','nu','khac') NULL` |
| `cccd` | `VARCHAR(20) NULL` |
| `nghe_nghiep` | `ENUM('sinh_vien','giao_vien','cu_nhan','tu_do') NOT NULL DEFAULT 'sinh_vien'` |
| `don_vi_cong_tac` | `VARCHAR(150) NULL` |
| `gioi_thieu` | `TEXT NULL` |
| `kinh_nghiem_nam` | `TINYINT UNSIGNED NOT NULL DEFAULT 0` |
| `hinh_thuc_day` | `SET('online','offline') NOT NULL DEFAULT 'offline'` |
| `don_gia_gio_chuan` | `DECIMAL(12,0) NOT NULL DEFAULT 0` |
| `ngan_hang` | `VARCHAR(100) NULL` |
| `so_tai_khoan` | `VARCHAR(30) NULL` |
| `chu_tai_khoan` | `VARCHAR(150) NULL` |
| `trang_thai` | `ENUM('hoat_dong','tam_nghi','ngung_hop_tac') NOT NULL DEFAULT 'hoat_dong'` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |
| `deleted_at` | `DATETIME NULL` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_ns_user (user_id)`
- `UNIQUE KEY uq_ns_ma (ma_ns)`
- `UNIQUE KEY uq_ns_cccd (cccd)`
- `KEY idx_ns_tt (trang_thai)`
- `CONSTRAINT fk_ns_user FOREIGN KEY (user_id) REFERENCES users(user_id)`
- `CONSTRAINT ck_ns_don_gia CHECK (don_gia_gio_chuan >= 0)`

<a id="bang-hoc-vien"></a>
### 13.13. hoc_vien

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `phu_huynh_user_id` | `BIGINT UNSIGNED NOT NULL` |
| `ma_hv` | `VARCHAR(20) NOT NULL` |
| `ho_ten` | `VARCHAR(150) NOT NULL` |
| `ngay_sinh` | `DATE NULL` |
| `gioi_tinh` | `ENUM('nam','nu','khac') NULL` |
| `truong` | `VARCHAR(150) NULL` |
| `cap_lop_id` | `TINYINT UNSIGNED NULL` |
| `dia_chi` | `VARCHAR(255) NULL` |
| `khu_vuc_id` | `INT UNSIGNED NULL` |
| `ghi_chu` | `TEXT NULL` |
| `trang_thai` | `ENUM('dang_hoc','tam_nghi','hoan_thanh','nghi_han') NOT NULL DEFAULT 'dang_hoc'` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |
| `deleted_at` | `DATETIME NULL` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_hv_ma (ma_hv)`
- `KEY idx_hv_ph (phu_huynh_user_id)`
- `KEY idx_hv_ten (ho_ten)`
- `KEY idx_hv_tt (trang_thai)`
- `CONSTRAINT fk_hv_ph FOREIGN KEY (phu_huynh_user_id) REFERENCES users(user_id)`
- `CONSTRAINT fk_hv_cap FOREIGN KEY (cap_lop_id) REFERENCES cap_lop(id) ON DELETE SET NULL`
- `CONSTRAINT fk_hv_kv FOREIGN KEY (khu_vuc_id) REFERENCES khu_vuc(id) ON DELETE SET NULL`

<a id="bang-nhan-su-mon"></a>
### 13.14. nhan_su_mon

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `nhan_su_id` | `BIGINT UNSIGNED NOT NULL` |
| `mon_hoc_id` | `INT UNSIGNED NOT NULL` |
| `cap_lop_tu` | `TINYINT UNSIGNED NOT NULL` |
| `cap_lop_den` | `TINYINT UNSIGNED NOT NULL` |
| `hoc_phi_buoi_de_xuat` | `DECIMAL(12,0) NOT NULL` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_nsm (nhan_su_id, mon_hoc_id, cap_lop_tu)`
- `KEY idx_nsm_tim (mon_hoc_id, cap_lop_tu, cap_lop_den, hoc_phi_buoi_de_xuat)`
- `CONSTRAINT fk_nsm_ns FOREIGN KEY (nhan_su_id) REFERENCES nhan_su_chuyen_mon(id) ON DELETE CASCADE`
- `CONSTRAINT fk_nsm_mon FOREIGN KEY (mon_hoc_id) REFERENCES mon_hoc(id)`
- `CONSTRAINT fk_nsm_tu FOREIGN KEY (cap_lop_tu) REFERENCES cap_lop(id)`
- `CONSTRAINT fk_nsm_den FOREIGN KEY (cap_lop_den) REFERENCES cap_lop(id)`
- `CONSTRAINT ck_nsm_lop CHECK (cap_lop_tu <= cap_lop_den)`
- `CONSTRAINT ck_nsm_phi CHECK (hoc_phi_buoi_de_xuat >= 0)`

<a id="bang-nhan-su-khu-vuc"></a>
### 13.15. nhan_su_khu_vuc

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `nhan_su_id` | `BIGINT UNSIGNED NOT NULL` |
| `khu_vuc_id` | `INT UNSIGNED NOT NULL` |

Khóa và ràng buộc:

- `PRIMARY KEY (nhan_su_id, khu_vuc_id)`
- `KEY idx_nskv_kv (khu_vuc_id)`
- `CONSTRAINT fk_nskv_ns FOREIGN KEY (nhan_su_id) REFERENCES nhan_su_chuyen_mon(id) ON DELETE CASCADE`
- `CONSTRAINT fk_nskv_kv FOREIGN KEY (khu_vuc_id) REFERENCES khu_vuc(id)`

<a id="bang-nhan-su-lich-ranh"></a>
### 13.16. nhan_su_lich_ranh

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `nhan_su_id` | `BIGINT UNSIGNED NOT NULL` |
| `thu` | `TINYINT UNSIGNED NOT NULL` |
| `gio_bat_dau` | `TIME NOT NULL` |
| `gio_ket_thuc` | `TIME NOT NULL` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `KEY idx_nslr (nhan_su_id, thu)`
- `CONSTRAINT fk_nslr_ns FOREIGN KEY (nhan_su_id) REFERENCES nhan_su_chuyen_mon(id) ON DELETE CASCADE`
- `CONSTRAINT ck_nslr_thu CHECK (thu BETWEEN 2 AND 8)`
- `CONSTRAINT ck_nslr_gio CHECK (gio_bat_dau < gio_ket_thuc)`

<a id="bang-yeu-cau-hoc"></a>
### 13.17. yeu_cau_hoc

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `ma_yc` | `VARCHAR(20) NOT NULL` |
| `hoc_vien_id` | `BIGINT UNSIGNED NULL` |
| `ho_ten_lien_he` | `VARCHAR(150) NULL` |
| `sdt_lien_he` | `VARCHAR(20) NULL` |
| `email_lien_he` | `VARCHAR(150) NULL` |
| `mon_hoc_id` | `INT UNSIGNED NOT NULL` |
| `cap_lop_id` | `TINYINT UNSIGNED NOT NULL` |
| `loai_lop_mong_muon` | `ENUM('kem_1_1','kem_1_2','nhom') NOT NULL DEFAULT 'kem_1_1'` |
| `co_so_mong_muon_id` | `INT UNSIGNED NULL` |
| `hinh_thuc` | `ENUM('online','offline','ca_hai') NOT NULL DEFAULT 'offline'` |
| `khu_vuc_id` | `INT UNSIGNED NULL` |
| `dia_chi_hoc` | `VARCHAR(255) NULL` |
| `ngan_sach_min` | `DECIMAL(12,0) NULL` |
| `ngan_sach_max` | `DECIMAL(12,0) NULL` |
| `muc_tieu` | `TEXT NULL` |
| `ghi_chu` | `TEXT NULL` |
| `nguon` | `ENUM('website','dien_thoai','truc_tiep','gioi_thieu') NOT NULL DEFAULT 'truc_tiep'` |
| `trang_thai` | `ENUM('moi','dang_tim','da_de_xuat','da_chot','huy') NOT NULL DEFAULT 'moi'` |
| `nhan_vien_id` | `BIGINT UNSIGNED NULL` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |
| `nguoi_gui_id` | `BIGINT UNSIGNED NULL` |
| `don_vi_ngan_sach` | `ENUM('theo_buoi','theo_gio','theo_thang','theo_khoa') NULL` |

Khóa và ràng buộc:

- `CONSTRAINT fk_yc_gui FOREIGN KEY (nguoi_gui_id) REFERENCES users(user_id)`
- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_yc_ma (ma_yc)`
- `KEY idx_yc_tt (trang_thai, created_at)`
- `KEY idx_yc_hv (hoc_vien_id)`
- `KEY idx_yc_mon (mon_hoc_id, cap_lop_id)`
- `CONSTRAINT fk_yc_hv FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id) ON DELETE SET NULL`
- `CONSTRAINT fk_yc_mon FOREIGN KEY (mon_hoc_id) REFERENCES mon_hoc(id)`
- `CONSTRAINT fk_yc_cap FOREIGN KEY (cap_lop_id) REFERENCES cap_lop(id)`
- `CONSTRAINT fk_yc_coso FOREIGN KEY (co_so_mong_muon_id) REFERENCES co_so(id) ON DELETE SET NULL`
- `CONSTRAINT fk_yc_kv FOREIGN KEY (khu_vuc_id) REFERENCES khu_vuc(id) ON DELETE SET NULL`
- `CONSTRAINT fk_yc_nv FOREIGN KEY (nhan_vien_id) REFERENCES nhan_vien(id)`
- `CONSTRAINT ck_yc_ns CHECK (ngan_sach_min IS NULL OR ngan_sach_max IS NULL OR ngan_sach_min <= ngan_sach_max)`
- `CONSTRAINT ck_yc_ns_duong CHECK ((ngan_sach_min IS NULL OR ngan_sach_min >= 0) AND (ngan_sach_max IS NULL OR ngan_sach_max >= 0))`

<a id="bang-yeu-cau-lich"></a>
### 13.18. yeu_cau_lich

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `yeu_cau_id` | `BIGINT UNSIGNED NOT NULL` |
| `thu` | `TINYINT UNSIGNED NOT NULL` |
| `gio_bat_dau` | `TIME NOT NULL` |
| `gio_ket_thuc` | `TIME NOT NULL` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `KEY idx_ycl (yeu_cau_id)`
- `CONSTRAINT fk_ycl_yc FOREIGN KEY (yeu_cau_id) REFERENCES yeu_cau_hoc(id) ON DELETE CASCADE`
- `CONSTRAINT ck_ycl_thu CHECK (thu BETWEEN 2 AND 8)`
- `CONSTRAINT ck_ycl_gio CHECK (gio_bat_dau < gio_ket_thuc)`

<a id="bang-de-xuat-nhan-su"></a>
### 13.19. de_xuat_nhan_su

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `yeu_cau_id` | `BIGINT UNSIGNED NOT NULL` |
| `nhan_su_id` | `BIGINT UNSIGNED NOT NULL` |
| `nhan_vien_id` | `BIGINT UNSIGNED NOT NULL` |
| `hoc_phi_de_xuat` | `DECIMAL(12,0) NULL` |
| `tt_nhan_su` | `ENUM('cho','dong_y','tu_choi') NOT NULL DEFAULT 'cho'` |
| `tt_phu_huynh` | `ENUM('cho','dong_y','tu_choi') NOT NULL DEFAULT 'cho'` |
| `ghi_chu` | `VARCHAR(255) NULL` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |
| `cach_thu_phi_de_xuat` | `ENUM('theo_buoi','theo_thang','theo_khoa') NULL` |
| `nhan_su_phan_hoi_luc` | `DATETIME NULL` |
| `phu_huynh_phan_hoi_luc` | `DATETIME NULL` |
| `phu_huynh_phan_hoi_boi` | `BIGINT UNSIGNED NULL` |
| `ly_do_tu_choi_nhan_su` | `VARCHAR(255) NULL` |
| `ly_do_tu_choi_phu_huynh` | `VARCHAR(255) NULL` |

Khóa và ràng buộc:

- `CONSTRAINT fk_dx_ph_phan_hoi FOREIGN KEY (phu_huynh_phan_hoi_boi) REFERENCES users(user_id)`
- `CONSTRAINT ck_dx_ly_do CHECK ((ly_do_tu_choi_nhan_su IS NULL OR tt_nhan_su='tu_choi') AND (ly_do_tu_choi_phu_huynh IS NULL OR tt_phu_huynh='tu_choi'))`
- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_dx (yeu_cau_id, nhan_su_id)`
- `KEY idx_dx_ns (nhan_su_id, tt_nhan_su)`
- `CONSTRAINT fk_dx_yc FOREIGN KEY (yeu_cau_id) REFERENCES yeu_cau_hoc(id) ON DELETE CASCADE`
- `CONSTRAINT fk_dx_ns FOREIGN KEY (nhan_su_id) REFERENCES nhan_su_chuyen_mon(id)`
- `CONSTRAINT fk_dx_nv FOREIGN KEY (nhan_vien_id) REFERENCES nhan_vien(id)`
- `CONSTRAINT ck_dx_phi CHECK (hoc_phi_de_xuat IS NULL OR hoc_phi_de_xuat >= 0)`

<a id="bang-khoa-hoc"></a>
### 13.20. khoa_hoc

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `ma` | `VARCHAR(40) NOT NULL` |
| `ten` | `VARCHAR(150) NOT NULL` |
| `mo_ta` | `TEXT NULL` |
| `hoc_phi` | `DECIMAL(12,0) NOT NULL` |
| `is_active` | `TINYINT(1) NOT NULL DEFAULT 1` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_khoa_ma (ma)`
- `CONSTRAINT ck_khoa_phi CHECK (hoc_phi>=0)`

<a id="bang-dang-ky-khoa-hoc"></a>
### 13.21. dang_ky_khoa_hoc

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `khoa_hoc_id` | `BIGINT UNSIGNED NOT NULL` |
| `hoc_vien_id` | `BIGINT UNSIGNED NOT NULL` |
| `hoc_phi_snapshot` | `DECIMAL(12,0) NOT NULL DEFAULT 0` |
| `trang_thai` | `ENUM('cho_xep_lop','dang_hoc','hoan_thanh','thoi_hoc') NOT NULL DEFAULT 'cho_xep_lop'` |
| `ngay_dang_ky` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `KEY idx_dkk_hv (hoc_vien_id,khoa_hoc_id)`
- `CONSTRAINT fk_dkk_khoa FOREIGN KEY (khoa_hoc_id) REFERENCES khoa_hoc(id)`
- `CONSTRAINT fk_dkk_hv FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id)`
- `CONSTRAINT ck_dkk_phi CHECK (hoc_phi_snapshot>=0)`

<a id="bang-lich-su-don-gia-gio"></a>
### 13.22. lich_su_don_gia_gio

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `nhan_su_id` | `BIGINT UNSIGNED NOT NULL` |
| `don_gia_gio` | `DECIMAL(12,0) NOT NULL` |
| `hieu_luc_tu` | `DATETIME(6) NOT NULL` |
| `nguoi_dat_id` | `BIGINT UNSIGNED NOT NULL` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_dgg_hieu_luc (nhan_su_id,hieu_luc_tu)`
- `CONSTRAINT fk_dgg_ns FOREIGN KEY (nhan_su_id) REFERENCES nhan_su_chuyen_mon(id)`
- `CONSTRAINT fk_dgg_admin FOREIGN KEY (nguoi_dat_id) REFERENCES users(user_id)`
- `CONSTRAINT ck_dgg_gia CHECK (don_gia_gio>=0)`

<a id="bang-lop-hoc"></a>
### 13.23. lop_hoc

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `khoa_hoc_id` | `BIGINT UNSIGNED NULL` |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `ma_lop` | `VARCHAR(30) NOT NULL` |
| `ten_lop` | `VARCHAR(150) NOT NULL` |
| `loai_lop` | `ENUM('kem_1_1','kem_1_2','nhom') NOT NULL DEFAULT 'kem_1_1'` |
| `de_xuat_id` | `BIGINT UNSIGNED NULL` |
| `mon_hoc_id` | `INT UNSIGNED NOT NULL` |
| `cap_lop_id` | `TINYINT UNSIGNED NOT NULL` |
| `nguoi_day_chinh_id` | `BIGINT UNSIGNED NOT NULL` |
| `co_so_id` | `INT UNSIGNED NULL` |
| `phong_hoc` | `VARCHAR(50) NULL` |
| `hinh_thuc` | `ENUM('online','offline') NOT NULL DEFAULT 'offline'` |
| `dia_diem` | `VARCHAR(255) NULL` |
| `link_hoc` | `VARCHAR(255) NULL` |
| `khu_vuc_id` | `INT UNSIGNED NULL` |
| `si_so_toi_thieu` | `TINYINT UNSIGNED NOT NULL DEFAULT 1` |
| `si_so_toi_da` | `TINYINT UNSIGNED NOT NULL DEFAULT 1` |
| `cach_thu_phi` | `ENUM('theo_buoi','theo_thang','theo_khoa') NOT NULL DEFAULT 'theo_buoi'` |
| `hoc_phi_buoi` | `DECIMAL(12,0) NOT NULL DEFAULT 0` |
| `hoc_phi_khoa` | `DECIMAL(12,0) NOT NULL DEFAULT 0` |
| `muc_tra_chinh` | `DECIMAL(12,0) NOT NULL` |
| `muc_tra_tro_giang` | `DECIMAL(12,0) NOT NULL DEFAULT 0` |
| `cach_tinh_luong` | `ENUM('theo_buoi','theo_gio') NOT NULL DEFAULT 'theo_buoi'` |
| `so_buoi_du_kien` | `SMALLINT UNSIGNED NULL` |
| `ngay_bat_dau` | `DATE NOT NULL` |
| `ngay_ket_thuc` | `DATE NULL` |
| `trang_thai` | `ENUM('sap_bat_dau','dang_hoc','tam_dung','hoan_thanh','huy') NOT NULL DEFAULT 'sap_bat_dau'` |
| `nhan_vien_id` | `BIGINT UNSIGNED NOT NULL` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |
| `deleted_at` | `DATETIME NULL` |
| `hoc_phi_thang` | `DECIMAL(12,0) NULL` |

Khóa và ràng buộc:

- `CONSTRAINT fk_lop_khoa FOREIGN KEY (khoa_hoc_id) REFERENCES khoa_hoc(id)`
- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_lop_ma (ma_lop)`
- `KEY idx_lop_ndc (nguoi_day_chinh_id, trang_thai)`
- `KEY idx_lop_tt (trang_thai)`
- `KEY idx_lop_loai (loai_lop)`
- `CONSTRAINT fk_lop_dx FOREIGN KEY (de_xuat_id) REFERENCES de_xuat_nhan_su(id) ON DELETE SET NULL`
- `CONSTRAINT fk_lop_mon FOREIGN KEY (mon_hoc_id) REFERENCES mon_hoc(id)`
- `CONSTRAINT fk_lop_cap FOREIGN KEY (cap_lop_id) REFERENCES cap_lop(id)`
- `CONSTRAINT fk_lop_ndc FOREIGN KEY (nguoi_day_chinh_id) REFERENCES nhan_su_chuyen_mon(id)`
- `CONSTRAINT fk_lop_coso FOREIGN KEY (co_so_id) REFERENCES co_so(id) ON DELETE SET NULL`
- `CONSTRAINT fk_lop_kv FOREIGN KEY (khu_vuc_id) REFERENCES khu_vuc(id) ON DELETE SET NULL`
- `CONSTRAINT fk_lop_nv FOREIGN KEY (nhan_vien_id) REFERENCES nhan_vien(id)`
- `CONSTRAINT ck_lop_ngay CHECK (ngay_ket_thuc IS NULL OR ngay_ket_thuc >= ngay_bat_dau)`
- `CONSTRAINT ck_lop_phi CHECK (hoc_phi_buoi >= 0 AND hoc_phi_khoa >= 0 AND muc_tra_chinh >= 0 AND muc_tra_tro_giang >= 0)`
- `CONSTRAINT ck_lop_siso CHECK (si_so_toi_da >= si_so_toi_thieu AND si_so_toi_thieu >= 1)`
- `CONSTRAINT ck_lop_phi_thang CHECK (hoc_phi_thang IS NULL OR hoc_phi_thang >= 0)`
- `CONSTRAINT ck_lop_so_buoi CHECK (so_buoi_du_kien IS NULL OR so_buoi_du_kien > 0)`

<a id="bang-lop-hoc-vien"></a>
### 13.24. lop_hoc_vien

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `xep_boi` | `BIGINT UNSIGNED NULL` |
| `xep_luc` | `DATETIME NULL` |
| `dang_ky_khoa_hoc_id` | `BIGINT UNSIGNED NULL` |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `lop_id` | `BIGINT UNSIGNED NOT NULL` |
| `hoc_vien_id` | `BIGINT UNSIGNED NOT NULL` |
| `ngay_vao` | `DATE NOT NULL` |
| `ngay_roi` | `DATE NULL` |
| `trang_thai` | `ENUM('dang_hoc','tam_nghi','da_roi','cho_xep_lop','hoc_thu') NOT NULL DEFAULT 'dang_hoc'` |
| `created_at` | `DATETIME NULL DEFAULT CURRENT_TIMESTAMP` |
| `updated_at` | `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP` |

Khóa và ràng buộc:

- `CONSTRAINT fk_lhv_admin FOREIGN KEY (xep_boi) REFERENCES users(user_id)`
- `CONSTRAINT ck_lhv_xep CHECK ((xep_boi IS NULL)=(xep_luc IS NULL))`
- `CONSTRAINT fk_lhv_dkk FOREIGN KEY (dang_ky_khoa_hoc_id) REFERENCES dang_ky_khoa_hoc(id)`
- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_lhv (lop_id, hoc_vien_id)`
- `KEY idx_lhv_hv (hoc_vien_id)`
- `CONSTRAINT fk_lhv_lop FOREIGN KEY (lop_id) REFERENCES lop_hoc(id)`
- `CONSTRAINT fk_lhv_hv FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id)`
- `CONSTRAINT ck_lhv_ngay CHECK (ngay_roi IS NULL OR ngay_roi >= ngay_vao)`

<a id="bang-lich-hoc"></a>
### 13.25. lich_hoc

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `lop_id` | `BIGINT UNSIGNED NOT NULL` |
| `thu` | `TINYINT UNSIGNED NOT NULL` |
| `gio_bat_dau` | `TIME NOT NULL` |
| `gio_ket_thuc` | `TIME NOT NULL` |
| `phong_hoc` | `VARCHAR(50) NULL` |
| `hieu_luc_tu` | `DATE NOT NULL` |
| `hieu_luc_den` | `DATE NULL` |
| `is_active` | `TINYINT(1) NOT NULL DEFAULT 1` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_lich (lop_id, thu, gio_bat_dau, hieu_luc_tu)`
- `CONSTRAINT fk_lich_lop FOREIGN KEY (lop_id) REFERENCES lop_hoc(id)`
- `CONSTRAINT ck_lich_thu CHECK (thu BETWEEN 2 AND 8)`
- `CONSTRAINT ck_lich_gio CHECK (gio_bat_dau < gio_ket_thuc)`
- `CONSTRAINT ck_lich_hieu_luc CHECK (hieu_luc_den IS NULL OR hieu_luc_den >= hieu_luc_tu)`

<a id="bang-buoi-hoc"></a>
### 13.26. buoi_hoc

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `pham_vi_bu` | `ENUM('ca_lop','ca_nhan') NOT NULL DEFAULT 'ca_lop'` |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `lop_id` | `BIGINT UNSIGNED NOT NULL` |
| `lich_hoc_id` | `BIGINT UNSIGNED NULL` |
| `loai` | `ENUM('thuong','bu') NOT NULL DEFAULT 'thuong'` |
| `buoi_goc_id` | `BIGINT UNSIGNED NULL` |
| `ngay` | `DATE NOT NULL` |
| `gio_bat_dau` | `TIME NOT NULL` |
| `gio_ket_thuc` | `TIME NOT NULL` |
| `phong_hoc` | `VARCHAR(50) NULL` |
| `chu_de_bai_hoc` | `VARCHAR(255) NULL` |
| `noi_dung_bai_hoc` | `TEXT NULL` |
| `file_bai_giang_url` | `VARCHAR(255) NULL` |
| `trang_thai` | `ENUM('du_kien','dang_day','da_day','huy','doi') NOT NULL DEFAULT 'du_kien'` |
| `huy_boi` | `ENUM('trung_tam','giao_vien','gia_su','hoc_vien','khac') NULL` |
| `ly_do_huy_doi` | `VARCHAR(255) NULL` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |
| `nguoi_huy_id` | `BIGINT UNSIGNED NULL` |
| `huy_luc` | `DATETIME NULL` |
| `co_so_id` | `INT UNSIGNED NULL` |

Khóa và ràng buộc:

- `CONSTRAINT ck_buoi_pham_vi CHECK (loai='bu' OR pham_vi_bu='ca_lop')`
- `CONSTRAINT fk_buoi_nguoi_huy FOREIGN KEY (nguoi_huy_id) REFERENCES users(user_id)`
- `CONSTRAINT ck_buoi_huy_meta CHECK ((nguoi_huy_id IS NULL) = (huy_luc IS NULL))`
- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_buoi (lop_id, ngay, gio_bat_dau)`
- `KEY idx_buoi_ngay (ngay, trang_thai)`
- `KEY idx_buoi_goc (buoi_goc_id)`
- `CONSTRAINT fk_buoi_lop FOREIGN KEY (lop_id) REFERENCES lop_hoc(id)`
- `CONSTRAINT fk_buoi_lich FOREIGN KEY (lich_hoc_id) REFERENCES lich_hoc(id) ON DELETE SET NULL`
- `CONSTRAINT fk_buoi_goc FOREIGN KEY (buoi_goc_id) REFERENCES buoi_hoc(id)`
- `CONSTRAINT ck_buoi_gio CHECK (gio_bat_dau < gio_ket_thuc)`
- `CONSTRAINT ck_buoi_bu CHECK (loai = 'thuong' OR buoi_goc_id IS NOT NULL)`
- `CONSTRAINT fk_buoi_coso FOREIGN KEY (co_so_id) REFERENCES co_so(id)`
- `KEY idx_buoi_phong (co_so_id, phong_hoc, ngay, trang_thai)`

<a id="bang-phan-cong-buoi-hoc"></a>
### 13.27. phan_cong_buoi_hoc

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `buoi_hoc_id` | `BIGINT UNSIGNED NOT NULL` |
| `user_id` | `BIGINT UNSIGNED NOT NULL` |
| `vai_tro` | `ENUM('day_chinh','tro_giang') NOT NULL` |
| `trang_thai` | `ENUM('da_phan_cong','da_go','da_day','vang') NOT NULL DEFAULT 'da_phan_cong'` |
| `don_gia` | `DECIMAL(12,0) NOT NULL DEFAULT 0` |
| `bang_cong_id` | `BIGINT UNSIGNED NULL` |
| `phan_cong_boi` | `BIGINT UNSIGNED NULL` |
| `go_boi` | `BIGINT UNSIGNED NULL` |
| `go_luc` | `DATETIME NULL` |
| `ly_do_go` | `VARCHAR(255) NULL` |
| `checkin_luc` | `DATETIME NULL` |
| `checkin_lat` | `DECIMAL(10,7) NULL` |
| `checkin_lng` | `DECIMAL(10,7) NULL` |
| `checkin_tre` | `TINYINT(1) NOT NULL DEFAULT 0` |
| `checkin_ghi_chu` | `VARCHAR(255) NULL` |
| `checkout_luc` | `DATETIME NULL` |
| `so_phut_thuc_te` | `INT GENERATED ALWAYS AS ( IF(checkin_luc IS NULL OR checkout_luc IS NULL, NULL, TIMESTAMPDIFF(MINUTE, checkin_luc, checkout_luc)) ) STORED` |
| `trang_thai_cong` | `ENUM('chua_check','hop_le','cho_duyet','tu_choi') NOT NULL DEFAULT 'chua_check'` |
| `day_chinh_key` | `BIGINT UNSIGNED GENERATED ALWAYS AS ( IF(vai_tro = 'day_chinh' AND trang_thai <> 'da_go', buoi_hoc_id, NULL) ) VIRTUAL` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |
| `cach_tinh_luong` | `ENUM('theo_buoi','theo_gio') NULL` |
| `quy_tac_cong` | `TINYINT UNSIGNED NOT NULL DEFAULT 4` |
| `gio_lich_bat_dau` | `DATETIME NULL` |
| `gio_lich_ket_thuc` | `DATETIME NULL` |
| `so_phut_tinh_cong` | `INT GENERATED ALWAYS AS (IF(quy_tac_cong=3,so_phut_thuc_te, IF(checkin_luc IS NULL OR checkout_luc IS NULL OR gio_lich_bat_dau IS NULL OR gio_lich_ket_thuc IS NULL,NULL, GREATEST(0,TIMESTAMPDIFF(MINUTE,GREATEST(checkin_luc,gio_lich_bat_dau),LEAST(checkout_luc,gio_lich_ket_thuc)))))) STORED` |
| `luong_sua_boi` | `BIGINT UNSIGNED NULL` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_pc_buoi_user (buoi_hoc_id, user_id)`
- `UNIQUE KEY uq_pc_day_chinh (day_chinh_key)`
- `KEY idx_pc_user (user_id, trang_thai)`
- `KEY idx_pc_cong (user_id, trang_thai_cong)`
- `CONSTRAINT fk_pc_buoi FOREIGN KEY (buoi_hoc_id) REFERENCES buoi_hoc(id)`
- `CONSTRAINT fk_pc_user FOREIGN KEY (user_id) REFERENCES users(user_id)`
- `CONSTRAINT fk_pc_pcb FOREIGN KEY (phan_cong_boi) REFERENCES users(user_id) ON DELETE SET NULL`
- `CONSTRAINT fk_pc_gob FOREIGN KEY (go_boi) REFERENCES users(user_id) ON DELETE SET NULL`
- `CONSTRAINT ck_pc_out CHECK (checkout_luc IS NULL OR (checkin_luc IS NOT NULL AND checkout_luc > checkin_luc))`
- `CONSTRAINT ck_pc_don_gia CHECK (don_gia >= 0)`
- `CONSTRAINT ck_pc_hop_le CHECK (trang_thai_cong <> 'hop_le' OR (checkin_luc IS NOT NULL AND checkout_luc IS NOT NULL))`
- `CONSTRAINT ck_pc_gps CHECK ((checkin_lat IS NULL AND checkin_lng IS NULL) OR (checkin_lat IS NOT NULL AND checkin_lng IS NOT NULL AND checkin_lat BETWEEN -90 AND 90 AND checkin_lng BETWEEN -180 AND 180))`
- `CONSTRAINT fk_pc_luong_sua FOREIGN KEY (luong_sua_boi) REFERENCES users(user_id)`
- `CONSTRAINT ck_pc_quy_tac CHECK (quy_tac_cong IN (3,4))`

<a id="bang-dieu-chinh-cong"></a>
### 13.28. dieu_chinh_cong

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `phan_cong_id` | `BIGINT UNSIGNED NOT NULL` |
| `loai` | `ENUM('bo_sung_checkin','sua_gio','huy_cong','tinh_cong') NOT NULL` |
| `checkin_cu` | `DATETIME NULL` |
| `checkout_cu` | `DATETIME NULL` |
| `checkin_moi` | `DATETIME NULL` |
| `checkout_moi` | `DATETIME NULL` |
| `ly_do` | `VARCHAR(255) NOT NULL` |
| `nguoi_yeu_cau_id` | `BIGINT UNSIGNED NOT NULL` |
| `trang_thai` | `ENUM('cho_duyet','da_duyet','tu_choi') NOT NULL DEFAULT 'cho_duyet'` |
| `nguoi_duyet_id` | `BIGINT UNSIGNED NULL` |
| `duyet_luc` | `DATETIME NULL` |
| `ghi_chu_duyet` | `VARCHAR(255) NULL` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `KEY idx_dcc_pc (phan_cong_id)`
- `KEY idx_dcc_tt (trang_thai, created_at)`
- `CONSTRAINT fk_dcc_pc FOREIGN KEY (phan_cong_id) REFERENCES phan_cong_buoi_hoc(id)`
- `CONSTRAINT fk_dcc_yc FOREIGN KEY (nguoi_yeu_cau_id) REFERENCES users(user_id)`
- `CONSTRAINT fk_dcc_duy FOREIGN KEY (nguoi_duyet_id) REFERENCES users(user_id) ON DELETE SET NULL`
- `CONSTRAINT ck_dcc_gio CHECK (checkout_moi IS NULL OR (checkin_moi IS NOT NULL AND checkout_moi > checkin_moi))`

<a id="bang-don-xin-nghi"></a>
### 13.29. don_xin_nghi

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `buoi_hoc_id` | `BIGINT UNSIGNED NOT NULL` |
| `doi_tuong` | `ENUM('hoc_vien','day_chinh','tro_giang') NOT NULL` |
| `hoc_vien_id` | `BIGINT UNSIGNED NULL` |
| `nguoi_gui_id` | `BIGINT UNSIGNED NOT NULL` |
| `ly_do` | `VARCHAR(500) NOT NULL` |
| `trang_thai` | `ENUM('cho_duyet','da_duyet','tu_choi','da_huy') NOT NULL DEFAULT 'cho_duyet'` |
| `nguoi_duyet_id` | `BIGINT UNSIGNED NULL` |
| `duyet_luc` | `DATETIME NULL` |
| `ly_do_tu_choi` | `VARCHAR(255) NULL` |
| `buoi_bu_id` | `BIGINT UNSIGNED NULL` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `KEY idx_dxn_buoi (buoi_hoc_id)`
- `KEY idx_dxn_tt (trang_thai, created_at)`
- `CONSTRAINT fk_dxn_buoi FOREIGN KEY (buoi_hoc_id) REFERENCES buoi_hoc(id)`
- `CONSTRAINT fk_dxn_hv FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id)`
- `CONSTRAINT fk_dxn_gui FOREIGN KEY (nguoi_gui_id) REFERENCES users(user_id)`
- `CONSTRAINT fk_dxn_duy FOREIGN KEY (nguoi_duyet_id) REFERENCES users(user_id) ON DELETE SET NULL`
- `CONSTRAINT fk_dxn_bu FOREIGN KEY (buoi_bu_id) REFERENCES buoi_hoc(id)`
- `CONSTRAINT ck_dxn_hv CHECK (doi_tuong <> 'hoc_vien' OR hoc_vien_id IS NOT NULL)`

<a id="bang-bai-tap"></a>
### 13.30. bai_tap

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `buoi_hoc_id` | `BIGINT UNSIGNED NOT NULL` |
| `tieu_de` | `VARCHAR(255) NOT NULL` |
| `noi_dung` | `TEXT NULL` |
| `file_de_bai_url` | `VARCHAR(255) NULL` |
| `han_nop` | `DATETIME NULL` |
| `nguoi_tao_id` | `BIGINT UNSIGNED NOT NULL` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `updated_at` | `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `KEY idx_bt_buoi (buoi_hoc_id)`
- `CONSTRAINT fk_bt_buoi FOREIGN KEY (buoi_hoc_id) REFERENCES buoi_hoc(id)`
- `CONSTRAINT fk_bt_user FOREIGN KEY (nguoi_tao_id) REFERENCES users(user_id)`

<a id="bang-so-theo-doi-buoi-hoc"></a>
### 13.31. so_theo_doi_buoi_hoc

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `buoi_hoc_id` | `BIGINT UNSIGNED NOT NULL` |
| `hoc_vien_id` | `BIGINT UNSIGNED NOT NULL` |
| `trang_thai_diem_danh` | `ENUM('co_mat','di_tre','vang_co_phep','vang_khong_phep') NOT NULL` |
| `bai_tap_id` | `BIGINT UNSIGNED NULL` |
| `diem_bai_tap_giay` | `DECIMAL(4,2) NULL` |
| `nhan_xet_bai_tap` | `VARCHAR(255) NULL` |
| `muc_tiep_thu` | `TINYINT UNSIGNED NULL` |
| `thai_do` | `ENUM('tot','kha','trung_binh','can_cai_thien') NULL` |
| `nhan_xet_buoi_hoc` | `TEXT NULL` |
| `huong_cai_thien` | `TEXT NULL` |
| `nguoi_nhap_id` | `BIGINT UNSIGNED NOT NULL` |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_sotd_buoi_hv (buoi_hoc_id, hoc_vien_id)`
- `KEY idx_sotd_hv (hoc_vien_id)`
- `CONSTRAINT fk_sotd_buoi FOREIGN KEY (buoi_hoc_id) REFERENCES buoi_hoc(id)`
- `CONSTRAINT fk_sotd_hv FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id)`
- `CONSTRAINT fk_sotd_bt FOREIGN KEY (bai_tap_id) REFERENCES bai_tap(id) ON DELETE SET NULL`
- `CONSTRAINT fk_sotd_user FOREIGN KEY (nguoi_nhap_id) REFERENCES users(user_id)`
- `CONSTRAINT ck_sotd_diem CHECK (diem_bai_tap_giay IS NULL OR (diem_bai_tap_giay >= 0 AND diem_bai_tap_giay <= 10))`
- `CONSTRAINT ck_sotd_tt CHECK (muc_tiep_thu IS NULL OR muc_tiep_thu BETWEEN 1 AND 5)`

<a id="bang-ket-qua-hoc-tap"></a>
### 13.32. ket_qua_hoc_tap

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `lop_id` | `BIGINT UNSIGNED NOT NULL` |
| `hoc_vien_id` | `BIGINT UNSIGNED NOT NULL` |
| `loai` | `ENUM('kiem_tra','thi_thu','thi_that','khac') NOT NULL DEFAULT 'kiem_tra'` |
| `ten` | `VARCHAR(150) NOT NULL` |
| `diem` | `DECIMAL(5,2) NOT NULL` |
| `diem_toi_da` | `DECIMAL(5,2) NOT NULL DEFAULT 10` |
| `ngay` | `DATE NOT NULL` |
| `nhan_xet` | `VARCHAR(500) NULL` |
| `nguoi_nhap_id` | `BIGINT UNSIGNED NOT NULL` |
| `created_at` | `DATETIME NULL DEFAULT CURRENT_TIMESTAMP` |
| `updated_at` | `DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `KEY idx_kq (hoc_vien_id, ngay)`
- `KEY idx_kq_lop (lop_id)`
- `CONSTRAINT fk_kq_lop FOREIGN KEY (lop_id) REFERENCES lop_hoc(id)`
- `CONSTRAINT fk_kq_hv FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id)`
- `CONSTRAINT fk_kq_user FOREIGN KEY (nguoi_nhap_id) REFERENCES users(user_id)`
- `CONSTRAINT ck_kq_diem CHECK (diem >= 0 AND diem <= diem_toi_da)`
- `CONSTRAINT ck_kq_thang_diem CHECK (diem_toi_da > 0)`

<a id="bang-bang-cong-thang"></a>
### 13.33. bang_cong_thang

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `mo_khoa_boi` | `BIGINT UNSIGNED NULL` |
| `mo_khoa_luc` | `DATETIME NULL` |
| `ly_do_mo_khoa` | `VARCHAR(255) NULL` |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `user_id` | `BIGINT UNSIGNED NOT NULL` |
| `thang` | `TINYINT UNSIGNED NOT NULL` |
| `nam` | `SMALLINT UNSIGNED NOT NULL` |
| `so_buoi` | `INT UNSIGNED NOT NULL DEFAULT 0` |
| `tong_phut` | `INT UNSIGNED NOT NULL DEFAULT 0` |
| `so_buoi_bu` | `INT UNSIGNED NOT NULL DEFAULT 0` |
| `so_buoi_vang` | `INT UNSIGNED NOT NULL DEFAULT 0` |
| `so_lan_tre` | `INT UNSIGNED NOT NULL DEFAULT 0` |
| `tong_thu_nhap` | `DECIMAL(12,0) NOT NULL DEFAULT 0` |
| `thuong` | `DECIMAL(12,0) NOT NULL DEFAULT 0` |
| `khau_tru` | `DECIMAL(12,0) NOT NULL DEFAULT 0` |
| `thuc_nhan` | `DECIMAL(12,0) GENERATED ALWAYS AS (tong_thu_nhap + thuong - khau_tru) STORED` |
| `trang_thai` | `ENUM('mo','da_chot','da_tra') NOT NULL DEFAULT 'mo'` |
| `chot_boi` | `BIGINT UNSIGNED NULL` |
| `chot_luc` | `DATETIME NULL` |
| `ngay_tra` | `DATE NULL` |
| `ghi_chu` | `VARCHAR(255) NULL` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |
| `tra_boi` | `BIGINT UNSIGNED NULL` |
| `tra_luc` | `DATETIME NULL` |
| `ma_giao_dich_chi` | `VARCHAR(60) NULL` |

Khóa và ràng buộc:

- `CONSTRAINT fk_bc_mo FOREIGN KEY (mo_khoa_boi) REFERENCES users(user_id)`
- `CONSTRAINT fk_bc_tra FOREIGN KEY (tra_boi) REFERENCES users(user_id)`
- `CONSTRAINT ck_bc_tra_meta CHECK ((tra_boi IS NULL) = (tra_luc IS NULL))`
- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_bc_thang (user_id, nam, thang)`
- `KEY idx_bc_tt (nam, thang, trang_thai)`
- `CONSTRAINT fk_bct_user FOREIGN KEY (user_id) REFERENCES users(user_id)`
- `CONSTRAINT fk_bct_chot FOREIGN KEY (chot_boi) REFERENCES users(user_id) ON DELETE SET NULL`
- `CONSTRAINT ck_bct_thang CHECK (thang BETWEEN 1 AND 12)`
- `CONSTRAINT ck_bc_tien CHECK (tong_thu_nhap >= 0 AND thuong >= 0 AND khau_tru >= 0)`

<a id="bang-hoa-don"></a>
### 13.34. hoa_don

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `dang_ky_khoa_hoc_id` | `BIGINT UNSIGNED NULL` |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `ma_hd` | `VARCHAR(30) NOT NULL` |
| `lop_hoc_vien_id` | `BIGINT UNSIGNED NULL` |
| `thang` | `TINYINT UNSIGNED NULL` |
| `nam` | `SMALLINT UNSIGNED NULL` |
| `tong_tien` | `DECIMAL(12,0) NOT NULL DEFAULT 0` |
| `giam_gia` | `DECIMAL(12,0) NOT NULL DEFAULT 0` |
| `phai_thu` | `DECIMAL(12,0) GENERATED ALWAYS AS (tong_tien - giam_gia) STORED` |
| `da_thu` | `DECIMAL(12,0) NOT NULL DEFAULT 0` |
| `trang_thai` | `ENUM('nhap','chua_thanh_toan','mot_phan','da_thanh_toan','huy') NOT NULL DEFAULT 'nhap'` |
| `han_thanh_toan` | `DATE NULL` |
| `nguoi_lap_id` | `BIGINT UNSIGNED NOT NULL` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |
| `phat_hanh_luc` | `DATETIME NULL` |
| `phat_hanh_boi` | `BIGINT UNSIGNED NULL` |
| `huy_luc` | `DATETIME NULL` |
| `huy_boi` | `BIGINT UNSIGNED NULL` |
| `ly_do_huy` | `VARCHAR(255) NULL` |

Khóa và ràng buộc:

- `CONSTRAINT fk_hd_dkk FOREIGN KEY (dang_ky_khoa_hoc_id) REFERENCES dang_ky_khoa_hoc(id)`
- `UNIQUE KEY uq_hd_dkk (dang_ky_khoa_hoc_id)`
- `CONSTRAINT ck_hd_nguon CHECK ((dang_ky_khoa_hoc_id IS NOT NULL AND lop_hoc_vien_id IS NULL AND thang IS NULL AND nam IS NULL) OR (dang_ky_khoa_hoc_id IS NULL AND lop_hoc_vien_id IS NOT NULL AND thang IS NOT NULL AND nam IS NOT NULL))`
- `CONSTRAINT fk_hd_phat_hanh FOREIGN KEY (phat_hanh_boi) REFERENCES users(user_id)`
- `CONSTRAINT fk_hd_huy FOREIGN KEY (huy_boi) REFERENCES users(user_id)`
- `CONSTRAINT ck_hd_phat_hanh_meta CHECK ((phat_hanh_luc IS NULL) = (phat_hanh_boi IS NULL))`
- `CONSTRAINT ck_hd_huy_meta CHECK ((huy_luc IS NULL AND huy_boi IS NULL AND ly_do_huy IS NULL) OR (huy_luc IS NOT NULL AND huy_boi IS NOT NULL AND ly_do_huy IS NOT NULL AND CHAR_LENGTH(TRIM(ly_do_huy))>0))`
- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_hd_ma (ma_hd)`
- `UNIQUE KEY uq_hd_ky (lop_hoc_vien_id, nam, thang)`
- `KEY idx_hd_tt (trang_thai, han_thanh_toan)`
- `CONSTRAINT fk_hd_lhv FOREIGN KEY (lop_hoc_vien_id) REFERENCES lop_hoc_vien(id)`
- `CONSTRAINT fk_hd_user FOREIGN KEY (nguoi_lap_id) REFERENCES users(user_id)`
- `CONSTRAINT ck_hd_tien CHECK (tong_tien >= 0 AND giam_gia >= 0 AND giam_gia <= tong_tien AND da_thu >= 0)`
- `CONSTRAINT ck_hd_thang CHECK (thang BETWEEN 1 AND 12)`
- `CONSTRAINT ck_hd_so_du CHECK (da_thu <= phai_thu)`

<a id="bang-hoa-don-chi-tiet"></a>
### 13.35. hoa_don_chi_tiet

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `hoa_don_id` | `BIGINT UNSIGNED NOT NULL` |
| `buoi_hoc_id` | `BIGINT UNSIGNED NULL` |
| `loai_khoan` | `ENUM('buoi_hoc','khoa_hoc','thang_co_dinh','phu_phi') NOT NULL DEFAULT 'buoi_hoc'` |
| `mo_ta` | `VARCHAR(255) NULL` |
| `don_gia` | `DECIMAL(12,0) NOT NULL` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `KEY idx_hdct_hd (hoa_don_id)`
- `CONSTRAINT fk_hdct_hd FOREIGN KEY (hoa_don_id) REFERENCES hoa_don(id) ON DELETE CASCADE`
- `CONSTRAINT fk_hdct_buoi FOREIGN KEY (buoi_hoc_id) REFERENCES buoi_hoc(id)`
- `CONSTRAINT ck_hdct_gia CHECK (don_gia >= 0)`
- `CONSTRAINT ck_hdct_buoi CHECK (loai_khoan <> 'buoi_hoc' OR buoi_hoc_id IS NOT NULL)`

<a id="bang-thanh-toan"></a>
### 13.36. thanh_toan

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `hoa_don_id` | `BIGINT UNSIGNED NOT NULL` |
| `loai` | `ENUM('thu','hoan') NOT NULL DEFAULT 'thu'` |
| `so_tien` | `DECIMAL(12,0) NOT NULL` |
| `phuong_thuc` | `ENUM('tien_mat','chuyen_khoan','vi_dien_tu','the') NOT NULL` |
| `ma_giao_dich` | `VARCHAR(60) NULL` |
| `ngay_thanh_toan` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `nguoi_thu_id` | `BIGINT UNSIGNED NOT NULL` |
| `trang_thai` | `ENUM('hop_le','da_huy') NOT NULL DEFAULT 'hop_le'` |
| `ghi_chu` | `VARCHAR(255) NULL` |
| `huy_luc` | `DATETIME NULL` |
| `huy_boi` | `BIGINT UNSIGNED NULL` |
| `ly_do_huy` | `VARCHAR(255) NULL` |

Khóa và ràng buộc:

- `CONSTRAINT fk_tt_huy FOREIGN KEY (huy_boi) REFERENCES users(user_id)`
- `CONSTRAINT ck_tt_huy_meta CHECK ((huy_luc IS NULL AND huy_boi IS NULL AND ly_do_huy IS NULL) OR (huy_luc IS NOT NULL AND huy_boi IS NOT NULL AND ly_do_huy IS NOT NULL AND CHAR_LENGTH(TRIM(ly_do_huy))>0))`
- `PRIMARY KEY (id)`
- `KEY idx_tt_hd (hoa_don_id)`
- `KEY idx_tt_ngay (ngay_thanh_toan)`
- `CONSTRAINT fk_tt_hd FOREIGN KEY (hoa_don_id) REFERENCES hoa_don(id)`
- `CONSTRAINT fk_tt_user FOREIGN KEY (nguoi_thu_id) REFERENCES users(user_id)`
- `CONSTRAINT ck_tt_tien CHECK (so_tien > 0)`

<a id="bang-thong-bao"></a>
### 13.37. thong_bao

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `user_id` | `BIGINT UNSIGNED NOT NULL` |
| `loai` | `ENUM('phan_cong','go_phan_cong','lop_moi','doi_lich','nhac_checkin','don_nghi','duyet_don', 'nhan_xet','hoa_don','chot_cong','de_xuat','he_thong') NOT NULL` |
| `tieu_de` | `VARCHAR(255) NOT NULL` |
| `noi_dung` | `VARCHAR(1000) NULL` |
| `doi_tuong_loai` | `VARCHAR(30) NULL` |
| `doi_tuong_id` | `BIGINT UNSIGNED NULL` |
| `khoa_chong_trung` | `VARCHAR(100) NULL` |
| `da_doc` | `TINYINT(1) GENERATED ALWAYS AS (doc_luc IS NOT NULL) VIRTUAL` |
| `doc_luc` | `DATETIME NULL` |
| `co_gui_email` | `TINYINT(1) NOT NULL DEFAULT 0` |
| `so_lan_thu_email` | `TINYINT UNSIGNED NOT NULL DEFAULT 0` |
| `trang_thai_email` | `ENUM('chua_gui','da_gui','loi') NOT NULL DEFAULT 'chua_gui'` |
| `loi_email` | `VARCHAR(255) NULL` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `email_gui_luc` | `DATETIME NULL` |
| `email_thu_gan_nhat_luc` | `DATETIME NULL` |
| `email_thu_tiep_luc` | `DATETIME NULL` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_tb_chong_trung (user_id, khoa_chong_trung)`
- `KEY idx_tb_user (user_id, da_doc, created_at)`
- `CONSTRAINT fk_tb_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE`

<a id="bang-audit-log"></a>
### 13.38. audit_log

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `user_id` | `BIGINT UNSIGNED NULL` |
| `hanh_dong` | `ENUM('them','sua','xoa','dang_nhap','chot_cong','mo_khoa_cong','duyet','khac') NOT NULL` |
| `bang` | `VARCHAR(60) NOT NULL` |
| `ban_ghi_id` | `BIGINT UNSIGNED NULL` |
| `du_lieu_cu` | `JSON NULL` |
| `du_lieu_moi` | `JSON NULL` |
| `ip` | `VARCHAR(45) NULL` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `KEY idx_al_bang (bang, ban_ghi_id)`
- `KEY idx_al_user (user_id, created_at)`
- `CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL`

<a id="bang-schema-migrations"></a>
### 13.39. schema_migrations

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `version` | `VARCHAR(80) NOT NULL PRIMARY KEY` |
| `status` | `ENUM('applying','applied') NOT NULL` |
| `applied_at` | `DATETIME NULL` |

Khóa/ràng buộc được khai báo trực tiếp trong cột ở trên.

<a id="bang-yeu-cau-nghi-lop"></a>
### 13.40. yeu_cau_nghi_lop

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `buoi_hoc_id` | `BIGINT UNSIGNED NOT NULL` |
| `nguoi_gui_id` | `BIGINT UNSIGNED NOT NULL` |
| `ly_do` | `VARCHAR(500) NOT NULL` |
| `trang_thai` | `ENUM('cho_duyet','da_duyet','tu_choi') NOT NULL DEFAULT 'cho_duyet'` |
| `nguoi_duyet_id` | `BIGINT UNSIGNED NULL` |
| `duyet_luc` | `DATETIME NULL` |
| `ghi_chu` | `VARCHAR(500) NULL` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `CONSTRAINT fk_nl_buoi FOREIGN KEY (buoi_hoc_id) REFERENCES buoi_hoc(id)`
- `CONSTRAINT fk_nl_gui FOREIGN KEY (nguoi_gui_id) REFERENCES users(user_id)`
- `CONSTRAINT fk_nl_duyet FOREIGN KEY (nguoi_duyet_id) REFERENCES users(user_id)`

<a id="bang-hoc-bu-hoc-vien"></a>
### 13.41. hoc_bu_hoc_vien

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `buoi_bu_id` | `BIGINT UNSIGNED NOT NULL` |
| `hoc_vien_id` | `BIGINT UNSIGNED NOT NULL` |
| `don_xin_nghi_id` | `BIGINT UNSIGNED NULL` |
| `xep_boi` | `BIGINT UNSIGNED NOT NULL` |
| `ghi_chu` | `VARCHAR(500) NULL` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_hb_buoi_hv (buoi_bu_id,hoc_vien_id)`
- `CONSTRAINT fk_hb_buoi FOREIGN KEY (buoi_bu_id) REFERENCES buoi_hoc(id)`
- `CONSTRAINT fk_hb_hv FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id)`
- `CONSTRAINT fk_hb_don FOREIGN KEY (don_xin_nghi_id) REFERENCES don_xin_nghi(id)`
- `CONSTRAINT fk_hb_xep FOREIGN KEY (xep_boi) REFERENCES users(user_id)`

<a id="bang-giao-dich-truc-tuyen"></a>
### 13.42. giao_dich_truc_tuyen

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `ma_yeu_cau` | `VARCHAR(64) COLLATE utf8mb4_bin NOT NULL` |
| `hoa_don_id` | `BIGINT UNSIGNED NOT NULL` |
| `phu_huynh_id` | `BIGINT UNSIGNED NOT NULL` |
| `nha_cung_cap` | `VARCHAR(40) COLLATE utf8mb4_bin NOT NULL` |
| `ma_giao_dich_cong` | `VARCHAR(120) COLLATE utf8mb4_bin NULL` |
| `so_tien` | `DECIMAL(12,0) NOT NULL` |
| `tien_te` | `VARCHAR(3) NOT NULL DEFAULT 'VND'` |
| `trang_thai` | `ENUM('khoi_tao','cho_xac_nhan','thanh_cong','that_bai','het_han','huy') NOT NULL DEFAULT 'khoi_tao'` |
| `thanh_toan_id` | `BIGINT UNSIGNED NULL` |
| `xac_nhan_boi` | `BIGINT UNSIGNED NULL` |
| `xac_nhan_luc` | `DATETIME NULL` |
| `het_han_luc` | `DATETIME NULL` |
| `created_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_gd_yeu_cau (ma_yeu_cau)`
- `UNIQUE KEY uq_gd_cong (nha_cung_cap,ma_giao_dich_cong)`
- `UNIQUE KEY uq_gd_phieu (thanh_toan_id)`
- `UNIQUE KEY uq_gd_id_cong (id,nha_cung_cap)`
- `KEY idx_gd_hd (hoa_don_id,trang_thai)`
- `KEY idx_gd_ph (phu_huynh_id,created_at)`
- `KEY idx_gd_han (trang_thai,het_han_luc)`
- `CONSTRAINT fk_gd_hd FOREIGN KEY (hoa_don_id) REFERENCES hoa_don(id)`
- `CONSTRAINT fk_gd_ph FOREIGN KEY (phu_huynh_id) REFERENCES users(user_id)`
- `CONSTRAINT fk_gd_phieu FOREIGN KEY (thanh_toan_id) REFERENCES thanh_toan(id)`
- `CONSTRAINT fk_gd_admin FOREIGN KEY (xac_nhan_boi) REFERENCES users(user_id)`
- `CONSTRAINT ck_gd_tien CHECK (so_tien>0 AND tien_te='VND')`
- `CONSTRAINT ck_gd_xac_nhan CHECK ( (trang_thai='thanh_cong' AND thanh_toan_id IS NOT NULL AND xac_nhan_boi IS NOT NULL AND xac_nhan_luc IS NOT NULL) OR (trang_thai<>'thanh_cong' AND thanh_toan_id IS NULL AND xac_nhan_boi IS NULL AND xac_nhan_luc IS NULL))`
- `CONSTRAINT ck_gd_ma CHECK (CHAR_LENGTH(TRIM(ma_yeu_cau))>0 AND CHAR_LENGTH(TRIM(nha_cung_cap))>0)`

<a id="bang-su-kien-thanh-toan"></a>
### 13.43. su_kien_thanh_toan

| Cột | Kiểu và quy định SQL |
| --- | --- |
| `id` | `BIGINT UNSIGNED NOT NULL AUTO_INCREMENT` |
| `giao_dich_id` | `BIGINT UNSIGNED NOT NULL` |
| `nha_cung_cap` | `VARCHAR(40) COLLATE utf8mb4_bin NOT NULL` |
| `ma_su_kien` | `VARCHAR(120) COLLATE utf8mb4_bin NOT NULL` |
| `payload_sha256` | `VARCHAR(64) NOT NULL` |
| `trang_thai` | `ENUM('cho_xu_ly','da_xu_ly','bo_qua','loi') NOT NULL DEFAULT 'cho_xu_ly'` |
| `loi` | `VARCHAR(255) NULL` |
| `nhan_luc` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP` |
| `xu_ly_luc` | `DATETIME NULL` |

Khóa và ràng buộc:

- `PRIMARY KEY (id)`
- `UNIQUE KEY uq_sk_cong (nha_cung_cap,ma_su_kien)`
- `KEY idx_sk_hang_doi (trang_thai,nhan_luc)`
- `CONSTRAINT fk_sk_gd FOREIGN KEY (giao_dich_id,nha_cung_cap) REFERENCES giao_dich_truc_tuyen(id,nha_cung_cap)`
- `CONSTRAINT ck_sk_hash CHECK (REGEXP_LIKE(payload_sha256,'^[a-fA-F0-9]{64}$','c'))`
- `CONSTRAINT ck_sk_xu_ly CHECK ((trang_thai='cho_xu_ly' AND xu_ly_luc IS NULL) OR (trang_thai<>'cho_xu_ly' AND xu_ly_luc IS NOT NULL))`

<a id="routine-trigger"></a>
## 14. Danh mục routine và trigger

### 14.1. Function và procedure

Helper được trigger gọi trong transaction hiện tại. Các procedure quản lý transaction riêng đã nêu tại mục 6.2 cần gọi độc lập, không lồng vào transaction service. Chữ ký dưới đây lấy từ source; tham số actor luôn phải do BE xác thực.

| Routine và chữ ký | Vai trò | Nguồn |
| --- | --- | --- |
| `fn_trung_lich(p_user BIGINT UNSIGNED,p_buoi BIGINT UNSIGNED,p_loai_tru BIGINT UNSIGNED) RETURNS TINYINT READS SQL DATA NOT DETERMINISTIC` | Kiểm tra chồng lịch nhân sự, loại ca được gỡ/buổi hủy hoặc dời. | [routines/functions.sql](../database/routines/functions.sql) |
| `fn_tien_cong(p_cach VARCHAR(20),p_don_gia DECIMAL(12,0),p_so_phut INT) RETURNS DECIMAL(14,0) DETERMINISTIC NO SQL` | Tính/làm tròn VND theo snapshot; giữ hỗ trợ theo_buoi cho lịch sử, ca mới theo_gio. | [routines/functions.sql](../database/routines/functions.sql) |
| `sp_kiem_tra_si_so(IN p_lop BIGINT UNSIGNED,IN p_tt VARCHAR(30),IN p_exclude BIGINT UNSIGNED)` | Khóa lớp và kiểm tra sĩ số khi ghi danh. | [routines/helpers.sql](../database/routines/helpers.sql) |
| `sp_kiem_tra_buoi(IN p_id BIGINT UNSIGNED,IN p_lop BIGINT UNSIGNED, IN p_lich BIGINT UNSIGNED,IN p_loai VARCHAR(20),IN p_goc BIGINT UNSIGNED, IN p_ngay DATE,IN p_bd TIME,IN p_kt TIME,IN p_coso INT UNSIGNED,IN p_phong VARCHAR(50),IN p_tt VARCHAR(20),IN p_pham_vi VARCHAR(20))` | Khớp lịch tuần/lớp, buổi gốc/phạm vi bù, phòng và lịch người dạy. | [routines/helpers.sql](../database/routines/helpers.sql) |
| `sp_kiem_tra_hoc_vu(IN p_buoi BIGINT UNSIGNED,IN p_hv BIGINT UNSIGNED,IN p_bt BIGINT UNSIGNED)` | Học viên thuộc lớp/ngày, đúng danh sách bù cá nhân và bài tập cùng lớp. | [routines/helpers.sql](../database/routines/helpers.sql) |
| `sp_kiem_tra_don_nghi(IN p_buoi BIGINT UNSIGNED,IN p_doi_tuong VARCHAR(20),IN p_hv BIGINT UNSIGNED,IN p_bu BIGINT UNSIGNED)` | Đối tượng nghỉ và liên kết buổi bù đúng buổi gốc. | [routines/helpers.sql](../database/routines/helpers.sql) |
| `sp_kiem_tra_khoan_thu(IN p_hd BIGINT UNSIGNED,IN p_buoi BIGINT UNSIGNED)` | Chi tiết thu phù hợp nguồn hóa đơn/khóa hoặc lớp legacy. | [routines/helpers.sql](../database/routines/helpers.sql) |
| `sp_kiem_tra_thanh_toan(IN p_hd BIGINT UNSIGNED,IN p_delta DECIMAL(12,0))` | Khóa hóa đơn, kiểm số dư sau biến động thu/hoàn/hủy. | [routines/helpers.sql](../database/routines/helpers.sql) |
| `sp_cap_nhat_so_du(IN p_hd BIGINT UNSIGNED)` | Tổng hợp thu trừ hoàn hợp lệ, cập nhật số dư/trạng thái hóa đơn. | [routines/helpers.sql](../database/routines/helpers.sql) |
| `fn_la_admin(p_user BIGINT UNSIGNED) RETURNS TINYINT READS SQL DATA NOT DETERMINISTIC` | Kiểm tra account/user/group Admin active. | [routines/business_rules.sql](../database/routines/business_rules.sql) |
| `fn_la_giao_vu(p_user BIGINT UNSIGNED) RETURNS TINYINT READS SQL DATA NOT DETERMINISTIC` | Kiểm tra giáo vụ/Admin active cho nghiệp vụ được giao. | [routines/business_rules.sql](../database/routines/business_rules.sql) |
| `sp_dat_don_gia_gio(IN p_nhan_su BIGINT UNSIGNED,IN p_gia DECIMAL(12,0),IN p_admin BIGINT UNSIGNED)` | Admin đổi giá từ hiện tại, giữ lịch sử/ca đã bắt đầu, cập nhật ca tương lai. | [routines/business_rules.sql](../database/routines/business_rules.sql) |
| `sp_kiem_tra_dang_ky_khoa(IN p_dkk BIGINT UNSIGNED,IN p_lop BIGINT UNSIGNED,IN p_hv BIGINT UNSIGNED)` | Ghi danh đúng khóa và học viên, không kiểm tra đã đóng tiền. | [routines/business_rules.sql](../database/routines/business_rules.sql) |
| `sp_kiem_tra_hoc_bu_ca_nhan(IN p_bu BIGINT UNSIGNED,IN p_hv BIGINT UNSIGNED,IN p_don BIGINT UNSIGNED,IN p_actor BIGINT UNSIGNED)` | Đúng học viên/lớp, giáo vụ xếp; đơn nghỉ tùy chọn nếu có phải khớp. | [routines/business_rules.sql](../database/routines/business_rules.sql) |
| `fn_phu_huynh_hoa_don(p_hd BIGINT UNSIGNED) RETURNS BIGINT UNSIGNED READS SQL DATA NOT DETERMINISTIC` | Suy phụ huynh hiện tại từ hóa đơn khóa hoặc ghi danh legacy. | [routines/payments.sql](../database/routines/payments.sql) |
| `sp_kiem_tra_giao_dich(IN p_hd BIGINT UNSIGNED,IN p_ph BIGINT UNSIGNED,IN p_tien DECIMAL(12,0))` | Đúng phụ huynh active, hóa đơn đã phát hành và số dư/VND. | [routines/payments.sql](../database/routines/payments.sql) |
| `sp_xac_nhan_giao_dich(IN p_gd BIGINT UNSIGNED,IN p_admin BIGINT UNSIGNED)` | Admin đối soát, transaction khóa hóa đơn→giao dịch, một phiếu thu, audit; gọi lặp an toàn. | [routines/payments.sql](../database/routines/payments.sql) |
| `sp_sinh_buoi_hoc(IN p_lop_id BIGINT UNSIGNED,IN p_tu DATE,IN p_den DATE)` | Sinh từ lịch tuần trong khoảng ngày và hiệu lực lớp, gán phân công/snapshot giá giờ. | [routines/procedures.sql](../database/routines/procedures.sql) |
| `sp_chot_cong(IN p_user BIGINT UNSIGNED,IN p_thang TINYINT,IN p_nam SMALLINT,IN p_nguoi_chot BIGINT UNSIGNED)` | Admin kiểm ngoại lệ, tổng hợp phút/tiền và khóa công kỳ. | [routines/procedures.sql](../database/routines/procedures.sql) |
| `sp_mo_khoa_cong(IN p_id BIGINT UNSIGNED,IN p_admin BIGINT UNSIGNED,IN p_ly_do VARCHAR(255))` | Admin mở kỳ đã chốt chưa trả, bắt buộc lý do/audit. | [routines/procedures.sql](../database/routines/procedures.sql) |

### 14.2. Trigger

BI/BU/BD tương ứng BEFORE INSERT/UPDATE/DELETE; AI/AU tương ứng AFTER INSERT/UPDATE. Mỗi dòng là một trigger thực, không phải một API độc lập. Nhóm timekeeping/payroll bảo vệ công; scheduling/classes bảo vệ lịch/sĩ số; academic bảo vệ học vụ; tuition/payments bảo vệ chứng từ; business_rules thực thi các quyết định004.

| Trigger | Thời điểm | Bảng | Nguồn |
| --- | --- | --- | --- |
| `trg_pc_bi` | BEFORE INSERT | `phan_cong_buoi_hoc` | [triggers/timekeeping.sql](../database/triggers/timekeeping.sql) |
| `trg_pc_bu` | BEFORE UPDATE | `phan_cong_buoi_hoc` | [triggers/timekeeping.sql](../database/triggers/timekeeping.sql) |
| `trg_pc_bd` | BEFORE DELETE | `phan_cong_buoi_hoc` | [triggers/timekeeping.sql](../database/triggers/timekeeping.sql) |
| `trg_dcc_bi` | BEFORE INSERT | `dieu_chinh_cong` | [triggers/timekeeping.sql](../database/triggers/timekeeping.sql) |
| `trg_dcc_bu` | BEFORE UPDATE | `dieu_chinh_cong` | [triggers/timekeeping.sql](../database/triggers/timekeeping.sql) |
| `trg_lhv_bi` | BEFORE INSERT | `lop_hoc_vien` | [triggers/classes.sql](../database/triggers/classes.sql) |
| `trg_lhv_bu` | BEFORE UPDATE | `lop_hoc_vien` | [triggers/classes.sql](../database/triggers/classes.sql) |
| `trg_lop_bu` | BEFORE UPDATE | `lop_hoc` | [triggers/classes.sql](../database/triggers/classes.sql) |
| `trg_buoi_bi` | BEFORE INSERT | `buoi_hoc` | [triggers/scheduling.sql](../database/triggers/scheduling.sql) |
| `trg_buoi_bu` | BEFORE UPDATE | `buoi_hoc` | [triggers/scheduling.sql](../database/triggers/scheduling.sql) |
| `trg_sotd_bi` | BEFORE INSERT | `so_theo_doi_buoi_hoc` | [triggers/academic.sql](../database/triggers/academic.sql) |
| `trg_sotd_bu` | BEFORE UPDATE | `so_theo_doi_buoi_hoc` | [triggers/academic.sql](../database/triggers/academic.sql) |
| `trg_dxn_bi` | BEFORE INSERT | `don_xin_nghi` | [triggers/academic.sql](../database/triggers/academic.sql) |
| `trg_dxn_bu` | BEFORE UPDATE | `don_xin_nghi` | [triggers/academic.sql](../database/triggers/academic.sql) |
| `trg_tt_bi` | BEFORE INSERT | `thanh_toan` | [triggers/tuition.sql](../database/triggers/tuition.sql) |
| `trg_tt_bu` | BEFORE UPDATE | `thanh_toan` | [triggers/tuition.sql](../database/triggers/tuition.sql) |
| `trg_tt_ai` | AFTER INSERT | `thanh_toan` | [triggers/tuition.sql](../database/triggers/tuition.sql) |
| `trg_tt_au` | AFTER UPDATE | `thanh_toan` | [triggers/tuition.sql](../database/triggers/tuition.sql) |
| `trg_tt_bd` | BEFORE DELETE | `thanh_toan` | [triggers/tuition.sql](../database/triggers/tuition.sql) |
| `trg_hd_bu` | BEFORE UPDATE | `hoa_don` | [triggers/tuition.sql](../database/triggers/tuition.sql) |
| `trg_hd_bi` | BEFORE INSERT | `hoa_don` | [triggers/tuition.sql](../database/triggers/tuition.sql) |
| `trg_hdct_bi` | BEFORE INSERT | `hoa_don_chi_tiet` | [triggers/tuition.sql](../database/triggers/tuition.sql) |
| `trg_hdct_bu` | BEFORE UPDATE | `hoa_don_chi_tiet` | [triggers/tuition.sql](../database/triggers/tuition.sql) |
| `trg_bc_bi` | BEFORE INSERT | `bang_cong_thang` | [triggers/payroll.sql](../database/triggers/payroll.sql) |
| `trg_bc_bu` | BEFORE UPDATE | `bang_cong_thang` | [triggers/payroll.sql](../database/triggers/payroll.sql) |
| `trg_dkk_bi` | BEFORE INSERT | `dang_ky_khoa_hoc` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_dkk_bu` | BEFORE UPDATE | `dang_ky_khoa_hoc` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_lop_khoa_bi` | BEFORE INSERT | `lop_hoc` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_lop_khoa_bu` | BEFORE UPDATE | `lop_hoc` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_lhv_khoa_bi` | BEFORE INSERT | `lop_hoc_vien` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_lhv_khoa_bu` | BEFORE UPDATE | `lop_hoc_vien` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_hd_khoa_bi` | BEFORE INSERT | `hoa_don` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_hd_khoa_bu` | BEFORE UPDATE | `hoa_don` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_tt_thoi_hoc_bi` | BEFORE INSERT | `thanh_toan` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_tt_thoi_hoc_bu` | BEFORE UPDATE | `thanh_toan` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_dxn_quyen_bi` | BEFORE INSERT | `don_xin_nghi` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_dxn_quyen_bu` | BEFORE UPDATE | `don_xin_nghi` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_nghi_lop_bi` | BEFORE INSERT | `yeu_cau_nghi_lop` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_nghi_lop_bu` | BEFORE UPDATE | `yeu_cau_nghi_lop` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_nghi_lop_au` | AFTER UPDATE | `yeu_cau_nghi_lop` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_hoc_bu_bi` | BEFORE INSERT | `hoc_bu_hoc_vien` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_hoc_bu_bu` | BEFORE UPDATE | `hoc_bu_hoc_vien` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_quyen_luong_bi` | BEFORE INSERT | `role_group_function` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_quyen_luong_bu` | BEFORE UPDATE | `role_group_function` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_don_gia_bi` | BEFORE INSERT | `lich_su_don_gia_gio` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_don_gia_bu` | BEFORE UPDATE | `lich_su_don_gia_gio` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_don_gia_bd` | BEFORE DELETE | `lich_su_don_gia_gio` | [triggers/business_rules.sql](../database/triggers/business_rules.sql) |
| `trg_gd_bi` | BEFORE INSERT | `giao_dich_truc_tuyen` | [triggers/payments.sql](../database/triggers/payments.sql) |
| `trg_gd_bu` | BEFORE UPDATE | `giao_dich_truc_tuyen` | [triggers/payments.sql](../database/triggers/payments.sql) |
| `trg_gd_bd` | BEFORE DELETE | `giao_dich_truc_tuyen` | [triggers/payments.sql](../database/triggers/payments.sql) |
| `trg_sk_bu` | BEFORE UPDATE | `su_kien_thanh_toan` | [triggers/payments.sql](../database/triggers/payments.sql) |
| `trg_sk_bd` | BEFORE DELETE | `su_kien_thanh_toan` | [triggers/payments.sql](../database/triggers/payments.sql) |
| `trg_hd_thong_bao_ai` | AFTER INSERT | `hoa_don` | [triggers/payments.sql](../database/triggers/payments.sql) |
| `trg_hd_thong_bao_au` | AFTER UPDATE | `hoa_don` | [triggers/payments.sql](../database/triggers/payments.sql) |
| `trg_tt_thong_bao_ai` | AFTER INSERT | `thanh_toan` | [triggers/payments.sql](../database/triggers/payments.sql) |
| `trg_lhv_admin_bi` | BEFORE INSERT | `lop_hoc_vien` | [triggers/payments.sql](../database/triggers/payments.sql) |
| `trg_lhv_admin_bu` | BEFORE UPDATE | `lop_hoc_vien` | [triggers/payments.sql](../database/triggers/payments.sql) |
