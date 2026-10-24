#!/usr/bin/env python3
from routes import request, check
import re, json, http.client

class Client:
    def __init__(self, app): self.app, self.cookie = app, None
    def get(self, path, data=None):
        status, headers, body = request(self.app, path, data, self.cookie)
        if 'Set-Cookie' in headers: self.cookie = headers['Set-Cookie'].split(';')[0]
        return status, headers, body
    def csrf(self, path):
        status, headers, body = self.get(path)
        check(status == 200, path + ' renders over TLS')
        token = re.search(rb'name="csrf_token"\s+value="([^"]+)"', body)
        check(token is not None, path + ' has CSRF form')
        return token.group(1).decode()

status, headers, _ = request('staff', '/login.php', cookie='AMEA_STAFF=syntheticexpiredstaffsession')
check(status == 302 and headers.get('Location') == 'login.php', 'staff inactivity beyond 1800 seconds expires')
staff, public = Client('staff'), Client('public')
for client, path, name in [(staff, '/login.php', 'AMEA_STAFF'), (public, '/register.php', 'AMEA_PUBLIC')]:
    status, headers, body = client.get(path)
    cookie = headers.get('Set-Cookie', '')
    check(cookie.startswith(name + '=') and 'secure' in cookie.lower() and 'httponly' in cookie.lower() and 'samesite=strict' in cookie.lower() and 'domain=' not in cookie.lower(), name + ' is Secure HttpOnly Strict and host-only')

csrf = staff.csrf('/login.php')
status, headers, _ = staff.get('/login.php', {'csrf_token': 'invalid', 'username': 'valid', 'password': 'Fixture-password-123!'})
check(status == 302 and headers.get('Location') == 'login.php', 'staff login rejects CSRF')
status, _, body = staff.get('/login.php', {'csrf_token': csrf, 'username': 'valid', 'password': 'wrong'})
check(status == 200 and b'Connexion Administration' in body, 'invalid staff login stays unauthenticated')
status, headers, _ = staff.get('/login.php', {'csrf_token': csrf, 'username': 'valid', 'password': 'Fixture-password-123!'})
check(status == 302, 'valid staff login authenticates')
status, _, body = staff.get('/login.php')
check(status == 200 and b'Connexion r' in body and b'ussie' in body, 'authenticated staff has usable rehearsal landing')

# Copy even the same session ID under the target cookie name: storage must remain separate.
public_id = public.cookie.split('=', 1)[1]
status, _, body = request('staff', '/login.php', cookie='AMEA_STAFF=' + public_id)
check(status == 200 and b'Connexion Administration' in body, 'public session cannot become staff authentication')
staff_id = staff.cookie.split('=', 1)[1]
status, _, _ = request('public', '/registration-preview.php?type=identite', cookie='AMEA_PUBLIC=' + staff_id)
check(status == 404, 'staff session cannot own public staged files')

recovery = Client('staff')
for token, allowed in [('a'*64, True), ('b'*64, False), ('invalid', False)]:
    status, headers, body = recovery.get('/reset-password.php?token=' + token)
    check(status == (200 if allowed else 302), 'reset token ' + ('valid accepted' if allowed else 'expired/invalid rejected'))
csrf = recovery.csrf('/reset-password.php?token=' + 'a'*64)
status, headers, _ = recovery.get('/reset-password.php', {'token': 'a'*64, 'csrf_token': 'invalid', 'password': 'New-password-123!', 'confirm_password': 'New-password-123!'})
check(status == 302 and headers.get('Location') == 'forgot-password.php', 'reset rejects CSRF')
status, headers, _ = recovery.get('/reset-password.php', {'token': 'a'*64, 'csrf_token': csrf, 'password': 'New-password-123!', 'confirm_password': 'New-password-123!'})
check(status == 302 and headers.get('Location') == 'login.php', 'valid reset updates credentials')
status, headers, _ = recovery.get('/reset-password.php?token=' + 'a'*64)
check(status == 302, 'used reset token cannot be replayed')
status, headers, body = staff.get('/login.php')
check(status == 302 and headers.get('Location') == 'login.php', 'password reset revokes existing authenticated staff session')
for password, accepted in [('Fixture-password-123!', False), ('New-password-123!', True)]:
    fresh = Client('staff'); token = fresh.csrf('/login.php')
    status, _, _ = fresh.get('/login.php', dict(csrf_token=token, username='valid', password=password))
    check(status == (302 if accepted else 200), 'reset credential ' + ('works' if accepted else 'old password denied'))
for email in ['absent@example.invalid', 'expired@example.invalid']:
    fresh = Client('staff'); token = fresh.csrf('/forgot-password.php')
    status, headers, _ = fresh.get('/forgot-password.php', dict(csrf_token=token, email=email))
    check(status == 302 and headers.get('Location') == 'login.php', 'forgot password has neutral response for ' + email)
