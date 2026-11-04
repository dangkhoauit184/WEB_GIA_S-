# Backend

Backend chỉ gồm **file khung rỗng**, chưa có code server, API hoặc kết nối MySQL. Chưa cài thư viện và chưa có lệnh chạy ứng dụng.

## Cấu trúc

```text
backend/
├── server.js
└── src/
    ├── app.js
    ├── config/
    ├── modules/
    │   ├── index.js
    │   └── academic/
    │       ├── academic.routes.js
    │       └── features/
    │           └── scheduling/
    │               ├── scheduling.routes.js
    │               ├── scheduling.controller.js
    │               ├── scheduling.service.js
    │               ├── scheduling.repository.js
    │               ├── scheduling.model.js
    │               ├── scheduling.validator.js
    │               └── scheduling.middleware.js
    └── shared/
```

Ví dụ trên minh họa một feature; toàn bộ 6 module và 21 feature nằm trong [danh sách chung](../shared/modules.json).

## Quy ước khi bắt đầu viết code

| Thành phần | Trách nhiệm dự kiến |
| --- | --- |
| `server.js` | Khởi động server và đóng kết nối khi dừng |
| `src/app.js` | Ghép middleware, module và xử lý lỗi |
| `<module>.routes.js` | Ghép route của các feature trong module |
| `*.routes.js` | Khai báo endpoint |
| `*.controller.js` | Nhận request, trả response |
| `*.service.js` | Xử lý nghiệp vụ và giao dịch |
| `*.repository.js` | Truy cập MySQL |
| `*.model.js` | Cấu trúc dữ liệu và ánh xạ |
| `*.validator.js` | Kiểm tra dữ liệu đầu vào |
| `*.middleware.js` | Middleware riêng của feature |
| `src/shared/` | Lỗi, middleware, phân quyền và tiện ích dùng chung |

Chuẩn bị database theo [database/README.md](../database/README.md) trước khi triển khai kết nối. Thông số server được chuẩn bị ở [config/server.env.example](../config/server.env.example), cách dùng tại [docs/server-setup.md](../docs/server-setup.md). File mẫu chưa được chương trình nào nạp.

Khi thêm feature, tạo dưới `src/modules/<module>/features/`, đồng bộ frontend, cập nhật `shared/modules.json` và tài liệu. Giai đoạn hiện tại giữ các file mã nguồn rỗng.
