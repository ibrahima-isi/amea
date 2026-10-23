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
    def request(browser, path, data=None, multipart=False, image=False):
        headers = {}
        if multipart:
            boundary = 'AMEAfixtureBoundary'
            parts = []
            for key, value in data.items():
                parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
            filename, field, mime, content = ('fixture.jpg', 'slider_image', 'image/jpeg', (ROOT / 'assets/img/presidents.jpg').read_bytes()) if image else ('fixture.pdf', 'photo', 'application/pdf', b'%PDF-1.4\nSafe synthetic document\n%%EOF\n')
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{field}"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode() + content + f'\r\n--{boundary}--\r\n'.encode())
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
        status, _, home = request(stranger, '/')
        check(status == 200 and b'AEESGS' in home and b'community-empty' in home, 'homepage shows association identity and useful empty carousel state')
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
        token = csrf(staff, '/manage-slider.php')
        status, headers, _ = request(staff, '/manage-slider.php', {'csrf_token': token, 'caption': 'Une communauté <solidaire>', 'display_order': '1'}, True, True)
        status, _, body = request(stranger, '/')
        check(status == 200 and b'Une communaut' in body and b'&lt;solidaire&gt;' in body and b'uploads/slider/' in body and b'data-carousel-pause' not in body, 'staff photo and escaped caption appear publicly; single photo has no rotation controls')
        if os.environ.get('AMEA_BROWSER_CHECK') == '1':
            subprocess.run(['node', str(ROOT / 'tests/http/homepage-browser.mjs'), base, str(ROOT / 'docs/testing/screenshots'), 'single'], check=True, timeout=60)
        token = csrf(staff, '/manage-slider.php')
        request(staff, '/manage-slider.php', {'csrf_token': token, 'caption': 'Ensemble au Sénégal', 'display_order': '2'}, True, True)
        status, _, body = request(stranger, '/')
        check(status == 200 and b'data-carousel-pause' in body and b'Photo suivante' in body, 'multiple staff photos expose accessible carousel controls')
        if os.environ.get('AMEA_BROWSER_CHECK') == '1':
            subprocess.run(['node', str(ROOT / 'tests/http/homepage-browser.mjs'), base, str(ROOT / 'docs/testing/screenshots')], check=True, timeout=120)
        for image_id in ['1', '2']:
            token = csrf(staff, '/manage-slider.php')
            request(staff, '/manage-slider.php', {'csrf_token': token, '_action': 'delete', 'id': image_id})
        status, _, body = request(stranger, '/')
        check(status == 200 and b'community-empty' in body and b'uploads/slider/' not in body, 'staff removal restores useful public empty state')
        if os.environ.get('AMEA_BROWSER_CHECK') == '1':
            subprocess.run(['node', str(ROOT / 'tests/http/homepage-browser.mjs'), base, str(ROOT / 'docs/testing/screenshots'), 'empty'], check=True, timeout=60)

    except Exception:
        log.flush()
        print((app / 'server.log').read_text())
        raise
    finally:
        server.terminate()
        server.wait(timeout=10)
        log.close()
