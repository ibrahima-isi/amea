#!/usr/bin/env python3
"""HTTP qualification at the runner's empty, populated and tools fixture stages."""
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
    check('Aucun statut à afficher.'.encode() in dashboard
          and 'Aucune répartition à afficher.'.encode() in dashboard,
          'legacy fixture with no categorized counts renders chart empty states')
    check(not re.search(rb'href="kyc-detail.php\?id=\d+', dashboard),
          'empty queue exposes no dossier action')
elif sys.argv[1] == '--populated':
    check(b'export.php' not in links(dashboard) and b'students.php' in links(dashboard),
          'admin without export permission only sees the permitted shortcut')
    status, _, queue = admin.go('/kyc-list.php')
    row = next((row for row in re.findall(rb'<tr>.*?</tr>', queue, re.S)
                if b'shared@example.invalid' in row), b'')
    match = re.search(rb'kyc-detail\.php\?id=(\d+)', row)
    check(status == 200 and match is not None, 'synthetic pending dossier exists in the KYC queue')
    member_id = match.group(1)
    status, _, listing = admin.go('/students.php?search=SharedMember')
    check(status == 200 and b'student-details.php?id=' + member_id not in links(listing),
          'pending dossier is absent from the member list')
    detail = b'kyc-detail.php?id=' + member_id
    check(all(('data-dashboard-chart="' + key + '"').encode() in dashboard
              for key in ['schools', 'status', 'gender']), 'populated dashboard exposes all three chart series')
    check(b'SharedMember' in dashboard and detail in links(dashboard),
          'admin queue names pending member and links directly to private KYC dossier')
    check(admin.go('/' + detail.decode())[0] == 200, 'queue KYC destination accessible to admin')
    reader = Client('member-reader')
    status, _, body = reader.go('/dashboard.php')
    check(status == 200 and b'SharedMember' not in body and detail not in links(body)
          and b'id="review-heading"' not in body and b'id="status-heading"' in body,
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
    status, _, form = admin.go('/export.php')
    check(status == 200, 'PDF export form available')
    token = re.search(rb'name="csrf_token"\s+value="([^"]+)"', form).group(1).decode()
    status, headers, _ = admin.go('/export.php', {
        'csrf_token': token, 'fields[]': 'last_name', 'export_format': 'pdf'})
    check(status == 302 and headers.get('Location', '').endswith('export-preview.php'),
          'PDF export redirects to standalone preview')
    status, _, preview = admin.go('/export-preview.php')
    check(status == 200 and b'Legacy' in preview, 'PDF preview renders actual synthetic Legacy value')
    check(b'window.print();' in preview and b'export.php' in links(preview),
          'PDF preview retains native print control and return link')
    check(all(value in preview for value in [b'assets/js/theme.js', b'assets/css/admin.css',
          b'assets/css/export-preview.css', b'admin-app admin-export-preview',
          b'theme-switcher', b'theme-label']), 'PDF preview serves staff theme assets and control')
    status, headers, _ = admin.go('/export-preview.php')
    check(status == 302 and headers.get('Location', '').endswith('export.php'),
          'PDF preview consumes export session data after first render')

auth_routes = ['/login.php', '/forgot-password.php']
if sys.argv[1] == '--empty':
    auth_routes.append('/reset-password.php?token=' + 'a' * 64)
for path in auth_routes:
    status, _, body = request('staff', path)
    check(status == 200 and b'assets/css/admin.css' in body, 'auth route serves shared styling ' + path)
for path in ['/assets/css/admin.css', '/assets/js/admin-shell.js']:
    status, _, body = request('staff', path)
    check(status == 200 and len(body) > 0, 'deployed shell asset available ' + path)
