-- [SỬA] Schema riêng cho database MỚI, MySQL 8.0.16+ / 8.4, utf8mb4 / InnoDB.
-- [SỬA] Không còn DROP DATABASE, không gắn cứng tên database, không chứa dữ liệu demo.
-- [LƯU Ý] Chọn database rỗng trước khi chạy. Không chạy file này lên database cũ.
SET NAMES utf8mb4;
SET SESSION time_zone = '+07:00';
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
  -- [THÊM] Phiên bản 003: thông tin vận hành và truy vết; NULL giữ nghĩa chưa biết với dữ liệu cũ.
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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
  -- [THÊM] Phiên bản 003: thông tin vận hành và truy vết; NULL giữ nghĩa chưa biết với dữ liệu cũ.
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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
  CONSTRAINT fk_ns_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  -- [THÊM] Đơn giá giờ chuẩn không âm.
  CONSTRAINT ck_ns_don_gia CHECK (don_gia_gio_chuan >= 0)
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
  nhan_vien_id        BIGINT UNSIGNED NULL, -- Chưa phân công khi khách gửi từ website
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- [THÊM] Phiên bản 003: thông tin vận hành và truy vết; NULL giữ nghĩa chưa biết với dữ liệu cũ.
  nguoi_gui_id BIGINT UNSIGNED NULL,
  don_vi_ngan_sach ENUM('theo_buoi','theo_gio','theo_thang','theo_khoa') NULL,
  CONSTRAINT fk_yc_gui FOREIGN KEY (nguoi_gui_id) REFERENCES users(user_id),
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
  CONSTRAINT ck_yc_ns   CHECK (ngan_sach_min IS NULL OR ngan_sach_max IS NULL OR ngan_sach_min <= ngan_sach_max),
  -- [THÊM] Ngân sách hai đầu không âm.
  CONSTRAINT ck_yc_ns_duong CHECK ((ngan_sach_min IS NULL OR ngan_sach_min >= 0) AND (ngan_sach_max IS NULL OR ngan_sach_max >= 0))
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
  ghi_chu           VARCHAR(255) NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- [THÊM] Phiên bản 003: thông tin vận hành và truy vết; NULL giữ nghĩa chưa biết với dữ liệu cũ.
  cach_thu_phi_de_xuat ENUM('theo_buoi','theo_thang','theo_khoa') NULL,
  nhan_su_phan_hoi_luc DATETIME NULL,
  phu_huynh_phan_hoi_luc DATETIME NULL,
  phu_huynh_phan_hoi_boi BIGINT UNSIGNED NULL,
  ly_do_tu_choi_nhan_su VARCHAR(255) NULL,
  ly_do_tu_choi_phu_huynh VARCHAR(255) NULL,
  CONSTRAINT fk_dx_ph_phan_hoi FOREIGN KEY (phu_huynh_phan_hoi_boi) REFERENCES users(user_id),
  CONSTRAINT ck_dx_ly_do CHECK ((ly_do_tu_choi_nhan_su IS NULL OR tt_nhan_su='tu_choi') AND (ly_do_tu_choi_phu_huynh IS NULL OR tt_phu_huynh='tu_choi')),
  PRIMARY KEY (id),
  UNIQUE KEY uq_dx (yeu_cau_id, nhan_su_id),
  KEY idx_dx_ns (nhan_su_id, tt_nhan_su),
  CONSTRAINT fk_dx_yc FOREIGN KEY (yeu_cau_id) REFERENCES yeu_cau_hoc(id) ON DELETE CASCADE,
  CONSTRAINT fk_dx_ns FOREIGN KEY (nhan_su_id) REFERENCES nhan_su_chuyen_mon(id),
  CONSTRAINT fk_dx_nv FOREIGN KEY (nhan_vien_id) REFERENCES nhan_vien(id),
  -- [THÊM] Học phí đề xuất không âm.
  CONSTRAINT ck_dx_phi CHECK (hoc_phi_de_xuat IS NULL OR hoc_phi_de_xuat >= 0)
) ENGINE=InnoDB;

-- =====================================================================
-- 6. LỚP HỌC & LỊCH (3 BẢNG)
-- =====================================================================

