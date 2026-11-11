#!/usr/bin/env python3
"""Exercise both fixed-identity front controllers with disposable SQLite data."""
from harness import check, client, disposable_apps

for app, allowed, foreign, allow in [
    ('public', '/register.php', '/login.php', 'GET, POST'),
    ('staff', '/confirm-upgrade.php', '/register.php', 'POST'),
]:
    with disposable_apps(app) as (fixture,):
        (fixture.root / 'unlisted.php').write_text('<?php echo "Unlisted file";')
        browser = client()
        for method in ['GET', 'POST', 'OPTIONS']:
            for path in [foreign, '/404-handler.php', '/src/bootstrap.php', '/unlisted.php']:
                status, headers, _ = fixture.request(browser, path, method=method)
                check(status == 404 and all(name not in headers for name in ['Allow', 'Location', 'Set-Cookie']),
                      f'{app} rejects {method} {path} locally before methods and sessions')

        status, headers, _ = fixture.request(browser, allowed, method='DELETE')
        check(status == 405 and headers.get('Allow') == allow and 'Set-Cookie' not in headers,
              f'{app} sends the Allow header without starting a session')

        for path in ['/', '/index.php?source=bookmark']:
            status, headers, body = fixture.request(browser, path)
            if app == 'public':
                check(status == 200 and b'href="register.php"' in body and 'Location' not in headers,
                      f'public home renders at {path}')
            else:
                check(status == 302 and headers.get('Location') == '/login.php' and 'Set-Cookie' not in headers,
                      f'staff redirects {path} to login before starting a session')

        entry = '/register.php' if app == 'public' else '/login.php'
        status, headers, _ = fixture.request(client(), entry)
        cookie = headers.get('Set-Cookie', '').lower()
        check(status == 200 and cookie.startswith(f'amea_{app}=')
              and all(attribute in cookie for attribute in ['; path=/', '; secure', '; httponly', '; samesite=strict'])
              and '; domain=' not in cookie, f'{app} starts its own host-only Secure session')

        if app == 'public':
            for path, expected in [('/journal.php', 200), ('/journal-article.php?slug=missing', 404),
                                   ('/journal-cover.php?slug=missing', 404)]:
                status, headers, _ = fixture.request(browser, path)
                check(status == expected and 'Set-Cookie' not in headers,
                      f'anonymous public journal read stays session-free at {path}')
