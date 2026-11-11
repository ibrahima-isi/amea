#!/usr/bin/env python3
"""Run against disposable TLS endpoints only; no existing deployment credentials."""
import http.client, ssl, re, urllib.parse, socket, os
PUBLIC_PORT = int(os.environ.get('AMEA_PUBLIC_PORT', '18443'))
STAFF_PORT = int(os.environ.get('AMEA_STAFF_PORT', '19443'))
context = ssl._create_unverified_context()
class LoopbackHTTPS(http.client.HTTPSConnection):
    def connect(self):
        sock = socket.create_connection(('127.0.0.1', self.port), timeout=10)
        self.sock = self._context.wrap_socket(sock, server_hostname=self.host)

def request(app, path, data=None, cookie=None, content_type=None, method=None):
    connection = LoopbackHTTPS('public.amea.test' if app == 'public' else 'staff.amea.test', PUBLIC_PORT if app == 'public' else STAFF_PORT, context=context)
    headers = {'Host': 'public.amea.test' if app == 'public' else 'staff.amea.test'}
    if cookie: headers['Cookie'] = cookie
    body = data if isinstance(data, bytes) else (urllib.parse.urlencode(data) if data is not None else None)
    if body: headers['Content-Type'] = content_type or 'application/x-www-form-urlencoded'
    connection.request(method or ('POST' if data is not None else 'GET'), path, body, headers)
    response = connection.getresponse()
    result = response.status, dict(response.getheaders()), response.read()
    connection.close()
    return result

def check(condition, message):
    assert condition, message
    print('PASS:', message)

if __name__ == '__main__':
    status, headers, body = request('public', '/students.php?page=2')
    check(status == 404 and 'Location' not in headers and 'Set-Cookie' not in headers,
          'staff paths are unknown on the public application before starting a session')
    for path in ['/', '/index.php?source=bookmark']:
        status, headers, body = request('public', path)
        check(status == 200 and b'href="register.php"' in body, 'public home renders at ' + path)
        status, headers, _ = request('staff', path)
        check(status == 302 and headers.get('Location') == '/login.php' and 'Set-Cookie' not in headers,
              'staff root redirects before starting a session at ' + path)
    for app, allowed, denied in [('public', '/register.php', '/login.php'), ('staff', '/login.php', '/register.php')]:
        status, headers, body = request(app, allowed)
        check(status == 200, app + ' allowed entry renders')
        for method in ['GET', 'POST', 'OPTIONS']:
            status, headers, _ = request(app, denied, method=method)
            check(status == 404 and all(name not in headers for name in ['Location', 'Allow', 'Set-Cookie']),
                  app + ' rejects wrong-app ' + method + ' before checking methods or starting a session')
        for path in ['/.env', '/404-handler.php', '/src/bootstrap.php', '/config/database.php', '/vendor/autoload.php', '/schema.sql', '/uploads/students/private.pdf', '/index.php/dashboard.php']:
            check(request(app, path)[0] == 404, app + ' denies ' + path)
    status, headers, _ = request('public', '/dashboard.php')
    check(status == 404 and 'Location' not in headers, 'public does not redirect to the staff dashboard')
    check(request('staff', '/dashboard.php')[0] == 302, 'staff dashboard requires authentication')
    for app, path, method, allow in [
        ('public', '/legal-notice.php', 'POST', 'GET'),
        ('public', '/registration-preview.php', 'POST', 'GET'),
        ('public', '/register.php', 'DELETE', 'GET, POST'),
        ('staff', '/login.php', 'OPTIONS', 'GET, POST'),
        ('staff', '/confirm-upgrade.php', 'GET', 'POST'),
    ]:
        status, headers, _ = request(app, path, method=method)
        check(status == 405 and headers.get('Allow') == allow and 'Set-Cookie' not in headers,
              app + ' sends Allow for unsupported ' + method + ' before starting a session at ' + path)
    check(request('public', '/assets/img/WhatsApp%20Image%202025-04-28%20at%2022.07.41_c84e18a5.jpg')[0] == 200, 'approved image filenames with spaces remain available')
    check(request('public', '/uploads/slider/synthetic-public.jpg')[0] == 200, 'approved shared carousel image is public')
    check(request('public', '/uploads/slider/synthetic.php.jpg')[0] in [403, 404], 'uploaded PHP filenames cannot execute')

    check(request('public', '/uploads/slider/synthetic-private')[0] in [403, 404], 'extensionless slider files are unavailable')

    for app in ['public', 'staff']:
        for method in ['TRACE', 'PATCH', 'PUT']:
            check(request(app, '/register.php' if app == 'public' else '/login.php', method=method)[0] == 405, app + ' denies unsupported ' + method)
