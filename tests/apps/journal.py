#!/usr/bin/env python3
"""Real HTTP checks against the disposable split applications and synthetic users."""
from routes import request, check
import re, pathlib, hashlib
from datetime import datetime, timedelta, timezone
class Client:
    def __init__(self, name):
        self.cookie = None
        token = self.csrf('/login.php')
        check(self.go('/login.php', {'csrf_token': token, 'username': name, 'password': 'Fixture-password-123!'})[0] == 302, name + ' authenticated')
    def go(self, path, data=None, content_type=None):
        result = request('staff', path, data, self.cookie, content_type)
        if 'Set-Cookie' in result[1]: self.cookie = result[1]['Set-Cookie'].split(';')[0]
        return result
    def csrf(self, path):
        status, _, body = self.go(path)
        check(status == 200, path + ' rendered')
        match = re.search(rb'name="csrf_token"\s+value="([^"]+)"', body)
        assert match, 'CSRF missing'
        return match[1].decode()
def multipart(fields, image, mime='image/jpeg'):
    boundary = 'syntheticJournalBoundary'
    data = b''.join(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode() for k,v in fields.items())
    data += f'--{boundary}\r\nContent-Disposition: form-data; name="cover"; filename="cover.jpg"\r\nContent-Type: {mime}\r\n\r\n'.encode() + image + b'\r\n'
    return data + f'--{boundary}--\r\n'.encode(), 'multipart/form-data; boundary=' + boundary
now = datetime.now(timezone.utc)
future_day = (now + timedelta(days=7)).strftime('%Y-%m-%d')
admin, denied, reader = (Client(n) for n in ['journal-admin', 'journal-denied', 'journal-reader'])
paths = ['/manage-journal.php', '/edit-journal.php', '/journal-preview.php?id=1', '/journal-preview-cover.php?id=1']
for path in paths:
    for method in ['GET', 'POST']:
        status, headers, body = request('public', path, method=method, cookie=admin.cookie)
        check(status == 404 and 'Location' not in headers and b'login.php' not in body, 'public rejects ' + method + ' ' + path)
    check(denied.go(path)[0] == 302 and reader.go(path)[0] == 302, 'permission and role denied ' + path)
token = admin.csrf('/edit-journal.php')
check(admin.go('/edit-journal.php', {'csrf_token':'invalid','_action':'save','type':'REPORT','title':'CSRF'})[0] == 403, 'CSRF blocks write')
fields = {'csrf_token':token, '_action':'save', 'type':'ACTIVITY', 'title':'Synthetic draft', 'summary':'Résumé', 'body':'Texte <script>alert(1)</script>', 'cover_alt':'Image', 'location':'Dakar', 'activity_start_date':future_day, 'activity_end_date':future_day, 'activity_start_time':'10:00', 'activity_end_time':'12:00'}
image = pathlib.Path('assets/img/presidents.jpg').read_bytes()
data, content = multipart(fields, image)
status, headers, _ = admin.go('/edit-journal.php', data, content)
check(status == 302 and 'id=' in headers.get('Location',''), 'draft with real image saved')
id = re.search(r'id=(\d+)', headers['Location']).group(1)
path = '/edit-journal.php?id=' + id
status, _, body = admin.go(path)
revision = re.search(rb'name="revision" value="(\d+)"', body).group(1).decode()
public_url = re.search(rb'href="([^"]*journal-article.php\?slug=[^"]+)"', body).group(1).decode()
slug = public_url.split('slug=')[1]
article = '/journal-article.php?slug=' + slug
public_cover = '/journal-cover.php?slug=' + slug
check(request('public', article)[0] == 404 and request('public', public_cover)[0] == 404, 'known draft inaccessible publicly')
for session_id in ['syntheticexpiredjournal', 'syntheticoldjournalidentity']:
    for route, expected in [('/journal.php', 200), (article, 404), (public_cover, 404)]:
        status, headers, response = request('public', route, cookie='AMEA_PUBLIC=' + session_id)
        check(status == expected and 'Location' not in headers and 'Set-Cookie' not in headers
              and b'never-show-session-marker' not in response,
              'anonymous journal ignores old public session: ' + session_id + ' ' + route)
        if route == public_cover:
            check('no-store' in headers.get('Cache-Control', '') and headers.get('X-Content-Type-Options') == 'nosniff',
                  'draft cover privacy headers survive old public session')
