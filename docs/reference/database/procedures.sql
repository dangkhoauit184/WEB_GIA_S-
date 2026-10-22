-- ===================================================================
-- STORED PROCEDURES & BUSINESS RULES (BR01 - BR20)
-- HỆ THỐNG TRUNG TÂM GIA SƯ & ĐÀO TẠO EDUCENTER ERP
-- ===================================================================

USE trung_tam_gia_su;

-- 1. PROCEDURE: Sinh chuỗi buổi học tự động từ lịch cố định (BR03)
DROP PROCEDURE IF EXISTS sp_sinh_buoi_hoc;
DELIMITER //
CREATE PROCEDURE sp_sinh_buoi_hoc(
    IN p_ma_lop VARCHAR(20),
    IN p_ngay_bat_dau DATE,
    IN p_so_buoi INT
)
BEGIN
    DECLARE v_count INT DEFAULT 0;
    DECLARE v_cur_date DATE;
    DECLARE v_thu INT;
    
    SET v_cur_date = p_ngay_bat_dau;
    
    WHILE v_count < p_so_buoi DO
        SET v_thu = DAYOFWEEK(v_cur_date); -- 1: CN, 2: T2, ..., 7: T7
        
        -- Kiểm tra nếu ngày hiện tại khớp với lịch cố định của lớp trong LICH_HOC
        IF EXISTS (SELECT 1 FROM LICH_HOC WHERE ma_lop = p_ma_lop AND thu_trong_tuan = v_thu AND trang_thai = 'ap_dung') THEN
            INSERT INTO BUOI_HOC (ma_lop, ngay_hoc, trang_thai, chu_de)
            VALUES (p_ma_lop, v_cur_date, 'sap_dien_ra', CONCAT('Buổi học số ', v_count + 1));
            
            SET v_count = v_count + 1;
        END IF;
        
        SET v_cur_date = DATE_ADD(v_cur_date, INTERVAL 1 DAY);
    END WHILE;
END //
DELIMITER ;

-- 2. PROCEDURE: Chốt công tháng & Khóa sổ thù lao (BR11, BR12)
DROP PROCEDURE IF EXISTS sp_chot_cong;
DELIMITER //
CREATE PROCEDURE sp_chot_cong(
    IN p_ky_cong VARCHAR(10), -- '10/2026'
    OUT p_status_code INT,
    OUT p_message VARCHAR(255)
)
BEGIN
    -- Kiểm tra điều kiện BR11: Còn ca học nào đã check-in nhưng thiếu check-out không
    IF EXISTS (
        SELECT 1 FROM PHAN_CONG_BUOI_HOC pc
        JOIN BUOI_HOC bh ON pc.ma_buoi_hoc = bh.ma_buoi_hoc
        WHERE pc.gio_checkin IS NOT NULL AND pc.gio_checkout IS NULL
    ) THEN
        SET p_status_code = -1;
        SET p_message = 'Lỗi BR11: Tồn tại ca dạy đã check-in nhưng chưa check-out. Chặn chốt công!';
    ELSE
        -- Khóa sổ bảng công tháng
        UPDATE BANG_CONG_THANG
        SET trang_thai = 'da_chot', da_khoa_so = 1, ngay_chot = NOW()
        WHERE ky_cong = p_ky_cong;
        
        SET p_status_code = 0;
        SET p_message = 'Chốt công thành công! Toàn bộ bảng công đã khóa sổ an toàn (BR12).';
    END IF;
END //
DELIMITER ;