-- 6.1. Bảng LOP_HOC: Thêm CACH_THU_PHI, HOC_PHI_KHOA, SI_SO_TOI_THIEU, PHONG_HOC dạng chuỗi
-- [THÊM] 004: Khóa là sản phẩm đăng ký độc lập; không tạo kỳ/đợt thu hoặc hạn mức/phí bù.
CREATE TABLE khoa_hoc (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ma VARCHAR(40) NOT NULL,
  ten VARCHAR(150) NOT NULL,
  mo_ta TEXT NULL,
  hoc_phi DECIMAL(12,0) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_khoa_ma (ma),
  CONSTRAINT ck_khoa_phi CHECK (hoc_phi>=0)
) ENGINE=InnoDB;

-- [THÊM] Giá khóa được chụp lúc đăng ký, không đổi giá các đăng ký cũ khi đổi giá khóa.
CREATE TABLE dang_ky_khoa_hoc (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  khoa_hoc_id BIGINT UNSIGNED NOT NULL,
  hoc_vien_id BIGINT UNSIGNED NOT NULL,
  hoc_phi_snapshot DECIMAL(12,0) NOT NULL DEFAULT 0,
  trang_thai ENUM('cho_xep_lop','dang_hoc','hoan_thanh','thoi_hoc') NOT NULL DEFAULT 'cho_xep_lop',
  ngay_dang_ky DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dkk_hv (hoc_vien_id,khoa_hoc_id),
  CONSTRAINT fk_dkk_khoa FOREIGN KEY (khoa_hoc_id) REFERENCES khoa_hoc(id),
  CONSTRAINT fk_dkk_hv FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id),
  CONSTRAINT ck_dkk_phi CHECK (hoc_phi_snapshot>=0)
) ENGINE=InnoDB;

-- [THÊM] Mức lương tiền/giờ do Admin đặt, có mốc hiệu lực và audit; không sửa ca đã dạy.
CREATE TABLE lich_su_don_gia_gio (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nhan_su_id BIGINT UNSIGNED NOT NULL,
  don_gia_gio DECIMAL(12,0) NOT NULL,
  hieu_luc_tu DATETIME(6) NOT NULL,
  nguoi_dat_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dgg_hieu_luc (nhan_su_id,hieu_luc_tu),
  CONSTRAINT fk_dgg_ns FOREIGN KEY (nhan_su_id) REFERENCES nhan_su_chuyen_mon(id),
  CONSTRAINT fk_dgg_admin FOREIGN KEY (nguoi_dat_id) REFERENCES users(user_id),
  CONSTRAINT ck_dgg_gia CHECK (don_gia_gio>=0)
) ENGINE=InnoDB;

CREATE TABLE lop_hoc (
  -- [THÊM] 004: NULL giữ lớp cũ chưa xác định khóa, không tự gán khóa giả.
  khoa_hoc_id BIGINT UNSIGNED NULL,
  CONSTRAINT fk_lop_khoa FOREIGN KEY (khoa_hoc_id) REFERENCES khoa_hoc(id),
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
  CONSTRAINT ck_lop_siso CHECK (si_so_toi_da >= si_so_toi_thieu AND si_so_toi_thieu >= 1),
  -- [THÊM] Mức học phí tháng cố định cho cach_thu_phi=theo_thang; NULL nghĩa là chưa cấu hình, không tự lấy giá buổi.
  hoc_phi_thang DECIMAL(12,0) NULL,
  -- [THÊM] Học phí tháng không âm.
  CONSTRAINT ck_lop_phi_thang CHECK (hoc_phi_thang IS NULL OR hoc_phi_thang >= 0),
  -- [THÊM] Số buổi dự kiến nếu khai báo phải dương.
  CONSTRAINT ck_lop_so_buoi CHECK (so_buoi_du_kien IS NULL OR so_buoi_du_kien > 0)
) ENGINE=InnoDB;