status, _, preview = admin.go('/journal-preview.php?id=' + id)
check(status == 200 and b'Synthetic draft' in preview and b'&lt;script&gt;' in preview and b'<script>alert(1)</script>' not in preview, 'draft preview escapes text')
status, headers, cover = admin.go('/journal-preview-cover.php?id=' + id)
check(status == 200 and headers.get('Content-Type') == 'image/jpeg' and headers.get('X-Content-Type-Options') == 'nosniff' and 'no-store' in headers.get('Cache-Control','') and hashlib.sha256(cover).digest() == hashlib.sha256(image).digest(), 'private image bytes and headers')
check(request('public', '/journal-preview-cover.php?id=' + id)[0] == 404, 'draft cover inaccessible publicly')
fields.update({'id':id, 'revision':revision, 'activity_start_date':'2026-02-30', 'title':'Preserved invalid draft'})
status, _, body = admin.go('/edit-journal.php', fields)
check(status == 422 and b'Preserved invalid draft' in body, 'invalid date rejected with values preserved')
# A failed form submission must retain explicit cleared dates/hours and cover removal.
cleared = dict(fields, title='', activity_start_date='', activity_end_date='', activity_start_time='', activity_end_time='', remove_cover='1')
status, _, body = admin.go('/edit-journal.php', cleared)
check(status == 422, 'invalid submission with cleared fields rejected')
failures = []
for key in ['activity_start_date', 'activity_end_date', 'activity_start_time', 'activity_end_time']:
    match = re.search(rb'name="' + key.encode() + rb'" value="([^"]*)"', body)
    if match is None or match.group(1) != b'': failures.append('cleared ' + key)
if re.search(rb'name="remove_cover"[^>]*checked', body) is None: failures.append('cover removal selection')
check(not failures, 'preserve submitted fields: ' + ', '.join(failures))
fields.update({'activity_start_date':future_day,'title':'Updated draft'})
check(admin.go('/edit-journal.php', fields)[0] == 302, 'updated draft saves')
check(admin.go('/edit-journal.php', fields)[0] == 409, 'stale revision rejected')
revision = re.search(rb'name="revision" value="(\d+)"',admin.go(path)[2]).group(1).decode()
fields['revision'] = revision
for payload, name in [(b'not an image', 'spoof'), (b'x' * (5 * 1024 * 1024 + 1), 'oversize')]:
    data, content = multipart(fields, payload)
    check(admin.go('/edit-journal.php', data, content)[0] == 422, name + ' image rejected')
fields['_action'] = 'publish'
check(admin.go('/edit-journal.php', fields)[0] == 302, 'publication succeeds')
status, headers, public_body = request('public', article, cookie=admin.cookie)
check(status == 200 and b'&lt;script&gt;' in public_body and b'<script>alert(1)</script>' not in public_body, 'published article escaped')
status, headers, public_image = request('public', public_cover)
check(status == 200 and hashlib.sha256(public_image).digest() == hashlib.sha256(image).digest() and 'no-store' in headers.get('Cache-Control', '') and headers.get('X-Content-Type-Options') == 'nosniff', 'published cover matches private image')
for route in [article, public_cover]:
    status, headers, _ = request('public', route, cookie='AMEA_PUBLIC=syntheticexpiredjournal')
    check(status == 200 and 'Location' not in headers and 'Set-Cookie' not in headers,
          'published journal remains accessible with expired public session ' + route)
revision = re.search(rb'name="revision" value="(\d+)"',admin.go(path)[2]).group(1).decode()
check(admin.go('/edit-journal.php', {'csrf_token':token,'id':id,'revision':revision,'_action':'withdraw'})[0] == 302, 'withdrawal succeeds')
check(request('public', article)[0] == 404 and request('public', public_cover)[0] == 404, 'withdrawn article and cover inaccessible')
first_date = re.search(rb'<time[^>]*datetime="([^"]+)"', public_body)
check(first_date is not None, 'article exposes first publication date')
revision = re.search(rb'name="revision" value="(\d+)"', admin.go(path)[2]).group(1).decode()
fields['revision'] = revision
check(admin.go('/edit-journal.php', fields)[0] == 302, 'republish succeeds')
republished = request('public', article)[2]
check(first_date.group(1) == re.search(rb'<time[^>]*datetime="([^"]+)"', republished).group(1), 'republish retains URL and first publication date')
for route in ['/journal.php?view=upcoming', article, '/']:
    body = request('public', route)[2]
    check(public_cover.encode().lstrip(b'/') in body and b'Activit' in body and b'<time' in body and b'AEESGS' in body, 'date type and cover metadata on ' + route)

