"""Khởi tạo database EduCenter ERP sạch (008_security_perf) trên MySQL cloud (Railway/VPS).

An toàn tuyệt đối:
- Chỉ chạy trên database RỖNG (0 bảng), từ chối ghi đè nếu đã có dữ liệu.
- Đọc MYSQL_PUBLIC_URL và mật khẩu Admin bằng nhập ẩn (không in ra terminal/args).
- Nạp đủ schema.sql, triggers/routines theo install-order.json, danh mục gốc (001/004/005),
  tạo 1 tài khoản Admin ban đầu (bcrypt) và chạy database/checks.sql xác nhận 0 VI_PHAM.
"""
from pathlib import Path
from urllib.parse import unquote, urlparse
import getpass
import json
import os
import re
import subprocess
import sys

sys.stdout.reconfigure(encoding="utf-8")
ROOT = Path(__file__).resolve().parents[2]
DB_ROOT = ROOT / "database"


def parse_mysql_url(raw: str) -> dict:
    raw = raw.strip()
    parsed = urlparse(raw)
    if parsed.scheme not in ("mysql", "mysql2") or not parsed.hostname or not parsed.username or not parsed.path.strip("/"):
        raise ValueError("URL MySQL không hợp lệ. Dạng chuẩn: mysql://user:password@host:port/database")
    db_name = unquote(parsed.path.lstrip("/"))
    if not re.fullmatch(r"[A-Za-z][A-Za-z0-9_]{0,63}", db_name):
        raise ValueError("Tên database không hợp lệ.")
    return {
        "host": parsed.hostname,
        "port": str(parsed.port or 3306),
        "user": unquote(parsed.username),
        "password": unquote(parsed.password or ""),
        "database": db_name,
    }


def run_mysql(conn: dict, sql_text: str, use_db: bool = True) -> str:
    env = os.environ.copy()
    env["MYSQL_PWD"] = conn["password"]
    cmd = [
        "docker", "run", "--rm", "-i",
        "-e", "MYSQL_PWD",
        "mysql:8.4",
        "mysql",
        f"--host={conn['host']}",
        f"--port={conn['port']}",
        f"--user={conn['user']}",
        "--protocol=TCP",
        "--batch", "--raw", "--skip-column-names",
        "--default-character-set=utf8mb4",
        "--init-command=SET SESSION time_zone='+07:00', SESSION transaction_isolation='READ-COMMITTED'",
    ]
    if use_db:
        cmd.append(f"--database={conn['database']}")
    res = subprocess.run(cmd, input=sql_text.encode("utf-8"), capture_output=True, env=env)
    if res.returncode != 0:
        err = res.stderr.decode("utf-8", "replace").strip()
        raise RuntimeError(f"MySQL lỗi ({res.returncode}): {err}")
    return res.stdout.decode("utf-8", "replace").strip()


def hash_password_bcrypt(password: str) -> str:
    code = "echo password_hash(stream_get_contents(STDIN), PASSWORD_BCRYPT);"
    for cmd in (
        ["docker", "exec", "-i", "educenter-backend-1", "php", "-r", code],
        ["docker", "run", "--rm", "-i", "educenter-php-runtime:local", "php", "-r", code],
    ):
        res = subprocess.run(cmd, input=password.encode("utf-8"), capture_output=True)
        if res.returncode == 0:
            hashed = res.stdout.decode("utf-8").strip()
            if hashed.startswith("$2y$") or hashed.startswith("$2a$"):
                return hashed
    raise RuntimeError("Không tạo được bcrypt hash qua PHP container (hãy chắc chắn Docker đang chạy).")


def sql_quote(val: str) -> str:
    escaped = val.replace("\\", "\\\\").replace("'", "''")
    return f"'{escaped}'"


