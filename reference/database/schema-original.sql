-- =====================================================================
--  HỆ THỐNG QUẢN LÝ TRUNG TÂM GIA SƯ & LUYỆN THI (MySQL 8.0+)
--  PHIÊN BẢN CHUẨN HÓA 35 BẢNG (ĐÃ ÁP DỤNG TOÀN BỘ YÊU CẦU CHỐT CỦA BẠN)
--  - Tài khoản, phân quyền (5): USERS, ACCOUNT, FUNCTION, ROLE_GROUP, ROLE_GROUP_FUNCTION
--  - Danh mục (5): MON_HOC, CAP_LOP, KHU_VUC, CO_SO, CAU_HINH
--  - Hồ sơ (3): NHAN_VIEN, NHAN_SU_CHUYEN_MON, HOC_VIEN
--  - Chuyên môn (3): NHAN_SU_MON, NHAN_SU_KHU_VUC, NHAN_SU_LICH_RANH
--  - Tuyển sinh (3): YEU_CAU_HOC, YEU_CAU_LICH, DE_XUAT_NHAN_SU
--  - Lớp, lịch (3): LOP_HOC, LOP_HOC_VIEN, LICH_HOC
--  - Vận hành (4): BUOI_HOC, PHAN_CONG_BUOI_HOC, DIEU_CHINH_CONG, DON_XIN_NGHI
--  - Học vụ (3): SO_THEO_DOI_BUOI_HOC, BAI_TAP, KET_QUA_HOC_TAP
--  - Công, lương, học phí (4): BANG_CONG_THANG, HOA_DON, HOA_DON_CHI_TIET, THANH_TOAN
--  - Thông báo, nhật ký (2): THONG_BAO, AUDIT_LOG
-- =====================================================================
DROP DATABASE IF EXISTS trung_tam_gia_su;
CREATE DATABASE trung_tam_gia_su CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE trung_tam_gia_su;
SET NAMES utf8mb4;

-- =====================================================================
-- 1. TÀI KHOẢN & PHÂN QUYỀN (5 BẢNG)
-- =====================================================================

-- 1.1. Bảng USERS: Hồ sơ cá nhân (con người)
CREATE TABLE users (
  user_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  hoten       VARCHAR(150) NOT NULL,
  sdt         VARCHAR(20)  NULL,
  email       VARCHAR(150) NOT NULL,
  ngaysinh    DATE NULL,
  diachi      VARCHAR(255) NULL,
  nghe_nghiep VARCHAR(100) NULL,           -- Dành cho Phụ huynh
  khu_vuc_id  INT UNSIGNED NULL,           -- Dành cho Phụ huynh
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_deleted  TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_sdt (sdt)
) ENGINE=InnoDB;

-- 1.2. Bảng ROLE_GROUP: 5 Nhóm quyền chuẩn hóa
CREATE TABLE role_group (
  group_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_name  VARCHAR(100) NOT NULL,       -- Admin, NhanVien, GiangVien, TroGiang, PhuHuynh
  description VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_deleted  TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (group_id),
  UNIQUE KEY uq_group_name (group_name)
) ENGINE=InnoDB;

-- 1.3. Bảng ACCOUNT: Thông tin đăng nhập, liên kết 1-1 với USERS và gắn GROUP_ID
CREATE TABLE account (
  account_id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED NOT NULL,
  group_id      INT UNSIGNED NOT NULL,     -- Mỗi tài khoản thuộc 1 nhóm quyền
  username      VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  status        ENUM('active','locked','pending') NOT NULL DEFAULT 'active',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_deleted    TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (account_id),
  UNIQUE KEY uq_account_user (user_id),
  UNIQUE KEY uq_account_username (username),
  KEY idx_acc_group (group_id),
  CONSTRAINT fk_account_user  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  CONSTRAINT fk_account_group FOREIGN KEY (group_id) REFERENCES role_group(group_id)
) ENGINE=InnoDB;

-- 1.4. Bảng FUNCTION: Danh mục các phân hệ / chức năng của hệ thống
CREATE TABLE `function` (
  function_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name_function VARCHAR(100) NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_deleted    TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (function_id),
  UNIQUE KEY uq_func_name (name_function)
) ENGINE=InnoDB;

