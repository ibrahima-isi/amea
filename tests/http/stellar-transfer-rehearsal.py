#!/usr/bin/env python3
"""End-to-end Ticket 03 rehearsal: legacy single-app VPS -> split native Stellar + real Chrome resume.

Runs offline against isolated local storage by default (suitable for CI), or against
the real Cloudflare R2 `amea-sandbox` bucket when AMEA_REHEARSAL_R2_ENV points to a
private 0600 env file containing R2_ENDPOINT/R2_BUCKET_NAME=amea-sandbox/R2_ACCESS_KEY_ID/R2_SECRET_ACCESS_KEY.
"""
import contextlib
import http.cookiejar
import json
import os
import pathlib
import re
import shutil
import socket
import sqlite3
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

from harness import NoRedirect, ROOT, SAFE_ENV, check, client


def free_port():
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        return sock.getsockname()[1]


def http_req(opener, base, path, data=None, method=None, headers=None):
    req_headers = dict(headers or {})
    payload = urllib.parse.urlencode(data).encode() if data is not None else None
    req = urllib.request.Request(base + path, data=payload, headers=req_headers, method=method)
    try:
        resp = opener.open(req, timeout=15)
        return resp.code, resp.headers, resp.read()
    except urllib.error.HTTPError as err:
        return err.code, err.headers, err.read()


def wait_server(base, path):
    probe = client()
    for _ in range(60):
        try:
            code, _, _ = http_req(probe, base, path)
            if code in (200, 302, 404, 503):
                return
        except urllib.error.URLError:
            time.sleep(0.1)
    raise RuntimeError(f'Test server at {base} did not start')


def load_r2_env(path_str):
    if not path_str:
        return None
    env_path = pathlib.Path(path_str).resolve()
    if not env_path.is_file():
        raise RuntimeError(f'R2 env file not found: {env_path}')
    parsed = {}
    for line in env_path.read_text().splitlines():
        trimmed = line.lstrip()
        if not trimmed or trimmed.startswith('#') or '=' not in line:
            continue
        k, v = line.split('=', 1)
        k = k.strip()
        if k in ('R2_ENDPOINT', 'R2_BUCKET_NAME', 'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY'):
            parsed[k] = v.strip().strip('"').strip("'")
    if parsed.get('R2_BUCKET_NAME') != 'amea-sandbox':
        raise RuntimeError('Live R2 rehearsal is strictly restricted to R2_BUCKET_NAME=amea-sandbox.')
    for req in ('R2_ENDPOINT', 'R2_BUCKET_NAME', 'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY'):
        if not parsed.get(req):
            raise RuntimeError(f'Missing required R2 setting: {req}')
    return parsed


def run_php(code, args=(), env=None):
    merged_env = {**SAFE_ENV, **(env or {})}
    res = subprocess.run(
        ['php', '-r', code, *args],
        cwd=ROOT,
        env=merged_env,
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
    )
    if res.returncode != 0:
        raise RuntimeError(f'PHP helper failed (rc={res.returncode}): {res.stderr.strip()} | {res.stdout.strip()}')
    return res.stdout


def clean_r2_sandbox(r2_cfg):
    if not r2_cfg:
        return
    run_php(
        """
        require $argv[1] . '/vendor/autoload.php';
        $storage = Amea\\Storage\\StorageFactory::fromEnvironment($argv[1]);
        $cursor = null;
        do {
            $page = $storage->listPage('uploads/', $cursor, 500);
            foreach ($page['objects'] as $obj) {
                $storage->delete($obj['key']);
            }
            $cursor = $page['cursor'];
        } while ($cursor !== null);
        $remaining = $storage->listPage('uploads/', null, 10)['objects'];
        if ($remaining !== []) {
            fwrite(STDERR, 'R2 sandbox cleanup left objects behind.');
            exit(1);
        }
        """,
        args=(str(ROOT),),
        env={
            'STORAGE_DRIVER': 's3',
            'S3_PATH_STYLE': 'false',
            **r2_cfg,
        },
    )


