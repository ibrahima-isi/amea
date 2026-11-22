#!/usr/bin/env python3
"""Final operational boundary: owned temporary projects, real HTTPS and files."""
import base64, hashlib, json, os, pathlib, re, shutil, subprocess, sys, tempfile, time, uuid
from http.client import HTTPException
import routes as http

project = sys.argv[1]
if not re.fullmatch(r'amea-ui-test-[a-z0-9-]+', project):
    raise RuntimeError('Only an owned amea-ui-test-* project is accepted')
ROOT = pathlib.Path(__file__).resolve().parents[2]
compose_file = ROOT / 'tests/apps/compose.yaml'
source_env = os.environ.copy()
source_ports = (http.PUBLIC_PORT, http.STAFF_PORT)
restore_project = project + '-restore'
restore_env = {**source_env, 'AMEA_PUBLIC_PORT': '18448', 'AMEA_STAFF_PORT': '19448'}

def command(project_name, *args, env=None, override=None, **kwargs):
    cmd = ['docker', 'compose', '-p', project_name, '-f', str(compose_file)]
    if override: cmd += ['-f', str(override)]
    return subprocess.run(cmd + list(args), cwd=ROOT, env=env or source_env, check=True, **kwargs)

def capture(project_name, *args, **kwargs):
    return command(project_name, *args, stdout=subprocess.PIPE, **kwargs).stdout

def wait_ready(ports):
    http.PUBLIC_PORT, http.STAFF_PORT = ports
    for _ in range(120):
        try:
            if http.request('public', '/legal-notice.php')[0] == 200 and http.request('staff', '/login.php')[0] == 200:
                return
        except (OSError, HTTPException): pass
        time.sleep(.5)
    raise RuntimeError('Owned HTTPS targets did not become ready')

class Staff:
    def __init__(self):
        self.cookie = None
        status, _, body = self.go('/login.php')
        assert status == 200
        csrf = re.search(rb'name="csrf_token"\s+value="([^"]+)"', body).group(1).decode()
        assert self.go('/login.php', dict(csrf_token=csrf, username='expired', password='Fixture-password-123!'))[0] == 302
    def go(self, path, data=None):
        result = http.request('staff', path, data, self.cookie)
        if 'Set-Cookie' in result[1]: self.cookie = result[1]['Set-Cookie'].split(';')[0]
        return result

def observe():
    staff = Staff()
    status, _, listing = staff.go('/students.php?search=SharedMember')
    assert status == 200 and b'StaffEdited' in listing
    member = re.search(rb'student-details.php\?id=(\d+)', listing).group(1).decode()
    status, _, private = staff.go('/download.php?id=' + member + '&type=identite')
    assert status == 200 and b'Shared synthetic identity' in private
    status, _, home = http.request('public', '/')
    assert status == 200 and b'Synthetic tools carousel' in home
    image = re.search(rb'(uploads/slider/[^"<> ]+)', home).group(1).decode()
    status, _, public = http.request('public', '/' + image)
    assert status == 200
    return dict(member=member, private=hashlib.sha256(private).hexdigest(), public=hashlib.sha256(public).hexdigest())

def observe_journal():
    status, _, listing = http.request('public', '/journal.php?view=upcoming')
    assert status == 200 and b'Updated draft' in listing and b'Activity happening today' in listing
    slug = re.search(rb'journal-article.php\?slug=([^"<>]+)"[^>]*>Updated draft</a>', listing).group(1).decode()
    status, _, article = http.request('public', '/journal-article.php?slug=' + slug)
    assert status == 200 and b'Updated draft' in article
    status, headers, image = http.request('public', '/journal-cover.php?slug=' + slug)
    assert status == 200 and headers.get('Content-Type') == 'image/jpeg'
    return dict(slug=slug, article=hashlib.sha256(article).hexdigest(), image=hashlib.sha256(image).hexdigest())


def verify(expected, label):
    assert observe() == expected, label
    http.check(True, label)