-- 6.2. Bảng LOP_HOC_VIEN: Thêm trạng thái cho_xep_lop và hoc_thu
CREATE TABLE lop_hoc_vien (
  -- [THÊM] 005: Dấu vết Admin xếp lớp; lịch sử cũ giữ NULL.
  xep_boi BIGINT UNSIGNED NULL,
  xep_luc DATETIME NULL,
  CONSTRAINT fk_lhv_admin FOREIGN KEY (xep_boi) REFERENCES users(user_id),
  CONSTRAINT ck_lhv_xep CHECK ((xep_boi IS NULL)=(xep_luc IS NULL)),
  -- [THÊM] 004: Ghi danh lớp có thể thuộc một đăng ký khóa; kiểm tra đúng khóa/học viên bằng trigger.
  dang_ky_khoa_hoc_id BIGINT UNSIGNED NULL,
  CONSTRAINT fk_lhv_dkk FOREIGN KEY (dang_ky_khoa_hoc_id) REFERENCES dang_ky_khoa_hoc(id),
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lop_id       BIGINT UNSIGNED NOT NULL,
  hoc_vien_id  BIGINT UNSIGNED NOT NULL,
  ngay_vao     DATE NOT NULL,
  ngay_roi     DATE NULL,
  trang_thai   ENUM('dang_hoc','tam_nghi','da_roi','cho_xep_lop','hoc_thu') NOT NULL DEFAULT 'dang_hoc',
  -- [THÊM] Phiên bản 003: thông tin vận hành và truy vết; NULL giữ nghĩa chưa biết với dữ liệu cũ.
  created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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
  CONSTRAINT ck_lich_gio CHECK (gio_bat_dau < gio_ket_thuc),
  -- [THÊM] Khoảng hiệu lực lịch tuần phải hợp lệ.
  CONSTRAINT ck_lich_hieu_luc CHECK (hieu_luc_den IS NULL OR hieu_luc_den >= hieu_luc_tu)
) ENGINE=InnoDB;

-- =====================================================================
-- 7. VẬN HÀNH BUỔI HỌC (4 BẢNG)
-- =====================================================================