def main():
    r2_cfg = load_r2_env(os.environ.get('AMEA_REHEARSAL_R2_ENV', ''))
    use_r2 = r2_cfg is not None
    if use_r2:
        # Ensure sandbox starts clean before rehearsal
        count = int(run_php(
            """
            require $argv[1] . '/vendor/autoload.php';
            $storage = Amea\\Storage\\StorageFactory::fromEnvironment($argv[1]);
            echo count($storage->listPage('uploads/', null, 10)['objects']);
            """,
            args=(str(ROOT),),
            env={'STORAGE_DRIVER': 's3', 'S3_PATH_STYLE': 'false', **r2_cfg},
        ).strip())
        check(count == 0, 'amea-sandbox R2 bucket starts empty before rehearsal')

    identity_pdf = '%PDF-1.4\nAMEA Transfer Rehearsal Identity Document\n%%EOF\n'
    cv_pdf = '%PDF-1.4\nAMEA Transfer Rehearsal CV Document\n%%EOF\n'
    existing_pdf = '%PDF-1.4\nAMEA Legacy Existing Confirmed Member PDF\n%%EOF\n'
    existing_created_at = 1600000000
    staged_created_at = 1600000123

    with tempfile.TemporaryDirectory(prefix='amea-transfer-rehearsal-') as tmp:
        work = pathlib.Path(tmp).resolve()
        source_root = work / 'source'
        source_sessions = work / 'source-sessions'
        source_storage = work / 'source-storage'
        source_gate_dir = work / 'source-gate'
        evidence_dir = work / 'evidence'
        copy_dest_local = work / 'copy-dest'
        restore_dest_local = work / 'restore-dest'

        for d in (source_root, source_root / 'uploads', source_sessions, source_storage,
                  source_gate_dir, evidence_dir, copy_dest_local, copy_dest_local / 'uploads',
                  restore_dest_local, restore_dest_local / 'uploads'):
            d.mkdir(parents=True, exist_ok=True)
            os.chmod(d, 0o700)

        # Build isolated SQLite fixture DB for the legacy single-app source
        fixture_dir = work / 'amea-http-seed'
        shutil.copytree(ROOT / 'src', fixture_dir / 'src')
        shutil.copytree(ROOT / 'config', fixture_dir / 'config')
        shutil.copytree(ROOT / 'vendor', fixture_dir / 'vendor')
        (fixture_dir / 'tests').mkdir()
        shutil.copy2(ROOT / 'tests/bootstrap.php', fixture_dir / 'tests/bootstrap.php')
        shutil.copytree(ROOT / 'tests/http', fixture_dir / 'tests/http')
        subprocess.run(['php', str(fixture_dir / 'tests/http/fixture.php')], cwd=fixture_dir, env=SAFE_ENV, check=True)
        source_db = work / 'source.sqlite'
        shutil.copy2(fixture_dir / 'fixture.sqlite', source_db)
        with sqlite3.connect(source_db) as conn:
            conn.execute("UPDATE users SET permissions='[\"students\",\"slider\",\"documents\"]'")
            conn.execute(
                "INSERT INTO students (id, last_name, first_name, gender, status, phone, email, identity_document, kyc_status, consent_privacy) "
                "VALUES (99, 'Legacy', 'Confirmed', 'Masculin', 'ETUDIANT', '770000099', 'legacy-confirmed@example.invalid', 'uploads/students/legacy-existing.pdf', 'APPROVED', 1)"
            )
            conn.commit()

        # Write pre-existing confirmed PDF into source local storage with backdated createdAt
        run_php(
            """
            require $argv[1] . '/vendor/autoload.php';
            $storage = new Amea\\Storage\\LocalStorage($argv[2]);
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, $argv[3]);
            rewind($stream);
            $storage->write('uploads/students/legacy-existing.pdf', $stream, 'application/pdf', (int) $argv[4]);
            fclose($stream);
            """,
            args=(str(ROOT), str(source_root), existing_pdf, str(existing_created_at)),
        )

        # Legacy single-app server (no AMEA_APP -> PHPSESSID + single session store + local storage)
        legacy_router = work / 'legacy-router.php'
        legacy_router.write_text(f"""<?php
require_once '{ROOT / "vendor/autoload.php"}';
require_once '{ROOT / "tests/http/SqliteFixture.php"}';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (is_string($path) && preg_match('#^/assets/.+\\.(css|js|json|png|jpg|jpeg|gif|svg|webp|ico|woff2?|ttf)$#i', $path)) {{
    $asset = realpath('{ROOT}' . $path);
    if ($asset && str_starts_with($asset, '{ROOT}/assets/') && is_file($asset)) {{
        return false;
    }}
}}
$_ENV['APP_ENV'] = 'test';
$_ENV['STORAGE_DRIVER'] = 'local';
$_ENV['FILE_WRITE_GATE_PATH'] = '{source_gate_dir}';
putenv('APP_ENV=test');
putenv('STORAGE_DRIVER=local');
putenv('FILE_WRITE_GATE_PATH={source_gate_dir}');
$db = new SqliteFixture('sqlite:{source_db}');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
\\Amea\\Core\\Container::setDb($db);
\\Amea\\Core\\Container::setStorage(new \\Amea\\Storage\\LocalStorage('{source_root}'));

$routes = [
    '/' => [\\Amea\\Controller\\HomeController::class, 'index'],
    '/register.php' => [\\Amea\\Controller\\RegistrationController::class, $_SERVER['REQUEST_METHOD'] === 'POST' ? 'register' : 'showForm'],
    '/registration-details.php' => [\\Amea\\Controller\\RegistrationController::class, $_SERVER['REQUEST_METHOD'] === 'POST' ? 'confirm' : 'review'],
    '/registration-preview.php' => [\\Amea\\Controller\\RegistrationController::class, 'preview'],
    '/login.php' => [\\Amea\\Controller\\AuthController::class, 'login'],
    '/dashboard.php' => [\\Amea\\Controller\\DashboardController::class, 'index'],
];
if (!isset($routes[$path])) {{
    http_response_code(404);
    echo 'Not found';
    return;
}}
(new \\Amea\\Core\\Session())->startRequest();
[$cls, $method] = $routes[$path];
(new $cls())->$method();
""")

        pub_port = free_port()
        staff_port = free_port()
        pub_base = f'http://127.0.0.1:{pub_port}'
        staff_base = f'http://127.0.0.1:{staff_port}'

        legacy_log = open(work / 'legacy.log', 'w+')
        legacy_srv = subprocess.Popen(
            ['php', '-d', f'session.save_path={source_sessions}', '-S', f'127.0.0.1:{pub_port}', '-t', str(ROOT), str(legacy_router)],
            cwd=ROOT,
            env=SAFE_ENV,
            stdout=legacy_log,
            stderr=legacy_log,
        )

        chrome_proc = None
        pub_srv = None
        staff_srv = None
        pub_log = None
        staff_log = None
        timings = {}

        try:
            wait_server(pub_base, '/register.php')

            # 1a. Legacy staff signs into combined single-app source (populates legacy PHPSESSID staff session)
            legacy_staff = client()
            status, _, login_html = http_req(legacy_staff, pub_base, '/login.php')
            check(status == 200, 'legacy single-app /login.php renders')
            csrf = re.search(rb'name="csrf_token"\s+value="([^"]+)"', login_html).group(1).decode()
            status, headers, _ = http_req(legacy_staff, pub_base, '/login.php', {
                'csrf_token': csrf,
                'username': 'fixture-admin',
                'password': 'fixture-password',
            })
            check(status == 302 and 'dashboard.php' in (headers.get('Location') or ''), 'legacy staff signs into combined source')
            status, _, _ = http_req(legacy_staff, pub_base, '/dashboard.php')
            check(status == 200, 'legacy staff accesses /dashboard.php on combined source')

            legacy_staff_cookie = None
            for handler in legacy_staff.handlers:
                if isinstance(handler, urllib.request.HTTPCookieProcessor):
                    for c in handler.cookiejar:
                        if c.name == 'PHPSESSID':
                            legacy_staff_cookie = f'PHPSESSID={c.value}'
            check(legacy_staff_cookie is not None, 'legacy staff holds a PHPSESSID session cookie')

            # 1b. Real Chrome browser starts an unconfirmed registration on the legacy single-app source
            chrome_proc = subprocess.Popen(
                ['node', str(ROOT / 'tests/http/stellar-transfer-browser.mjs'), pub_base, identity_pdf, cv_pdf],
                cwd=ROOT,
                env={**SAFE_ENV, **{k: os.environ[k] for k in ('HOME', 'CHROME_BIN') if k in os.environ}},
                stdin=subprocess.PIPE,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
            )
            staged_line = chrome_proc.stdout.readline().strip()
            if not staged_line.startswith('STAGED_READY'):
                err_out = chrome_proc.stderr.read()
                raise RuntimeError(f'Chrome staging failed: {staged_line} | {err_out}')
            check(True, 'original Chrome browser staged two PDFs on legacy single-app source with PHPSESSID')

            # Backdate staged pending files' createdAt via LocalStorage so metadata + mtime both match staged_created_at
            staged_files = sorted((source_root / 'uploads/students/pending').rglob('*.pdf'))
            check(len(staged_files) == 2, 'two staged pending PDFs exist in legacy source local storage')
            staged_keys = [sf.relative_to(source_root).as_posix() for sf in staged_files]
            run_php(
                """
                require $argv[1] . '/vendor/autoload.php';
                $storage = new Amea\\Storage\\LocalStorage($argv[2]);
                $keys = json_decode($argv[3], true, 16, JSON_THROW_ON_ERROR);
                $ts = (int) $argv[4];
                foreach ($keys as $k) {
                    $stream = $storage->read($k);
                    $bytes = stream_get_contents($stream);
                    fclose($stream);
                    $storage->delete($k);
                    $tmp = fopen('php://temp', 'w+b');
                    fwrite($tmp, $bytes);
                    rewind($tmp);
                    $storage->write($k, $tmp, 'application/pdf', $ts);
                    fclose($tmp);
                }
                """,
                args=(str(ROOT), str(source_root), json.dumps(staged_keys), str(staged_created_at)),
            )

            # 2. Pause source writers & stop legacy server
            pause_started = time.monotonic()
            dest_env_for_migrate = {
                'MIGRATION_ENVIRONMENT': 'ticket03-rehearsal',
                'MIGRATION_DB_DSN': f'sqlite:{source_db}',
                'MIGRATION_SOURCE_DRIVER': 'local',
                'MIGRATION_SOURCE_ROOT': str(source_root),
                'MIGRATION_SESSION_PATH': str(source_sessions),
                'MIGRATION_SESSION_SERIALIZER': 'php',
                'MIGRATION_TOPOLOGY': 'single',
                'FILE_WRITE_GATE_PATH': str(source_gate_dir),
            }
            if use_r2:
                dest_env_for_migrate.update({
                    'MIGRATION_DESTINATION_DRIVER': 's3',
                    'MIGRATION_DESTINATION_S3_ENDPOINT': r2_cfg['R2_ENDPOINT'],
                    'MIGRATION_DESTINATION_S3_REGION': 'auto',
                    'MIGRATION_DESTINATION_S3_BUCKET': r2_cfg['R2_BUCKET_NAME'],
                    'MIGRATION_DESTINATION_S3_ACCESS_KEY_ID': r2_cfg['R2_ACCESS_KEY_ID'],
                    'MIGRATION_DESTINATION_S3_SECRET_ACCESS_KEY': r2_cfg['R2_SECRET_ACCESS_KEY'],
                    'MIGRATION_DESTINATION_S3_PATH_STYLE': 'false',
                })
            else:
                dest_env_for_migrate.update({
                    'MIGRATION_DESTINATION_DRIVER': 'local',
                    'MIGRATION_DESTINATION_ROOT': str(copy_dest_local),
                })

            ids = json.loads(run_php(
                """
                require $argv[1] . '/vendor/autoload.php';
                $db = new PDO('sqlite:' . $argv[2], options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $source = new Amea\\Storage\\LocalStorage($argv[3]);
                $dest = Amea\\Storage\\StorageFactory::fromEnvironment($argv[4]);
                echo json_encode([
                    'database' => Amea\\Storage\\Migration\\ReferenceAudit::databaseIdentity($db),
                    'source' => Amea\\Storage\\Migration\\Manifest::backendIdentity($source),
                    'destination' => Amea\\Storage\\Migration\\Manifest::backendIdentity($dest),
                ], JSON_THROW_ON_ERROR);
                """,
                args=(str(ROOT), str(source_db), str(source_root), str(copy_dest_local)),
                env=({'STORAGE_DRIVER': 's3', 'S3_PATH_STYLE': 'false', **r2_cfg} if use_r2 else {'STORAGE_DRIVER': 'local'}),
            ))

            manifest_path = evidence_dir / 'transfer.jsonl'
            mig_base_args = [
                f'--environment=ticket03-rehearsal',
                f'--database={ids["database"]}',
                f'--source={ids["source"]}',
                f'--destination={ids["destination"]}',
                f'--manifest={manifest_path}',
            ]

            def run_migrate(op, extra=()):
                return subprocess.run(
                    ['php', str(ROOT / 'scripts/storage-migrate.php'), op, *mig_base_args, *extra],
                    cwd=ROOT,
                    env={**SAFE_ENV, **dest_env_for_migrate},
                    stdout=subprocess.PIPE,
                    stderr=subprocess.PIPE,
                    text=True,
                )

            res = run_migrate('pause')
            check(res.returncode == 0, f'source write gate paused ({res.stderr.strip()})')

            # Confirm paused source rejects new public registrations (503) before shutting down legacy server
            check(http_req(client(), pub_base, '/register.php')[0] == 503, 'paused legacy source returns 503 on /register.php')
            legacy_srv.terminate()
            legacy_srv.wait(timeout=10)
            legacy_srv = None

            copy_started = time.monotonic()
            res = run_migrate('inventory')
            check(res.returncode == 0, f'storage-migrate inventory passed ({res.stderr.strip()})')
            review_sha = re.search(r'Manifest SHA256 ([a-f0-9]{64})', res.stdout).group(1)

            res = run_migrate('copy', [f'--review-manifest={review_sha}'])
            check(res.returncode == 0, f'storage-migrate copy to {"R2 amea-sandbox" if use_r2 else "local"} passed ({res.stderr.strip()})')

            res = run_migrate('verify')
            if res.returncode != 0 or 'Verification differences 0' not in res.stdout:
                cp = pathlib.Path(str(manifest_path) + '.checkpoint')
                diag = cp.read_text() if cp.is_file() else ''
                raise AssertionError(f'storage-migrate verify failed (rc={res.returncode}): {res.stdout.strip()} {res.stderr.strip()} | checkpoint={diag}')
            check(True, 'storage-migrate verify confirmed 0 differences')
            timings['copy_and_verify_ms'] = round((time.monotonic() - copy_started) * 1000, 1)

            # Verify MIME, keys, and preserved original staging age on destination
            dest_check = json.loads(run_php(
                """
                require $argv[1] . '/vendor/autoload.php';
                $dest = Amea\\Storage\\StorageFactory::fromEnvironment($argv[2]);
                $keys = json_decode($argv[3], true, 16, JSON_THROW_ON_ERROR);
                $out = [];
                foreach ($keys as $k) {
                    $m = $dest->stat($k);
                    $stream = $dest->read($k);
                    $fp = Amea\\Storage\\Migration\\Inventory::fingerprint($stream);
                    fclose($stream);
                    $out[$k] = ['mime' => $m['mime'], 'createdAt' => $m['createdAt'], 'sha256' => $fp['sha256'], 'size' => $fp['size']];
                }
                echo json_encode($out, JSON_THROW_ON_ERROR);
                """,
                args=(str(ROOT), str(copy_dest_local), json.dumps([
                    'uploads/students/legacy-existing.pdf',
                    *staged_keys,
                ])),
                env=({'STORAGE_DRIVER': 's3', 'S3_PATH_STYLE': 'false', **r2_cfg} if use_r2 else {'STORAGE_DRIVER': 'local'}),
            ))
            check(dest_check['uploads/students/legacy-existing.pdf']['createdAt'] == existing_created_at
                  and dest_check['uploads/students/legacy-existing.pdf']['mime'] == 'application/pdf',
                  'destination preserved confirmed PDF MIME and original age')
            for k in staged_keys:
                check(dest_check[k]['createdAt'] == staged_created_at and dest_check[k]['mime'] == 'application/pdf',
                      f'destination preserved staged {k} MIME and original staging age')

            # 2b. Interrupted/failed transfer exercise #1: remove one destination object -> verify fails -> resume blocked -> recover cleanly
            dropped_key = staged_keys[0]
            run_php(
                """
                require $argv[1] . '/vendor/autoload.php';
                Amea\\Storage\\StorageFactory::fromEnvironment($argv[2])->delete($argv[3]);
                """,
                args=(str(ROOT), str(copy_dest_local), dropped_key),
                env=({'STORAGE_DRIVER': 's3', 'S3_PATH_STYLE': 'false', **r2_cfg} if use_r2 else {'STORAGE_DRIVER': 'local'}),
            )
            res = run_migrate('verify')
            check(res.returncode == 3, 'interrupted transfer (missing destination object) fails verification (rc=3)')
            check(json.loads(run_migrate('status').stdout)['paused'] is True, 'source write gate stays paused after failed transfer verify')
            res = run_migrate('copy', [f'--review-manifest={review_sha}'])
            check(res.returncode == 0, 'retry copy recovers interrupted transfer cleanly')
            res = run_migrate('verify')
            check(res.returncode == 0 and 'Verification differences 0' in res.stdout, 'post-recovery verify reports 0 differences')

            # 3. Package & deploy split native Stellar runtime
            archive_path = work / 'release.tar.gz'
            subprocess.run(
                ['php', str(ROOT / 'scripts/stellar/package.php'), f'--output={archive_path}', '--release-id=rehearsal-split'],
                cwd=ROOT, env=SAFE_ENV, check=True, stdout=subprocess.DEVNULL,
            )
            stellar_base = work / 'stellar-private'
            public_docroot = work / 'public_html'
            staff_docroot = work / 'staff_html'
            public_docroot.mkdir()
            staff_docroot.mkdir()

            cfg_lines = [
                'APP_ENV=test',
                'DB_HOST=127.0.0.1',
                'DB_PORT=3306',
                'DB_NAME=unused',
                'DB_USER=unused',
                'DB_PASS=unused',
                f'PUBLIC_APP_URL=https://aeesgs.example',
                f'STAFF_APP_URL=https://admin.aeesgs.example',
                'MAIL_USER=synthetic@example.invalid',
                'MAIL_PASS=synthetic-smtp-key',
            ]
            if use_r2:
                cfg_lines.extend([
                    'STORAGE_DRIVER=s3',
                    'S3_PATH_STYLE=false',
                    f'R2_ENDPOINT={r2_cfg["R2_ENDPOINT"]}',
                    f'R2_BUCKET_NAME={r2_cfg["R2_BUCKET_NAME"]}',
                    f'R2_ACCESS_KEY_ID={r2_cfg["R2_ACCESS_KEY_ID"]}',
                    f'R2_SECRET_ACCESS_KEY={r2_cfg["R2_SECRET_ACCESS_KEY"]}',
                ])
            else:
                cfg_lines.append('STORAGE_DRIVER=local')
                cfg_lines.append(f'AMEA_STORAGE_ROOT={restore_dest_local}')
            staged_cfg = work / 'staged-runtime.env'
            staged_cfg.write_text('\n'.join(cfg_lines) + '\n')
            os.chmod(staged_cfg, 0o600)

            dep = subprocess.run(
                [
                    'bash', str(ROOT / 'scripts/stellar/deploy.sh'),
                    f'--base-dir={stellar_base}',
                    f'--public-docroot={public_docroot}',
                    f'--staff-docroot={staff_docroot}',
                    f'--archive={archive_path}',
                    f'--config-source={staged_cfg}',
                    '--release-id=rehearsal-split',
                    '--skip-migrations',
                ],
                cwd=ROOT, env=SAFE_ENV, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True,
            )
            check(dep.returncode == 0, f'deploy.sh provisioned split Stellar target ({dep.stderr.strip()})')

            # Capture coordinated snapshot from paused legacy source
            snapshot_dir = work / 'coordinated-snapshot'
            capture_started = time.monotonic()
            capture_proof = json.loads(run_php(
                """
                require $argv[1] . '/vendor/autoload.php';
                $gate = new Amea\\Core\\FileWriteGate($argv[2]);
                $db = new PDO('sqlite:' . $argv[3], options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $source = new Amea\\Storage\\LocalStorage($argv[4]);
                $snap = new Amea\\Storage\\Migration\\RecoverySnapshot($gate);
                $proof = $snap->capture($argv[5], $db, $source, 'legacy-single-owner',
                    ['app' => $argv[6]], $argv[7], $argv[8], ['serializer' => 'php']);
                echo json_encode($proof, JSON_THROW_ON_ERROR);
                """,
                args=(str(ROOT), str(source_gate_dir), str(source_db), str(source_root),
                      str(snapshot_dir), str(source_sessions), str(source_storage), str(manifest_path)),
            ))
            timings['capture_ms'] = round((time.monotonic() - capture_started) * 1000, 1)
            check(capture_proof['objects'] == 3, f'coordinated snapshot captured all 3 objects ({capture_proof["objects"]})')

            # If using live R2 amea-sandbox, clear copy-phase synthetic objects so isolated restore verifies fresh R2 restore
            if use_r2:
                clean_r2_sandbox(r2_cfg)

            # Isolated single -> split restore into fresh empty DB + split Stellar session stores + destination backend
            restore_db = work / 'restore.sqlite'
            stellar_state = stellar_base / 'shared/state'
            stellar_cfg = stellar_base / 'shared/config/runtime.env'
            stellar_current = (stellar_base / 'current').resolve()
            stellar_persistent = work / 'stellar-persistent'
            stellar_persistent.mkdir(mode=0o700)

            restore_started = time.monotonic()
            restore_info = json.loads(run_php(
                """
                require $argv[1] . '/vendor/autoload.php';
                $stellarRoot = $argv[1];
                $configFile = $argv[2];
                $stateDir = $argv[3];
                $snapshotDir = $argv[4];
                $snapshotSha = $argv[5];
                $restoreDbPath = $argv[6];
                $persistentDir = $argv[7];
                $publicDocroot = $argv[8];
                $staffDocroot = $argv[9];
                $jobWrapper = $argv[10];

                Amea\\Core\\Runtime::loadEnv($stellarRoot, $configFile, true);
                putenv('AMEA_CONFIG_FILE=' . $configFile);
                putenv('AMEA_STATE_DIR=' . $stateDir);
                $_ENV['AMEA_CONFIG_FILE'] = $configFile;
                $_ENV['AMEA_STATE_DIR'] = $stateDir;

                $gate = Amea\\Core\\FileWriteGate::application();
                $gate->pause(['environment' => 'ticket03-restore']);

                $db = new PDO('sqlite:' . $restoreDbPath, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $storageRoot = Amea\\Core\\Runtime::storageProjectRoot($stellarRoot);
                $backend = Amea\\Storage\\StorageFactory::fromEnvironment($storageRoot);
                $appBackend = Amea\\Storage\\StorageFactory::identity($storageRoot);
                $sessions = ['public' => $stateDir . '/sessions/public', 'staff' => $stateDir . '/sessions/staff'];

                $snapshot = new Amea\\Storage\\Migration\\RecoverySnapshot($gate);
                $restoreState = $snapshot->restore(
                    $snapshotDir, $snapshotSha, $db, $backend, $appBackend,
                    $sessions, $persistentDir, Amea\\Storage\\Migration\\ReferenceAudit::databaseIdentity($db)
                );

                // Collect actual native Stellar target reports for public, staff, jobs
                $reports = [
                    Amea\\Storage\\Migration\\TargetAttestation::report($stellarRoot, [
                        'role' => 'public', 'config' => $configFile, 'state_dir' => $stateDir, 'docroot' => $publicDocroot,
                    ], $db),
                    Amea\\Storage\\Migration\\TargetAttestation::report($stellarRoot, [
                        'role' => 'staff', 'config' => $configFile, 'state_dir' => $stateDir, 'docroot' => $staffDocroot,
                    ], $db),
                    Amea\\Storage\\Migration\\TargetAttestation::report($stellarRoot, [
                        'role' => 'jobs', 'config' => $configFile, 'state_dir' => $stateDir, 'job_wrapper' => $jobWrapper,
                    ], $db),
                ];

                // Interrupted/invalid reopening check A: missing role must block reopening
                $blockedMissingRole = false;
                try {
                    $snapshot->resumeRestored($snapshotDir, $snapshotSha, $db, $backend, $sessions, array_slice($reports, 0, 2));
                } catch (Amea\\Storage\\StorageException) {
                    $blockedMissingRole = $gate->status()['paused'] === true;
                }

                // Interrupted/invalid reopening check B: DB drift after restore must block reopening
                $blockedDbDrift = false;
                $db->exec("UPDATE students SET first_name='Drifted' WHERE id=99");
                try {
                    $snapshot->resumeRestored($snapshotDir, $snapshotSha, $db, $backend, $sessions, $reports);
                } catch (Amea\\Storage\\StorageException) {
                    $blockedDbDrift = $gate->status()['paused'] === true;
                }
                $db->exec("UPDATE students SET first_name='Confirmed' WHERE id=99");

                // Re-attest and cleanly reopen
                $freshReports = [
                    Amea\\Storage\\Migration\\TargetAttestation::report($stellarRoot, [
                        'role' => 'public', 'config' => $configFile, 'state_dir' => $stateDir, 'docroot' => $publicDocroot,
                    ], $db),
                    Amea\\Storage\\Migration\\TargetAttestation::report($stellarRoot, [
                        'role' => 'staff', 'config' => $configFile, 'state_dir' => $stateDir, 'docroot' => $staffDocroot,
                    ], $db),
                    Amea\\Storage\\Migration\\TargetAttestation::report($stellarRoot, [
                        'role' => 'jobs', 'config' => $configFile, 'state_dir' => $stateDir, 'job_wrapper' => $jobWrapper,
                    ], $db),
                ];
                $snapshot->resumeRestored($snapshotDir, $snapshotSha, $db, $backend, $sessions, $freshReports);

                echo json_encode([
                    'state' => $restoreState['state'],
                    'blocked_missing_role' => $blockedMissingRole,
                    'blocked_db_drift' => $blockedDbDrift,
                    'reopened' => $gate->status()['paused'] === false,
                    'public_session_count' => count(glob($sessions['public'] . '/sess_*') ?: []),
                    'staff_session_count' => count(glob($sessions['staff'] . '/sess_*') ?: []),
                    'backend' => $freshReports[0]['backend'],
                    'code' => $freshReports[0]['code'],
                ], JSON_THROW_ON_ERROR);
                """,
                args=(
                    str(stellar_current), str(stellar_cfg), str(stellar_state),
                    str(snapshot_dir), capture_proof['sha256'], str(restore_db),
                    str(stellar_persistent), str(public_docroot), str(staff_docroot),
                    str(stellar_base / 'bin/run-job.php'),
                ),
            ))
            timings['restore_and_verify_ms'] = round((time.monotonic() - restore_started) * 1000, 1)
            timings['total_pause_ms'] = round((time.monotonic() - pause_started) * 1000, 1)

            check(restore_info['state'] == 'verified', 'isolated single-to-split restore verified')
            check(restore_info['blocked_missing_role'] is True, 'missing role report blocked target reopening')
            check(restore_info['blocked_db_drift'] is True, 'database drift blocked target reopening')
            check(restore_info['reopened'] is True, 'target write gate reopened after fresh native attestation')
            check(restore_info['public_session_count'] == 1, 'split public store contains only the 1 unfinished registration session')
            check(restore_info['staff_session_count'] == 0, 'split staff store excludes all legacy staff sessions')

            # 4. Start split native Stellar servers on the EXACT same public origin (127.0.0.1:<pub_port>)
            preload = work / 'stellar-sqlite-preload.php'
            preload.write_text(f"""<?php
require_once '{stellar_current / "vendor/autoload.php"}';
require_once '{ROOT / "tests/http/SqliteFixture.php"}';
$db = new SqliteFixture('sqlite:{restore_db}');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
\\Amea\\Core\\Container::setDb($db);
""")

            def make_stellar_router(docroot):
                router = work / f'stellar-router-{docroot.name}.php'
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

            pub_log = open(work / 'stellar-pub.log', 'w+')
            staff_log = open(work / 'stellar-staff.log', 'w+')
            pub_srv = subprocess.Popen(
                ['php', '-d', f'auto_prepend_file={preload}', '-S', f'127.0.0.1:{pub_port}',
                 '-t', str(public_docroot), str(make_stellar_router(public_docroot))],
                env=SAFE_ENV, stdout=pub_log, stderr=pub_log,
            )
            staff_srv = subprocess.Popen(
                ['php', '-d', f'auto_prepend_file={preload}', '-S', f'127.0.0.1:{staff_port}',
                 '-t', str(staff_docroot), str(make_stellar_router(staff_docroot))],
                env=SAFE_ENV, stdout=staff_log, stderr=staff_log,
            )
            wait_server(pub_base, '/legal-notice.php')
            wait_server(staff_base, '/login.php')

            # Separate browser cannot preview staged document
            stranger = client()
            check(http_req(stranger, pub_base, '/registration-preview.php?type=identite')[0] == 404,
                  'separate browser is denied staged identity preview (404)')
            check(http_req(stranger, pub_base, '/registration-preview.php?type=cv')[0] == 404,
                  'separate browser is denied staged CV preview (404)')

            # Signal the SAME Chrome browser (still holding its legacy PHPSESSID cookie) to resume & confirm
            chrome_proc.stdin.write('RESUME\n')
            chrome_proc.stdin.flush()
            resume_line = chrome_proc.stdout.readline().strip()
            chrome_rc = chrome_proc.wait(timeout=30)
            if chrome_rc != 0 or not resume_line.startswith('RESUME_CONFIRMED'):
                raise RuntimeError(f'Chrome resume failed (rc={chrome_rc}): {resume_line} | {chrome_proc.stderr.read()}')
            check(True, 'same Chrome browser resumed, rotated PHPSESSID -> AMEA_PUBLIC, previewed both PDFs, and confirmed once')

            # Confirm single persisted member record in restored DB
            with sqlite3.connect(restore_db) as conn:
                rows = conn.execute(
                    "SELECT id, identity_document, cv_path FROM students WHERE email = 'transfer-rehearsal@example.invalid'"
                ).fetchall()
            check(len(rows) == 1, 'confirmed registration persisted exactly 1 member record')
            new_student_id, new_id_key, new_cv_key = rows[0]
            check(bool(new_id_key) and bool(new_cv_key), 'confirmed member record carries both finalized document keys')

            # Staff authentication isolation checks
            check(http_req(client(), pub_base, '/dashboard.php', headers={'Cookie': legacy_staff_cookie})[0] == 404,
                  'legacy staff PHPSESSID returns local 404 on split public /dashboard.php')
            check(http_req(client(), pub_base, f'/download.php?id={new_student_id}&type=cv', headers={'Cookie': legacy_staff_cookie})[0] == 404,
                  'legacy staff PHPSESSID returns local 404 on split public /download.php')
            code, hdrs, _ = http_req(client(), staff_base, '/dashboard.php', headers={'Cookie': legacy_staff_cookie})
            check(code == 302 and 'login.php' in (hdrs.get('Location') or ''),
                  'legacy staff PHPSESSID is rejected on split staff /dashboard.php (302 login.php)')
            code, hdrs, _ = http_req(client(), staff_base, f'/download.php?id={new_student_id}&type=cv', headers={'Cookie': legacy_staff_cookie})
            check(code == 302 and 'login.php' in (hdrs.get('Location') or ''),
                  'legacy staff PHPSESSID is rejected on split staff /download.php (302 login.php)')

            # Fresh staff login on split staff host downloads the confirmed member's private PDFs from destination
            fresh_staff = client()
            status, _, login_html = http_req(fresh_staff, staff_base, '/login.php')
            csrf = re.search(rb'name="csrf_token"\s+value="([^"]+)"', login_html).group(1).decode()
            status, hdrs, _ = http_req(fresh_staff, staff_base, '/login.php', {
                'csrf_token': csrf,
                'username': 'fixture-admin',
                'password': 'fixture-password',
            })
            check(status == 302 and 'dashboard.php' in (hdrs.get('Location') or ''), 'fresh staff signs into split staff host')

            code, hdrs, body = http_req(fresh_staff, staff_base, f'/download.php?id={new_student_id}&type=identite')
            check(code == 200 and body.decode() == identity_pdf and hdrs.get('X-Content-Type-Options') == 'nosniff',
                  'fresh staff downloads byte-identical finalized identity PDF from destination')
            code, hdrs, body = http_req(fresh_staff, staff_base, f'/download.php?id={new_student_id}&type=cv')
            check(code == 200 and body.decode() == cv_pdf and hdrs.get('X-Content-Type-Options') == 'nosniff',
                  'fresh staff downloads byte-identical finalized CV PDF from destination')

            print('REHEARSAL_SUMMARY=' + json.dumps({
                'mode': 'r2-amea-sandbox' if use_r2 else 'local',
                'timings': timings,
                'backend': restore_info['backend'],
                'code': restore_info['code'],
            }))
        finally:
            if chrome_proc and chrome_proc.poll() is None:
                chrome_proc.terminate()
                try:
                    chrome_proc.wait(timeout=5)
                except subprocess.TimeoutExpired:
                    chrome_proc.kill()
            for srv in (legacy_srv, pub_srv, staff_srv):
                if srv and srv.poll() is None:
                    srv.terminate()
                    srv.wait(timeout=10)
            for fh in (legacy_log, pub_log, staff_log):
                if fh:
                    fh.close()
            if use_r2:
                clean_r2_sandbox(r2_cfg)
                print('PASS: amea-sandbox R2 synthetic objects cleaned up completely')


if __name__ == '__main__':
    main()
