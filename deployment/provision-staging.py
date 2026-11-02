"""Install a private, isolated staging origin. Does not alter Apache/port 80 or DNS.

Run only through an explicitly authorized SSH session. Secrets are generated/read
inside the server and are never printed or transferred back to the client.
"""
import argparse
import base64
import grp
import hashlib
import json
import os
from pathlib import Path
import pwd
import secrets
import shutil
import sqlite3
import subprocess
import tarfile
import time
import urllib.request

BASE = Path('/www/wwwroot/nansan-tools-staging')
OWNER = 'nansan-staging'
MARKER = 'NANSAN NET Tools: isolated staging v1'
PHP = Path('/www/server/php/84/bin/php')
FPM = Path('/www/server/php/84/sbin/php-fpm')
DB_NAME = 'nansan_tools_staging'

def run(args, *, env=None, data=None, user=False, quiet=False):
    command = ['runuser', '-u', OWNER, '--'] + list(args) if user else list(args)
    result = subprocess.run(command, input=data, env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
    if result.returncode:
        # Never echo subprocess arguments, SQL, environments or credentials.
        detail = (result.stderr + result.stdout).decode(errors='replace')[-2400:] if Path(args[0]).name in {'redis-server', 'nginx', 'php', 'php-fpm'} else ''
        private_env = BASE / 'shared/.env'
        if detail and private_env.exists():
            for line in private_env.read_text().splitlines():
                if '=' in line:
                    key, value = line.split('=', 1)
                    if value and any(word in key for word in ['PASSWORD', 'SECRET', 'KEY']):
                        detail = detail.replace(value, '<redacted>')
        raise RuntimeError('Operation failed: ' + Path(args[0]).name + (' — ' + detail if detail else ''))
    if not quiet and result.stdout:
        print(result.stdout.decode(errors='replace')[-2000:], flush=True)
    return result.stdout

def write(path, text, mode=0o640, owner=None):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text)
    path.chmod(mode)
    if owner:
        entry = pwd.getpwnam(owner)
        os.chown(path, entry.pw_uid, entry.pw_gid)

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--archive', required=True)
    parser.add_argument('--sha256', required=True)
    args = parser.parse_args()
    archive = Path(args.archive)
    if archive.parent != Path('/tmp') or not archive.name.startswith('nansan-staging-'):
        raise RuntimeError('Unexpected transfer archive path')
    if hashlib.sha256(archive.read_bytes()).hexdigest() != args.sha256:
        raise RuntimeError('Transfer checksum mismatch')
    if not PHP.is_file() or not FPM.is_file():
        raise RuntimeError('Verified PHP 8.4 CLI/FPM paths required')
    existed = BASE.exists()
    if BASE.is_symlink() or (existed and (BASE / '.owner').read_text().strip() != MARKER):
        raise RuntimeError('Staging path is not an owned project directory')
    if not Path('/run/systemd/system').is_dir():
        raise RuntimeError('This provisioner requires systemd; no service changes made')

    # Read aaPanel's database credential only within the authorized host process.
    panel = sqlite3.connect('/www/server/panel/data/default.db')
    row = panel.execute('SELECT mysql_root FROM config LIMIT 1').fetchone()
    panel.close()
    if not row or not row[0]:
        raise RuntimeError('Database provisioning access unavailable')
    mysql_env = {**os.environ, 'MYSQL_PWD': row[0]}
    def sql(statement):
        return run(['mysql', '--protocol=socket', '--user=root', '--batch', '--skip-column-names'],
                   env=mysql_env, data=statement.encode(), quiet=True)
    exists = sql("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='" + DB_NAME + "';").strip()
    if exists and not existed:
        raise RuntimeError('Database already exists without this project ownership marker')
    for unit in ['web', 'php', 'redis', 'worker', 'scheduler.service', 'scheduler.timer']:
        name = 'nansan-staging-' + unit + ('' if '.' in unit else '.service')
        if Path('/etc/systemd/system', name).exists() and not existed:
            raise RuntimeError('Service name already exists without project ownership')
    print('Ownership, database access and PHP prerequisites checked.', flush=True)

    BASE.mkdir(mode=0o750, parents=True, exist_ok=True)
    write(BASE / '.owner', MARKER + '\n', 0o600)
    try:
        account = pwd.getpwnam(OWNER)
        if account.pw_dir != str(BASE / 'shared'):
            raise RuntimeError('Existing service account has a different home')
    except KeyError:
        run(['useradd', '--system', '--user-group', '--home-dir', str(BASE / 'shared'), '--shell', '/usr/sbin/nologin', OWNER], quiet=True)
    account = pwd.getpwnam(OWNER)
    os.chown(BASE, 0, account.pw_gid)
    for folder in ['shared', 'shared/storage/app/private', 'shared/storage/framework/cache/data',
                   'shared/storage/framework/sessions', 'shared/storage/framework/views', 'shared/storage/logs',
                   'run', 'logs', 'redis', 'tools', 'releases']:
        target = BASE / folder
        target.mkdir(parents=True, exist_ok=True)
        os.chown(target, account.pw_uid if folder not in {'releases','tools'} else 0, account.pw_gid)
        target.chmod(0o750)

    # Extract distribution packages privately; do not install/start system port-80 services.
    package_dir = BASE / 'tools/packages'
    package_dir.mkdir(exist_ok=True)
    dependency_revision = 'nginx-redis-private-v2'
    revision_file = BASE / 'tools/dependency-revision'
    if not revision_file.exists() or revision_file.read_text() != dependency_revision:
        run(['apt-get', 'update'], quiet=True)
        packages = ['nginx-core', 'nginx-common', 'redis-server', 'redis-tools', 'libpcre3', 'libjemalloc2', 'liblzf1', 'liblua5.1-0', 'libhiredis0.14']
        for candidate in ['lua-bitop', 'lua-cjson', 'liblua5.1-bitop0', 'liblua5.1-cjson0', 'libatomic1']:
            available = subprocess.run(['apt-cache', 'show', candidate], stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            if available.returncode == 0 and available.stdout.strip():
                packages.append(candidate)
        for package in packages:
            result = subprocess.run(['apt-get', 'download', package], cwd=package_dir, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
            if result.returncode:
                raise RuntimeError('Private dependency download failed: ' + package)
        for package in package_dir.glob('*.deb'):
            run(['dpkg-deb', '-x', str(package), str(BASE / 'tools')], quiet=True)
        revision_file.write_text(dependency_revision)
    libraries = str(BASE / 'tools/usr/lib/x86_64-linux-gnu')
    composer = BASE / 'tools/composer.phar'
    composer_source = Path('/tmp/nansan-staging-composer.phar')
    if hashlib.sha256(composer_source.read_bytes()).hexdigest() != '6f1b891e1d4927d62740276c540fb490e47689e19e102c49e8da849794882f8c':
        raise RuntimeError('Private Composer checksum mismatch')
    shutil.copyfile(composer_source, composer)
    composer.chmod(0o644)
    runtime_env = {**os.environ, 'LD_LIBRARY_PATH': libraries}
    if run([str(PHP), '-r', 'echo extension_loaded("fileinfo")?"yes":"no";'], quiet=True).strip() != b'yes':
        private_module = BASE / 'tools/php-extensions/fileinfo.so'
        module = private_module if private_module.exists() else next(Path('/www/server/php/84/lib/php/extensions').glob('**/fileinfo.so'), None)
        if module is None:
            print('Building missing fileinfo extension privately from checksum-verified PHP source.', flush=True)
            php_version = run([str(PHP), '-r', 'echo PHP_VERSION;'], quiet=True).decode().strip()
            with urllib.request.urlopen('https://www.php.net/releases/index.php?json&version=' + php_version) as response:
                metadata = json.load(response)
            source_meta = next(item for item in metadata['source'] if item['filename'].endswith('.tar.xz'))
            source_archive = BASE / 'tools' / source_meta['filename']
            urllib.request.urlretrieve('https://www.php.net/distributions/' + source_meta['filename'], source_archive)
            if hashlib.sha256(source_archive.read_bytes()).hexdigest() != source_meta['sha256']:
                raise RuntimeError('PHP source checksum mismatch')
            source_root = BASE / 'tools/php-source'
            source_root.mkdir(exist_ok=True)
            with tarfile.open(source_archive) as source:
                selected = [member for member in source.getmembers() if '/ext/fileinfo/' in member.name and not member.issym() and not member.islnk()]
                source.extractall(source_root, members=selected, filter='data')
            extension = source_root / ('php-' + php_version) / 'ext/fileinfo'
            for command in [[str(PHP.parent / 'phpize')], ['./configure', '--with-php-config=' + str(PHP.parent / 'php-config')], ['make', '-j1']]:
                built = subprocess.run(command, cwd=extension, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
                if built.returncode:
                    raise RuntimeError('Private fileinfo build failed: ' + Path(command[0]).name)
            module = extension / 'modules/fileinfo.so'
        private_module = BASE / 'tools/php-extensions/fileinfo.so'
        private_module.parent.mkdir(exist_ok=True)
        if module.resolve() != private_module.resolve():
            shutil.copyfile(module, private_module)
        scan = BASE / 'php-conf.d'
        scan.mkdir(exist_ok=True)
        os.chown(scan, 0, account.pw_gid)
        scan.chmod(0o750)
        write(scan / 'fileinfo.ini', 'extension=' + str(private_module) + '\n')
        os.chown(scan / 'fileinfo.ini', 0, account.pw_gid)
        runtime_env['PHP_INI_SCAN_DIR'] = str(scan)
        if run([str(PHP), '-r', 'echo extension_loaded("fileinfo")?"yes":"no";'], env=runtime_env, quiet=True).strip() != b'yes':
            raise RuntimeError('Private fileinfo extension did not load')
    disabled = run([str(PHP), '-r', 'echo ini_get("disable_functions");'], quiet=True).decode().strip()
    needed = {'putenv', 'proc_open', 'proc_get_status', 'proc_close', 'proc_terminate', 'proc_nice', 'escapeshellarg', 'escapeshellcmd'}
    cli_disabled = ','.join(name.strip() for name in disabled.split(',') if name.strip() and name.strip() not in needed and not name.strip().startswith('pcntl_'))
    cli = [str(PHP), '-d', 'disable_functions=' + cli_disabled]
    run([str(BASE / 'tools/usr/sbin/nginx'), '-v'], env=runtime_env, quiet=True)
    run([str(BASE / 'tools/usr/bin/redis-server'), '--version'], env=runtime_env)

    release = BASE / 'releases' / time.strftime('%Y%m%d-%H%M%S')
    release.mkdir(mode=0o750)
    with tarfile.open(archive) as packed:
        for member in packed.getmembers():
            parts = Path(member.name).parts
            if member.name.startswith('/') or '..' in parts or member.issym() or member.islnk():
                raise RuntimeError('Unsafe release archive member')
            if any(part in {'.env', '.runtime', '.cache', '.git', 'node_modules'} for part in parts):
                raise RuntimeError('Private state found in release archive')
        packed.extractall(release, filter='data')
    (release / 'bootstrap/cache').mkdir(parents=True, exist_ok=True)
    os.symlink(BASE / 'shared/storage', release / 'storage')
    env_path = BASE / 'shared/.env'
    if not env_path.exists():
        db_password = secrets.token_hex(24)
        redis_password = secrets.token_hex(24)
        sql("CREATE DATABASE IF NOT EXISTS `" + DB_NAME + "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
            "CREATE USER IF NOT EXISTS 'nansan_staging'@'127.0.0.1' IDENTIFIED BY '" + db_password + "';"
            "CREATE USER IF NOT EXISTS 'nansan_staging'@'localhost' IDENTIFIED BY '" + db_password + "';"
            "ALTER USER 'nansan_staging'@'127.0.0.1' IDENTIFIED BY '" + db_password + "';"
            "ALTER USER 'nansan_staging'@'localhost' IDENTIFIED BY '" + db_password + "';"
            "GRANT ALL PRIVILEGES ON `" + DB_NAME + "`.* TO 'nansan_staging'@'127.0.0.1';"
            "GRANT ALL PRIVILEGES ON `" + DB_NAME + "`.* TO 'nansan_staging'@'localhost';")
        contents = '\n'.join([
            'APP_NAME="NANSAN NET Tools"', 'APP_ENV=staging', 'APP_DEBUG=false',
            'APP_URL=http://127.0.0.1:8091', 'APP_KEY=base64:' + base64.b64encode(secrets.token_bytes(32)).decode(),
            'APP_LOCALE=id', 'APP_FALLBACK_LOCALE=en', 'APP_TIMEZONE=Asia/Jakarta',
            'DB_CONNECTION=mysql', 'DB_HOST=127.0.0.1', 'DB_PORT=3306', 'DB_DATABASE=' + DB_NAME,
            'DB_USERNAME=nansan_staging', 'DB_PASSWORD=' + db_password,
            'SESSION_DRIVER=database', 'SESSION_COOKIE=nansan_staging_session', 'SESSION_ENCRYPT=true',
            'SESSION_SECURE_COOKIE=false', 'SESSION_SAME_SITE=lax',
            'CACHE_STORE=redis', 'CACHE_PREFIX=nansan_staging_', 'QUEUE_CONNECTION=redis',
            'REDIS_CLIENT=predis', 'REDIS_HOST=127.0.0.1', 'REDIS_PORT=6381', 'REDIS_PASSWORD=' + redis_password,
            'REDIS_PREFIX=nansan_staging_', 'REDIS_QUEUE_RETRY_AFTER=900',
            'TURNSTILE_ENABLED=true', 'TURNSTILE_SITE_KEY=', 'TURNSTILE_SECRET_KEY=',
            'TURNSTILE_HOSTNAMES=staging.mikrotik.nansannet.cloud', 'SITE_INDEXABLE=false',
            'LOG_CHANNEL=stack', 'LOG_LEVEL=warning', 'BACKUP_DISK=',
        ]) + '\n'
        write(env_path, contents, 0o600, OWNER)
        write(BASE / 'redis.conf', '\n'.join([
            'bind 127.0.0.1', 'port 6381', 'protected-mode yes', 'requirepass ' + redis_password,
            'dir ' + str(BASE / 'redis'), 'appendonly yes', 'appendfilename "staging.aof"',
            'maxmemory 128mb', 'maxmemory-policy noeviction', 'logfile ""',
        ]) + '\n', 0o600, OWNER)
    # Private, same-host encrypted snapshots and a restricted scratch-restore role.
    # Off-host storage remains a separate, unconfigured production requirement.
    values = dict(line.split('=', 1) for line in env_path.read_text().splitlines() if '=' in line and not line.startswith('#'))
    restore_marker = BASE / 'shared/.restore-account-owned'
    if not values.get('RESTORE_MYSQL_PASSWORD'):
        if sql("SELECT User FROM mysql.user WHERE User='nansan_restore';").strip() and not restore_marker.exists():
            raise RuntimeError('Restore account already exists without project ownership')
        restore_password = secrets.token_hex(24)
        for source in ['127.0.0.1', 'localhost']:
            sql("CREATE USER IF NOT EXISTS 'nansan_restore'@'" + source + "' IDENTIFIED BY '" + restore_password + "';"
                "ALTER USER 'nansan_restore'@'" + source + "' IDENTIFIED BY '" + restore_password + "';"
                "GRANT ALL PRIVILEGES ON `nansan\\_restore\\_%`.* TO 'nansan_restore'@'" + source + "';")
        write(restore_marker, MARKER + '\n', 0o600)
        values.update({'RESTORE_MYSQL_HOST': '127.0.0.1', 'RESTORE_MYSQL_PORT': '3306', 'RESTORE_MYSQL_USERNAME': 'nansan_restore', 'RESTORE_MYSQL_PASSWORD': restore_password})
    if not values.get('BACKUP_ENCRYPTION_KEY'):
        values['BACKUP_ENCRYPTION_KEY'] = base64.b64encode(secrets.token_bytes(32)).decode()
    if not values.get('BACKUP_DISK'):
        values['BACKUP_DISK'] = 'backups'
    values['MYSQL_CLIENT_PATH'] = shutil.which('mysql') or '/usr/bin/mysql'
    values['MYSQLDUMP_PATH'] = shutil.which('mysqldump') or '/usr/bin/mysqldump'
    values.setdefault('LOG_STACK', 'daily')
    values.setdefault('LOG_DAILY_DAYS', '14')
    write(env_path, '\n'.join(key + '=' + value for key, value in values.items()) + '\n', 0o600, OWNER)
    logrotate = shutil.which('logrotate')
    if logrotate:
        rotation = Path('/etc/logrotate.d/nansan-staging')
        rotation_marker = '# NANSAN NET Tools private staging log rotation'
        if rotation.exists() and not rotation.read_text().startswith(rotation_marker):
            raise RuntimeError('Logrotate configuration exists without project ownership')
        log = BASE / 'logs/php-fpm.log'
        if log.is_symlink():
            raise RuntimeError('Unexpected symlink at staging log path')
        if not log.exists(): log.touch(mode=0o640)
        os.chown(log, account.pw_uid, account.pw_gid)
        write(rotation, rotation_marker + f'''\n{BASE}/logs/*.log {{
 daily
 rotate 14
 compress
 delaycompress
 missingok
 notifempty
 copytruncate
 su {OWNER} {OWNER}
}}
''', 0o644)
        run([logrotate, '--debug', str(rotation)], quiet=True)
    os.symlink(env_path, release / '.env')
    run(['chown', '-R', OWNER + ':' + OWNER, str(release)], quiet=True)
    composer_env = {**runtime_env, 'HOME': str(BASE / 'shared'), 'COMPOSER_HOME': str(BASE / 'shared/composer'), 'COMPOSER_CACHE_DIR': str(BASE / 'shared/composer-cache')}
    run(cli + [str(composer), 'install', '--working-dir=' + str(release), '--no-dev', '--prefer-dist', '--no-interaction', '--optimize-autoloader'], env=composer_env, user=True)
    run(cli + [str(release / 'artisan'), 'migrate', '--force'], env=runtime_env, user=True)
    run(cli + [str(release / 'artisan'), 'db:seed', '--force'], env=runtime_env, user=True)
    run(cli + [str(composer), 'check-platform-reqs', '--no-dev', '--working-dir=' + str(release)], env=composer_env, user=True)

    write(BASE / 'php-fpm.conf', f'''[global]
pid = {BASE}/run/php-fpm.pid
error_log = {BASE}/logs/php-fpm.log
daemonize = no
[staging]
user = {OWNER}
group = {OWNER}
listen = {BASE}/run/php.sock
listen.owner = {OWNER}
listen.group = {OWNER}
listen.mode = 0600
pm = ondemand
pm.max_children = 3
pm.process_idle_timeout = 10s
clear_env = yes
php_admin_value[memory_limit] = 192M
php_admin_flag[display_errors] = off
''')
    write(BASE / 'nginx.conf', f'''worker_processes 1;
pid {BASE}/run/nginx.pid;
error_log {BASE}/logs/nginx-error.log warn;
events {{ worker_connections 256; }}
http {{
 include {BASE}/tools/etc/nginx/mime.types;
 default_type application/octet-stream;
 access_log {BASE}/logs/nginx-access.log;
 sendfile on;
 client_body_temp_path {BASE}/run/client;
 fastcgi_temp_path {BASE}/run/fastcgi;
 proxy_temp_path {BASE}/run/proxy;
 uwsgi_temp_path {BASE}/run/uwsgi;
 scgi_temp_path {BASE}/run/scgi;
 server {{
  listen 127.0.0.1:8091;
  server_name 127.0.0.1 staging.mikrotik.nansannet.cloud;
  root {BASE}/current/public;
  index index.php;
  client_max_body_size 6m;
  location / {{ try_files $uri $uri/ /index.php?$query_string; }}
  location ~ /\\. {{ deny all; }}
  location = /index.php {{
   include {BASE}/tools/etc/nginx/fastcgi_params;
   fastcgi_param SCRIPT_FILENAME $document_root/index.php;
   fastcgi_pass unix:{BASE}/run/php.sock;
   fastcgi_read_timeout 60s;
  }}
  location ~ \\.php$ {{ return 404; }}
 }}
}}
''')
    for folder in ['run/client', 'run/fastcgi', 'run/proxy', 'run/uwsgi', 'run/scgi']:
        target = BASE / folder
        target.mkdir(exist_ok=True)
        os.chown(target, account.pw_uid, account.pw_gid)
    os.chown(BASE / 'nginx.conf', 0, account.pw_gid)
    run([str(FPM), '-t', '-y', str(BASE / 'php-fpm.conf')], env=runtime_env, quiet=True)
    run([str(BASE / 'tools/usr/sbin/nginx'), '-t', '-c', str(BASE / 'nginx.conf'), '-p', str(BASE) + '/'], env=runtime_env, user=True, quiet=True)
    units = {
        'redis': (OWNER, f'{BASE}/tools/usr/bin/redis-server {BASE}/redis.conf', ''),
        'php': ('root', f'{FPM} --nodaemonize --fpm-config {BASE}/php-fpm.conf', ''),
        'web': (OWNER, f'{BASE}/tools/usr/sbin/nginx -c {BASE}/nginx.conf -p {BASE}/ -g "daemon off;"', 'After=nansan-staging-php.service\nRequires=nansan-staging-php.service\n'),
        'worker': (OWNER, ' '.join(cli) + f' {BASE}/current/artisan queue:work redis --sleep=3 --tries=3 --timeout=610', 'After=nansan-staging-redis.service\nRequires=nansan-staging-redis.service\n'),
    }
    for name, (user, command, dependencies) in units.items():
        write(Path('/etc/systemd/system/nansan-staging-' + name + '.service'), f'''[Unit]
Description=NANSAN NET private staging {name}
After=network.target
{dependencies}[Service]
Type=simple
User={user}
WorkingDirectory={BASE}
Environment=LD_LIBRARY_PATH={libraries}
Environment=PHP_INI_SCAN_DIR={BASE}/php-conf.d
ExecStart={command}
Restart=on-failure
RestartSec=5
NoNewPrivileges=true
PrivateTmp=true
[Install]
WantedBy=multi-user.target
''')
    write(Path('/etc/systemd/system/nansan-staging-scheduler.service'), f'''[Unit]
Description=NANSAN NET staging scheduler
[Service]
Type=oneshot
User={OWNER}
WorkingDirectory={BASE}
Environment=PHP_INI_SCAN_DIR={BASE}/php-conf.d
ExecStart={' '.join(cli)} {BASE}/current/artisan schedule:run
NoNewPrivileges=true
PrivateTmp=true
''')
    write(Path('/etc/systemd/system/nansan-staging-scheduler.timer'), '''[Unit]
Description=NANSAN NET staging minute timer
[Timer]
OnCalendar=*-*-* *:*:00
Persistent=true
[Install]
WantedBy=timers.target
''')
    temporary = BASE / 'next'
    if temporary.is_symlink(): temporary.unlink()
    os.symlink(release, temporary)
    os.replace(temporary, BASE / 'current')
    run(['systemctl', 'daemon-reload'], quiet=True)
    run(['systemctl', 'enable', '--now', 'nansan-staging-redis', 'nansan-staging-php', 'nansan-staging-web', 'nansan-staging-worker', 'nansan-staging-scheduler.timer'], quiet=True)
    run(cli + [str(BASE / 'current/artisan'), 'optimize'], user=True, env=runtime_env)
    # enable --now does not restart an already running worker after a release switch.
    run(['systemctl', 'restart', 'nansan-staging-php', 'nansan-staging-worker', 'nansan-staging-web'], quiet=True)
    run(['systemctl', 'start', 'nansan-staging-scheduler.service'], quiet=True)
    print(json.dumps({'status': 'internal_staging_installed', 'release': str(release), 'origin': 'http://127.0.0.1:8091', 'redis': '127.0.0.1:6381', 'production_modified': False, 'remaining': ['Turnstile keys', 'Cloudflare Access/Tunnel', 'off-host backups', 'generator publication gates']}), flush=True)

if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        print('STAGING BLOCKED: ' + str(error), flush=True)
        raise SystemExit(1)
