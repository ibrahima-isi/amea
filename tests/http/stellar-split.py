#!/usr/bin/env python3
"""End-to-end native Stellar packaging, deployment, rollback, and split-HTTP rehearsal."""
import http.cookiejar
import json
import os
import pathlib
import shutil
import socket
import subprocess
import tarfile
import tempfile
import time
import urllib.error
import urllib.request

from harness import NoRedirect, ROOT, SAFE_ENV, check


def free_port():
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return sock.getsockname()[1]


def request(base, path, method='GET', cookie_header=None):
    headers = {}
    if cookie_header:
        headers['Cookie'] = cookie_header
    opener = urllib.request.build_opener(NoRedirect())
    req = urllib.request.Request(base + path, headers=headers, method=method)
    try:
        resp = opener.open(req, timeout=10)
        return resp.code, resp.headers, resp.read()
    except urllib.error.HTTPError as err:
        return err.code, err.headers, err.read()


def wait_server(base, path):
    for _ in range(50):
        try:
            code, _, _ = request(base, path)
            if code in (200, 302, 404, 503):
                return
        except urllib.error.URLError:
            time.sleep(0.1)
    raise RuntimeError(f'Test server at {base} did not start')


with tempfile.TemporaryDirectory(prefix='amea-stellar-e2e-') as tmp:
    work = pathlib.Path(tmp).resolve()
    archive_path = work / 'release.tar.gz'

    # 1. Package secret-free release archive
    pkg = subprocess.run(
        ['php', str(ROOT / 'scripts/stellar/package.php'), f'--output={archive_path}', '--release-id=20261122-001'],
        cwd=ROOT, env=SAFE_ENV, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True,
    )
    pkg_info = json.loads(pkg.stdout)
    check(archive_path.is_file() and pkg_info['files'] > 10, 'package.php created release archive')

    with tarfile.open(archive_path, 'r:gz') as tar:
        names = [m.name.lstrip('./') for m in tar.getmembers()]
    check('src/Core/Router.php' in names and 'scripts/stellar/render-entry.php' in names, 'archive includes canonical router and native entry renderer')
    for forbidden_prefix in ('.env', '.git', 'tests/', 'docker/', 'uploads/', 'storage/', 'logs/', 'compose.', 'Dockerfile'):
        check(not any(n == forbidden_prefix or n.startswith(forbidden_prefix) for n in names),
              f'archive excludes {forbidden_prefix}')

    # 2. Deploy two releases using deploy.sh (synthetic config, --skip-migrations for local SQLite harness)
    base_dir = work / 'private'
    public_docroot = work / 'public_html'
    staff_docroot = work / 'staff_html'
    public_docroot.mkdir()
    staff_docroot.mkdir()

    auth_file = work / 'staging.htpasswd'
    auth_file.write_text('fixture:unused-in-test-server\n')
    os.chmod(auth_file, 0o600)

    runtime_cfg = work / 'staged-runtime.env'
    render_env = {
        **SAFE_ENV,
        'DB_HOST': '127.0.0.1',
        'DB_PORT': '3306',
        'DB_NAME': 'amea_synthetic',
        'DB_USER': 'amea_user',
        'DB_PASS': 'synthetic-pass',
        'PUBLIC_APP_URL': 'https://aeesgs.example',
        'STAFF_APP_URL': 'https://admin.aeesgs.example',
        'MAIL_USER': 'synthetic@example.invalid',
        'MAIL_PASS': 'synthetic-smtp-key',
        'MAIL_ASYNC': '1',
        'STORAGE_DRIVER': 's3',
        'R2_ACCOUNT_ID': '0123456789abcdef0123456789abcdef',
        'R2_BUCKET_NAME': 'amea-prod-synthetic',
        'R2_ACCESS_KEY_ID': 'synthetic-key',
        'R2_SECRET_ACCESS_KEY': 'synthetic-secret',
    }
    render_res = subprocess.run(
        ['php', str(ROOT / 'scripts/stellar/render-runtime-env.php'), f'--output={runtime_cfg}', '--app-env=production'],
        cwd=ROOT, env=render_env, check=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True,
    )
    check('synthetic-pass' not in render_res.stdout and 'synthetic-secret' not in render_res.stdout,
          'render-runtime-env.php never echoes secret values')
    check((runtime_cfg.stat().st_mode & 0o077) == 0, 'render-runtime-env.php writes 0600 file')

    def run_deploy(*extra):
        return subprocess.run(
            [
                'bash', str(ROOT / 'scripts/stellar/deploy.sh'),
                f'--base-dir={base_dir}',
                f'--public-docroot={public_docroot}',
                f'--staff-docroot={staff_docroot}',
                '--stellar-php-handler',
                f'--staging-auth-file={auth_file}',
                *extra,
            ],
            cwd=ROOT, env=SAFE_ENV, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True,
        )

    res1 = run_deploy(f'--archive={archive_path}', f'--config-source={runtime_cfg}', '--release-id=rel-001', '--skip-migrations')
    check(res1.returncode == 0, f'deploy.sh installed rel-001 ({res1.stderr.strip()})')

    live_cfg = base_dir / 'shared/config/runtime.env'
    check(live_cfg.is_file() and (live_cfg.stat().st_mode & 0o077) == 0, 'live runtime.env has 0600 permissions outside docroots')
    for docroot_name, docroot in (('public', public_docroot), ('staff', staff_docroot)):
        check((docroot / 'index.php').is_file() and (docroot / '.htaccess').is_file() and (docroot / 'assets/js/theme.js').is_file(),
              f'{docroot_name} docroot contains index.php, .htaccess, and static assets')
        htaccess = (docroot / '.htaccess').read_text()
        check('application/x-httpd-alt-php85___lsphp' in htaccess,
              f'{docroot_name} docroot preserves the Stellar PHP 8.5 handler')
        check(f'AuthUserFile {auth_file}' in htaccess and 'Require valid-user' in htaccess,
              f'{docroot_name} docroot retains private staging authentication')
        for forbidden in ('.env', 'runtime.env', 'src', 'vendor', 'config', 'scripts', 'storage', 'uploads', 'logs'):
            check(not (docroot / forbidden).exists(), f'{docroot_name} docroot excludes {forbidden}')

    # 3. Production sandbox-bucket guard blocks bad config without overwriting live runtime.env
    bad_cfg = work / 'bad-sandbox.env'
    bad_cfg.write_text(runtime_cfg.read_text().replace('R2_BUCKET_NAME=amea-prod-synthetic', 'R2_BUCKET_NAME=amea-sandbox'))
    os.chmod(bad_cfg, 0o600)
    res_bad = run_deploy(f'--archive={archive_path}', f'--config-source={bad_cfg}', '--release-id=rel-bad', '--skip-migrations')
    check(res_bad.returncode != 0, 'deploy.sh blocks amea-sandbox bucket in production')
    check('amea-prod-synthetic' in live_cfg.read_text(), 'failed deploy leaves live runtime.env untouched')
    check(not (base_dir / 'releases/rel-bad').exists(), 'failed deploy cleans up staged release directory')

    # A failed migration must leave the active release and config intact.
    migration_cfg = work / 'unreachable-db.env'
    migration_cfg.write_text(runtime_cfg.read_text().replace('DB_PORT=3306', f'DB_PORT={free_port()}'))
    os.chmod(migration_cfg, 0o600)
    res_migrate = run_deploy(f'--archive={archive_path}', f'--config-source={migration_cfg}', '--release-id=rel-migration-failed')
    check(res_migrate.returncode != 0 and 'Migration command failed' in res_migrate.stderr,
          'deployment reports a failed Doctrine migration')
    check((base_dir / 'current').resolve().name == 'rel-001' and live_cfg.read_text() == runtime_cfg.read_text(),
          'failed migration preserves the active release and config')

    # 4. Deploy second release and verify previous link + rollback
    res2 = run_deploy(f'--archive={archive_path}', f'--config-source={runtime_cfg}', '--release-id=rel-002', '--skip-migrations')
    check(res2.returncode == 0, 'deploy.sh installed rel-002')
    check((base_dir / 'current').resolve().name == 'rel-002', 'current points to rel-002')
    check((base_dir / 'previous').resolve().name == 'rel-001', 'previous retains rel-001')

    # 5. Cutover write-gate pause blocks deployment AND rollback
    gate_dir = base_dir / 'shared/state/write-gate'
    subprocess.run(
        ['php', '-r', f"require '{ROOT}/vendor/autoload.php'; (new Amea\\Core\\FileWriteGate($argv[1]))->pause();", str(gate_dir)],
        cwd=ROOT, env=SAFE_ENV, check=True,
    )
    res_paused = run_deploy(f'--archive={archive_path}', f'--config-source={runtime_cfg}', '--release-id=rel-003', '--skip-migrations')
    check(res_paused.returncode != 0 and 'paused' in res_paused.stderr.lower(), 'deploy.sh refuses deployment during cutover pause')
    (gate_dir / 'paused.json').unlink()

    res_rb = run_deploy('--rollback')
    check(res_rb.returncode == 0 and (base_dir / 'current').resolve().name == 'rel-001', 'deploy.sh --rollback restores rel-001')

    # 6. CLI jobs wrapper loads private config, uses shared lock & mail queue, and rejects web identity
    job_env = {**SAFE_ENV, 'APP_ENV': 'test'}
    job_res = subprocess.run(['php', str(base_dir / 'bin/run-job.php'), 'mail'], env=job_env, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    check(job_res.returncode == 0, f'bin/run-job.php executes shared mail job ({job_res.stderr.strip()})')
    check((base_dir / 'shared/state/.shared-jobs.lock').is_file(), 'shared jobs lock created inside persistent private state')
    web_job_res = subprocess.run(
        ['php', str(base_dir / 'current/scripts/run-shared-jobs.php'), 'mail'],
        env={**SAFE_ENV, 'AMEA_APP': 'public'}, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
    )
    check(web_job_res.returncode != 0, 'run-shared-jobs.php refuses web identity')

    # 7. Real split HTTP verification against packaged public and staff docroots (SQLite preload)
    sqlite_db = work / 'fixture.sqlite'
    fixture_app = work / 'amea-http-seed'
    shutil.copytree(ROOT / 'src', fixture_app / 'src')
    shutil.copytree(ROOT / 'config', fixture_app / 'config')
    shutil.copytree(ROOT / 'vendor', fixture_app / 'vendor')
    (fixture_app / 'tests').mkdir()
    shutil.copy2(ROOT / 'tests/bootstrap.php', fixture_app / 'tests/bootstrap.php')
    shutil.copytree(ROOT / 'tests/http', fixture_app / 'tests/http')
    subprocess.run(['php', str(fixture_app / 'tests/http/fixture.php')], cwd=fixture_app, env=SAFE_ENV, check=True)
    shutil.copy2(fixture_app / 'fixture.sqlite', sqlite_db)

    # Switch live runtime config to local test mode for loopback HTTP testing
    test_cfg = base_dir / 'shared/config/runtime.env'
    test_cfg.write_text('\n'.join([
        'APP_ENV=test',
        'DB_HOST=127.0.0.1',
        'DB_NAME=unused',
        'DB_USER=unused',
        'DB_PASS=unused',
        'PUBLIC_APP_URL=https://aeesgs.example',
        'STAFF_APP_URL=https://admin.aeesgs.example',
        'MAIL_USER=synthetic@example.invalid',
        'MAIL_PASS=synthetic-smtp-key',
        'STORAGE_DRIVER=local',
        '',
    ]))
    os.chmod(test_cfg, 0o600)

    # Preload SQLite Container::db() so packaged index.php runs unchanged without MySQL.
    preload = work / 'sqlite-preload.php'
    preload.write_text(f"""<?php
require_once '{base_dir / "current/vendor/autoload.php"}';
require_once '{ROOT / "tests/http/SqliteFixture.php"}';
$db = new SqliteFixture('sqlite:{sqlite_db}');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
\\Amea\\Core\\Container::setDb($db);
""")

    # Thin PHP dev-server router emulating LiteSpeed .htaccess (assets directly, all else -> packaged index.php).
    def make_router(docroot):
        router = work / f'router-{docroot.name}.php'
        router.write_text(f"""<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (is_string($path) && preg_match('#^/assets/.+\\.(css|js|json|png|jpg|jpeg|gif|svg|webp|ico|woff2?|ttf)$#i', $path)) {{
    $asset = realpath('{docroot}' . $path);
    if ($asset && str_starts_with($asset, '{docroot}/assets/') && is_file($asset)) {{
        return false;
    }}
}}
require '{docroot}/index.php';
""")
        return router

    pub_port, staff_port = free_port(), free_port()
    pub_base, staff_base = f'http://127.0.0.1:{pub_port}', f'http://127.0.0.1:{staff_port}'
    pub_log = open(work / 'pub.log', 'w+')
    staff_log = open(work / 'staff.log', 'w+')

    # Disable cookie_secure only for loopback HTTP so urllib receives cookies; production keeps Session's Secure=1.
    common_flags = ['-d', f'auto_prepend_file={preload}']
    pub_srv = subprocess.Popen(
        ['php', *common_flags, '-S', f'127.0.0.1:{pub_port}', '-t', str(public_docroot), str(make_router(public_docroot))],
        env=SAFE_ENV, stdout=pub_log, stderr=pub_log,
    )
    staff_srv = subprocess.Popen(
        ['php', *common_flags, '-S', f'127.0.0.1:{staff_port}', '-t', str(staff_docroot), str(make_router(staff_docroot))],
        env=SAFE_ENV, stdout=staff_log, stderr=staff_log,
    )
    try:
        wait_server(pub_base, '/legal-notice.php')
        wait_server(staff_base, '/login.php')

        code, headers, body = request(pub_base, '/legal-notice.php')
        check(code == 200 and 'AMEA_PUBLIC=' in (headers.get('Set-Cookie') or ''), 'public packaged entry serves /legal-notice.php with AMEA_PUBLIC cookie')

        code, _, _ = request(pub_base, '/assets/js/theme.js')
        check(code == 200, 'public docroot serves allowlisted static asset')

        for staff_path in ('/login.php', '/dashboard.php', '/download.php', '/students.php'):
            for method in ('GET', 'POST'):
                code, headers, _ = request(pub_base, staff_path, method=method)
                check(code == 404 and 'Location' not in headers, f'public packaged entry returns local 404 for {method} {staff_path}')

        code, headers, _ = request(staff_base, '/')
        check(code == 302 and headers.get('Location') == '/login.php', 'staff packaged entry redirects / to /login.php')

        code, headers, _ = request(staff_base, '/login.php')
        check(code == 200 and 'AMEA_STAFF=' in (headers.get('Set-Cookie') or ''), 'staff packaged entry serves /login.php with AMEA_STAFF cookie')

        for pub_only in ('/register.php', '/journal.php'):
            code, _, _ = request(staff_base, pub_only)
            check(code == 404, f'staff packaged entry denies public-only {pub_only}')

        # verify-http.php validates the packaged split HTTP boundaries and fails closed if Basic Auth is expected but absent.
        verify_http = subprocess.run(
            ['php', str(ROOT / 'scripts/stellar/verify-http.php'), f'--public-url={pub_base}', f'--staff-url={staff_base}'],
            cwd=ROOT, env=SAFE_ENV, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True,
        )
        check(verify_http.returncode == 0, f'verify-http.php passed on packaged split servers ({verify_http.stdout.strip()} {verify_http.stderr.strip()})')

        verify_http_auth_fail = subprocess.run(
            [
                'php', str(ROOT / 'scripts/stellar/verify-http.php'),
                f'--public-url={pub_base}', f'--staff-url={staff_base}',
                '--basic-auth-env=STELLAR_STAGING_BASIC_AUTH',
            ],
            cwd=ROOT, env={**SAFE_ENV, 'STELLAR_STAGING_BASIC_AUTH': 'stage:secret-pass'},
            stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True,
        )
        check(verify_http_auth_fail.returncode != 0 and 'secret-pass' not in verify_http_auth_fail.stdout,
              'verify-http.php fails closed when expected Basic Auth 401 challenge is missing')

        pub_sessions = list((base_dir / 'shared/state/sessions/public').glob('sess_*'))
        staff_sessions = list((base_dir / 'shared/state/sessions/staff').glob('sess_*'))
        check(len(pub_sessions) >= 1 and len(staff_sessions) >= 1, 'public and staff persist sessions in isolated private directories')

        pub_twig = list((base_dir / 'shared/state/cache/twig/public').rglob('*.php'))
        staff_twig = list((base_dir / 'shared/state/cache/twig/staff').rglob('*.php'))
        check(len(pub_twig) >= 1 and len(staff_twig) >= 1, 'public and staff compile into separate Twig cache directories')

        # Shared write gate pauses BOTH packaged entries simultaneously while keeping stateless journal open.
        subprocess.run(
            ['php', '-r', f"require '{ROOT}/vendor/autoload.php'; (new Amea\\Core\\FileWriteGate($argv[1]))->pause();", str(gate_dir)],
            cwd=ROOT, env=SAFE_ENV, check=True,
        )
        check(request(pub_base, '/register.php')[0] == 503, 'shared write gate pauses public stateful route')
        check(request(staff_base, '/login.php')[0] == 503, 'shared write gate pauses staff route')
        check(request(pub_base, '/journal.php')[0] == 200, 'stateless journal stays 200 during write-gate pause')
    finally:
        pub_srv.terminate()
        staff_srv.terminate()
        pub_srv.wait(timeout=10)
        staff_srv.wait(timeout=10)
        pub_log.close()
        staff_log.close()

print('PASS: native Stellar packaging, deployment, rollback, and split HTTP boundaries verified')