for endpoint in ['/journal.php', '/journal-article.php?slug=x', '/journal-cover.php?slug=x']:
    for method in ['GET', 'POST']:
        check(request('staff', endpoint, method=method)[0] == 404, 'staff rejects public route ' + method + ' ' + endpoint)
for invalid in ['slug[]=x', 'slug=../x', 'slug=unknown', 'slug=']:
    for endpoint in ['/journal-article.php?', '/journal-cover.php?']:
        status, headers, _ = request('public', endpoint + invalid)
        check(status == 404 and 'Location' not in headers, 'invalid slug rejected ' + invalid)
        if 'cover' in endpoint:
            check('no-store' in headers.get('Cache-Control', '') and headers.get('X-Content-Type-Options') == 'nosniff', 'invalid cover privacy headers')
def publish_synthetic(title, kind, day=None):
    data = {'csrf_token': token, '_action': 'publish', 'type': kind, 'title': title, 'summary': 'Résumé public', 'body': 'Texte public'}
    if day:
        data.update({'location': 'Dakar', 'activity_start_date': day, 'activity_end_date': day})
    status, headers, _ = admin.go('/edit-journal.php', data)
    check(status == 302, 'publish ' + title)
    return headers['Location']
for title, kind, day in [('Future event', 'ACTIVITY', (now + timedelta(days=7)).strftime('%Y-%m-%d')), ('Past event', 'ACTIVITY', (now - timedelta(days=7)).strftime('%Y-%m-%d')), ('Report item', 'REPORT', None), ('Announcement item', 'ANNOUNCEMENT', None)]:
    publish_synthetic(title, kind, day)
for view, present, absent in [('upcoming', b'Future event', b'Past event'), ('archives', b'Past event', b'Future event'), ('reports', b'Report item', b'Announcement item'), ('announcements', b'Announcement item', b'Report item')]:
    status, _, content = request('public', '/journal.php?view=' + view)
    check(status == 200 and present in content and absent not in content, view + ' filter')
status, _, home = request('public', '/')
check(status == 200 and home.count(b'journal-article.php?slug=') == 3, 'homepage shows three recent publications')
check(request('public', '/journal.php?view=archives&page=1')[0] == 200, 'archive pagination route available')
print('Journal HTTP checks complete')

# More than one page in a single filtered view; titles identify exact membership.
for number in range(14):
    publish_synthetic('Archive pagination %02d' % number, 'ACTIVITY', (now - timedelta(days=14 + number)).strftime('%Y-%m-%d'))
def archive_titles(page):
    status, _, body = request('public', '/journal.php?view=archives&page=' + str(page))
    check(status == 200, 'archive page renders ' + str(page))
    return re.findall(rb'>(Archive pagination \d+|Past event)</a>', body), body
first, first_body = archive_titles(1)
second, second_body = archive_titles(2)
check(len(first) == 12 and len(second) == 3 and not set(first) & set(second), 'archive pages have exact disjoint subsets')
check(first == [b'Past event'] + [('Archive pagination %02d' % n).encode() for n in range(11)] and second == [b'Archive pagination 11', b'Archive pagination 12', b'Archive pagination 13'], 'archives order by event end and keep ACTIVITY type')
check(b'view=archives&amp;page=2' in first_body and b'view=archives&amp;page=1' in second_body, 'pagination preserves archive view')
check(archive_titles(1)[0] == first, 'archive first page remains stable')
publish_synthetic('Activity happening today', 'ACTIVITY', now.strftime('%Y-%m-%d'))
current = request('public', '/journal.php?view=upcoming')[2]
check(b'Activity happening today' in current and b'En cours' in current, 'in-progress activity labelled in upcoming')
date_labels = b''.join(re.findall(rb'<p class="journal-dates">(.*?)</p>', current, re.S))
check(b'00:00' not in date_labels and b'23:59' not in date_labels, 'date-only activities omit artificial times')
for route in ['/', '/journal.php', '/journal.php?view=archives&page=2', article]:
    for cookie in [None, admin.cookie, denied.cookie, reader.cookie]:
        status, headers, body = request('public', route, cookie=cookie)
        check(status == 200 and 'Location' not in headers and not any(marker in body for marker in [b'staff.amea.test', b'login.php', b'edit-journal.php', b'journal-preview.php', b'journal-admin', b'never-show-session-marker']), 'public reads never bridge staff ' + route)
print('Expanded journal HTTP checks complete')
