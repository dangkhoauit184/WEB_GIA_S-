"""Local Docker MySQL backup and isolated restore verification. Never prints credentials or rows."""
from pathlib import Path
import argparse
import hashlib
import json
import os
import re
import secrets
import subprocess
import time
import tempfile
import uuid

ROOT = Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser()
sub = parser.add_subparsers(dest='action', required=True)
backup = sub.add_parser('backup', help='Pause application writes first; never performs DROP on source.')
backup.add_argument('--container', default='educenter-mysql')
backup.add_argument('--database', default='trung_tam_gia_su')
backup.add_argument('--output-dir', type=Path, default=ROOT / '.cache/backups')
verify = sub.add_parser('verify', help='Restore to a disposable MySQL container without publishing ports.')
verify.add_argument('file', type=Path)
verify.add_argument('--migrate', action='store_true', help='Also rehearse current migrations on the restored copy (requires pwsh).')
args = parser.parse_args()


def run(argv, content=None, **kwargs):
    result = subprocess.run(argv, input=content, capture_output=True, **kwargs)
    if result.returncode:
        # Output from SQL restoration can contain private data; do not echo it.
        raise RuntimeError('Command failed: ' + argv[0] + '. Check Docker/container state; private SQL output was suppressed.')
    return result.stdout.decode('utf-8').strip()


def sql(container, database, query):
    if not re.fullmatch(r'[A-Za-z][A-Za-z0-9_]{0,63}', database):
        raise ValueError('Invalid database name.')
    inside = ('MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --user=root --batch --raw --skip-column-names '
              '--default-character-set=utf8mb4 --database=' + database)
    return run(['docker', 'exec', '-i', container, 'sh', '-c', inside], query.encode())


def snapshot(container, database, fields=None):
    metadata = sql(container, database, 'SELECT table_name,column_name FROM information_schema.columns '
                   'WHERE table_schema=DATABASE() ORDER BY table_name,ordinal_position')
    if fields is None:
        fields = {}
        for line in metadata.splitlines():
            table, col = line.split('\t')
            fields.setdefault(table, []).append(col)
    queries = ["SELECT '" + table + "',JSON_ARRAY(" + ','.join('`' + c + '`' for c in cols)
               + ') FROM `' + table + '`;' for table, cols in fields.items()]
    rows = {t: [] for t in fields}
    for line in sql(container, database, '\n'.join(queries)).splitlines():
        table, row = line.split('\t', 1)
        rows[table].append(row)
    hashes = {t: {'rows': len(items), 'sha256': hashlib.sha256('\n'.join(sorted(items)).encode()).hexdigest()}
              for t, items in rows.items()}
    objects = sql(container, database, 'SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema=DATABASE();'
                  'SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema=DATABASE();')
    return {'tables': hashes, 'trigger_routine_counts': objects}


if args.action == 'backup':
    if not re.fullmatch(r'[A-Za-z0-9][A-Za-z0-9_.-]*', args.container):
        raise SystemExit('Invalid container name.')
    before = snapshot(args.container, args.database)
    args.output_dir.mkdir(parents=True, exist_ok=True)
    path = args.output_dir / (args.database + '-' + time.strftime('%Y%m%d-%H%M%S') + '-' + uuid.uuid4().hex[:6] + '.sql')
    inside = ('MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump --user=root --single-transaction --routines --triggers '
              '--events --hex-blob --no-tablespaces --set-gtid-purged=OFF --default-character-set=utf8mb4 ' + args.database)
    with path.open('xb') as stream:
        result = subprocess.run(['docker', 'exec', args.container, 'sh', '-c', inside], stdout=stream, stderr=subprocess.PIPE)
    if result.returncode or path.stat().st_size == 0:
        raise SystemExit('Backup failed; incomplete file must not be restored: ' + str(path))
    if before != snapshot(args.container, args.database):
        raise SystemExit('Data changed while dumping. Pause writers and take a new backup: ' + str(path))
    manifest = {'database': args.database, 'sha256': hashlib.sha256(path.read_bytes()).hexdigest(), 'snapshot': before}
    path.with_suffix('.json').write_text(json.dumps(manifest, indent=2), encoding='utf-8')
    print(path.resolve())
    print('PASS: backup + manifest saved. Run verify before migration; keep both files private.')
else:
    path = args.file.resolve(strict=True)
    manifest = json.loads(path.with_suffix('.json').read_text('utf-8'))
    if hashlib.sha256(path.read_bytes()).hexdigest() != manifest['sha256']:
        raise SystemExit('Backup checksum mismatch; refusing restore.')
    tag = uuid.uuid4().hex[:12]
    container = 'educenter-restore-' + tag
    database = 'educenter_restore_' + tag + '_test'
    env = os.environ.copy()
    env['MYSQL_ROOT_PASSWORD'] = secrets.token_hex(24)
    created = False
    try:
        run(['docker', 'run', '-d', '--name', container, '--network', 'none', '-e', 'MYSQL_ROOT_PASSWORD',
             '-e', 'MYSQL_DATABASE=' + database, 'mysql:8.4', '--character-set-server=utf8mb4',
             '--collation-server=utf8mb4_unicode_ci', '--default-time-zone=+07:00'], env=env)
        created = True
        for attempt in range(60):
            try:
                # TCP distinguishes the final server from the initialization socket-only server.
                run(['docker', 'exec', container, 'sh', '-c',
                     'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --host=127.0.0.1 --protocol=TCP --user=root --execute="SELECT 1"'])
                break
            except RuntimeError:
                time.sleep(1)
        else:
            raise RuntimeError('Disposable MySQL did not become ready.')
        sql(container, database, path.read_text('utf-8'))
        if snapshot(container, database) != manifest['snapshot']:
            raise RuntimeError('Restored values/object counts differ from the backup manifest.')
        print('PASS: isolated restore matches all table hashes and trigger/routine counts. Source database untouched.')
        if args.migrate:
            metadata = sql(container, database, 'SELECT table_name,column_name FROM information_schema.columns '
                           'WHERE table_schema=DATABASE() ORDER BY table_name,ordinal_position')
            fields = {}
            for line in metadata.splitlines():
                table, col = line.split('\t')
                fields.setdefault(table, []).append(col)
            with tempfile.NamedTemporaryFile(mode='w', suffix='.env', encoding='utf-8', delete=False) as config:
                config.write('DB_NAME=' + database + '\n')
                env_path = config.name
            try:
                for action in ['preflight', 'migrate', 'check']:
                    run(['pwsh', '-NoProfile', '-File', str(ROOT / 'database/scripts/run.ps1'), '-Action', action,
                         '-Container', container, '-EnvFile', env_path])
            finally:
                os.unlink(env_path)
            after = snapshot(container, database, fields)['tables']
            for table, expected in manifest['snapshot']['tables'].items():
                if table not in ['cau_hinh', 'role_group_function', 'schema_migrations'] and after[table] != expected:
                    raise RuntimeError('Migration changed original business values in restored copy: ' + table)
            print('PASS: migration on actual-data clone passed; original business-column hashes preserved.')
    finally:
        if created:
            run(['docker', 'rm', '-f', '-v', container])
