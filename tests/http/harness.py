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
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())

class HttpFixture:
    def __init__(self, base):
        self.base = base

    def request(self, browser, path, data=None, multipart=False, image=False, upload=None):
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
            response = browser.open(urllib.request.Request(self.base + path, data=payload, headers=headers), timeout=10)
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
def disposable_app(extra_fixtures=()):
    # PHP fixture/router also require this basename prefix and isolated DB file.
    with tempfile.TemporaryDirectory(prefix='amea-http-') as directory:
        app = pathlib.Path(directory)
        for name in ['src', 'templates', 'functions', 'assets', 'config', 'vendor', 'tests']:
            shutil.copytree(ROOT / name, app / name, ignore=shutil.ignore_patterns('__pycache__'))
        for entry in ROOT.glob('*.php'):
            shutil.copy2(entry, app / entry.name)
        (app / 'sessions').mkdir()
        for fixture in ('fixture.php', *extra_fixtures):
            subprocess.run(['php', str(app / 'tests/http' / fixture)], cwd=app, check=True, env=SAFE_ENV)
        with socket.socket() as listener:
            listener.bind(('127.0.0.1', 0))
            port = listener.getsockname()[1]
        base = f'http://127.0.0.1:{port}'
        with open(app / 'server.log', 'w+') as log:
            server = subprocess.Popen(['php', '-d', f'session.save_path={app / "sessions"}', '-S', f'127.0.0.1:{port}', 'tests/http/router.php'], cwd=app, stdout=log, stderr=log, env=SAFE_ENV)
            fixture = HttpFixture(base)
            try:
                for attempt in range(50):
                    try:
                        fixture.request(client(), '/legal-notice.php')
                        break
                    except urllib.error.URLError:
                        time.sleep(.1)
                else:
                    raise RuntimeError('Disposable HTTP server did not start')
                yield fixture
            except Exception:
                log.flush()
                print((app / 'server.log').read_text())
                raise
            finally:
                server.terminate()
                server.wait(timeout=10)
