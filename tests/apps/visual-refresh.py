#!/usr/bin/env python3
"""Read-only HTTP qualification at the runner's empty and populated fixture stages."""
import re
import sys
from routes import request, check


class Client:
    def __init__(self, username):
        self.cookie = None
        status, _, body = self.go('/login.php')
        check(status == 200, username + ' login form available')
        token = re.search(rb'name="csrf_token"\s+value="([^"]+)"', body).group(1).decode()
        status, _, _ = self.go('/login.php', dict(csrf_token=token, username=username,
                                                password='Fixture-password-123!'))
        check(status == 302, username + ' authenticates')

    def go(self, path, data=None):
        result = request('staff', path, data, self.cookie)
        if 'Set-Cookie' in result[1]:
            self.cookie = result[1]['Set-Cookie'].split(';')[0]
        return result


def links(body):
    return re.findall(rb'href=["\']([^"\']+)', body)


if len(sys.argv) != 2 or sys.argv[1] not in ['--empty', '--populated', '--tools']:
    raise SystemExit('usage: visual-refresh.py --empty|--populated|--tools')

admin = Client('toolsadmin' if sys.argv[1] == '--tools' else 'expired')
status, _, dashboard = admin.go('/dashboard.php')
check(status == 200, 'admin dashboard renders')
if sys.argv[1] == '--empty':
    check('Aucun dossier à examiner pour le moment.'.encode() in dashboard,
          'empty review queue explains that no dossier needs action')
    check(not re.search(rb'href="kyc-detail.php\?id=\d+', dashboard),
          'empty queue exposes no dossier action')
elif sys.argv[1] == '--populated':
    status, _, listing = admin.go('/students.php?search=SharedMember')
    check(status == 200 and b'SharedMember' in listing, 'synthetic pending member exists')
    member_id = re.search(rb'student-details.php\?id=(\d+)', listing).group(1)
    detail = b'kyc-detail.php?id=' + member_id
    check(b'SharedMember' in dashboard and detail in links(dashboard),
          'admin queue names pending member and links directly to private KYC dossier')
    check(admin.go('/' + detail.decode())[0] == 200, 'queue KYC destination accessible to admin')
    reader = Client('member-reader')
    status, _, body = reader.go('/dashboard.php')
    check(status == 200 and b'SharedMember' not in body and detail not in links(body),
          'ordinary dashboard does not expose pending dossier')
    denied = [b'kyc-list.php', b'trash.php', b'export.php', b'upgrade-levels.php',
              b'manage-slider.php', b'communications.php', b'users.php', b'settings.php']
    check(all(path not in links(body) for path in denied)
          and not re.search(rb'href="kyc-detail.php\?', body),
          'ordinary navigation hides denied modules and queue actions')
    for path in ['/students.php', '/profile.php']:
        check(reader.go(path)[0] == 200, 'ordinary user retains allowed route ' + path)
    for path in ['/students.php', '/kyc-list.php', '/trash.php', '/profile.php']:
        status, _, body = admin.go(path)
        check(status == 200 and b'assets/css/admin.css' in body
              and b'assets/js/admin-shell.js' in body,
              'served module includes shared admin shell ' + path)
    for label in ['Tableau de bord', 'Adhérents', 'Validation KYC', 'Mon profil']:
        check(label.encode() in dashboard, 'French admin navigation: ' + label)
else:
    for path in ['/export.php', '/upgrade-levels.php', '/manage-slider.php',
                 '/communications.php', '/users.php', '/settings.php']:
        status, _, body = admin.go(path)
        check(status == 200 and b'assets/css/admin.css' in body
              and b'assets/js/admin-shell.js' in body,
              'served permitted tools module includes shared admin shell ' + path)
    check('Paramètres'.encode() in dashboard, 'French tools navigation: Paramètres')
auth_routes = ['/login.php', '/forgot-password.php']
if sys.argv[1] == '--empty':
    auth_routes.append('/reset-password.php?token=' + 'a' * 64)
for path in auth_routes:
    status, _, body = request('staff', path)
    check(status == 200 and b'assets/css/admin.css' in body, 'auth route serves shared styling ' + path)
for path in ['/assets/css/admin.css', '/assets/js/admin-shell.js']:
    status, _, body = request('staff', path)
    check(status == 200 and len(body) > 0, 'deployed shell asset available ' + path)