def main() -> None:
    print("=== Khởi tạo Database sạch EduCenter ERP (008) lên MySQL Cloud ===")
    url = os.environ.get("RAILWAY_MYSQL_URL") or getpass.getpass("Dán MYSQL_PUBLIC_URL (nhập ẩn, Enter để xác nhận): ")
    conn = parse_mysql_url(url)
    print(f"Đang kiểm tra kết nối tới {conn['host']}:{conn['port']}/{conn['database']} ...")

    version = run_mysql(conn, "SELECT VERSION();")
    if "MariaDB" in version or not re.match(r"^8\.(0|4)\.", version):
        raise SystemExit(f"Chỉ hỗ trợ MySQL 8.0.16+ hoặc 8.4 LTS (hiện tại: {version}).")
    m = re.match(r"^8\.0\.(\d+)", version)
    if m and int(m.group(1)) < 16:
        raise SystemExit(f"MySQL {version} quá cũ (cần >= 8.0.16).")
    print(f"OK: MySQL {version}")

    table_count = run_mysql(
        conn,
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE();",
    )
    if table_count != "0":
        raise SystemExit(
            f"TỪ CHỐI CHẠY: Database `{conn['database']}` hiện đã có {table_count} bảng. "
            "Script này chỉ khởi tạo trên database rỗng để bảo vệ dữ liệu."
        )

    admin_user = (os.environ.get("INIT_ADMIN_USER") or input("Username Admin [admin]: ").strip() or "admin")
    if not re.fullmatch(r"[A-Za-z0-9._-]{3,50}", admin_user):
        raise SystemExit("Username chỉ gồm chữ, số, ., _, - (3-50 ký tự).")
    admin_name = (os.environ.get("INIT_ADMIN_NAME") or input("Họ tên Admin [Quản Trị Viên]: ").strip() or "Quản Trị Viên")
    admin_email = (os.environ.get("INIT_ADMIN_EMAIL") or input("Email Admin [admin@educenter.local]: ").strip() or "admin@educenter.local")
    if "@" not in admin_email:
        raise SystemExit("Email không hợp lệ.")
    admin_pass = os.environ.get("INIT_ADMIN_PASS")
    if not admin_pass:
        admin_pass = getpass.getpass("Mật khẩu Admin (tối thiểu 8 ký tự, nhập ẩn): ")
        confirm = getpass.getpass("Nhập lại mật khẩu Admin: ")
        if admin_pass != confirm:
            raise SystemExit("Mật khẩu xác nhận không khớp.")
    if len(admin_pass) < 8:
        raise SystemExit("Mật khẩu Admin phải tối thiểu 8 ký tự.")

    print("Đang tạo hash bcrypt cho tài khoản Admin...")
    pw_hash = hash_password_bcrypt(admin_pass)

    print("Đang ghép schema, triggers, routines và danh mục gốc (001 -> 008)...")
    parts = [
        "SET NAMES utf8mb4;",
        (DB_ROOT / "schema.sql").read_text(encoding="utf-8"),
        "INSERT INTO schema_migrations(version,status) VALUES "
        "('001_feature_based','applying'),('002_routine_review','applying'),"
        "('003_attribute_review','applying'),('004_business_rules','applying'),"
        "('005_operations_ready','applying'),('006_shift_recruitment','applying'),"
        "('007_email_queue_index','applying'),('008_security_perf','applying');",
    ]
    order = json.loads((DB_ROOT / "install-order.json").read_text(encoding="utf-8"))
    for rel in order:
        parts.append((DB_ROOT / rel).read_text(encoding="utf-8"))
    for seeder in ("seeders/001_reference.sql", "seeders/004_business_policy.sql", "seeders/005_operations_policy.sql"):
        parts.append((DB_ROOT / seeder).read_text(encoding="utf-8"))
    parts.append(
        "UPDATE schema_migrations SET status='applied', applied_at=NOW() "
        "WHERE status='applying' AND version IN ("
        "'001_feature_based','002_routine_review','003_attribute_review','004_business_rules',"
        "'005_operations_ready','006_shift_recruitment','007_email_queue_index','008_security_perf');"
    )
    parts.append(
        "START TRANSACTION;\n"
        f"INSERT INTO users (hoten, email) VALUES ({sql_quote(admin_name)}, {sql_quote(admin_email)});\n"
        "SET @admin_uid = LAST_INSERT_ID();\n"
        f"INSERT INTO account (user_id, group_id, username, password_hash, status) "
        f"VALUES (@admin_uid, 1, {sql_quote(admin_user)}, {sql_quote(pw_hash)}, 'active');\n"
        "COMMIT;"
    )

    print("Đang nạp cấu trúc & danh mục gốc lên MySQL Cloud (khoảng 10-20 giây)...")
    run_mysql(conn, "\n".join(parts))

    print("Đang chạy kiểm tra toàn vẹn database/checks.sql...")
    report = run_mysql(conn, (DB_ROOT / "checks.sql").read_text(encoding="utf-8"))
    violations = []
    for line in report.splitlines():
        cells = line.split("\t")
        if len(cells) == 3 and cells[0] == "VI_PHAM" and cells[2] != "0":
            violations.append(line)
    if violations:
        raise SystemExit("PHÁT HIỆN VI PHẠM:\n" + "\n".join(violations))

    counts = run_mysql(
        conn,
        "SELECT CONCAT("
        "(SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()), ' tables / ', "
        "(SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE()), ' triggers / ', "
        "(SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema=DATABASE()), ' routines / ', "
        "(SELECT COUNT(*) FROM account WHERE status='active'), ' active account(s)');",
    )
    print(f"PASS: Khởi tạo sạch hoàn tất! ({counts}; 0 VI_PHAM)")
    print(f"Bạn có thể đăng nhập Admin bằng username `{admin_user}` trên web Railway ngay bây giờ.")


if __name__ == "__main__":
    main()
