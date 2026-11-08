"""[THÊM] Verify fresh install and 003→004 with disposable databases in a supplied test container."""
from pathlib import Path
import argparse
import hashlib
import json
import os
import re
import subprocess
import sys
import tempfile
import uuid

sys.stdout.reconfigure(encoding='utf-8')
ROOT = Path(__file__).resolve().parents[2]
BASELINE = '64e4f18328658c86a92875aa8ffeb07c46206f50'
parser = argparse.ArgumentParser()
parser.add_argument('--container', required=True)
args = parser.parse_args()
if not re.fullmatch(r'educenter-(?:test|db004)-[a-f0-9]+', args.container):
    raise SystemExit('Refusing non-test container name.')


def command(arguments, content=None, success=True):
    result = subprocess.run(arguments, input=content, capture_output=True, cwd=ROOT)
    if success and result.returncode:
        raise RuntimeError((result.stderr + result.stdout).decode('utf-8', 'replace'))
    return result


def sql(text, database=None):
    script = ('MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --user=root --batch --raw '
              '--skip-column-names --default-character-set=utf8mb4 '
              '--init-command="SET SESSION time_zone=\'+07:00\', SESSION transaction_isolation=\'READ-COMMITTED\'"')
    if database:
        assert re.fullmatch(r'educenter_upgrade_[a-f0-9]+_(?:old|new)_test', database)
        script += ' --database=' + database
    return command(['docker', 'exec', '-i', args.container, 'sh', '-c', script], text.encode()).stdout.decode('utf-8').strip()


def source(name):
    return command(['git', 'show', BASELINE + ':database/' + name]).stdout.decode('utf-8')


def snapshot(database, fields=None):
    if fields is None:
        fields = {}
        metadata = sql('SELECT table_name,column_name FROM information_schema.columns WHERE table_schema=DATABASE() ORDER BY table_name,ordinal_position', database)
        for line in metadata.splitlines():
            table, column = line.split('\t')
            fields.setdefault(table, []).append(column)
    statements = []
    for table, columns in fields.items():
        statements.append("SELECT '" + table + "',JSON_ARRAY(" + ','.join('`' + col + '`' for col in columns) + f') FROM `{table}`;')
    rows = {table: [] for table in fields}
    output = sql('\n'.join(statements), database)
    for line in output.splitlines():
        table, record = line.split('\t', 1)
        rows[table].append(record)
    hashes = {table: hashlib.sha256('\n'.join(sorted(records)).encode()).hexdigest() for table, records in rows.items()}
    return fields, hashes


def run_action(action, database, success=True):
    with tempfile.NamedTemporaryFile(mode='w', encoding='utf-8', suffix='.env', delete=False) as file:
        file.write('DB_NAME=' + database + '\n')
        config = file.name
    try:
        return command(['pwsh', '-NoProfile', '-File', str(ROOT / 'database/scripts/run.ps1'),
                        '-Action', action, '-Container', args.container, '-EnvFile', config], success=success)
    finally:
        os.unlink(config)


def structure(database):
    columns = sql("SELECT table_name,column_name,column_type,is_nullable,COALESCE(column_default,'<NULL>'),"
                  "extra,COALESCE(generation_expression,'') FROM information_schema.columns "
                  "WHERE table_schema=DATABASE() ORDER BY table_name,column_name", database)
    constraints = sql("SELECT table_name,constraint_name,constraint_type FROM information_schema.table_constraints "
                      "WHERE constraint_schema=DATABASE() ORDER BY table_name,constraint_name", database)
    return columns, constraints


tag = uuid.uuid4().hex[:12]
old_db = f'educenter_upgrade_{tag}_old_test'
new_db = f'educenter_upgrade_{tag}_new_test'
try:
    for name in [old_db, new_db]:
        sql(f'CREATE DATABASE `{name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
    order = json.loads(source('install-order.json'))
    baseline = source('schema.sql') + '\n' + '\n'.join(source(name) for name in order)
    baseline += '\n' + source('seeders/001_reference.sql')
    baseline += "\nINSERT INTO schema_migrations(version,status,applied_at) VALUES ('001_feature_based','applied',NOW()),('002_routine_review','applied',NOW()),('003_attribute_review','applied',NOW());"
    baseline += '\n' + source('seeders/002_demo.sql')
    sql(baseline, old_db)
    fields, before = snapshot(old_db)
    run_action('preflight', old_db)
    run_action('migrate', old_db)
    _, after = snapshot(old_db, fields)
    for table in fields:
        if table not in ['role_group_function', 'cau_hinh', 'schema_migrations']:
            assert before[table] == after[table], f'Original values changed: {table}'
    old_versions = sql("SELECT COUNT(*) FROM schema_migrations WHERE status='applied'", old_db)
    assert old_versions == '4'
    full_fields, full_hash = snapshot(old_db)
    run_action('migrate', old_db)
    assert full_hash == snapshot(old_db, full_fields)[1], 'Repeated migrate changed data'
    run_action('check', old_db)
    run_action('init', new_db)
    assert run_action('init', new_db, success=False).returncode != 0, 'Init accepted non-empty DB'
    assert structure(old_db) == structure(new_db), 'Fresh and upgraded schema differ'
    run_action('check', new_db)
    print('PASS: installer fresh 004, upgrade 003, preserved old values, matching schema, repeat migration, refused duplicate init.')
finally:
    for name in [old_db, new_db]:
        sql(f'DROP DATABASE IF EXISTS `{name}`')
