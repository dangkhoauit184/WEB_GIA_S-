"""[THÊM] Kiểm tra bảo toàn file và cấu trúc SQL; không kết nối hoặc ghi database."""
from pathlib import Path
import hashlib
import json
import re
import argparse

parser = argparse.ArgumentParser(description='Verify layout, references, and SQL modules.')
parser.add_argument('--scaffold-only', action='store_true', help='Compatibility option for structure verification.')
args = parser.parse_args()

ROOT = Path(__file__).resolve().parents[2]


def require(condition, message):
    if not condition:
        raise AssertionError(message)


manifest = json.loads((ROOT / 'reference/preserved-files.json').read_text('utf-8'))
for name, expected in manifest.items():
    require(hashlib.sha256((ROOT / name).read_bytes()).hexdigest() == expected, f'File changed: {name}')

modules = json.loads((ROOT / 'shared/modules.json').read_text('utf-8'))
features = [feature for group in modules.values() for feature in group]
require(len(features) == len(set(features)), 'Feature belongs to multiple modules')
layers = ['Controller', 'Service', 'Repository', 'Model', 'Validator', 'Filter']
def pascal(name):
    return ''.join(piece.capitalize() for piece in name.split('-'))

for module, group in modules.items():
    actual = {p.name for p in (ROOT / f'frontend/src/modules/{module}/features').iterdir() if p.is_dir()}
    require(actual == set(group), f'Unexpected features in frontend/{module}')
    php_features = ROOT / 'backend/app/Modules' / pascal(module) / 'Features'
    require({p.name for p in php_features.iterdir() if p.is_dir()} == {pascal(f) for f in group}, f'Unexpected PHP features: {module}')
    for feature in group:
        for suffix in ['html', 'css', 'js']:
            p = ROOT / f'frontend/src/modules/{module}/features/{feature}/{feature}.{suffix}'
            require(p.is_file(), f'Missing frontend file: {p}')
        for layer in layers:
            p = php_features / pascal(feature) / f'{pascal(feature)}{layer}.php'
            require(p.is_file(), f'Missing backend file: {p}')
        require((php_features / pascal(feature) / 'Routes.php').is_file(), f'Missing PHP routes: {feature}')
placeholders = []
for folder in ['frontend']:
    actual_modules = {p.name for p in (ROOT / folder / 'src/modules').iterdir() if p.is_dir()}
    require(actual_modules == set(modules), f'Unexpected modules in {folder}')
    require((ROOT / folder / 'src/modules/index.js').is_file(), f'Missing module entry: {folder}')
    require(not (ROOT / folder / 'src/features').exists(), f'Old flat feature directory: {folder}')
    for p in (ROOT / folder).rglob('*'):
        if 'node_modules' in p.parts or 'vendor' in p.parts:
            continue
        if p.is_file() and p.suffix in {'.js', '.html', '.css'}:
            placeholders.append(p)
readmes = {p.relative_to(ROOT).as_posix() for folder in ['frontend', 'backend', 'database', 'docs', '.github']
           for p in (ROOT / folder).rglob('*')
           if not {'node_modules', 'vendor', 'writable'}.intersection(p.parts) and p.is_file() and p.name.lower().startswith('readme')}
readmes.add('README.md')
require(readmes == {'README.md', 'frontend/README.md', 'backend/README.md', 'database/README.md'}, 'Keep exactly four project READMEs')
require({p.relative_to(ROOT / 'docs').as_posix() for p in (ROOT / 'docs').rglob('*') if p.is_file()} == {'huong-dan-cai-dat-va-su-dung.md','thong-tin-do-an.md','demo/index.html'}, 'Keep two authoritative Markdown files and the approved UI index in docs')
require([p.relative_to(ROOT).as_posix() for p in (ROOT / '.github').rglob('*')
         if p.is_file() and 'PULL_REQUEST' in p.name.upper()] == ['.github/PULL_REQUEST_TEMPLATE.md'], 'Keep one PR template')
