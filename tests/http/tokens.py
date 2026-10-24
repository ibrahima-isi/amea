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
    subprocess.run(['php', str(app / 'tests/http/token-fixture.php')], cwd=app, check=True, env=SAFE_ENV)
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
                request(stranger, '/legal-notice.php')
                break
            except urllib.error.URLError:
                time.sleep(.1)
        correction = '/kyc-correction.php?token=' + 'a' * 64
        consent = '/accept-cgu.php?token=' + 'a' * 64
        status, _, body = request(member, correction)
        check(status == 200 and b'&lt;identit' in body and b'<identit' not in body, 'correction preserves existing token and escapes review notes')
        token = csrf(member, correction)
        status, headers, _ = request(member, correction, {'csrf_token': 'invalid'})
        check(status == 302 and headers.get('Location') == correction[1:], 'correction rejects invalid CSRF without consuming token')
        payload = dict(csrf_token=token, last_name='Retained', first_name='Aminata', gender='Féminin', status='ETUDIANT', phone='', email='a@example.invalid')
        request(member, correction, payload)
        status, _, body = request(member, correction)
        check(status == 200 and b'value="Retained"' in body and b'invalid-feedback' in body, 'correction validation retains entered data and exposes field errors')
        for bad in ['', 'z' * 64, 'b' * 64, 'c' * 64, 'd' * 64]:
            status, headers, body = request(stranger, '/kyc-correction.php?token=' + bad)
            check(status == 302 and headers.get('Location') == '/', 'invalid, revoked, deleted or ineligible correction fails safely: ' + bad[:1])
        for bad in ['', 'z' * 64, 'c' * 64, 'd' * 64]:
            status, _, body = request(stranger, '/accept-cgu.php?token=' + bad)
            check(status == 200 and b'Lien invalide' in body and b'Aminata' not in body, 'invalid consent reveals no member data: ' + bad[:1])
        status, _, body = request(member, consent)
        check(status == 200 and b'Acceptation des CGU' in body and b'&lt;safe&gt;' in body, 'consent pending state escapes member name')
        token = csrf(member, consent)
        status, _, body = request(member, consent, dict(csrf_token='invalid', action='accept'))
        check(b'Lien invalide' in body, 'consent refuses invalid CSRF')
        status, _, body = request(member, consent, dict(csrf_token=token, action='refuse'))
        check(b'Vous avez refus' in body, 'consent refusal offers deletion and reconsideration')
        status, _, body = request(member, consent, dict(csrf_token=token, action='request_deletion'))
        check(b'Demande de suppression envoy' in body, 'deletion notification succeeds with suppressed test transport')
        status, _, body = request(member, consent, dict(csrf_token=token, action='accept'))
        check(b'Consentement enregistr' in body, 'consent acceptance is saved')
        status, _, body = request(member, consent)
        check(b'Mise à jour des CGU' in body, 'accepted member sees consent update state')
        status, headers, _ = request(stranger, '/download.php?id=1&type=identite')
        check(status == 302 and headers.get('Location') == 'login.php', 'token possession does not authorize private downloads')
        if os.environ.get('AMEA_BROWSER_CHECK') == '1':
            subprocess.run(['node', str(ROOT / 'tests/http/tokens-browser.mjs'), base, str(ROOT / 'docs/testing/screenshots')], check=True, timeout=120)
        payload.update(phone='771234567', csrf_token=csrf(member, correction), consent_privacy='1')
        status, headers, _ = request(member, correction, payload)
        check(status == 302 and headers.get('Location') == '/', 'valid correction resubmits record for review')
        status, headers, _ = request(member, correction)
        check(status == 302 and headers.get('Location') == '/', 'resubmitted token cannot reopen correction')
        status, _, body = request(stranger, '/legal-notice.php')
        check(status == 200 and b'id="privacy"' in body and b'public-header' in body, 'legal content uses public navigation')
    except Exception:
        log.flush()
        print((app / 'server.log').read_text())
        raise
    finally:
        server.terminate()
        server.wait(timeout=10)
        log.close()
