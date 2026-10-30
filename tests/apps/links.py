#!/usr/bin/env python3
"""Independent application routes and internal staff login returns over HTTPS."""
from routes import request, check, STAFF_PORT
import re
class Client:
    def __init__(self): self.cookie = None
    def go(self, path, data=None):
        result = request('staff', path, data, self.cookie)
        if 'Set-Cookie' in result[1]: self.cookie = result[1]['Set-Cookie'].split(';')[0]
        return result
    def login(self):
        status, _, body = self.go('/login.php')
        token = re.search(rb'name="csrf_token"\s+value="([^"]+)"', body).group(1).decode()
        return self.go('/login.php', {'csrf_token': token, 'username': 'expired', 'password': 'Fixture-password-123!'})
client = Client()
check(client.go('/students.php?page=2')[0] == 302, 'legacy destination requires staff authentication')
check(client.login()[1].get('Location') == '/students.php?page=2', 'staff login returns to validated local destination')
for cookie_id in ['syntheticexpiredstafflink', 'syntheticrevokedstafflink']:
    waiting = Client()
    waiting.cookie = 'AMEA_STAFF=' + cookie_id
    status, headers, _ = waiting.go('/students.php?page=2')
    check(status == 302, cookie_id + ' requests fresh login')
    destination = headers.get('Location', '')
    waiting.go('/' + destination.lstrip('/'))
    check(waiting.login()[1].get('Location') == '/students.php?page=2', cookie_id + ' preserves bookmarked destination through fresh login')
# Old bookmarks must not reveal or reach the staff application through public.
status, headers, _ = request('public', '/student-details.php?id=42&utm_source=archive')
check(status == 404 and 'Location' not in headers, 'public rejects old staff bookmarks locally')
# A real old-host form submission cannot repeat a staff action.
status, _, listing = client.go('/students.php?search=Legacy')
member = re.search(rb'student-details.php\?id=(\d+)', listing).group(1).decode()
csrf = re.search(rb'name="csrf_token"\s+value="([^"]+)"', listing).group(1).decode()
status, headers, body = request('public', '/students.php', {'id': member, 'csrf_token': csrf, 'action': 'delete', 'private_marker': 'never-reflect-this'}, client.cookie)
check(status == 404 and 'Location' not in headers and b'never-reflect-this' not in body and b'href=' not in body, 'stale staff POST is not replayed, redirected, reflected or linked to staff')
check(b'Legacy' in client.go('/students.php?search=Legacy')[2], 'stale POST leaves member record unchanged through staff HTTP')
# Return destinations are exact local GET routes, not external or encoded aliases.
from urllib.parse import quote
for target in ['https://evil.invalid/', '//evil.invalid/x', '/\\evil.invalid', '/students.php%0d%0aX-Test:yes', '/%2e%2e/login.php', '/unknown.php', '/students.php?redirect=https://evil.invalid', '/logout.php', '/confirm-upgrade.php', '/students.php#fragment', '/student-details.php?id[]=1']:
    c = Client()
    c.go('/login.php?return_to=' + quote(target, safe=''))
    check(c.login()[1].get('Location') == 'dashboard.php', 'unsafe login target rejected: ' + target)
c = Client()
c.go('/login.php?return_to=' + quote('/students.php?search=Legacy', safe=''))
check(c.login()[1].get('Location') == '/students.php?search=Legacy', 'explicit vetted GET return destination survives login')
check(c.go('/login.php')[1].get('Location') == 'dashboard.php', 'login consumes return destination once')
# Explicit staff/member surface and auxiliary endpoints; no accidental fallback.
staff_get = ['/login.php', '/logout.php', '/forgot-password.php', '/reset-password.php', '/dashboard.php', '/students.php', '/student-details.php', '/edit-student.php', '/kyc-list.php', '/kyc-detail.php', '/trash.php', '/download.php', '/manage-slider.php', '/communications.php', '/communications-count.php', '/export.php', '/export-preview.php', '/settings.php', '/users.php', '/add-user.php', '/edit-user.php', '/profile.php', '/upgrade-levels.php']
for path in staff_get:
    status, headers, body = request('public', path)
    check(status == 404 and 'Location' not in headers and b'href=' not in body, 'public rejects staff GET without a bridge ' + path)
    status, headers, body = request('public', path, {'csrf_token': 'old', 'password': 'never-forward'})
    check(status == 404 and 'Location' not in headers and b'href=' not in body, 'public rejects staff POST without a bridge ' + path)
    check(request('staff', path)[0] in ([200] if path in ['/login.php', '/forgot-password.php'] else [302]), 'staff route keeps authentication behavior ' + path)
for path, expected in [('/', 200), ('/index.php', 200), ('/register.php', 200), ('/registration-details.php', 302), ('/registration-preview.php?type=identite', 404), ('/kyc-correction.php?token=invalid', 302), ('/legal-notice.php', 200), ('/accept-cgu.php?token=invalid', 200)]:
    check(request('public', path)[0] == expected, 'public member URL compatible ' + path)
    if path not in ['/', '/index.php']:
        check(request('staff', path)[0] == 404, 'staff does not expose public member handler ' + path)
for app in ['public', 'staff']:
    for path in ['/unknown.php', '/src/bootstrap.php', '/config/database.php', '/uploads/students/member-review.pdf', '/index.php/dashboard.php', '/%6cogin.php', '/%2e%2e/config/database.php', '/%252e%252e/config/database.php', '/uploads/slider/..%2fstudents/member-review.pdf']:
        check(request(app, path)[0] in [400, 403, 404], app + ' rejects direct/encoded private or unknown path ' + path)
    for path, method in [('/confirm-upgrade.php', 'GET'), ('/login.php', 'HEAD'), ('/login.php', 'OPTIONS'), ('/students.php', 'DELETE'), ('/registration-preview.php', 'POST')]:
        response = request(app, path, method=method)
        wrong_app = (app == 'staff' and path.startswith('/registration-')) or (app == 'public' and not path.startswith('/registration-'))
        expected = 404 if wrong_app else 405
        check(response[0] == expected, app + ' rejects unsupported ' + method + ' ' + path)
status, headers, _ = request('public', '/reset-password.php?token=' + 'a' * 64)
check(status == 404 and 'Location' not in headers, 'public does not forward staff recovery tokens')
check(request('staff', '/reset-password.php?token=' + 'a'*64)[0] == 200, 'staff recovery token remains usable directly on staff')
_, _, staff_login = request('staff', '/login.php')
public_origin = 'https://public.amea.test:' + str(__import__('routes').PUBLIC_PORT)
check(('href="' + public_origin + '/register.php"').encode() in staff_login, 'staff navigation registration points to public application')
for path in ['/', '/register.php', '/legal-notice.php', '/accept-cgu.php?token=invalid']:
    _, _, body = request('public', path)
    check(b'staff.amea.test' not in body and not re.search(rb'(?:href|action)=[\"\'][^\"\']*(?:login|dashboard)\.php', body), 'public page has no staff navigation ' + path)