-- 7.1. Bảng BUOI_HOC: Gộp báo cáo buổi học (chủ đề, nội dung, file bài giảng)
CREATE TABLE buoi_hoc (
  -- [THÊM] 004: Bù cá nhân không làm hủy buổi gốc của cả lớp.
  pham_vi_bu ENUM('ca_lop','ca_nhan') NOT NULL DEFAULT 'ca_lop',
  CONSTRAINT ck_buoi_pham_vi CHECK (loai='bu' OR pham_vi_bu='ca_lop'),
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
  -- [THÊM] Phiên bản 003: thông tin vận hành và truy vết; NULL giữ nghĩa chưa biết với dữ liệu cũ.
  nguoi_huy_id BIGINT UNSIGNED NULL,
  huy_luc DATETIME NULL,
  CONSTRAINT fk_buoi_nguoi_huy FOREIGN KEY (nguoi_huy_id) REFERENCES users(user_id),
  CONSTRAINT ck_buoi_huy_meta CHECK ((nguoi_huy_id IS NULL) = (huy_luc IS NULL)),
  PRIMARY KEY (id),
  UNIQUE KEY uq_buoi (lop_id, ngay, gio_bat_dau),
  KEY idx_buoi_ngay (ngay, trang_thai),
  KEY idx_buoi_goc (buoi_goc_id),
  CONSTRAINT fk_buoi_lop  FOREIGN KEY (lop_id) REFERENCES lop_hoc(id),
  CONSTRAINT fk_buoi_lich FOREIGN KEY (lich_hoc_id) REFERENCES lich_hoc(id) ON DELETE SET NULL,
  CONSTRAINT fk_buoi_goc  FOREIGN KEY (buoi_goc_id) REFERENCES buoi_hoc(id),
  CONSTRAINT ck_buoi_gio  CHECK (gio_bat_dau < gio_ket_thuc),
  CONSTRAINT ck_buoi_bu   CHECK (loai = 'thuong' OR buoi_goc_id IS NOT NULL),
  -- [THÊM] Chụp cơ sở của buổi học để kiểm tra phòng theo cơ sở, không thay đổi tên phòng dạng chuỗi đã quy định.
  co_so_id INT UNSIGNED NULL,
  -- [THÊM] Ràng buộc cơ sở của buổi học; không xóa lịch sử theo cơ sở.
  CONSTRAINT fk_buoi_coso FOREIGN KEY (co_so_id) REFERENCES co_so(id),
  -- [THÊM] Bổ sung chỉ mục kiểm tra xung đột phòng trong cùng cơ sở/ngày.
  KEY idx_buoi_phong (co_so_id, phong_hoc, ngay, trang_thai)
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
  CONSTRAINT ck_pc_out  CHECK (checkout_luc IS NULL OR (checkin_luc IS NOT NULL AND checkout_luc > checkin_luc)),
  -- [THÊM] Snapshot cách tính lương cùng đơn giá; NULL cho dữ liệu lịch sử chưa xác minh, chặn chốt công nếu còn NULL.
  cach_tinh_luong ENUM('theo_buoi','theo_gio') NULL,
  -- [THÊM] Đơn giá ca dạy không được âm.
  CONSTRAINT ck_pc_don_gia CHECK (don_gia >= 0),
  -- [THÊM] Công hợp lệ phải có cả check-in và check-out, không ép mọi ca dự kiến phải check-in.
  CONSTRAINT ck_pc_hop_le CHECK (trang_thai_cong <> 'hop_le' OR (checkin_luc IS NOT NULL AND checkout_luc IS NOT NULL)),
  -- [THÊM] GPS phải đủ cặp và đúng miền giá trị. [LƯU Ý] Chưa ép bán kính khi tài liệu Word chỉ yêu cầu GPS nếu có.
  CONSTRAINT ck_pc_gps CHECK ((checkin_lat IS NULL AND checkin_lng IS NULL) OR (checkin_lat IS NOT NULL AND checkin_lng IS NOT NULL AND checkin_lat BETWEEN -90 AND 90 AND checkin_lng BETWEEN -180 AND 180)),
  -- [THÊM] 004: Giữ phút hiện diện cũ; phút trả lương chỉ tính phần giao với khung giờ lịch.
  quy_tac_cong TINYINT UNSIGNED NOT NULL DEFAULT 4,
  gio_lich_bat_dau DATETIME NULL,
  gio_lich_ket_thuc DATETIME NULL,
  so_phut_tinh_cong INT GENERATED ALWAYS AS (IF(quy_tac_cong=3,so_phut_thuc_te,
    IF(checkin_luc IS NULL OR checkout_luc IS NULL OR gio_lich_bat_dau IS NULL OR gio_lich_ket_thuc IS NULL,NULL,
      GREATEST(0,TIMESTAMPDIFF(MINUTE,GREATEST(checkin_luc,gio_lich_bat_dau),LEAST(checkout_luc,gio_lich_ket_thuc)))))) STORED,
  luong_sua_boi BIGINT UNSIGNED NULL,
  CONSTRAINT fk_pc_luong_sua FOREIGN KEY (luong_sua_boi) REFERENCES users(user_id),
  CONSTRAINT ck_pc_quy_tac CHECK (quy_tac_cong IN (3,4))
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
  CONSTRAINT fk_dcc_duy FOREIGN KEY (nguoi_duyet_id) REFERENCES users(user_id) ON DELETE SET NULL,
  -- [THÊM] Giờ công đề nghị mới phải có thứ tự hợp lệ.
  CONSTRAINT ck_dcc_gio CHECK (checkout_moi IS NULL OR (checkin_moi IS NOT NULL AND checkout_moi > checkin_moi))
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
  -- [THÊM] Phiên bản 003: thông tin vận hành và truy vết; NULL giữ nghĩa chưa biết với dữ liệu cũ.
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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
  -- [THÊM] Phiên bản 003: thông tin vận hành và truy vết; NULL giữ nghĩa chưa biết với dữ liệu cũ.
  created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_kq (hoc_vien_id, ngay),
  KEY idx_kq_lop (lop_id),
  CONSTRAINT fk_kq_lop  FOREIGN KEY (lop_id) REFERENCES lop_hoc(id),
  CONSTRAINT fk_kq_hv   FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id),
  CONSTRAINT fk_kq_user FOREIGN KEY (nguoi_nhap_id) REFERENCES users(user_id),
  CONSTRAINT ck_kq_diem CHECK (diem >= 0 AND diem <= diem_toi_da),
  -- [THÊM] Thang điểm phải dương, giữ nguyên điểm tối đa có thể tùy chỉnh.
  CONSTRAINT ck_kq_thang_diem CHECK (diem_toi_da > 0)
) ENGINE=InnoDB;

-- =====================================================================
-- 9. CÔNG, LƯƠNG & HỌC PHÍ (4 BẢNG)
-- =====================================================================

