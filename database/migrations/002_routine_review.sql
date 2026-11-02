-- [THÊM] Phiên bản 002 rà soát routine/trigger; không ALTER/DROP bảng, không đổi dữ liệu nghiệp vụ.
-- [LƯU Ý] Script run.ps1 nạp modules trong install-order.json, chỉ đánh dấu applied sau khi tất cả thành công.
-- [LƯU Ý] Dừng ghi dữ liệu trong thời gian thay stored objects; DDL MySQL tự COMMIT.
INSERT INTO schema_migrations(version,status) VALUES ('002_routine_review','applying');
