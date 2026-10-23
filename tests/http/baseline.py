#!/usr/bin/env python3
"""HTTP characterization against a disposable copy, never an existing deployment."""
import http.cookiejar
import json
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
# Do not forward host database/SMTP credentials into either PHP process.
SAFE_ENV = {key: os.environ[key] for key in ['PATH', 'SYSTEMROOT', 'TMPDIR', 'TMP', 'TEMP'] if key in os.environ}

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args):
        return None

def check(condition, message):
    if not condition:
        raise AssertionError(message)
    print('PASS:', message)

with tempfile.TemporaryDirectory(prefix='amea-http-') as directory:
    app = pathlib.Path(directory)
    for name in ['src', 'templates', 'functions', 'assets', 'config', 'vendor', 'tests']:
        shutil.copytree(ROOT / name, app / name, ignore=shutil.ignore_patterns('__pycache__'))
    for entry in ROOT.glob('*.php'):
        shutil.copy2(entry, app / entry.name)
    (app / 'sessions').mkdir()
    subprocess.run(['php', str(app / 'tests/http/fixture.php')], cwd=app, check=True, env=SAFE_ENV)
    with socket.socket() as listener:
        listener.bind(('127.0.0.1', 0))
        port = listener.getsockname()[1]
    base = f'http://127.0.0.1:{port}'
    log = open(app / 'server.log', 'w+')
    server = subprocess.Popen(['php', '-d', f'session.save_path={app / "sessions"}', '-S', f'127.0.0.1:{port}', 'tests/http/router.php'], cwd=app, stdout=log, stderr=log, env=SAFE_ENV)
    def client():
        return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())
    member, stranger, staff = client(), client(), client()
    def request(browser, path, data=None, multipart=False):
        headers = {}
        if multipart:
            boundary = 'AMEAfixtureBoundary'
            parts = []
            for key, value in data.items():
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="photo"; filename="fixture.pdf"\r\nContent-Type: application/pdf\r\n\r\n'.encode() + b'%PDF-1.4\nSafe synthetic document\n%%EOF\n' + f'\r\n--{boundary}--\r\n'.encode())
            payload = b''.join(parts)
            headers['Content-Type'] = f'multipart/form-data; boundary={boundary}'
        else:
            payload = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            response = browser.open(urllib.request.Request(base + path, data=payload, headers=headers), timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        return response.code, response.headers, response.read()
    def csrf(browser, path):
        status, _, body = request(browser, path)
        check(status == 200, path + ' renders')
        token = re.search(rb'name="csrf_token"\s+value="([^"]+)"', body)
        check(token is not None, path + ' exposes CSRF form')
        return token.group(1).decode()
    try:
        for attempt in range(50):
            try:
                request(stranger, '/login.php')
                break
            except urllib.error.URLError:
                time.sleep(.1)
        token = csrf(member, '/register.php')
        status, headers, _ = request(member, '/register.php', {'csrf_token': 'invalid'})
        check(status == 302 and headers.get('Location') == 'register.php', 'registration rejects invalid CSRF')
        status, headers, _ = request(member, '/register.php', dict(csrf_token=token, last_name='Fixture', first_name='Aminata', gender='Féminin', status='ETUDIANT', phone='771234567', email='member@example.invalid', consent_privacy='1'), True)
        check(status == 302 and headers.get('Location') == 'registration-details.php', 'valid registration reaches review')
        status, _, body = request(member, '/registration-preview.php?type=identite')
        check(status == 200 and b'Safe synthetic document' in body, 'member previews own staged document')
        status, _, _ = request(stranger, '/registration-preview.php?type=identite')
        check(status == 404, 'another session cannot preview staged document')
        token = csrf(member, '/registration-details.php')
        status, headers, _ = request(member, '/registration-details.php', {'csrf_token': token})
        check(status == 302 and headers.get('Location') == '/', 'confirmation completes registration')
        status, _, body = request(member, '/')
        flash = re.search(rb'<script id="flash-data" type="application/json">(.*?)</script>', body, re.S)
        check(status == 200 and flash is not None and 'en cours de révision' in json.loads(flash.group(1))['message'], 'confirmation returns success message on homepage')
        status, headers, _ = request(stranger, '/download.php?id=1&type=identite')
        check(status == 302 and headers.get('Location') == 'login.php', 'anonymous download requires staff login')
        token = csrf(staff, '/login.php')
        status, headers, _ = request(staff, '/login.php', {'csrf_token': token, 'username': 'fixture-admin', 'password': 'fixture-password'})
        check(status == 302 and headers.get('Location') == 'dashboard.php', 'staff can sign in')
        status, headers, body = request(staff, '/download.php?id=1&type=identite')
        check(status == 200 and b'Safe synthetic document' in body and headers.get('Cache-Control') == 'private, no-store', 'authorized staff downloads persisted document')
    except Exception:
        log.flush()
        print((app / 'server.log').read_text())
        raise
    finally:
        server.terminate()
        server.wait(timeout=10)
        log.close()