-- 9.1. Bảng BANG_CONG_THANG: Chốt công tháng cho người dạy
CREATE TABLE bang_cong_thang (
  -- [THÊM] 004: Mở khóa cần dấu vết Admin, không tin biến phiên do client tự đặt.
  mo_khoa_boi BIGINT UNSIGNED NULL,
  mo_khoa_luc DATETIME NULL,
  ly_do_mo_khoa VARCHAR(255) NULL,
  CONSTRAINT fk_bc_mo FOREIGN KEY (mo_khoa_boi) REFERENCES users(user_id),
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
  -- [THÊM] Phiên bản 003: thông tin vận hành và truy vết; NULL giữ nghĩa chưa biết với dữ liệu cũ.
  tra_boi BIGINT UNSIGNED NULL,
  tra_luc DATETIME NULL,
  ma_giao_dich_chi VARCHAR(60) NULL,
  CONSTRAINT fk_bc_tra FOREIGN KEY (tra_boi) REFERENCES users(user_id),
  CONSTRAINT ck_bc_tra_meta CHECK ((tra_boi IS NULL) = (tra_luc IS NULL)),
  PRIMARY KEY (id),
  UNIQUE KEY uq_bc_thang (user_id, nam, thang),
  KEY idx_bc_tt (nam, thang, trang_thai),
  CONSTRAINT fk_bct_user FOREIGN KEY (user_id) REFERENCES users(user_id),
  CONSTRAINT fk_bct_chot FOREIGN KEY (chot_boi) REFERENCES users(user_id) ON DELETE SET NULL,
  CONSTRAINT ck_bct_thang CHECK (thang BETWEEN 1 AND 12),
  -- [THÊM] Thu nhập, thưởng và khấu trừ không âm; không tự cấm thuc_nhan âm khi chưa có quy định.
  CONSTRAINT ck_bc_tien CHECK (tong_thu_nhap >= 0 AND thuong >= 0 AND khau_tru >= 0)
) ENGINE=InnoDB;

-- Thêm khóa ngoại bang_cong_id trong phan_cong_buoi_hoc trỏ đến bang_cong_thang
-- [SỬA] Chặn xóa bảng công đang liên kết ca dạy, giữ lịch sử quyết toán.
ALTER TABLE phan_cong_buoi_hoc ADD CONSTRAINT fk_pc_bct FOREIGN KEY (bang_cong_id) REFERENCES bang_cong_thang(id);

