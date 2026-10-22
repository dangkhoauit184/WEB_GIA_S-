"""[THÊM] Kiểm tra bảo toàn file và cấu trúc SQL; không kết nối hoặc ghi database."""
from pathlib import Path
import hashlib
import json
import re

ROOT = Path(__file__).resolve().parents[2]


def require(condition, message):
    if not condition:
        raise AssertionError(message)


manifest = json.loads((ROOT / 'docs/reference/preserved-files.json').read_text('utf-8'))
for name, expected in manifest.items():
    require(hashlib.sha256((ROOT / name).read_bytes()).hexdigest() == expected, f'File changed: {name}')

features = ['auth', 'dashboard', 'staff', 'permissions', 'academic-affairs', 'scheduling',
            'classes', 'students', 'teachers', 'tutors', 'parents', 'attendance',
            'leave-requests', 'makeup-classes', 'tuition', 'timekeeping', 'payroll', 'notifications']
layers = ['routes', 'controller', 'service', 'repository', 'model', 'validator', 'middleware']
for feature in features:
    for suffix in ['html', 'css', 'js']:
        p = ROOT / f'frontend/src/features/{feature}/{feature}.{suffix}'
        require(p.is_file() and p.stat().st_size == 0, f'Invalid frontend placeholder: {p}')
    for layer in layers:
        p = ROOT / f'backend/src/features/{feature}/{feature}.{layer}.js'
        require(p.is_file() and p.stat().st_size == 0, f'Invalid backend placeholder: {p}')
placeholders = []
for folder in ['frontend/src', 'backend', 'shared']:
    for p in (ROOT / folder).rglob('*'):
        if p.is_file():
            require(p.stat().st_size == 0, f'Nonempty scaffold: {p}')
            placeholders.append(p)


def tables(sql):
    return {m[0]: m[1] for m in re.findall(r'CREATE TABLE `?(\w+)`?\s*\(([\s\S]*?)\) ENGINE=InnoDB;', sql)}


def columns(body):
    return {m[0]: re.sub(r'\s+', ' ', m[1].lower()) for m in re.findall(
        r'^\s*`?(\w+)`?\s+((?:BIGINT|SMALLINT|TINYINT|INT|DECIMAL|VARCHAR|DATETIME|DATE|TIME|TEXT|JSON|ENUM|SET)(?:\([^\n]*?\))?(?: UNSIGNED)?)\b',
        body, re.M | re.I)}


schema = (ROOT / 'database/schema.sql').read_text('utf-8')
original = (ROOT / 'database/trung_tam_gia_su_35_tables.sql').read_text('utf-8')
old, new = tables(original), tables(schema)
require(len(old) == 35, 'Expected original 35 tables')
require(set(new) == set(old) | {'schema_migrations'}, 'Unexpected tables')
for name in old:
    require(columns(old[name]).items() <= columns(new[name]).items(), f'Original columns changed: {name}')
sql_code = re.sub(r'--[^\n]*', '', schema)
require(not re.search(r'^\s*(?:DROP|TRUNCATE|DELETE\s+FROM|INSERT\s+INTO)\b', sql_code, re.I | re.M), 'Schema contains destructive commands or seed')
all_columns = {name: columns(body) for name, body in new.items()}
fk_count = 0
for table, body in new.items():
    for col, target, target_col in re.findall(r'FOREIGN KEY\s*\((\w+)\)\s+REFERENCES\s+`?(\w+)`?\s*\((\w+)\)', body, re.I):
        require(target in new, f'Missing FK target {target}')
        require(col in all_columns[table] and target_col in all_columns[target], f'Missing FK column {table}.{col}')
        require(all_columns[table][col] == all_columns[target][target_col], f'FK type mismatch: {table}.{col}')
        fk_count += 1
procedures = (ROOT / 'database/procedures.sql').read_text('utf-8')
triggers = (ROOT / 'database/triggers.sql').read_text('utf-8')
routines = set(re.findall(r'CREATE (?:PROCEDURE|FUNCTION) (\w+)', procedures, re.I))
for call in re.findall(r'\bCALL\s+(\w+)\s*\(', procedures + triggers, re.I):
    require(call in routines, f'Missing called procedure: {call}')
for sql_name in ['schema.sql', 'procedures.sql', 'triggers.sql', 'migrations/001_feature_based.sql']:
    content = (ROOT / 'database' / sql_name).read_text('utf-8')
    require('[SỬA]' in content or '[THÊM]' in content, f'Missing Vietnamese change comments: {sql_name}')
migration = re.sub(r'--[^\n]*', '', (ROOT / 'database/migrations/001_feature_based.sql').read_text('utf-8'))
require(not re.search(r'\b(?:DROP\s+(?:DATABASE|TABLE|COLUMN)|TRUNCATE|DELETE\s+FROM)\b', migration, re.I), 'Destructive migration')
require(len(set(re.findall(r'CREATE TRIGGER (\w+)', triggers))) == len(re.findall(r'CREATE TRIGGER (\w+)', triggers)), 'Duplicate triggers')
print(f'PASS: {len(manifest)} preserved hashes; {len(features)} features; {len(placeholders)} empty files; '
      f'{len(new)} tables; {fk_count} inline FKs; {len(routines)} routines.')