def storage_hash(name, env=None):
    return capture(name, 'exec', '-u', 'www-data', '-T', 'web', 'php', '-r', "echo hash_file('sha256','storage/release-marker.txt');", env=env).decode().strip()

for kind in ['container', 'volume']:
    args = ['docker', 'ps', '-aq', '--filter', 'label=com.docker.compose.project=' + restore_project] if kind == 'container' else ['docker', 'volume', 'ls', '-q', '--filter', 'label=com.docker.compose.project=' + restore_project]
    if subprocess.check_output(args).strip():
        raise RuntimeError('Restore project must be fresh')

with tempfile.TemporaryDirectory(prefix='amea-release-') as temporary:
    directory = pathlib.Path(temporary)
    images = {service: capture(project, 'images', '-q', service).decode().strip() for service in ['web', 'backoffice']}
    images['jobs'] = subprocess.check_output(['docker', 'image', 'inspect', project + '-jobs', '--format', '{{.Id}}']).decode().strip()
    assert all(images.values())
    forward = directory / 'forward.json'
    forward.write_text(json.dumps({'services': {s: {'image': image} for s, image in images.items()}}))
    expected = observe()
    journal = observe_journal()
    for _ in range(2):
        command(project, 'exec', '-T', 'web', 'php', 'migrations/07_create_association_journal.php')
        assert observe_journal() == journal
    http.check(True, 'migration07 reruns preserve published journal and controlled cover bytes over HTTPS')
    command(project, 'exec', '-u', 'www-data', '-T', 'web', 'php', '-r', "file_put_contents('storage/release-marker.txt','Synthetic storage continuity');")
    storage = storage_hash(project)
    for service in ['web', 'backoffice', 'mysql']:
        command(project, 'up', '-d', '--no-deps', '--force-recreate', service)
        if service == 'mysql':
            for _ in range(120):
                result = subprocess.run(['docker','compose','-p',project,'-f',str(compose_file),'exec','-T','mysql','mysql','--protocol=TCP','-h','127.0.0.1','-uamea','-psynthetic-only','amea_rehearsal','-e','SELECT 1'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, env=source_env)
                if result.returncode == 0: break
                time.sleep(.5)
            else: raise RuntimeError('Replaced MySQL did not become ready')
        wait_ready(source_ports)
        verify(expected, service + ' container replacement preserves shared member and private/public file bytes')
        assert observe_journal() == journal
        assert storage_hash(project) == storage
    http.check(True, 'storage volume persists through all container replacements')
    # Retain the pre-08 local image and a backend-aware base revision for S3.
    backend_aware = source_env.get('STORAGE_DRIVER') == 's3'
    prior_revision = '6201369b6e9144afda2a36565130dbcdac546af4' if backend_aware else 'ff3a8abac51eda2bec3e115b603c7e788de472f8'
    prior = source_env.get('AMEA_ROLLBACK_PUBLIC_IMAGE', 'amea-ui-prior-public:storage-6201369' if backend_aware else 'amea-ui-prior-public:ff3a8ab')
    prior_id = subprocess.run(['docker','image','inspect',prior,'--format','{{.Id}}'], stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, text=True)
    if prior_id.returncode != 0:
        context = directory / 'prior'
        context.mkdir()
        revision = prior_revision
        present = subprocess.run(['git','cat-file','-e',revision+'^{commit}'],cwd=ROOT,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
        if present.returncode != 0:
            subprocess.run(['git','fetch','--no-tags','--depth=1','origin',revision],cwd=ROOT,check=True)
        archive = subprocess.Popen(['git','archive',revision], cwd=ROOT, stdout=subprocess.PIPE)
        subprocess.run(['tar','-x','-C',str(context)], stdin=archive.stdout, check=True)
        archive.stdout.close()
        assert archive.wait() == 0
        # The archived Dockerfile predates the web target name.
        subprocess.run(['docker','build','-f',str(context/'docker/Dockerfile.apps'),'--target','public','-t',prior,str(context)], check=True, env=source_env)
    rollback = directory / 'rollback.json'
    rollback.write_text(json.dumps({'services': {'web': {'image': prior}}}))
    command(project, 'up', '-d', '--no-deps', '--no-build', '--force-recreate', 'web', override=rollback)
    wait_ready(source_ports)
    http.check(http.request('public','/login.php')[0] == 404, 'retained prior image preserves public staff-route denial')
    verify(expected, 'real prior image rollback preserves candidate database and file bytes')
    command(project, 'up', '-d', '--no-deps', '--no-build', '--force-recreate', 'web', override=forward)
    wait_ready(source_ports)
    status, headers, _ = http.request('public','/login.php')
    http.check(status == 404 and 'Location' not in headers, 'forward deployment preserves public isolation from staff')
    verify(expected, 'forward deployment preserves candidate database and files')
    assert observe_journal() == journal
    http.check(True, 'forward after legacy rollback restores published journal and cover bytes')
    def recovery(name, mode, *args, env=None):
        with (ROOT / 'tests/apps/recovery.php').open('rb') as source:
            return capture(name, '--profile', 'jobs', 'run', '--rm', '-T', '--no-deps', 'jobs',
                           'php', '/dev/stdin', mode, *args, env=env, override=forward, stdin=source)

    recovery(project, 'seed')
    pending_cookie = 'AMEA_PUBLIC=recoverypending'
    status, _, pending_bytes = http.request('public', '/registration-preview.php?type=identite', cookie=pending_cookie)
    http.check(status == 200 and b'Synthetic registration opened before recovery pause' in pending_bytes, 'pre-pause registration has an actual authorized preview')
    browser_enabled = source_env.get('AMEA_SKIP_BROWSER', '0') != '1'
    if browser_enabled:
        subprocess.run(['node', 'tests/apps/recovery-browser.mjs', source_env.get('AMEA_SCREENSHOT_DIR', '/private/tmp/amea-app-browser')], cwd=ROOT, env={**source_env, 'AMEA_RECOVERY_BROWSER_PHASE':'before'}, check=True)
    proof = json.loads(recovery(project, 'capture').decode().strip())
    http.check(http.request('public', '/register.php')[0] == 503 and http.request('staff', '/login.php')[0] == 503,
               'durable coordinated pause closes public and staff session/DB mutations')
    http.check(http.request('public', '/journal.php')[0] == 200, 'stateless journal remains available during backup pause')
    status, headers, paused_preview = http.request('public', '/registration-preview.php?type=identite', cookie=pending_cookie)
    http.check(status == 200 and paused_preview == pending_bytes and 'Set-Cookie' not in headers,
               'private preview smoke read remains available during pause without cookie/session mutation')
    backup = directory / 'coordinated-backup.tar'
    with backup.open('wb') as output:
        backup.chmod(0o600)
        command(project, '--profile', 'jobs', 'run', '--rm', '-T', '--no-deps', 'jobs', 'tar', '-C', '/var/lib/amea/recovery', '-cf', '-', '.', stdout=output)
    if source_env.get('AMEA_RECOVERY_EVIDENCE_DIR'):
        retained = pathlib.Path(source_env['AMEA_RECOVERY_EVIDENCE_DIR'])
        retained.mkdir(mode=0o700, parents=True, exist_ok=True)
        shutil.copy2(backup, retained / (project + '-coordinated-backup.tar'))
    http.check(proof['objects'] > 0 and backup.stat().st_size > 0, 'snapshot includes verified private/public objects and finalized manifest/checkpoint identity')
    restore_bucket = None
    if source_env.get('STORAGE_DRIVER') == 's3':
        restore_bucket = 'amea-ui-restore-' + uuid.uuid4().hex
        recovery(project, 'create-restore-bucket', restore_bucket)
        restore_env['S3_BUCKET'] = restore_bucket
    try:
        command(restore_project, 'create', '--no-build', 'web', env=restore_env, override=forward)
        command(restore_project, 'create', '--no-build', 'backoffice', env=restore_env, override=forward)
        command(restore_project, 'up', '-d', '--wait', '--wait-timeout', '90', '--no-build', 'mysql', env=restore_env, override=forward)
        with backup.open('rb') as source:
            command(restore_project, '--profile', 'jobs', 'run', '--rm', '-T', '--no-deps', 'jobs',
                    'tar', '-C', '/var/lib/amea/recovery', '-xf', '-', env=restore_env, override=forward, stdin=source)
        restored = json.loads(recovery(restore_project, 'restore', env=restore_env).decode().strip())
        http.check(restored['state'] == 'verified', 'separate restore verifies DB, ledger, bytes, lifecycle ages and both session stores before reopening')
        command(restore_project, 'up', '-d', '--no-build', 'web', 'backoffice', 'tls', env=restore_env, override=forward)
        http.PUBLIC_PORT, http.STAFF_PORT = (18448, 19448)
        for _ in range(60):
            try:
                if http.request('public', '/register.php')[0] == 503 and http.request('staff', '/login.php')[0] == 503:
                    break
            except (OSError, HTTPException):
                pass
            time.sleep(.5)
        else:
            raise RuntimeError('Fresh restored images did not preserve the persistent pause')
        status, headers, paused_preview = http.request('public', '/registration-preview.php?type=identite', cookie=pending_cookie)
        http.check(status == 200 and paused_preview == pending_bytes and 'Set-Cookie' not in headers,
                   'fresh paused images preserve private read-only preview and block new mutations')
        reports = []
        for service in ['web', 'backoffice']:
            reports.append(json.loads(capture(restore_project, 'exec', '-u', 'www-data', '-T', service, 'php',
                                               '/opt/amea-apps/storage-target.php', env=restore_env, override=forward)))
        reports.append(json.loads(capture(restore_project, '--profile', 'jobs', 'run', '--rm', '-T', '--no-deps', 'jobs',
                                           'php', '/opt/amea-apps/storage-target.php', env=restore_env, override=forward)))
        encoded = base64.b64encode(json.dumps(reports).encode()).decode()
        recovery(restore_project, 'resume', encoded, env=restore_env)
        wait_ready((18448, 19448))
        verify(expected, 'separate coordinated restore reproduces member and authorized private/public bytes over HTTPS')
        assert observe_journal() == journal
        assert storage_hash(restore_project, restore_env) == storage
        http.check(True, 'restored journal and persistent storage marker match the coordinated recovery point')
        status, _, restored_pending = http.request('public', '/registration-preview.php?type=identite', cookie=pending_cookie)
        http.check(status == 200 and restored_pending == pending_bytes, 'original browser session retains its pre-pause preview after isolated restore')
        if browser_enabled:
            subprocess.run(['node', 'tests/apps/recovery-browser.mjs', source_env.get('AMEA_SCREENSHOT_DIR', '/private/tmp/amea-app-browser')], cwd=ROOT, env={**restore_env, 'AMEA_RECOVERY_BROWSER_PHASE':'after'}, check=True)
        else:
            status, _, review = http.request('public', '/registration-details.php', cookie=pending_cookie)
            token = re.search(rb'name="csrf_token"\s+value="([^"]+)"', review).group(1).decode()
            status, headers, _ = http.request('public', '/registration-details.php', {'csrf_token': token}, cookie=pending_cookie)
            http.check(status == 302 and headers.get('Location') == '/', 'registration opened before pause confirms after restored reopening')
        http.check(http.request('public', '/uploads/students/member-review.pdf')[0] in [403, 404], 'restoration preserves private-document route protection')
        print('RECOVERY', json.dumps({'source_snapshot': proof, 'restore': restored}))
    finally:
        if sys.exc_info()[0] is not None:
            command(restore_project, 'logs', '--tail', '60', 'web', 'backoffice', env=restore_env, override=forward)
        command(restore_project, '--profile', 'jobs', 'down', '-v', '--remove-orphans', env=restore_env, override=forward)
        if restore_bucket:
            recovery(project, 'cleanup-restore-bucket', restore_bucket)
        http.PUBLIC_PORT, http.STAFF_PORT = source_ports