-- 9.2. Bảng HOA_DON: Học phí theo tháng hoặc theo khóa
CREATE TABLE hoa_don (
  -- [THÊM] 004: Hóa đơn trọn khóa không ép tháng/năm hay tạo ghi danh lớp giả.
  dang_ky_khoa_hoc_id BIGINT UNSIGNED NULL,
  CONSTRAINT fk_hd_dkk FOREIGN KEY (dang_ky_khoa_hoc_id) REFERENCES dang_ky_khoa_hoc(id),
  UNIQUE KEY uq_hd_dkk (dang_ky_khoa_hoc_id),
  CONSTRAINT ck_hd_nguon CHECK ((dang_ky_khoa_hoc_id IS NOT NULL AND lop_hoc_vien_id IS NULL AND thang IS NULL AND nam IS NULL)
    OR (dang_ky_khoa_hoc_id IS NULL AND lop_hoc_vien_id IS NOT NULL AND thang IS NOT NULL AND nam IS NOT NULL)),
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ma_hd           VARCHAR(30) NOT NULL,
  lop_hoc_vien_id BIGINT UNSIGNED NULL,
  thang           TINYINT UNSIGNED NULL,
  nam             SMALLINT UNSIGNED NULL,
  tong_tien       DECIMAL(12,0) NOT NULL DEFAULT 0,
  giam_gia        DECIMAL(12,0) NOT NULL DEFAULT 0,
  phai_thu        DECIMAL(12,0) GENERATED ALWAYS AS (tong_tien - giam_gia) STORED,
  da_thu          DECIMAL(12,0) NOT NULL DEFAULT 0,
  trang_thai      ENUM('nhap','chua_thanh_toan','mot_phan','da_thanh_toan','huy') NOT NULL DEFAULT 'nhap',
  han_thanh_toan  DATE NULL,
  nguoi_lap_id    BIGINT UNSIGNED NOT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- [THÊM] Phiên bản 003: thông tin vận hành và truy vết; NULL giữ nghĩa chưa biết với dữ liệu cũ.
  phat_hanh_luc DATETIME NULL,
  phat_hanh_boi BIGINT UNSIGNED NULL,
  huy_luc DATETIME NULL,
  huy_boi BIGINT UNSIGNED NULL,
  ly_do_huy VARCHAR(255) NULL,
  CONSTRAINT fk_hd_phat_hanh FOREIGN KEY (phat_hanh_boi) REFERENCES users(user_id),
  CONSTRAINT fk_hd_huy FOREIGN KEY (huy_boi) REFERENCES users(user_id),
  CONSTRAINT ck_hd_phat_hanh_meta CHECK ((phat_hanh_luc IS NULL) = (phat_hanh_boi IS NULL)),
  CONSTRAINT ck_hd_huy_meta CHECK ((huy_luc IS NULL AND huy_boi IS NULL AND ly_do_huy IS NULL) OR (huy_luc IS NOT NULL AND huy_boi IS NOT NULL AND ly_do_huy IS NOT NULL AND CHAR_LENGTH(TRIM(ly_do_huy))>0)),
  PRIMARY KEY (id),
  UNIQUE KEY uq_hd_ma (ma_hd),
  UNIQUE KEY uq_hd_ky (lop_hoc_vien_id, nam, thang),
  KEY idx_hd_tt (trang_thai, han_thanh_toan),
  CONSTRAINT fk_hd_lhv  FOREIGN KEY (lop_hoc_vien_id) REFERENCES lop_hoc_vien(id),
  CONSTRAINT fk_hd_user FOREIGN KEY (nguoi_lap_id) REFERENCES users(user_id),
  CONSTRAINT ck_hd_tien CHECK (tong_tien >= 0 AND giam_gia >= 0 AND giam_gia <= tong_tien AND da_thu >= 0),
  CONSTRAINT ck_hd_thang CHECK (thang BETWEEN 1 AND 12),
  -- [THÊM] Lũy kế thu không được lớn hơn phải thu; hoàn tiền không được làm số dư âm.
  CONSTRAINT ck_hd_so_du CHECK (da_thu <= phai_thu)
) ENGINE=InnoDB;

