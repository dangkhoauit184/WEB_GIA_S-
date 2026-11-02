"""[THÊM] Kiểm tra bảo toàn file và cấu trúc SQL; không kết nối hoặc ghi database."""
from pathlib import Path
import hashlib
import json
import re
import argparse

parser = argparse.ArgumentParser(description='Verify layout, references, and SQL modules.')
parser.add_argument('--scaffold-only', action='store_true', help='Also require new frontend/backend files to remain empty.')
args = parser.parse_args()

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
        require(p.is_file(), f'Missing frontend file: {p}')
        require(not args.scaffold_only or p.stat().st_size == 0, f'Nonempty frontend scaffold: {p}')
    for layer in layers:
        p = ROOT / f'backend/src/features/{feature}/{feature}.{layer}.js'
        require(p.is_file(), f'Missing backend file: {p}')
        require(not args.scaffold_only or p.stat().st_size == 0, f'Nonempty backend scaffold: {p}')
placeholders = []
for folder in ['frontend/src', 'backend', 'shared']:
    for p in (ROOT / folder).rglob('*'):
        if p.is_file():
            require(not args.scaffold_only or p.stat().st_size == 0, f'Nonempty scaffold: {p}')
            if p.stat().st_size == 0:
                placeholders.append(p)


def tables(sql):
    return {m[0]: m[1] for m in re.findall(r'CREATE TABLE `?(\w+)`?\s*\(([\s\S]*?)\) ENGINE=InnoDB;', sql)}


def columns(body):
    return {m[0]: re.sub(r'\s+', ' ', m[1].lower()) for m in re.findall(
        r'^\s*`?(\w+)`?\s+((?:BIGINT|SMALLINT|TINYINT|INT|DECIMAL|VARCHAR|DATETIME|DATE|TIME|TEXT|JSON|ENUM|SET)(?:\([^\n]*?\))?(?: UNSIGNED)?)(?=\s|,|$)',
        body, re.M | re.I)}


schema = (ROOT / 'database/schema.sql').read_text('utf-8')
original = (ROOT / 'database/reference/schema-original.sql').read_text('utf-8')
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
order = json.loads((ROOT / 'database/install-order.json').read_text('utf-8'))
require(len(order) == len(set(order)), 'Duplicate install modules')
for name in order:
    require((ROOT / 'database' / name).is_file(), f'Missing module: {name}')
procedures = '\n'.join((ROOT / 'database' / f).read_text('utf-8') for f in order if f.startswith('routines/'))
triggers = '\n'.join((ROOT / 'database' / f).read_text('utf-8') for f in order if f.startswith('triggers/'))
routines = set(re.findall(r'CREATE (?:PROCEDURE|FUNCTION) (\w+)', procedures, re.I))
for call in re.findall(r'\bCALL\s+(\w+)\s*\(', procedures + triggers, re.I):
    require(call in routines, f'Missing called procedure: {call}')
for sql_name in ['schema.sql', 'migrations/001_feature_based.sql', 'migrations/002_routine_review.sql'] + order:
    content = (ROOT / 'database' / sql_name).read_text('utf-8')
    require('[SỬA]' in content or '[THÊM]' in content, f'Missing Vietnamese change comments: {sql_name}')
migration = re.sub(r'--[^\n]*', '', (ROOT / 'database/migrations/001_feature_based.sql').read_text('utf-8'))
require(not re.search(r'\b(?:DROP\s+(?:DATABASE|TABLE|COLUMN)|TRUNCATE|DELETE\s+FROM)\b', migration, re.I), 'Destructive migration')
require(len(set(re.findall(r'CREATE TRIGGER (\w+)', triggers))) == len(re.findall(r'CREATE TRIGGER (\w+)', triggers)), 'Duplicate triggers')
require(not (ROOT / 'index.html').exists() and not (ROOT / 'frontend/index.html').exists(), 'Preview still mixed with source')
require((ROOT / 'preview/index.html').is_file(), 'Missing isolated preview')
require(not (ROOT / 'database/procedures.sql').exists() and not (ROOT / 'database/triggers.sql').exists(), 'Duplicate executable SQL sources')
print(f'PASS: {len(manifest)} preserved hashes; {len(features)} features; {len(placeholders)} empty files; '
      f'{len(new)} tables; {fk_count} inline FKs; {len(routines)} routines.')
