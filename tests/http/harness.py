"""Shared test-only HTTP boundary; creates no deployment/configuration switches."""
import contextlib
import http.cookiejar
import os
import pathlib
import re
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[2]
# Never forward host database/SMTP credentials to fixture or server processes.
SAFE_ENV = {key: os.environ[key] for key in ['PATH', 'SYSTEMROOT', 'TMPDIR', 'TMP', 'TEMP'] if key in os.environ}

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None

def check(condition, message):
    if not condition:
        raise AssertionError(message)
    print('PASS:', message)

def client():
    # These clients only call disposable loopback HTTP servers. Keep the
    # production Secure attribute so route checks can still assert it.
    policy = http.cookiejar.DefaultCookiePolicy(secure_protocols=('https', 'http'))
    return urllib.request.build_opener(
        urllib.request.ProxyHandler({}),
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar(policy)),
        NoRedirect())

class HttpFixture:
    def __init__(self, base, root):
        self.base = base
        self.root = root

    def request(self, browser, path, data=None, multipart=False, image=False, upload=None, method=None):
        headers = {}
        if multipart:
            boundary = 'AMEAfixtureBoundary'
            parts = []
            for key, value in data.items():
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
            filename, field, mime, content = ('fixture.jpg', 'slider_image', 'image/jpeg', (ROOT / 'assets/img/presidents.jpg').read_bytes()) if image else ('fixture.pdf', 'photo', 'application/pdf', b'%PDF-1.4\nSafe synthetic document\n%%EOF\n')
            if upload is not None:
                filename, field, mime, content = upload
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{field}"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode() + content + f'\r\n--{boundary}--\r\n'.encode())
            payload = b''.join(parts)
            headers['Content-Type'] = f'multipart/form-data; boundary={boundary}'
        else:
            payload = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            response = browser.open(urllib.request.Request(self.base + path, data=payload, headers=headers, method=method), timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        return response.code, response.headers, response.read()

    def csrf(self, browser, path):
        status, _, body = self.request(browser, path)
        check(status == 200, path + ' renders')
        token = re.search(rb'name="csrf_token"\s+value="([^"]+)"', body)
        check(token is not None, path + ' exposes CSRF form')
        return token.group(1).decode()

@contextlib.contextmanager
def serve(app, application):
    session_dir = app / 'sessions' / application
    with socket.socket() as listener:
        listener.bind(('127.0.0.1', 0))
        port = listener.getsockname()[1]
    address = f'127.0.0.1:{port}'
    entry_root = 'web' if application == 'public' else 'backoffice'
    with open(app / f'{application}.log', 'w+') as log:
        server = subprocess.Popen([
            'php', '-d', f'session.save_path={session_dir}', '-S', address,
            '-t', f'apps/{entry_root}/public', 'tests/http/router.php',
        ], cwd=app, stdout=log, stderr=log,
            env={**SAFE_ENV, 'AMEA_HTTP_TEST_APPLICATION': application})
        fixture = HttpFixture(f'http://{address}', app)
        try:
            for attempt in range(50):
                try:
                    fixture.request(client(), '/')
                    break
                except urllib.error.URLError:
                    time.sleep(.1)
            else:
                raise RuntimeError(f'Disposable {application} HTTP server did not start')
            yield fixture
        except Exception:
            log.flush()
            log.seek(0)
            print(log.read())
            raise
        finally:
            server.terminate()
            server.wait(timeout=10)


@contextlib.contextmanager
def disposable_apps(*applications, extra_fixtures=()):
    # PHP fixture/router also require this basename prefix and isolated DB file.
    with tempfile.TemporaryDirectory(prefix='amea-http-') as directory, contextlib.ExitStack() as servers:
        app = pathlib.Path(directory)
        for name in ['apps', 'src', 'templates', 'assets', 'vendor', 'tests']:
            shutil.copytree(ROOT / name, app / name, ignore=shutil.ignore_patterns('__pycache__'))
        for application in ['public', 'staff']:
            (app / 'sessions' / application).mkdir(parents=True)
        for fixture in ('fixture.php', *extra_fixtures):
            subprocess.run(['php', str(app / 'tests/http' / fixture)], cwd=app, check=True, env=SAFE_ENV)
        yield tuple(servers.enter_context(serve(app, application)) for application in applications)
