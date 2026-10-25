#!/usr/bin/env python3
"""Final operational boundary: owned temporary projects, real HTTPS and files."""
import hashlib, json, os, pathlib, re, subprocess, sys, tempfile, time
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

def verify(expected, label):
    assert observe() == expected, label
    http.check(True, label)

def storage_hash(name, env=None):
    return capture(name, 'exec', '-u', 'www-data', '-T', 'public', 'php', '-r', "echo hash_file('sha256','storage/release-marker.txt');", env=env).decode().strip()

for kind in ['container', 'volume']:
    args = ['docker', 'ps', '-aq', '--filter', 'label=com.docker.compose.project=' + restore_project] if kind == 'container' else ['docker', 'volume', 'ls', '-q', '--filter', 'label=com.docker.compose.project=' + restore_project]
    if subprocess.check_output(args).strip():
        raise RuntimeError('Restore project must be fresh')

with tempfile.TemporaryDirectory(prefix='amea-release-') as temporary:
    directory = pathlib.Path(temporary)
    images = {service: capture(project, 'images', '-q', service).decode().strip() for service in ['public', 'staff']}
    assert all(images.values())
    forward = directory / 'forward.json'
    forward.write_text(json.dumps({'services': {s: {'image': image} for s, image in images.items()}}))
    expected = observe()
    command(project, 'exec', '-u', 'www-data', '-T', 'public', 'php', '-r', "file_put_contents('storage/release-marker.txt','Synthetic storage continuity');")
    storage = storage_hash(project)
    for service in ['public', 'staff', 'mysql']:
        command(project, 'up', '-d', '--no-deps', '--force-recreate', service)
        if service == 'mysql':
            for _ in range(120):
                result = subprocess.run(['docker','compose','-p',project,'-f',str(compose_file),'exec','-T','mysql','mysql','--protocol=TCP','-h','127.0.0.1','-uamea','-psynthetic-only','amea_rehearsal','-e','SELECT 1'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, env=source_env)
                if result.returncode == 0: break
                time.sleep(.5)
            else: raise RuntimeError('Replaced MySQL did not become ready')
        wait_ready(source_ports)
        verify(expected, service + ' container replacement preserves shared member and private/public file bytes')
        assert storage_hash(project) == storage
    http.check(True, 'storage volume persists through all container replacements')
    # A real retained pre-08 public image has different compatibility behavior.
    prior = source_env.get('AMEA_ROLLBACK_PUBLIC_IMAGE', 'amea-ui-prior-public:ff3a8ab')
    prior_id = subprocess.run(['docker','image','inspect',prior,'--format','{{.Id}}'], stdout=subprocess.PIPE, stderr=subprocess.DEVNULL, text=True)
    if prior_id.returncode != 0:
        context = directory / 'prior'
        context.mkdir()
        revision = 'ff3a8abac51eda2bec3e115b603c7e788de472f8'
        present = subprocess.run(['git','cat-file','-e',revision+'^{commit}'],cwd=ROOT,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
        if present.returncode != 0:
            subprocess.run(['git','fetch','--no-tags','--depth=1','origin',revision],cwd=ROOT,check=True)
        archive = subprocess.Popen(['git','archive',revision], cwd=ROOT, stdout=subprocess.PIPE)
        subprocess.run(['tar','-x','-C',str(context)], stdin=archive.stdout, check=True)
        archive.stdout.close()
        assert archive.wait() == 0
        subprocess.run(['docker','build','-f',str(context/'docker/Dockerfile.apps'),'--target','public','-t',prior,str(context)], check=True, env=source_env)
    rollback = directory / 'rollback.json'
    rollback.write_text(json.dumps({'services': {'public': {'image': prior}}}))
    command(project, 'up', '-d', '--no-deps', '--no-build', '--force-recreate', 'public', override=rollback)
    wait_ready(source_ports)
    http.check(http.request('public','/login.php')[0] == 404, 'retained pre-08 image restores its observable legacy 404 behavior')
    verify(expected, 'real prior image rollback preserves candidate database and file bytes')
    command(project, 'up', '-d', '--no-deps', '--no-build', '--force-recreate', 'public', override=forward)
    wait_ready(source_ports)
    http.check(http.request('public','/login.php')[0] == 302, 'forward deployment restores configured staff redirect')
    verify(expected, 'forward deployment preserves candidate database and files')
    sql, files = directory/'database.sql', directory/'files.tar'
    with sql.open('wb') as output:
        command(project,'exec','-T','mysql','mysqldump','--single-transaction','--no-tablespaces','-uamea','-psynthetic-only','amea_rehearsal',stdout=output)
    with files.open('wb') as output:
        command(project,'exec','-T','public','tar','-C','/opt/amea','-cf','-','uploads','storage',stdout=output)
    assert sql.stat().st_size and files.stat().st_size
    try:
        command(restore_project,'up','-d','--no-build',env=restore_env,override=forward)
        for _ in range(120):
            check = subprocess.run(['docker','compose','-p',restore_project,'-f',str(compose_file),'-f',str(forward),'exec','-T','mysql','mysql','--protocol=TCP','-h','127.0.0.1','-uamea','-psynthetic-only','amea_rehearsal','-e','SELECT 1'],env=restore_env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
            if check.returncode == 0: break
            time.sleep(.5)
        else: raise RuntimeError('Restore MySQL did not become ready')
        with sql.open('rb') as source:
            command(restore_project,'exec','-T','mysql','mysql','-uamea','-psynthetic-only','amea_rehearsal',env=restore_env,override=forward,stdin=source)
        with files.open('rb') as source:
            command(restore_project,'exec','-T','public','tar','-C','/opt/amea','-xf','-',env=restore_env,override=forward,stdin=source)
        wait_ready((18448,19448))
        verify(expected, 'separate fresh SQL/files restore reproduces member and authorized private/public bytes over HTTPS')
        assert storage_hash(restore_project,restore_env) == storage
        http.check(True, 'separate restored storage marker matches backup')
        http.check(http.request('public','/uploads/students/member-review.pdf')[0] in [403,404], 'restoration does not expose private uploaded documents')
    finally:
        command(restore_project,'down','-v','--remove-orphans',env=restore_env,override=forward)
        http.PUBLIC_PORT, http.STAFF_PORT = source_ports