-- 9.3. Bảng HOA_DON_CHI_TIET: BUOI_HOC_ID NULLABLE, thêm LOAI_KHOAN, MO_TA
CREATE TABLE hoa_don_chi_tiet (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  hoa_don_id  BIGINT UNSIGNED NOT NULL,
  buoi_hoc_id BIGINT UNSIGNED NULL,        -- Cho phép NULL đối với học phí trọn gói/khóa/tháng cố định
  loai_khoan  ENUM('buoi_hoc','khoa_hoc','thang_co_dinh','phu_phi') NOT NULL DEFAULT 'buoi_hoc',
  mo_ta       VARCHAR(255) NULL,
  don_gia     DECIMAL(12,0) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_hdct_hd (hoa_don_id),
  CONSTRAINT fk_hdct_hd   FOREIGN KEY (hoa_don_id) REFERENCES hoa_don(id) ON DELETE CASCADE,
  -- [SỬA] Không SET NULL khi xóa buổi đã lập học phí; bảo toàn chứng từ và tương thích CHECK khoản theo buổi.
  CONSTRAINT fk_hdct_buoi FOREIGN KEY (buoi_hoc_id) REFERENCES buoi_hoc(id),
  -- [THÊM] Đơn giá từng khoản học phí không âm.
  CONSTRAINT ck_hdct_gia CHECK (don_gia >= 0),
  -- [THÊM] Khoản theo buổi phải có buổi học; khoản tháng/khóa vẫn được NULL.
  CONSTRAINT ck_hdct_buoi CHECK (loai_khoan <> 'buoi_hoc' OR buoi_hoc_id IS NOT NULL)
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
  -- [THÊM] Phiên bản 003: thông tin vận hành và truy vết; NULL giữ nghĩa chưa biết với dữ liệu cũ.
  huy_luc DATETIME NULL,
  huy_boi BIGINT UNSIGNED NULL,
  ly_do_huy VARCHAR(255) NULL,
  CONSTRAINT fk_tt_huy FOREIGN KEY (huy_boi) REFERENCES users(user_id),
  CONSTRAINT ck_tt_huy_meta CHECK ((huy_luc IS NULL AND huy_boi IS NULL AND ly_do_huy IS NULL) OR (huy_luc IS NOT NULL AND huy_boi IS NOT NULL AND ly_do_huy IS NOT NULL AND CHAR_LENGTH(TRIM(ly_do_huy))>0)),
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
  -- [SỬA] Trạng thái đọc chỉ có một nguồn dữ liệu là doc_luc.
  da_doc TINYINT(1) GENERATED ALWAYS AS (doc_luc IS NOT NULL) VIRTUAL,
  doc_luc           DATETIME NULL,
  co_gui_email      TINYINT(1) NOT NULL DEFAULT 0,
  so_lan_thu_email  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  trang_thai_email  ENUM('chua_gui','da_gui','loi') NOT NULL DEFAULT 'chua_gui',
  loi_email         VARCHAR(255) NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- [THÊM] Phiên bản 003: thông tin vận hành và truy vết; NULL giữ nghĩa chưa biết với dữ liệu cũ.
  email_gui_luc DATETIME NULL,
  email_thu_gan_nhat_luc DATETIME NULL,
  email_thu_tiep_luc DATETIME NULL,
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


-- [THÊM] Nhật ký phiên bản kỹ thuật; không lưu hay thay đổi dữ liệu nghiệp vụ.
CREATE TABLE schema_migrations (
  version VARCHAR(80) NOT NULL PRIMARY KEY,
  status ENUM('applying','applied') NOT NULL,
  applied_at DATETIME NULL
) ENGINE=InnoDB;

-- [THÊM] Nghỉ nguyên buổi của lớp cần Admin duyệt; không tự sinh tiền hoàn hoặc hạn mức bù.
CREATE TABLE yeu_cau_nghi_lop (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  buoi_hoc_id BIGINT UNSIGNED NOT NULL,
  nguoi_gui_id BIGINT UNSIGNED NOT NULL,
  ly_do VARCHAR(500) NOT NULL,
  trang_thai ENUM('cho_duyet','da_duyet','tu_choi') NOT NULL DEFAULT 'cho_duyet',
  nguoi_duyet_id BIGINT UNSIGNED NULL,
  duyet_luc DATETIME NULL,
  ghi_chu VARCHAR(500) NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_nl_buoi FOREIGN KEY (buoi_hoc_id) REFERENCES buoi_hoc(id),
  CONSTRAINT fk_nl_gui FOREIGN KEY (nguoi_gui_id) REFERENCES users(user_id),
  CONSTRAINT fk_nl_duyet FOREIGN KEY (nguoi_duyet_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- [THÊM] Giáo vụ chỉ xếp lịch bù theo thỏa thuận. Không có hạn mức hoặc khoản tiền học bù.
CREATE TABLE hoc_bu_hoc_vien (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  buoi_bu_id BIGINT UNSIGNED NOT NULL,
  hoc_vien_id BIGINT UNSIGNED NOT NULL,
  don_xin_nghi_id BIGINT UNSIGNED NULL,
  xep_boi BIGINT UNSIGNED NOT NULL,
  ghi_chu VARCHAR(500) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hb_buoi_hv (buoi_bu_id,hoc_vien_id),
  CONSTRAINT fk_hb_buoi FOREIGN KEY (buoi_bu_id) REFERENCES buoi_hoc(id),
  CONSTRAINT fk_hb_hv FOREIGN KEY (hoc_vien_id) REFERENCES hoc_vien(id),
  CONSTRAINT fk_hb_don FOREIGN KEY (don_xin_nghi_id) REFERENCES don_xin_nghi(id),
  CONSTRAINT fk_hb_xep FOREIGN KEY (xep_boi) REFERENCES users(user_id)
) ENGINE=InnoDB;


-- [THÊM] 005: Yêu cầu thanh toán không đồng nghĩa đã nhận tiền. Không lưu dữ liệu thẻ hay secret.
CREATE TABLE giao_dich_truc_tuyen (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ma_yeu_cau VARCHAR(64) COLLATE utf8mb4_bin NOT NULL,
  hoa_don_id BIGINT UNSIGNED NOT NULL,
  phu_huynh_id BIGINT UNSIGNED NOT NULL,
  nha_cung_cap VARCHAR(40) COLLATE utf8mb4_bin NOT NULL,
  ma_giao_dich_cong VARCHAR(120) COLLATE utf8mb4_bin NULL,
  so_tien DECIMAL(12,0) NOT NULL,
  tien_te VARCHAR(3) NOT NULL DEFAULT 'VND',
  trang_thai ENUM('khoi_tao','cho_xac_nhan','thanh_cong','that_bai','het_han','huy') NOT NULL DEFAULT 'khoi_tao',
  thanh_toan_id BIGINT UNSIGNED NULL,
  xac_nhan_boi BIGINT UNSIGNED NULL,
  xac_nhan_luc DATETIME NULL,
  het_han_luc DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_gd_yeu_cau (ma_yeu_cau),
  UNIQUE KEY uq_gd_cong (nha_cung_cap,ma_giao_dich_cong),
  UNIQUE KEY uq_gd_phieu (thanh_toan_id),
  UNIQUE KEY uq_gd_id_cong (id,nha_cung_cap),
  KEY idx_gd_hd (hoa_don_id,trang_thai),
  KEY idx_gd_ph (phu_huynh_id,created_at),
  KEY idx_gd_han (trang_thai,het_han_luc),
  CONSTRAINT fk_gd_hd FOREIGN KEY (hoa_don_id) REFERENCES hoa_don(id),
  CONSTRAINT fk_gd_ph FOREIGN KEY (phu_huynh_id) REFERENCES users(user_id),
  CONSTRAINT fk_gd_phieu FOREIGN KEY (thanh_toan_id) REFERENCES thanh_toan(id),
  CONSTRAINT fk_gd_admin FOREIGN KEY (xac_nhan_boi) REFERENCES users(user_id),
  CONSTRAINT ck_gd_tien CHECK (so_tien>0 AND tien_te='VND'),
  CONSTRAINT ck_gd_xac_nhan CHECK (
    (trang_thai='thanh_cong' AND thanh_toan_id IS NOT NULL AND xac_nhan_boi IS NOT NULL AND xac_nhan_luc IS NOT NULL)
    OR (trang_thai<>'thanh_cong' AND thanh_toan_id IS NULL AND xac_nhan_boi IS NULL AND xac_nhan_luc IS NULL)),
  CONSTRAINT ck_gd_ma CHECK (CHAR_LENGTH(TRIM(ma_yeu_cau))>0 AND CHAR_LENGTH(TRIM(nha_cung_cap))>0)
) ENGINE=InnoDB;

-- [THÊM] Dedupe webhook. Chỉ lưu hash payload để truy vết, không xem một event là bằng chứng tiền đã nhận.
CREATE TABLE su_kien_thanh_toan (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  giao_dich_id BIGINT UNSIGNED NOT NULL,
  nha_cung_cap VARCHAR(40) COLLATE utf8mb4_bin NOT NULL,
  ma_su_kien VARCHAR(120) COLLATE utf8mb4_bin NOT NULL,
  payload_sha256 VARCHAR(64) NOT NULL,
  trang_thai ENUM('cho_xu_ly','da_xu_ly','bo_qua','loi') NOT NULL DEFAULT 'cho_xu_ly',
  loi VARCHAR(255) NULL,
  nhan_luc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  xu_ly_luc DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sk_cong (nha_cung_cap,ma_su_kien),
  KEY idx_sk_hang_doi (trang_thai,nhan_luc),
  CONSTRAINT fk_sk_gd FOREIGN KEY (giao_dich_id,nha_cung_cap) REFERENCES giao_dich_truc_tuyen(id,nha_cung_cap),
  CONSTRAINT ck_sk_hash CHECK (REGEXP_LIKE(payload_sha256,'^[a-fA-F0-9]{64}$','c')),
  CONSTRAINT ck_sk_xu_ly CHECK ((trang_thai='cho_xu_ly' AND xu_ly_luc IS NULL) OR (trang_thai<>'cho_xu_ly' AND xu_ly_luc IS NOT NULL))
) ENGINE=InnoDB;