require(not any((ROOT / 'docs').rglob('mau[12].html')), 'Obsolete UI samples still present')
require({p.name for p in (ROOT / 'backend/app/Modules').iterdir() if p.is_dir()} == {pascal(m) for m in modules}, 'Unexpected PHP modules')
require(not (ROOT / 'backend/src').exists() and not (ROOT / 'backend/server.js').exists(), 'Legacy Node backend still present')
composer = json.loads((ROOT / 'backend/composer.json').read_text('utf-8'))
require('codeigniter4/framework' in composer['require'] and (ROOT / 'backend/composer.lock').is_file(), 'Missing locked PHP MVC dependencies')


def tables(sql):
    return {m[0]: m[1] for m in re.findall(r'CREATE TABLE `?(\w+)`?\s*\(([\s\S]*?)\) ENGINE=InnoDB;', sql)}


def columns(body):
    return {m[0]: re.sub(r'\s+', ' ', m[1].lower()) for m in re.findall(
        r'^\s*`?(\w+)`?\s+((?:BIGINT|SMALLINT|TINYINT|INT|DECIMAL|VARCHAR|DATETIME|DATE|TIME|TEXT|JSON|ENUM|SET)(?:\([^\n]*?\))?(?: UNSIGNED)?)(?=\s|,|$)',
        body, re.M | re.I)}


schema = (ROOT / 'database/schema.sql').read_text('utf-8')
original = (ROOT / 'reference/database/schema-original.sql').read_text('utf-8')
old, new = tables(original), tables(schema)
require(len(old) == 35, 'Expected original 35 tables')
require(set(new) == set(old) | {'schema_migrations','khoa_hoc','dang_ky_khoa_hoc','lich_su_don_gia_gio','yeu_cau_nghi_lop','hoc_bu_hoc_vien','giao_dich_truc_tuyen','su_kien_thanh_toan'}, 'Unexpected tables')
removed = {'hoa_don_chi_tiet': {'hoc_vien_id'}, 'de_xuat_nhan_su': {'ly_do_tu_choi'}}
for name in old:
    before = {k: v for k, v in columns(old[name]).items() if k not in removed.get(name, set())}
    require(before.items() <= columns(new[name]).items(), f'Original columns changed: {name}')
    for col in removed.get(name, set()):
        require(col not in columns(new[name]), f'Redundant column retained: {name}.{col}')
require('GENERATED ALWAYS AS (doc_luc IS NOT NULL)' in new['thong_bao'], 'Read state is not derived')
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
for sql_name in ['schema.sql', 'migrations/001_feature_based.sql', 'migrations/002_routine_review.sql', 'migrations/003_attribute_review.sql'] + order:
    content = (ROOT / 'database' / sql_name).read_text('utf-8')
    require('[SỬA]' in content or '[THÊM]' in content, f'Missing Vietnamese change comments: {sql_name}')
migration = re.sub(r'--[^\n]*', '', (ROOT / 'database/migrations/001_feature_based.sql').read_text('utf-8'))
require(not re.search(r'\b(?:DROP\s+(?:DATABASE|TABLE|COLUMN)|TRUNCATE|DELETE\s+FROM)\b', migration, re.I), 'Destructive migration')
require(len(set(re.findall(r'CREATE TRIGGER (\w+)', triggers))) == len(re.findall(r'CREATE TRIGGER (\w+)', triggers)), 'Duplicate triggers')
require(not (ROOT / 'index.html').exists() and not (ROOT / 'preview').exists(), 'Preview still mixed with source')
require((ROOT / 'docs/demo/index.html').is_file(), 'Missing approved docs/demo/index.html')
require(not (ROOT / 'database/procedures.sql').exists() and not (ROOT / 'database/triggers.sql').exists(), 'Duplicate executable SQL sources')
print(f'PASS: {len(manifest)} preserved hashes; {len(modules)} modules; {len(features)} features; {len(placeholders)} source files; '
      f'{len(new)} tables; {fk_count} inline FKs; {len(routines)} routines.')