-- 1.5. Bảng ROLE_GROUP_FUNCTION: Ma trận 5 cờ quyền phân theo Nhóm quyền (gộp từ mapping + role)
CREATE TABLE role_group_function (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id    INT UNSIGNED NOT NULL,
  function_id INT UNSIGNED NOT NULL,
  `add`       TINYINT(1) NOT NULL DEFAULT 0,
  `edit`      TINYINT(1) NOT NULL DEFAULT 0,
  `delete`    TINYINT(1) NOT NULL DEFAULT 0,
  download    TINYINT(1) NOT NULL DEFAULT 0,
  `view`      TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_deleted  TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rgf (group_id, function_id),
  CONSTRAINT fk_rgf_group    FOREIGN KEY (group_id) REFERENCES role_group(group_id) ON DELETE CASCADE,
  CONSTRAINT fk_rgf_function FOREIGN KEY (function_id) REFERENCES `function`(function_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- 2. DANH MỤC & CƠ SỞ (5 BẢNG)
-- =====================================================================
CREATE TABLE mon_hoc (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ma        VARCHAR(20)  NOT NULL,
  ten       VARCHAR(100) NOT NULL,
  is_active TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mon_ma (ma),
  UNIQUE KEY uq_mon_ten (ten)
) ENGINE=InnoDB;

CREATE TABLE cap_lop (
  id      TINYINT UNSIGNED NOT NULL,
  ten     VARCHAR(30) NOT NULL,
  cap_hoc ENUM('tieu_hoc','thcs','thpt') NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_caplop_ten (ten)
) ENGINE=InnoDB;

CREATE TABLE khu_vuc (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ten        VARCHAR(100) NOT NULL,
  thanh_pho  VARCHAR(100) NOT NULL DEFAULT 'TP. Hồ Chí Minh',
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kv (ten, thanh_pho)
) ENGINE=InnoDB;

-- Bảng CO_SO: QUAN_LY_ID trỏ tới USERS để tránh FK vòng tròn với NHAN_VIEN.CO_SO_ID
CREATE TABLE co_so (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ma_co_so       VARCHAR(20) NOT NULL,
  ten_co_so      VARCHAR(150) NOT NULL,
  dia_chi        VARCHAR(255) NOT NULL,
  so_dien_thoai  VARCHAR(20) NULL,
  email          VARCHAR(150) NULL,
  khu_vuc_id     INT UNSIGNED NULL,
  quan_ly_id     BIGINT UNSIGNED NULL,      -- Trỏ tới USERS (tránh FK vòng)
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_coso_ma (ma_co_so),
  CONSTRAINT fk_coso_kv FOREIGN KEY (khu_vuc_id) REFERENCES khu_vuc(id) ON DELETE SET NULL,
  CONSTRAINT fk_coso_ql FOREIGN KEY (quan_ly_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE cau_hinh (
  khoa       VARCHAR(60)  NOT NULL,
  gia_tri    VARCHAR(255) NOT NULL,
  mo_ta      VARCHAR(255) NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (khoa)
) ENGINE=InnoDB;

-- Cập nhật ràng buộc khu_vuc_id của USERS sau khi bảng khu_vuc đã tạo
ALTER TABLE users ADD CONSTRAINT fk_users_kv FOREIGN KEY (khu_vuc_id) REFERENCES khu_vuc(id) ON DELETE SET NULL;

-- =====================================================================
-- 3. HỒ SƠ ĐỐI TƯỢNG (3 BẢNG)
-- =====================================================================

-- 3.1. Bảng NHAN_VIEN: Bỏ LUONG_CO_BAN và HOA_HONG_LOP (chỉ tính lương người dạy)
CREATE TABLE nhan_vien (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED NOT NULL,
  ma_nv         VARCHAR(20) NOT NULL,
  chuc_vu       VARCHAR(100) NULL,
  co_so_id      INT UNSIGNED NULL,         -- Nhân viên trực thuộc cơ sở
  ngay_vao_lam  DATE NULL,
  trang_thai    ENUM('dang_lam','nghi_viec') NOT NULL DEFAULT 'dang_lam',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_nv_user (user_id),
  UNIQUE KEY uq_nv_ma (ma_nv),
  CONSTRAINT fk_nv_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT fk_nv_coso FOREIGN KEY (co_so_id) REFERENCES co_so(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 3.2. Bảng NHAN_SU_CHUYEN_MON: Gộp Giáo viên, Gia sư, Trợ giảng
CREATE TABLE nhan_su_chuyen_mon (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id            BIGINT UNSIGNED NOT NULL,
  ma_ns              VARCHAR(20) NOT NULL,
  phan_loai          SET('giao_vien','gia_su','tro_giang') NOT NULL DEFAULT 'gia_su',
  gioi_tinh          ENUM('nam','nu','khac') NULL,
  cccd               VARCHAR(20) NULL,
  nghe_nghiep        ENUM('sinh_vien','giao_vien','cu_nhan','tu_do') NOT NULL DEFAULT 'sinh_vien',
  don_vi_cong_tac    VARCHAR(150) NULL,
  gioi_thieu         TEXT NULL,
  kinh_nghiem_nam    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  hinh_thuc_day      SET('online','offline') NOT NULL DEFAULT 'offline',
  don_gia_gio_chuan  DECIMAL(12,0) NOT NULL DEFAULT 0,
  ngan_hang          VARCHAR(100) NULL,
  so_tai_khoan       VARCHAR(30)  NULL,
  chu_tai_khoan      VARCHAR(150) NULL,
  trang_thai         ENUM('hoat_dong','tam_nghi','ngung_hop_tac') NOT NULL DEFAULT 'hoat_dong',
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ns_user (user_id),
  UNIQUE KEY uq_ns_ma (ma_ns),
  UNIQUE KEY uq_ns_cccd (cccd),
  KEY idx_ns_tt (trang_thai),
  CONSTRAINT fk_ns_user FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- 3.3. Bảng HOC_VIEN: Profile con gắn với PHU_HUYNH_USER_ID (USERS)
CREATE TABLE hoc_vien (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  phu_huynh_user_id  BIGINT UNSIGNED NOT NULL,
  ma_hv              VARCHAR(20) NOT NULL,
  ho_ten             VARCHAR(150) NOT NULL,
  ngay_sinh          DATE NULL,
  gioi_tinh          ENUM('nam','nu','khac') NULL,
  truong             VARCHAR(150) NULL,
  cap_lop_id         TINYINT UNSIGNED NULL,
  dia_chi            VARCHAR(255) NULL,
  khu_vuc_id         INT UNSIGNED NULL,
  ghi_chu            TEXT NULL,
  trang_thai         ENUM('dang_hoc','tam_nghi','hoan_thanh','nghi_han') NOT NULL DEFAULT 'dang_hoc',
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hv_ma (ma_hv),
  KEY idx_hv_ph (phu_huynh_user_id),
  KEY idx_hv_ten (ho_ten),
  KEY idx_hv_tt (trang_thai),
  CONSTRAINT fk_hv_ph   FOREIGN KEY (phu_huynh_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_hv_cap  FOREIGN KEY (cap_lop_id) REFERENCES cap_lop(id) ON DELETE SET NULL,
  CONSTRAINT fk_hv_kv   FOREIGN KEY (khu_vuc_id) REFERENCES khu_vuc(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 4. CHUYÊN MÔN GIẢNG DẠY (3 BẢNG)
-- =====================================================================
CREATE TABLE nhan_su_mon (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nhan_su_id          BIGINT UNSIGNED NOT NULL,
  mon_hoc_id          INT UNSIGNED NOT NULL,
  cap_lop_tu          TINYINT UNSIGNED NOT NULL,
  cap_lop_den         TINYINT UNSIGNED NOT NULL,
  hoc_phi_buoi_de_xuat DECIMAL(12,0) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_nsm (nhan_su_id, mon_hoc_id, cap_lop_tu),
  KEY idx_nsm_tim (mon_hoc_id, cap_lop_tu, cap_lop_den, hoc_phi_buoi_de_xuat),
  CONSTRAINT fk_nsm_ns  FOREIGN KEY (nhan_su_id) REFERENCES nhan_su_chuyen_mon(id) ON DELETE CASCADE,
  CONSTRAINT fk_nsm_mon FOREIGN KEY (mon_hoc_id) REFERENCES mon_hoc(id),
  CONSTRAINT fk_nsm_tu  FOREIGN KEY (cap_lop_tu) REFERENCES cap_lop(id),
  CONSTRAINT fk_nsm_den FOREIGN KEY (cap_lop_den) REFERENCES cap_lop(id),
  CONSTRAINT ck_nsm_lop CHECK (cap_lop_tu <= cap_lop_den),
  CONSTRAINT ck_nsm_phi CHECK (hoc_phi_buoi_de_xuat >= 0)
) ENGINE=InnoDB;

CREATE TABLE nhan_su_khu_vuc (
  nhan_su_id  BIGINT UNSIGNED NOT NULL,
  khu_vuc_id  INT UNSIGNED NOT NULL,
  PRIMARY KEY (nhan_su_id, khu_vuc_id),
  KEY idx_nskv_kv (khu_vuc_id),
  CONSTRAINT fk_nskv_ns FOREIGN KEY (nhan_su_id) REFERENCES nhan_su_chuyen_mon(id) ON DELETE CASCADE,
  CONSTRAINT fk_nskv_kv FOREIGN KEY (khu_vuc_id) REFERENCES khu_vuc(id)
) ENGINE=InnoDB;

CREATE TABLE nhan_su_lich_ranh (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nhan_su_id    BIGINT UNSIGNED NOT NULL,
  thu           TINYINT UNSIGNED NOT NULL,
  gio_bat_dau   TIME NOT NULL,
  gio_ket_thuc  TIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_nslr (nhan_su_id, thu),
  CONSTRAINT fk_nslr_ns FOREIGN KEY (nhan_su_id) REFERENCES nhan_su_chuyen_mon(id) ON DELETE CASCADE,
  CONSTRAINT ck_nslr_thu CHECK (thu BETWEEN 2 AND 8),
  CONSTRAINT ck_nslr_gio CHECK (gio_bat_dau < gio_ket_thuc)
) ENGINE=InnoDB;

-- =====================================================================
-- 5. TUYỂN SINH & GHÉP LỚP (3 BẢNG)
-- =====================================================================

-- 5.1. Bảng YEU_CAU_HOC: HOC_VIEN_ID NULLABLE (cho khách mới), bỏ JSON
CREATE TABLE yeu_cau_hoc (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ma_yc               VARCHAR(20) NOT NULL,
  hoc_vien_id         BIGINT UNSIGNED NULL,        -- Cho phép NULL nếu khách chưa có hồ sơ
  ho_ten_lien_he      VARCHAR(150) NULL,           -- Khách vãng lai để lại tên
  sdt_lien_he         VARCHAR(20)  NULL,           -- Khách vãng lai để lại SĐT
  email_lien_he       VARCHAR(150) NULL,
  mon_hoc_id          INT UNSIGNED NOT NULL,
  cap_lop_id          TINYINT UNSIGNED NOT NULL,
  loai_lop_mong_muon  ENUM('kem_1_1','kem_1_2','nhom') NOT NULL DEFAULT 'kem_1_1',
  co_so_mong_muon_id  INT UNSIGNED NULL,           -- Cơ sở mong muốn nếu học lớp nhóm
  hinh_thuc           ENUM('online','offline','ca_hai') NOT NULL DEFAULT 'offline',
  khu_vuc_id          INT UNSIGNED NULL,
  dia_chi_hoc         VARCHAR(255) NULL,
  ngan_sach_min       DECIMAL(12,0) NULL,
  ngan_sach_max       DECIMAL(12,0) NULL,
  muc_tieu            TEXT NULL,
  ghi_chu             TEXT NULL,
  nguon               ENUM('website','dien_thoai','truc_tiep','gioi_thieu') NOT NULL DEFAULT 'truc_tiep',
  trang_thai          ENUM('moi','dang_tim','da_de_xuat','da_chot','huy') NOT NULL DEFAULT 'moi',
  nhan_vien_id        BIGINT UNSIGNED NOT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_yc_ma (ma_yc),
  KEY idx_yc_tt (trang_thai, created_at),
  KEY idx_yc_hv (hoc_vien_id),
  KEY idx_yc_mon (mon_hoc_id, cap_lop_id),
  CONSTRAINT fk_yc_hv   FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id) ON DELETE SET NULL,
  CONSTRAINT fk_yc_mon  FOREIGN KEY (mon_hoc_id) REFERENCES mon_hoc(id),
  CONSTRAINT fk_yc_cap  FOREIGN KEY (cap_lop_id) REFERENCES cap_lop(id),
  CONSTRAINT fk_yc_coso FOREIGN KEY (co_so_mong_muon_id) REFERENCES co_so(id) ON DELETE SET NULL,
  CONSTRAINT fk_yc_kv   FOREIGN KEY (khu_vuc_id) REFERENCES khu_vuc(id) ON DELETE SET NULL,
  CONSTRAINT fk_yc_nv   FOREIGN KEY (nhan_vien_id) REFERENCES nhan_vien(id),
  CONSTRAINT ck_yc_ns   CHECK (ngan_sach_min IS NULL OR ngan_sach_max IS NULL OR ngan_sach_min <= ngan_sach_max)
) ENGINE=InnoDB;

-- 5.2. Bảng YEU_CAU_LICH: Tách riêng bảng để so khớp SQL với lịch rảnh người dạy
CREATE TABLE yeu_cau_lich (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  yeu_cau_id    BIGINT UNSIGNED NOT NULL,
  thu           TINYINT UNSIGNED NOT NULL,
  gio_bat_dau   TIME NOT NULL,
  gio_ket_thuc  TIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ycl (yeu_cau_id),
  CONSTRAINT fk_ycl_yc FOREIGN KEY (yeu_cau_id) REFERENCES yeu_cau_hoc(id) ON DELETE CASCADE,
  CONSTRAINT ck_ycl_thu CHECK (thu BETWEEN 2 AND 8),
  CONSTRAINT ck_ycl_gio CHECK (gio_bat_dau < gio_ket_thuc)
) ENGINE=InnoDB;

-- 5.3. Bảng DE_XUAT_NHAN_SU
CREATE TABLE de_xuat_nhan_su (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  yeu_cau_id        BIGINT UNSIGNED NOT NULL,
  nhan_su_id        BIGINT UNSIGNED NOT NULL,
  nhan_vien_id      BIGINT UNSIGNED NOT NULL,
  hoc_phi_de_xuat   DECIMAL(12,0) NULL,
  tt_nhan_su        ENUM('cho','dong_y','tu_choi') NOT NULL DEFAULT 'cho',
  tt_phu_huynh      ENUM('cho','dong_y','tu_choi') NOT NULL DEFAULT 'cho',
  ly_do_tu_choi     VARCHAR(255) NULL,
  ghi_chu           VARCHAR(255) NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dx (yeu_cau_id, nhan_su_id),
  KEY idx_dx_ns (nhan_su_id, tt_nhan_su),
  CONSTRAINT fk_dx_yc FOREIGN KEY (yeu_cau_id) REFERENCES yeu_cau_hoc(id) ON DELETE CASCADE,
  CONSTRAINT fk_dx_ns FOREIGN KEY (nhan_su_id) REFERENCES nhan_su_chuyen_mon(id),
  CONSTRAINT fk_dx_nv FOREIGN KEY (nhan_vien_id) REFERENCES nhan_vien(id)
) ENGINE=InnoDB;

-- =====================================================================
-- 6. LỚP HỌC & LỊCH (3 BẢNG)
-- =====================================================================

-- 6.1. Bảng LOP_HOC: Thêm CACH_THU_PHI, HOC_PHI_KHOA, SI_SO_TOI_THIEU, PHONG_HOC dạng chuỗi
CREATE TABLE lop_hoc (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ma_lop             VARCHAR(30) NOT NULL,
  ten_lop            VARCHAR(150) NOT NULL,
  loai_lop           ENUM('kem_1_1','kem_1_2','nhom') NOT NULL DEFAULT 'kem_1_1',
  de_xuat_id         BIGINT UNSIGNED NULL,
  mon_hoc_id         INT UNSIGNED NOT NULL,
  cap_lop_id         TINYINT UNSIGNED NOT NULL,
  nguoi_day_chinh_id BIGINT UNSIGNED NOT NULL,   -- Giáo viên (nhóm) hoặc Gia sư (kèm)
  co_so_id           INT UNSIGNED NULL,          -- Cơ sở đào tạo (cho lớp nhóm)
  phong_hoc          VARCHAR(50) NULL,           -- Tên phòng học (chuỗi nhập tay)
  hinh_thuc          ENUM('online','offline') NOT NULL DEFAULT 'offline',
  dia_diem           VARCHAR(255) NULL,
  link_hoc           VARCHAR(255) NULL,
  khu_vuc_id         INT UNSIGNED NULL,
  si_so_toi_thieu    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  si_so_toi_da       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  cach_thu_phi       ENUM('theo_buoi','theo_thang','theo_khoa') NOT NULL DEFAULT 'theo_buoi',
  hoc_phi_buoi       DECIMAL(12,0) NOT NULL DEFAULT 0,
  hoc_phi_khoa       DECIMAL(12,0) NOT NULL DEFAULT 0,
  muc_tra_chinh      DECIMAL(12,0) NOT NULL,     -- Trả người dạy chính
  muc_tra_tro_giang  DECIMAL(12,0) NOT NULL DEFAULT 0, -- Trả trợ giảng
  cach_tinh_luong    ENUM('theo_buoi','theo_gio') NOT NULL DEFAULT 'theo_buoi',
  so_buoi_du_kien    SMALLINT UNSIGNED NULL,
  ngay_bat_dau       DATE NOT NULL,
  ngay_ket_thuc      DATE NULL,
  trang_thai         ENUM('sap_bat_dau','dang_hoc','tam_dung','hoan_thanh','huy') NOT NULL DEFAULT 'sap_bat_dau',
  nhan_vien_id       BIGINT UNSIGNED NOT NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at         DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lop_ma (ma_lop),
  KEY idx_lop_ndc (nguoi_day_chinh_id, trang_thai),
  KEY idx_lop_tt (trang_thai),
  KEY idx_lop_loai (loai_lop),
  CONSTRAINT fk_lop_dx   FOREIGN KEY (de_xuat_id) REFERENCES de_xuat_nhan_su(id) ON DELETE SET NULL,
  CONSTRAINT fk_lop_mon  FOREIGN KEY (mon_hoc_id) REFERENCES mon_hoc(id),
  CONSTRAINT fk_lop_cap  FOREIGN KEY (cap_lop_id) REFERENCES cap_lop(id),
  CONSTRAINT fk_lop_ndc  FOREIGN KEY (nguoi_day_chinh_id) REFERENCES nhan_su_chuyen_mon(id),
  CONSTRAINT fk_lop_coso FOREIGN KEY (co_so_id) REFERENCES co_so(id) ON DELETE SET NULL,
  CONSTRAINT fk_lop_kv   FOREIGN KEY (khu_vuc_id) REFERENCES khu_vuc(id) ON DELETE SET NULL,
  CONSTRAINT fk_lop_nv   FOREIGN KEY (nhan_vien_id) REFERENCES nhan_vien(id),
  CONSTRAINT ck_lop_ngay CHECK (ngay_ket_thuc IS NULL OR ngay_ket_thuc >= ngay_bat_dau),
  CONSTRAINT ck_lop_phi  CHECK (hoc_phi_buoi >= 0 AND hoc_phi_khoa >= 0 AND muc_tra_chinh >= 0 AND muc_tra_tro_giang >= 0),
  CONSTRAINT ck_lop_siso CHECK (si_so_toi_da >= si_so_toi_thieu AND si_so_toi_thieu >= 1)
) ENGINE=InnoDB;

-- 6.2. Bảng LOP_HOC_VIEN: Thêm trạng thái cho_xep_lop và hoc_thu
CREATE TABLE lop_hoc_vien (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lop_id       BIGINT UNSIGNED NOT NULL,
  hoc_vien_id  BIGINT UNSIGNED NOT NULL,
  ngay_vao     DATE NOT NULL,
  ngay_roi     DATE NULL,
  trang_thai   ENUM('dang_hoc','tam_nghi','da_roi','cho_xep_lop','hoc_thu') NOT NULL DEFAULT 'dang_hoc',
  PRIMARY KEY (id),
  UNIQUE KEY uq_lhv (lop_id, hoc_vien_id),
  KEY idx_lhv_hv (hoc_vien_id),
  CONSTRAINT fk_lhv_lop FOREIGN KEY (lop_id) REFERENCES lop_hoc(id),
  CONSTRAINT fk_lhv_hv  FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id),
  CONSTRAINT ck_lhv_ngay CHECK (ngay_roi IS NULL OR ngay_roi >= ngay_vao)
) ENGINE=InnoDB;

-- 6.3. Bảng LICH_HOC
CREATE TABLE lich_hoc (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lop_id        BIGINT UNSIGNED NOT NULL,
  thu           TINYINT UNSIGNED NOT NULL,
  gio_bat_dau   TIME NOT NULL,
  gio_ket_thuc  TIME NOT NULL,
  phong_hoc     VARCHAR(50) NULL,
  hieu_luc_tu   DATE NOT NULL,
  hieu_luc_den  DATE NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_lich (lop_id, thu, gio_bat_dau, hieu_luc_tu),
  CONSTRAINT fk_lich_lop FOREIGN KEY (lop_id) REFERENCES lop_hoc(id),
  CONSTRAINT ck_lich_thu CHECK (thu BETWEEN 2 AND 8),
  CONSTRAINT ck_lich_gio CHECK (gio_bat_dau < gio_ket_thuc)
) ENGINE=InnoDB;

-- =====================================================================
-- 7. VẬN HÀNH BUỔI HỌC (4 BẢNG)
-- =====================================================================

-- 7.1. Bảng BUOI_HOC: Gộp báo cáo buổi học (chủ đề, nội dung, file bài giảng)
CREATE TABLE buoi_hoc (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lop_id             BIGINT UNSIGNED NOT NULL,
  lich_hoc_id        BIGINT UNSIGNED NULL,
  loai               ENUM('thuong','bu') NOT NULL DEFAULT 'thuong',
  buoi_goc_id        BIGINT UNSIGNED NULL,
  ngay               DATE NOT NULL,
  gio_bat_dau        TIME NOT NULL,
  gio_ket_thuc       TIME NOT NULL,
  phong_hoc          VARCHAR(50) NULL,
  chu_de_bai_hoc     VARCHAR(255) NULL,
  noi_dung_bai_hoc   TEXT NULL,
  file_bai_giang_url VARCHAR(255) NULL,
  trang_thai         ENUM('du_kien','dang_day','da_day','huy','doi') NOT NULL DEFAULT 'du_kien',
  huy_boi            ENUM('trung_tam','giao_vien','gia_su','hoc_vien','khac') NULL,
  ly_do_huy_doi      VARCHAR(255) NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_buoi (lop_id, ngay, gio_bat_dau),
  KEY idx_buoi_ngay (ngay, trang_thai),
  KEY idx_buoi_goc (buoi_goc_id),
  CONSTRAINT fk_buoi_lop  FOREIGN KEY (lop_id) REFERENCES lop_hoc(id),
  CONSTRAINT fk_buoi_lich FOREIGN KEY (lich_hoc_id) REFERENCES lich_hoc(id) ON DELETE SET NULL,
  CONSTRAINT fk_buoi_goc  FOREIGN KEY (buoi_goc_id) REFERENCES buoi_hoc(id),
  CONSTRAINT ck_buoi_gio  CHECK (gio_bat_dau < gio_ket_thuc),
  CONSTRAINT ck_buoi_bu   CHECK (loai = 'thuong' OR buoi_goc_id IS NOT NULL)
) ENGINE=InnoDB;

-- 7.2. Bảng PHAN_CONG_BUOI_HOC: Lưu đơn giá snapshot, gán BANG_CONG_ID khi chốt
CREATE TABLE phan_cong_buoi_hoc (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  buoi_hoc_id     BIGINT UNSIGNED NOT NULL,
  user_id         BIGINT UNSIGNED NOT NULL,
  vai_tro         ENUM('day_chinh','tro_giang') NOT NULL,
  trang_thai      ENUM('da_phan_cong','da_go','da_day','vang') NOT NULL DEFAULT 'da_phan_cong',
  don_gia         DECIMAL(12,0) NOT NULL DEFAULT 0,
  bang_cong_id    BIGINT UNSIGNED NULL,        -- Gán khi chốt công tháng (chống tính trùng)
  phan_cong_boi   BIGINT UNSIGNED NULL,
  go_boi          BIGINT UNSIGNED NULL,
  go_luc          DATETIME NULL,
  ly_do_go        VARCHAR(255) NULL,
  checkin_luc     DATETIME NULL,
  checkin_lat     DECIMAL(10,7) NULL,
  checkin_lng     DECIMAL(10,7) NULL,
  checkin_tre     TINYINT(1) NOT NULL DEFAULT 0,
  checkin_ghi_chu VARCHAR(255) NULL,
  checkout_luc    DATETIME NULL,
  so_phut_thuc_te INT GENERATED ALWAYS AS (
      IF(checkin_luc IS NULL OR checkout_luc IS NULL, NULL, TIMESTAMPDIFF(MINUTE, checkin_luc, checkout_luc))
  ) STORED,
  trang_thai_cong ENUM('chua_check','hop_le','cho_duyet','tu_choi') NOT NULL DEFAULT 'chua_check',
  -- Đảm bảo chỉ 1 người dạy chính mỗi buổi
  day_chinh_key   BIGINT UNSIGNED GENERATED ALWAYS AS (
      IF(vai_tro = 'day_chinh' AND trang_thai <> 'da_go', buoi_hoc_id, NULL)
  ) VIRTUAL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pc_buoi_user (buoi_hoc_id, user_id),
  UNIQUE KEY uq_pc_day_chinh (day_chinh_key),
  KEY idx_pc_user (user_id, trang_thai),
  KEY idx_pc_cong (user_id, trang_thai_cong),
  CONSTRAINT fk_pc_buoi FOREIGN KEY (buoi_hoc_id) REFERENCES buoi_hoc(id),
  CONSTRAINT fk_pc_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT fk_pc_pcb  FOREIGN KEY (phan_cong_boi) REFERENCES users(user_id) ON DELETE SET NULL,
  CONSTRAINT fk_pc_gob  FOREIGN KEY (go_boi) REFERENCES users(user_id) ON DELETE SET NULL,
  CONSTRAINT ck_pc_out  CHECK (checkout_luc IS NULL OR (checkin_luc IS NOT NULL AND checkout_luc > checkin_luc))
) ENGINE=InnoDB;

-- 7.3. Bảng DIEU_CHINH_CONG
CREATE TABLE dieu_chinh_cong (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  phan_cong_id     BIGINT UNSIGNED NOT NULL,
  loai             ENUM('bo_sung_checkin','sua_gio','huy_cong','tinh_cong') NOT NULL,
  checkin_cu       DATETIME NULL,
  checkout_cu      DATETIME NULL,
  checkin_moi      DATETIME NULL,
  checkout_moi     DATETIME NULL,
  ly_do            VARCHAR(255) NOT NULL,
  nguoi_yeu_cau_id BIGINT UNSIGNED NOT NULL,
  trang_thai       ENUM('cho_duyet','da_duyet','tu_choi') NOT NULL DEFAULT 'cho_duyet',
  nguoi_duyet_id   BIGINT UNSIGNED NULL,
  duyet_luc        DATETIME NULL,
  ghi_chu_duyet    VARCHAR(255) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dcc_pc (phan_cong_id),
  KEY idx_dcc_tt (trang_thai, created_at),
  CONSTRAINT fk_dcc_pc  FOREIGN KEY (phan_cong_id) REFERENCES phan_cong_buoi_hoc(id),
  CONSTRAINT fk_dcc_yc  FOREIGN KEY (nguoi_yeu_cau_id) REFERENCES users(user_id),
  CONSTRAINT fk_dcc_duy FOREIGN KEY (nguoi_duyet_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 7.4. Bảng DON_XIN_NGHI
CREATE TABLE don_xin_nghi (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  buoi_hoc_id    BIGINT UNSIGNED NOT NULL,
  doi_tuong      ENUM('hoc_vien','day_chinh','tro_giang') NOT NULL,
  hoc_vien_id    BIGINT UNSIGNED NULL,
  nguoi_gui_id   BIGINT UNSIGNED NOT NULL,
  ly_do          VARCHAR(500) NOT NULL,
  trang_thai     ENUM('cho_duyet','da_duyet','tu_choi','da_huy') NOT NULL DEFAULT 'cho_duyet',
  nguoi_duyet_id BIGINT UNSIGNED NULL,
  duyet_luc      DATETIME NULL,
  ly_do_tu_choi  VARCHAR(255) NULL,
  buoi_bu_id     BIGINT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dxn_buoi (buoi_hoc_id),
  KEY idx_dxn_tt (trang_thai, created_at),
  CONSTRAINT fk_dxn_buoi FOREIGN KEY (buoi_hoc_id) REFERENCES buoi_hoc(id),
  CONSTRAINT fk_dxn_hv   FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id),
  CONSTRAINT fk_dxn_gui  FOREIGN KEY (nguoi_gui_id) REFERENCES users(user_id),
  CONSTRAINT fk_dxn_duy  FOREIGN KEY (nguoi_duyet_id) REFERENCES users(user_id) ON DELETE SET NULL,
  CONSTRAINT fk_dxn_bu   FOREIGN KEY (buoi_bu_id) REFERENCES buoi_hoc(id),
  CONSTRAINT ck_dxn_hv   CHECK (doi_tuong <> 'hoc_vien' OR hoc_vien_id IS NOT NULL)
) ENGINE=InnoDB;

-- =====================================================================
-- 8. HỌC VỤ & ĐÁNH GIÁ (3 BẢNG)
-- =====================================================================

-- 8.1. Bảng BAI_TAP: Giao bài tập về nhà (có link phiếu bài tập làm giấy)
CREATE TABLE bai_tap (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  buoi_hoc_id     BIGINT UNSIGNED NOT NULL,
  tieu_de         VARCHAR(255) NOT NULL,
  noi_dung        TEXT NULL,
  file_de_bai_url VARCHAR(255) NULL,
  han_nop         DATETIME NULL,
  nguoi_tao_id    BIGINT UNSIGNED NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_bt_buoi (buoi_hoc_id),
  CONSTRAINT fk_bt_buoi FOREIGN KEY (buoi_hoc_id) REFERENCES buoi_hoc(id),
  CONSTRAINT fk_bt_user FOREIGN KEY (nguoi_tao_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- 8.2. Bảng SO_THEO_DOI_BUOI_HOC: Điểm danh (4 trạng thái) + Điểm bài tập giấy + Nhận xét
CREATE TABLE so_theo_doi_buoi_hoc (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  buoi_hoc_id         BIGINT UNSIGNED NOT NULL,
  hoc_vien_id         BIGINT UNSIGNED NOT NULL,
  trang_thai_diem_danh ENUM('co_mat','di_tre','vang_co_phep','vang_khong_phep') NOT NULL,
  bai_tap_id          BIGINT UNSIGNED NULL,        -- Cho biết điểm bài tập giấy thuộc bài nào
  diem_bai_tap_giay   DECIMAL(4,2) NULL,           -- Điểm chấm bài tập giấy trên lớp
  nhan_xet_bai_tap    VARCHAR(255) NULL,
  muc_tiep_thu        TINYINT UNSIGNED NULL,       -- Thang 1..5
  thai_do             ENUM('tot','kha','trung_binh','can_cai_thien') NULL,
  nhan_xet_buoi_hoc   TEXT NULL,
  huong_cai_thien     TEXT NULL,
  nguoi_nhap_id       BIGINT UNSIGNED NOT NULL,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sotd_buoi_hv (buoi_hoc_id, hoc_vien_id),
  KEY idx_sotd_hv (hoc_vien_id),
  CONSTRAINT fk_sotd_buoi FOREIGN KEY (buoi_hoc_id) REFERENCES buoi_hoc(id),
  CONSTRAINT fk_sotd_hv   FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id),
  CONSTRAINT fk_sotd_bt   FOREIGN KEY (bai_tap_id) REFERENCES bai_tap(id) ON DELETE SET NULL,
  CONSTRAINT fk_sotd_user FOREIGN KEY (nguoi_nhap_id) REFERENCES users(user_id),
  CONSTRAINT ck_sotd_diem CHECK (diem_bai_tap_giay IS NULL OR (diem_bai_tap_giay >= 0 AND diem_bai_tap_giay <= 10)),
  CONSTRAINT ck_sotd_tt   CHECK (muc_tiep_thu IS NULL OR muc_tiep_thu BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- 8.3. Bảng KET_QUA_HOC_TAP: Bài kiểm tra định kỳ, thi thử
CREATE TABLE ket_qua_hoc_tap (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lop_id        BIGINT UNSIGNED NOT NULL,
  hoc_vien_id   BIGINT UNSIGNED NOT NULL,
  loai          ENUM('kiem_tra','thi_thu','thi_that','khac') NOT NULL DEFAULT 'kiem_tra',
  ten           VARCHAR(150) NOT NULL,
  diem          DECIMAL(5,2) NOT NULL,
  diem_toi_da   DECIMAL(5,2) NOT NULL DEFAULT 10,
  ngay          DATE NOT NULL,
  nhan_xet      VARCHAR(500) NULL,
  nguoi_nhap_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY idx_kq (hoc_vien_id, ngay),
  KEY idx_kq_lop (lop_id),
  CONSTRAINT fk_kq_lop  FOREIGN KEY (lop_id) REFERENCES lop_hoc(id),
  CONSTRAINT fk_kq_hv   FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id),
  CONSTRAINT fk_kq_user FOREIGN KEY (nguoi_nhap_id) REFERENCES users(user_id),
  CONSTRAINT ck_kq_diem CHECK (diem >= 0 AND diem <= diem_toi_da)
) ENGINE=InnoDB;

-- =====================================================================
-- 9. CÔNG, LƯƠNG & HỌC PHÍ (4 BẢNG)
-- =====================================================================

-- 9.1. Bảng BANG_CONG_THANG: Chốt công tháng cho người dạy
CREATE TABLE bang_cong_thang (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        BIGINT UNSIGNED NOT NULL,
  thang          TINYINT UNSIGNED NOT NULL,
  nam            SMALLINT UNSIGNED NOT NULL,
  so_buoi        INT UNSIGNED NOT NULL DEFAULT 0,
  tong_phut      INT UNSIGNED NOT NULL DEFAULT 0,
  so_buoi_bu     INT UNSIGNED NOT NULL DEFAULT 0,
  so_buoi_vang   INT UNSIGNED NOT NULL DEFAULT 0,
  so_lan_tre     INT UNSIGNED NOT NULL DEFAULT 0,
  tong_thu_nhap  DECIMAL(12,0) NOT NULL DEFAULT 0,
  thuong         DECIMAL(12,0) NOT NULL DEFAULT 0,
  khau_tru       DECIMAL(12,0) NOT NULL DEFAULT 0,
  thuc_nhan      DECIMAL(12,0) GENERATED ALWAYS AS (tong_thu_nhap + thuong - khau_tru) STORED,
  trang_thai     ENUM('mo','da_chot','da_tra') NOT NULL DEFAULT 'mo',
  chot_boi       BIGINT UNSIGNED NULL,
  chot_luc       DATETIME NULL,
  ngay_tra       DATE NULL,
  ghi_chu        VARCHAR(255) NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bc_thang (user_id, nam, thang),
  KEY idx_bc_tt (nam, thang, trang_thai),
  CONSTRAINT fk_bct_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT fk_bct_chot FOREIGN KEY (chot_boi) REFERENCES users(user_id) ON DELETE SET NULL,
  CONSTRAINT ck_bct_thang CHECK (thang BETWEEN 1 AND 12)
) ENGINE=InnoDB;

-- Thêm khóa ngoại bang_cong_id trong phan_cong_buoi_hoc trỏ đến bang_cong_thang
ALTER TABLE phan_cong_buoi_hoc ADD CONSTRAINT fk_pc_bct FOREIGN KEY (bang_cong_id) REFERENCES bang_cong_thang(id) ON DELETE SET NULL;

-- 9.2. Bảng HOA_DON: Học phí theo tháng hoặc theo khóa
CREATE TABLE hoa_don (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ma_hd           VARCHAR(30) NOT NULL,
  lop_hoc_vien_id BIGINT UNSIGNED NOT NULL,
  thang           TINYINT UNSIGNED NOT NULL,
  nam             SMALLINT UNSIGNED NOT NULL,
  tong_tien       DECIMAL(12,0) NOT NULL DEFAULT 0,
  giam_gia        DECIMAL(12,0) NOT NULL DEFAULT 0,
  phai_thu        DECIMAL(12,0) GENERATED ALWAYS AS (tong_tien - giam_gia) STORED,
  da_thu          DECIMAL(12,0) NOT NULL DEFAULT 0,
  trang_thai      ENUM('nhap','chua_thanh_toan','mot_phan','da_thanh_toan','huy') NOT NULL DEFAULT 'nhap',
  han_thanh_toan  DATE NULL,
  nguoi_lap_id    BIGINT UNSIGNED NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hd_ma (ma_hd),
  UNIQUE KEY uq_hd_ky (lop_hoc_vien_id, nam, thang),
  KEY idx_hd_tt (trang_thai, han_thanh_toan),
  CONSTRAINT fk_hd_lhv  FOREIGN KEY (lop_hoc_vien_id) REFERENCES lop_hoc_vien(id),
  CONSTRAINT fk_hd_user FOREIGN KEY (nguoi_lap_id) REFERENCES users(user_id),
  CONSTRAINT ck_hd_tien CHECK (tong_tien >= 0 AND giam_gia >= 0 AND giam_gia <= tong_tien AND da_thu >= 0),
  CONSTRAINT ck_hd_thang CHECK (thang BETWEEN 1 AND 12)
) ENGINE=InnoDB;

-- 9.3. Bảng HOA_DON_CHI_TIET: BUOI_HOC_ID NULLABLE, thêm LOAI_KHOAN, MO_TA
CREATE TABLE hoa_don_chi_tiet (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  hoa_don_id  BIGINT UNSIGNED NOT NULL,
  buoi_hoc_id BIGINT UNSIGNED NULL,        -- Cho phép NULL đối với học phí trọn gói/khóa/tháng cố định
  hoc_vien_id BIGINT UNSIGNED NOT NULL,
  loai_khoan  ENUM('buoi_hoc','khoa_hoc','thang_co_dinh','phu_phi') NOT NULL DEFAULT 'buoi_hoc',
  mo_ta       VARCHAR(255) NULL,
  don_gia     DECIMAL(12,0) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_hdct_hd (hoa_don_id),
  CONSTRAINT fk_hdct_hd   FOREIGN KEY (hoa_don_id) REFERENCES hoa_don(id) ON DELETE CASCADE,
  CONSTRAINT fk_hdct_buoi FOREIGN KEY (buoi_hoc_id) REFERENCES buoi_hoc(id) ON DELETE SET NULL,
  CONSTRAINT fk_hdct_hv   FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id)
) ENGINE=InnoDB;

-- 9.4. Bảng THANH_TOAN: Thêm LOAI ('thu', 'hoan')
CREATE TABLE thanh_toan (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  hoa_don_id      BIGINT UNSIGNED NOT NULL,
  loai            ENUM('thu','hoan') NOT NULL DEFAULT 'thu',
  so_tien         DECIMAL(12,0) NOT NULL,
  phuong_thuc     ENUM('tien_mat','chuyen_khoan','vi_dien_tu','the') NOT NULL,
  ma_giao_dich    VARCHAR(60) NULL,
  ngay_thanh_toan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  nguoi_thu_id    BIGINT UNSIGNED NOT NULL,
  trang_thai      ENUM('hop_le','da_huy') NOT NULL DEFAULT 'hop_le',
  ghi_chu         VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_tt_hd (hoa_don_id),
  KEY idx_tt_ngay (ngay_thanh_toan),
  CONSTRAINT fk_tt_hd   FOREIGN KEY (hoa_don_id) REFERENCES hoa_don(id),
  CONSTRAINT fk_tt_user FOREIGN KEY (nguoi_thu_id) REFERENCES users(user_id),
  CONSTRAINT ck_tt_tien CHECK (so_tien > 0)
) ENGINE=InnoDB;

-- =====================================================================
-- 10. THÔNG BÁO & NHẬT KÝ HỆ THỐNG (2 BẢNG)
-- =====================================================================

-- 10.1. Bảng THONG_BAO: Thêm SO_LAN_THU_EMAIL
CREATE TABLE thong_bao (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id           BIGINT UNSIGNED NOT NULL,
  loai              ENUM('phan_cong','go_phan_cong','lop_moi','doi_lich','nhac_checkin','don_nghi','duyet_don',
                          'nhan_xet','hoa_don','chot_cong','de_xuat','he_thong') NOT NULL,
  tieu_de           VARCHAR(255) NOT NULL,
  noi_dung          VARCHAR(1000) NULL,
  doi_tuong_loai    VARCHAR(30) NULL,
  doi_tuong_id      BIGINT UNSIGNED NULL,
  khoa_chong_trung  VARCHAR(100) NULL,
  da_doc            TINYINT(1) NOT NULL DEFAULT 0,
  doc_luc           DATETIME NULL,
  co_gui_email      TINYINT(1) NOT NULL DEFAULT 0,
  so_lan_thu_email  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  trang_thai_email  ENUM('chua_gui','da_gui','loi') NOT NULL DEFAULT 'chua_gui',
  loi_email         VARCHAR(255) NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tb_chong_trung (user_id, khoa_chong_trung),
  KEY idx_tb_user (user_id, da_doc, created_at),
  CONSTRAINT fk_tb_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10.2. Bảng AUDIT_LOG: Ghi log thao tác nhạy cảm
CREATE TABLE audit_log (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED NULL,
  hanh_dong    ENUM('them','sua','xoa','dang_nhap','chot_cong','mo_khoa_cong','duyet','khac') NOT NULL,
  bang         VARCHAR(60) NOT NULL,
  ban_ghi_id   BIGINT UNSIGNED NULL,
  du_lieu_cu   JSON NULL,
  du_lieu_moi  JSON NULL,
  ip           VARCHAR(45) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_al_bang (bang, ban_ghi_id),
  KEY idx_al_user (user_id, created_at),
  CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =====================================================================
-- 11. HÀM, TRIGGER & THỦ TỤC NGHIỆP VỤ
-- =====================================================================
DELIMITER $$

-- Hàm kiểm tra trùng lịch nhân sự
CREATE FUNCTION fn_trung_lich(p_user BIGINT UNSIGNED, p_buoi BIGINT UNSIGNED, p_loai_tru BIGINT UNSIGNED)
RETURNS TINYINT(1)
READS SQL DATA
BEGIN
  DECLARE v_ngay DATE; DECLARE v_bd TIME; DECLARE v_kt TIME; DECLARE v_n INT;
  SELECT ngay, gio_bat_dau, gio_ket_thuc INTO v_ngay, v_bd, v_kt FROM buoi_hoc WHERE id = p_buoi;
  SELECT COUNT(*) INTO v_n
    FROM phan_cong_buoi_hoc pc
    JOIN buoi_hoc b ON b.id = pc.buoi_hoc_id
   WHERE pc.user_id = p_user
     AND pc.id <> p_loai_tru
     AND pc.buoi_hoc_id <> p_buoi
     AND pc.trang_thai <> 'da_go'
     AND b.trang_thai NOT IN ('huy','doi')
     AND b.ngay = v_ngay
     AND b.gio_bat_dau < v_kt AND b.gio_ket_thuc > v_bd;
  RETURN v_n > 0;
END$$

-- Trigger chống trùng lịch khi phân công
CREATE TRIGGER trg_pc_bi BEFORE INSERT ON phan_cong_buoi_hoc FOR EACH ROW
BEGIN
  IF NEW.trang_thai <> 'da_go' AND fn_trung_lich(NEW.user_id, NEW.buoi_hoc_id, 0) = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Trùng lịch: nhân sự đã có ca dạy khác trong cùng khung giờ!';
  END IF;
END$$

CREATE TRIGGER trg_pc_bu BEFORE UPDATE ON phan_cong_buoi_hoc FOR EACH ROW
BEGIN
  IF NEW.trang_thai <> 'da_go'
     AND (OLD.trang_thai = 'da_go' OR NEW.user_id <> OLD.user_id OR NEW.buoi_hoc_id <> OLD.buoi_hoc_id)
     AND fn_trung_lich(NEW.user_id, NEW.buoi_hoc_id, NEW.id) = 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Trùng lịch: nhân sự đã có ca dạy khác trong cùng khung giờ!';
  END IF;

  -- Khóa sửa công sau khi bảng công đã chốt
  IF (NOT (NEW.checkin_luc <=> OLD.checkin_luc) OR NOT (NEW.checkout_luc <=> OLD.checkout_luc)
      OR NEW.don_gia <> OLD.don_gia)
     AND OLD.bang_cong_id IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ca dạy này đã được chốt công tháng, không được sửa!';
  END IF;
END$$

-- Trigger kiểm tra sĩ số tối đa lớp học
CREATE TRIGGER trg_lhv_bi BEFORE INSERT ON lop_hoc_vien FOR EACH ROW
BEGIN
  DECLARE v_max INT; DECLARE v_cur INT;
  SELECT si_so_toi_da INTO v_max FROM lop_hoc WHERE id = NEW.lop_id;
  SELECT COUNT(*) INTO v_cur FROM lop_hoc_vien WHERE lop_id = NEW.lop_id AND trang_thai IN ('dang_hoc','hoc_thu');
  IF NEW.trang_thai IN ('dang_hoc','hoc_thu') AND v_cur >= v_max THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Lớp học đã đạt sĩ số tối đa!';
  END IF;
END$$

-- Trigger điểm danh: Học viên phải thuộc lớp của buổi học
CREATE TRIGGER trg_sotd_bi BEFORE INSERT ON so_theo_doi_buoi_hoc FOR EACH ROW
BEGIN
  IF NOT EXISTS (SELECT 1 FROM buoi_hoc b JOIN lop_hoc_vien l ON l.lop_id = b.lop_id
                  WHERE b.id = NEW.buoi_hoc_id AND l.hoc_vien_id = NEW.hoc_vien_id) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Học viên không thuộc danh sách lớp của buổi học này!';
  END IF;
END$$

-- Trigger kiểm soát thanh toán & cập nhật trạng thái hóa đơn
CREATE TRIGGER trg_tt_bi BEFORE INSERT ON thanh_toan FOR EACH ROW
BEGIN
  DECLARE v_phai DECIMAL(12,0); DECLARE v_da DECIMAL(12,0); DECLARE v_tt VARCHAR(20);
  SELECT phai_thu, trang_thai INTO v_phai, v_tt FROM hoa_don WHERE id = NEW.hoa_don_id;
  SELECT COALESCE(SUM(CASE WHEN loai = 'thu' THEN so_tien ELSE -so_tien END), 0)
    INTO v_da FROM thanh_toan WHERE hoa_don_id = NEW.hoa_don_id AND trang_thai = 'hop_le';
  IF v_tt IN ('nhap','huy') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Hóa đơn chưa phát hành hoặc đã hủy!';
  END IF;
  IF NEW.trang_thai = 'hop_le' AND NEW.loai = 'thu' AND v_da + NEW.so_tien > v_phai THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Số tiền thanh toán vượt quá số tiền phải thu của hóa đơn!';
  END IF;
END$$

CREATE TRIGGER trg_tt_ai AFTER INSERT ON thanh_toan FOR EACH ROW
BEGIN
  DECLARE v_da DECIMAL(12,0);
  SELECT COALESCE(SUM(CASE WHEN loai = 'thu' THEN so_tien ELSE -so_tien END), 0)
    INTO v_da FROM thanh_toan WHERE hoa_don_id = NEW.hoa_don_id AND trang_thai = 'hop_le';
  UPDATE hoa_don SET da_thu = v_da,
         trang_thai = CASE WHEN v_da >= phai_thu THEN 'da_thanh_toan' WHEN v_da > 0 THEN 'mot_phan' ELSE 'chua_thanh_toan' END
   WHERE id = NEW.hoa_don_id;
END$$

CREATE TRIGGER trg_tt_au AFTER UPDATE ON thanh_toan FOR EACH ROW
BEGIN
  DECLARE v_da DECIMAL(12,0);
  SELECT COALESCE(SUM(CASE WHEN loai = 'thu' THEN so_tien ELSE -so_tien END), 0)
    INTO v_da FROM thanh_toan WHERE hoa_don_id = NEW.hoa_don_id AND trang_thai = 'hop_le';
  UPDATE hoa_don SET da_thu = v_da,
         trang_thai = CASE WHEN v_da >= phai_thu THEN 'da_thanh_toan' WHEN v_da > 0 THEN 'mot_phan' ELSE 'chua_thanh_toan' END
   WHERE id = NEW.hoa_don_id;
END$$

-- Thủ tục sinh buổi học đều đặn theo lịch (không bỏ qua ngày lễ, giáo vụ hủy tay khi cần)
CREATE PROCEDURE sp_sinh_buoi_hoc(IN p_lop_id BIGINT UNSIGNED, IN p_tu DATE, IN p_den DATE)
BEGIN
  INSERT IGNORE INTO buoi_hoc (lop_id, lich_hoc_id, loai, ngay, gio_bat_dau, gio_ket_thuc, phong_hoc, trang_thai)
  WITH RECURSIVE d (ngay) AS (
    SELECT p_tu UNION ALL SELECT ngay + INTERVAL 1 DAY FROM d WHERE ngay < p_den
  )
  SELECT lh.lop_id, lh.id, 'thuong', d.ngay, lh.gio_bat_dau, lh.gio_ket_thuc, lh.phong_hoc, 'du_kien'
    FROM d
    JOIN lich_hoc lh ON lh.lop_id = p_lop_id AND lh.is_active = 1
                    AND lh.thu = WEEKDAY(d.ngay) + 2
                    AND d.ngay >= lh.hieu_luc_tu
                    AND (lh.hieu_luc_den IS NULL OR d.ngay <= lh.hieu_luc_den);

  -- Tự động phân công người dạy chính theo đơn giá lớp
  INSERT INTO phan_cong_buoi_hoc (buoi_hoc_id, user_id, vai_tro, don_gia)
  SELECT b.id, ns.user_id, 'day_chinh', l.muc_tra_chinh
    FROM buoi_hoc b
    JOIN lop_hoc l ON l.id = b.lop_id
    JOIN nhan_su_chuyen_mon ns ON ns.id = l.nguoi_day_chinh_id
   WHERE b.lop_id = p_lop_id AND b.ngay BETWEEN p_tu AND p_den
     AND NOT EXISTS (SELECT 1 FROM phan_cong_buoi_hoc x WHERE x.buoi_hoc_id = b.id AND x.vai_tro = 'day_chinh' AND x.trang_thai <> 'da_go');
END$$

-- Thủ tục chốt công tháng: Gán bang_cong_id vào phân công, chặn nếu còn ca dạy chưa check-out
CREATE PROCEDURE sp_chot_cong(IN p_user BIGINT UNSIGNED, IN p_thang TINYINT, IN p_nam SMALLINT, IN p_nguoi_chot BIGINT UNSIGNED)
BEGIN
  DECLARE v_id BIGINT UNSIGNED;
  DECLARE v_tt VARCHAR(10);
  DECLARE v_chua_out INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;

  -- Chặn nếu còn buổi dạy trong tháng đã check-in nhưng chưa check-out
  SELECT COUNT(*) INTO v_chua_out
    FROM phan_cong_buoi_hoc pc
    JOIN buoi_hoc b ON b.id = pc.buoi_hoc_id
   WHERE pc.user_id = p_user AND pc.trang_thai <> 'da_go'
     AND YEAR(b.ngay) = p_nam AND MONTH(b.ngay) = p_thang
     AND pc.checkin_luc IS NOT NULL AND pc.checkout_luc IS NULL;
  IF v_chua_out > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Chưa thể chốt: Còn ca dạy đã check-in nhưng chưa check-out!';
  END IF;

  START TRANSACTION;
  INSERT INTO bang_cong_thang (user_id, thang, nam) VALUES (p_user, p_thang, p_nam)
    ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
  SET v_id = LAST_INSERT_ID();
  SELECT trang_thai INTO v_tt FROM bang_cong_thang WHERE id = v_id FOR UPDATE;
  IF v_tt <> 'mo' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Bảng công tháng này đã được chốt!';
  END IF;

  -- Gán trực tiếp bang_cong_id vào các buổi dạy đủ điều kiện
  UPDATE phan_cong_buoi_hoc pc
    JOIN buoi_hoc b ON b.id = pc.buoi_hoc_id
    SET pc.bang_cong_id = v_id
   WHERE pc.user_id = p_user AND pc.trang_thai <> 'da_go'
     AND pc.trang_thai_cong = 'hop_le' AND pc.so_phut_thuc_te IS NOT NULL
     AND YEAR(b.ngay) = p_nam AND MONTH(b.ngay) = p_thang
     AND pc.bang_cong_id IS NULL;

  -- Tổng hợp dữ liệu vào bảng công tháng
  UPDATE bang_cong_thang bc SET
     so_buoi       = (SELECT COUNT(*) FROM phan_cong_buoi_hoc WHERE bang_cong_id = v_id),
     tong_phut     = (SELECT COALESCE(SUM(so_phut_thuc_te),0) FROM phan_cong_buoi_hoc WHERE bang_cong_id = v_id),
     tong_thu_nhap = (SELECT COALESCE(SUM(
                       CASE l.cach_tinh_luong WHEN 'theo_gio' THEN ROUND(pc.so_phut_thuc_te / 60 * pc.don_gia, 0) ELSE pc.don_gia END
                     ),0)
                     FROM phan_cong_buoi_hoc pc
                     JOIN buoi_hoc b ON b.id = pc.buoi_hoc_id
                     JOIN lop_hoc l  ON l.id = b.lop_id
                     WHERE pc.bang_cong_id = v_id),
     so_buoi_bu    = (SELECT COUNT(*) FROM phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id = pc.buoi_hoc_id WHERE pc.bang_cong_id = v_id AND b.loai = 'bu'),
     so_lan_tre    = (SELECT COUNT(*) FROM phan_cong_buoi_hoc WHERE bang_cong_id = v_id AND checkin_tre = 1),
     so_buoi_vang  = (SELECT COUNT(*) FROM phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id = pc.buoi_hoc_id
                        WHERE pc.user_id = p_user AND pc.trang_thai = 'vang' AND YEAR(b.ngay) = p_nam AND MONTH(b.ngay) = p_thang),
     trang_thai    = 'da_chot',
     chot_boi      = p_nguoi_chot,
     chot_luc      = NOW()
   WHERE bc.id = v_id;

  COMMIT;
END$$

DELIMITER ;

-- =====================================================================
-- 12. DỮ LIỆU KHỞI TẠO (SEED DATA)
-- =====================================================================

-- 12.1. Nhóm quyền
INSERT INTO role_group (group_id, group_name, description) VALUES
 (1, 'Admin', 'Ban Giám Đốc, Quản trị viên tối cao'),
 (2, 'NhanVien', 'Điều phối viên, Giáo vụ, Kế toán trung tâm'),
 (3, 'GiangVien', 'Giáo viên cơ hữu và Gia sư đứng lớp chính'),
 (4, 'TroGiang', 'Trợ giảng hỗ trợ lớp nhóm'),
 (5, 'PhuHuynh', 'Phụ huynh học viên đại diện gia đình');

-- 12.2. Phân hệ chức năng
INSERT INTO `function` (function_id, name_function) VALUES
 (1, 'QuanLyTaiKhoan'), (2, 'QuanLyNhanSu'), (3, 'TiepNhanYeuCau'),
 (4, 'QuanLyLopHoc'), (5, 'VanHanhBuoiHoc'), (6, 'ChamCongLuong'),
 (7, 'HocPhiThanhToan'), (8, 'BaoCaoThongKe');

-- 12.3. Ma trận phân quyền nhóm (ROLE_GROUP_FUNCTION)
INSERT INTO role_group_function (group_id, function_id, `add`, `edit`, `delete`, download, `view`) VALUES
 -- Admin toàn quyền trên cả 8 phân hệ
 (1, 1, 1, 1, 1, 1, 1), (1, 2, 1, 1, 1, 1, 1), (1, 3, 1, 1, 1, 1, 1), (1, 4, 1, 1, 1, 1, 1),
 (1, 5, 1, 1, 1, 1, 1), (1, 6, 1, 1, 1, 1, 1), (1, 7, 1, 1, 1, 1, 1), (1, 8, 1, 1, 1, 1, 1),
 -- Nhân viên
 (2, 3, 1, 1, 0, 1, 1), (2, 4, 1, 1, 0, 1, 1), (2, 5, 1, 1, 0, 1, 1), (2, 7, 1, 1, 0, 1, 1), (2, 8, 0, 0, 0, 1, 1),
 -- Giảng viên (Giáo viên / Gia sư chính)
 (3, 5, 1, 1, 0, 1, 1), (3, 6, 0, 0, 0, 1, 1),
 -- Trợ giảng (chỉ hỗ trợ điểm danh & check-in công của mình)
 (4, 5, 0, 1, 0, 0, 1), (4, 6, 0, 0, 0, 0, 1),
 -- Phụ huynh
 (5, 5, 0, 0, 0, 1, 1), (5, 7, 0, 0, 0, 1, 1);

-- 12.4. Danh mục cơ bản
INSERT INTO mon_hoc (id, ma, ten) VALUES
 (1,'TOAN','Toán'),(2,'VAN','Ngữ văn'),(3,'ANH','Tiếng Anh'),(4,'LY','Vật lý'),(5,'HOA','Hóa học'),(6,'SINH','Sinh học');

INSERT INTO cap_lop (id, ten, cap_hoc) VALUES
 (1,'Lớp 1','tieu_hoc'),(2,'Lớp 2','tieu_hoc'),(3,'Lớp 3','tieu_hoc'),(4,'Lớp 4','tieu_hoc'),(5,'Lớp 5','tieu_hoc'),
 (6,'Lớp 6','thcs'),(7,'Lớp 7','thcs'),(8,'Lớp 8','thcs'),(9,'Lớp 9','thcs'),
 (10,'Lớp 10','thpt'),(11,'Lớp 11','thpt'),(12,'Lớp 12','thpt');

INSERT INTO khu_vuc (id, ten) VALUES
 (1,'Thủ Đức'),(2,'Bình Thạnh'),(3,'Gò Vấp'),(4,'Phú Nhuận'),(5,'Tân Bình'),(6,'Quận 1'),(7,'Quận 3');

INSERT INTO cau_hinh (khoa, gia_tri, mo_ta) VALUES
 ('checkin_truoc_phut','30','Được check-in sớm tối đa trước giờ học'),
 ('checkin_tre_sau_phut','15','Sau mốc này bị tính là đi trễ'),
 ('han_nhap_bao_cao_gio','24','Thời hạn người dạy nhập nhận xét & bài tập');

-- 12.5. Người dùng mẫu & Cơ sở
INSERT INTO users (user_id, hoten, sdt, email, ngaysinh, diachi, nghe_nghiep, khu_vuc_id) VALUES
 (1, 'Quản Trị Viên', '0900000001', 'admin@giasu.local', '1988-01-01', 'Quận 1, TP.HCM', 'Quản lý', 6),
 (2, 'Lê Thị Mai (Điều phối)', '0900000002', 'nhanvien1@giasu.local', '1995-05-10', 'Bình Thạnh, TP.HCM', 'Nhân viên giáo vụ', 2),
 (3, 'Nguyễn Minh Anh (Gia sư)', '0900000003', 'gs.minhanh@giasu.local', '2002-03-15', 'Thủ Đức, TP.HCM', 'Sinh viên', 1),
 (4, 'Trần Quốc Bảo (Gia sư)', '0900000004', 'gs.quocbao@giasu.local', '2003-07-20', 'Thủ Đức, TP.HCM', 'Sinh viên', 1),
 (5, 'Cô Lê Thu Cúc (Giáo viên)', '0900000005', 'gv.thucuc@giasu.local', '1990-11-02', 'Gò Vấp, TP.HCM', 'Giáo viên', 3),
 (6, 'Phạm Gia Huy (Trợ giảng)', '0900000006', 'tg.giahuy@giasu.local', '2005-04-18', 'Thủ Đức, TP.HCM', 'Sinh viên', 1),
 (7, 'Nguyễn Thị Hoa (Phụ huynh)', '0900000007', 'ph.hoa@giasu.local', '1982-08-25', '12 Võ Văn Ngân, Thủ Đức', 'Kế toán', 1),
 (8, 'Trần Văn Nam (Phụ huynh)', '0900000008', 'ph.nam@giasu.local', '1980-12-14', '45 Phan Văn Trị, Gò Vấp', 'Kinh doanh', 3);

-- Tài khoản đăng nhập (mật khẩu mặc định: "password")
INSERT INTO account (account_id, user_id, group_id, username, password_hash, status) VALUES
 (1, 1, 1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'),
 (2, 2, 2, 'nhanvien1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'),
 (3, 3, 3, 'gs_minhanh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'),
 (4, 4, 3, 'gs_quocbao', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'),
 (5, 5, 3, 'gv_thucuc', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'),
 (6, 6, 4, 'tg_giahuy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'),
 (7, 7, 5, 'ph_hoa', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active'),
 (8, 8, 5, 'ph_nam', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active');

-- Cơ sở đào tạo (QUAN_LY_ID trỏ tới USERS)
INSERT INTO co_so (id, ma_co_so, ten_co_so, dia_chi, so_dien_thoai, khu_vuc_id, quan_ly_id) VALUES
 (1, 'CS-THUDUC', 'Cơ sở 1 - TP. Thủ Đức', '12 Võ Văn Ngân, P. Linh Chiểu, TP. Thủ Đức', '02838960001', 1, 1),
 (2, 'CS-GOVAP', 'Cơ sở 2 - Quận Gò Vấp', '45 Phan Văn Trị, Phường 5, Quận Gò Vấp', '02838960002', 3, 1);

-- Nhân viên
INSERT INTO nhan_vien (id, user_id, ma_nv, chuc_vu, co_so_id, ngay_vao_lam) VALUES
 (1, 2, 'NV001', 'Điều phối viên', 1, '2025-01-10');

-- Người dạy
INSERT INTO nhan_su_chuyen_mon (id, user_id, ma_ns, phan_loai, gioi_tinh, cccd, nghe_nghiep, don_vi_cong_tac, kinh_nghiem_nam, hinh_thuc_day, don_gia_gio_chuan, ngan_hang, so_tai_khoan, chu_tai_khoan) VALUES
 (1, 3, 'NS001', 'gia_su', 'nu', '079202000001', 'sinh_vien', 'ĐH Sư phạm TP.HCM', 3, 'online,offline', 70000, 'Vietcombank', '0011000000001', 'NGUYEN MINH ANH'),
 (2, 4, 'NS002', 'gia_su', 'nam', '079203000002', 'sinh_vien', 'ĐH Khoa học Tự nhiên', 2, 'offline', 80000, 'Techcombank', '19030000000002', 'TRAN QUOC BAO'),
 (3, 5, 'NS003', 'giao_vien', 'nu', '079190000003', 'giao_vien', 'THCS Nguyễn Du', 10, 'online,offline', 150000, 'ACB', '24000000003', 'LE THU CUC'),
 (4, 6, 'NS004', 'tro_giang', 'nam', '079205000004', 'sinh_vien', 'ĐH Công nghệ Thông tin', 1, 'online,offline', 35000, 'MBBank', '09000000006', 'PHAM GIA HUY');

-- Học viên
INSERT INTO hoc_vien (id, phu_huynh_user_id, ma_hv, ho_ten, ngay_sinh, gioi_tinh, truong, cap_lop_id, dia_chi, khu_vuc_id) VALUES
 (1, 7, 'HV001', 'Nguyễn Văn A', '2011-05-12', 'nam', 'THCS Linh Trung', 9, '12 Võ Văn Ngân, Thủ Đức', 1),
 (2, 7, 'HV002', 'Nguyễn Thị B', '2014-09-01', 'nu', 'TH Linh Chiểu', 6, '12 Võ Văn Ngân, Thủ Đức', 1),
 (3, 8, 'HV003', 'Trần Minh C', '2014-02-20', 'nam', 'THCS Gò Vấp', 6, '45 Phan Văn Trị, Gò Vấp', 3);

-- Chuyên môn môn học & Lịch rảnh
INSERT INTO nhan_su_mon (nhan_su_id, mon_hoc_id, cap_lop_tu, cap_lop_den, hoc_phi_buoi_de_xuat) VALUES
 (1, 1, 6, 9, 180000), (2, 1, 6, 12, 200000), (3, 3, 1, 9, 200000), (3, 2, 6, 9, 190000);

INSERT INTO nhan_su_khu_vuc VALUES (1,1),(1,2),(2,1),(2,3),(3,1),(3,3),(4,1);

INSERT INTO nhan_su_lich_ranh (nhan_su_id, thu, gio_bat_dau, gio_ket_thuc) VALUES
 (1, 3, '18:00', '21:00'), (1, 5, '18:00', '21:00'), (1, 7, '08:00', '12:00'),
 (2, 3, '18:00', '21:00'), (2, 5, '18:00', '21:00'),
 (3, 7, '08:00', '12:00'), (3, 8, '08:00', '12:00'),
 (4, 7, '08:00', '12:00');

-- Tuyển sinh: Yêu cầu học & Yêu cầu lịch
INSERT INTO yeu_cau_hoc (id, ma_yc, hoc_vien_id, mon_hoc_id, cap_lop_id, loai_lop_mong_muon, hinh_thuc, khu_vuc_id, ngan_sach_min, ngan_sach_max, muc_tieu, nhan_vien_id) VALUES
 (1, 'YC-2026-0001', 1, 1, 9, 'kem_1_1', 'offline', 1, 150000, 200000, 'Ôn thi vào lớp 10', 1);

INSERT INTO yeu_cau_lich (yeu_cau_id, thu, gio_bat_dau, gio_ket_thuc) VALUES
 (1, 3, '18:00', '20:00'), (1, 5, '18:00', '20:00');

INSERT INTO de_xuat_nhan_su (id, yeu_cau_id, nhan_su_id, nhan_vien_id, hoc_phi_de_xuat, tt_nhan_su, tt_phu_huynh) VALUES
 (1, 1, 1, 1, 180000, 'dong_y', 'dong_y');

-- Khởi tạo Lớp học
INSERT INTO lop_hoc (id, ma_lop, ten_lop, loai_lop, de_xuat_id, mon_hoc_id, cap_lop_id, nguoi_day_chinh_id, co_so_id, phong_hoc, hinh_thuc, dia_diem, si_so_toi_thieu, si_so_toi_da, cach_thu_phi, hoc_phi_buoi, hoc_phi_khoa, muc_tra_chinh, muc_tra_tro_giang, cach_tinh_luong, so_buoi_du_kien, ngay_bat_dau, trang_thai, nhan_vien_id) VALUES
 (1, 'TOAN9-KEM11', 'Toán 9 kèm 1-1 em Nguyễn Văn A', 'kem_1_1', 1, 1, 9, 1, 1, NULL, 'offline', '12 Võ Văn Ngân, Thủ Đức', 1, 1, 'theo_buoi', 180000, 0, 140000, 0, 'theo_buoi', 24, '2026-10-01', 'dang_hoc', 1),
 (2, 'ANH6-NHOM01', 'Tiếng Anh 6 - Lớp nhóm chất lượng cao', 'nhom', NULL, 3, 6, 3, 1, 'Phòng 201', 'offline', 'Cơ sở 1 Thủ Đức', 3, 10, 'theo_thang', 120000, 0, 200000, 60000, 'theo_buoi', 20, '2026-10-03', 'dang_hoc', 1);

INSERT INTO lop_hoc_vien (lop_id, hoc_vien_id, ngay_vao, trang_thai) VALUES
 (1, 1, '2026-10-01', 'dang_hoc'),
 (2, 2, '2026-10-03', 'dang_hoc'),
 (2, 3, '2026-10-03', 'dang_hoc');

INSERT INTO lich_hoc (lop_id, thu, gio_bat_dau, gio_ket_thuc, phong_hoc, hieu_luc_tu) VALUES
 (1, 3, '18:00', '20:00', NULL, '2026-10-01'), (1, 5, '18:00', '20:00', NULL, '2026-10-01'),
 (2, 7, '09:00', '11:00', 'Phòng 201', '2026-10-03');

-- Tự động sinh lịch học tháng 10
CALL sp_sinh_buoi_hoc(1, '2026-10-01', '2026-10-31');
CALL sp_sinh_buoi_hoc(2, '2026-10-03', '2026-10-31');

-- Phân công thêm Trợ giảng cho buổi đầu của lớp 2
INSERT INTO phan_cong_buoi_hoc (buoi_hoc_id, user_id, vai_tro, don_gia, phan_cong_boi)
SELECT id, 6, 'tro_giang', 60000, 2 FROM buoi_hoc WHERE lop_id=2 AND ngay='2026-10-03';

-- Cập nhật buổi đã học
UPDATE buoi_hoc SET trang_thai='da_day' WHERE (lop_id=1 AND ngay='2026-10-01') OR (lop_id=2 AND ngay='2026-10-03');
UPDATE phan_cong_buoi_hoc pc JOIN buoi_hoc b ON b.id=pc.buoi_hoc_id
   SET pc.checkin_luc  = CONCAT(b.ngay,' ', SUBTIME(b.gio_bat_dau,'00:05:00')),
       pc.checkout_luc = CONCAT(b.ngay,' ', ADDTIME(b.gio_ket_thuc,'00:02:00')),
       pc.trang_thai='da_day', pc.trang_thai_cong='hop_le'
 WHERE b.trang_thai='da_day';

-- Bài tập về nhà
INSERT INTO bai_tap (id, buoi_hoc_id, tieu_de, noi_dung, file_de_bai_url, han_nop, nguoi_tao_id)
SELECT 1, id, 'Phiếu bài tập số 01 - Hệ phương trình', 'Làm bài tập 1 đến 8 trong phiếu giấy phát tại lớp.', '/uploads/bt/phieu_01_toan9.pdf', '2026-10-06 18:00:00', 3
  FROM buoi_hoc WHERE lop_id=1 AND ngay='2026-10-01';

-- Sổ theo dõi buổi học: Điểm danh + Điểm bài tập giấy + Nhận xét
INSERT INTO so_theo_doi_buoi_hoc (buoi_hoc_id, hoc_vien_id, trang_thai_diem_danh, bai_tap_id, diem_bai_tap_giay, nhan_xet_bai_tap, muc_tiep_thu, thai_do, nhan_xet_buoi_hoc, nguoi_nhap_id)
SELECT b.id, 1, 'co_mat', 1, 8.5, 'Làm tốt bài 1-7, bài 8 nhầm dấu', 4, 'tot', 'Tiếp thu bài nhanh, tích cực phát biểu.', 3
  FROM buoi_hoc b WHERE b.lop_id=1 AND b.ngay='2026-10-01';

INSERT INTO so_theo_doi_buoi_hoc (buoi_hoc_id, hoc_vien_id, trang_thai_diem_danh, muc_tiep_thu, thai_do, nhan_xet_buoi_hoc, nguoi_nhap_id)
SELECT b.id, 2, 'co_mat', 4, 'tot', 'Nắm vững từ vựng Unit 1.', 5
  FROM buoi_hoc b WHERE b.lop_id=2 AND b.ngay='2026-10-03';

INSERT INTO so_theo_doi_buoi_hoc (buoi_hoc_id, hoc_vien_id, trang_thai_diem_danh, nhan_xet_buoi_hoc, nguoi_nhap_id)
SELECT b.id, 3, 'vang_co_phep', 'Gia đình có việc bận xin phép nghỉ.', 5
  FROM buoi_hoc b WHERE b.lop_id=2 AND b.ngay='2026-10-03';

-- Hóa đơn & chi tiết
INSERT INTO hoa_don (id, ma_hd, lop_hoc_vien_id, thang, nam, tong_tien, giam_gia, trang_thai, han_thanh_toan, nguoi_lap_id)
VALUES (1, 'HD-202610-0001', 1, 10, 2026, 180000, 0, 'chua_thanh_toan', '2026-10-15', 2);

INSERT INTO hoa_don_chi_tiet (hoa_don_id, buoi_hoc_id, hoc_vien_id, loai_khoan, mo_ta, don_gia)
SELECT 1, id, 1, 'buoi_hoc', 'Học phí buổi 01/10/2026', 180000 FROM buoi_hoc WHERE lop_id=1 AND ngay='2026-10-01';

INSERT INTO thanh_toan (hoa_don_id, loai, so_tien, phuong_thuc, nguoi_thu_id)
VALUES (1, 'thu', 180000, 'chuyen_khoan', 2);

-- Thông báo
INSERT INTO thong_bao (user_id, loai, tieu_de, noi_dung, doi_tuong_loai, doi_tuong_id, khoa_chong_trung)
VALUES (3, 'phan_cong', 'Bạn được phân công dạy lớp TOAN9-KEM11', 'Lịch thứ 3 & thứ 5 lúc 18h', 'lop_hoc', 1, 'pc:lop1:u3');
