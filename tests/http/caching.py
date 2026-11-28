#!/usr/bin/env python3
"""End-to-end HTTP caching, conditional 304, session safety, and Cloudflare purge verification."""
import hashlib
import http.server
import json
import os
import pathlib
import re
import sqlite3
import threading
import urllib.error
import urllib.request

from harness import ROOT, check, client, disposable_apps

ZONE_ID = '0123456789abcdef0123456789abcdef'
TOKEN = 'cf-http-test-purge-token'


class CloudflareMockState:
    def __init__(self):
        self.calls = []
        self.lock = threading.Lock()


def start_cloudflare_mock():
    state = CloudflareMockState()

    class Handler(http.server.BaseHTTPRequestHandler):
        def do_POST(self):
            length = int(self.headers.get('Content-Length', '0'))
            raw = self.rfile.read(length).decode('utf-8')
            with state.lock:
                state.calls.append({
                    'path': self.path,
                    'auth': self.headers.get('Authorization', ''),
                    'body': json.loads(raw),
                })
            response = json.dumps({'success': True, 'result': {'id': ZONE_ID}}).encode('utf-8')
            self.send_response(200)
            self.send_header('Content-Type', 'application/json')
            self.send_header('Content-Length', str(len(response)))
            self.end_headers()
            self.wfile.write(response)

        def log_message(self, format, *args):
            return

    server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), Handler)
    thread = threading.Thread(target=server.serve_forever, daemon=True)
    thread.start()
    port = server.server_address[1]
    return server, f'http://127.0.0.1:{port}/client/v4', state


def raw_get(base, path, extra_headers=None):
    headers = dict(extra_headers or {})
    req = urllib.request.Request(base + path, headers=headers, method='GET')
    try:
        resp = urllib.request.urlopen(req, timeout=10)
        return resp.code, resp.headers, resp.read()
    except urllib.error.HTTPError as err:
        return err.code, err.headers, err.read()


def check_legacy_media(public, key, url):
    path = public.root / key
    sidecar = public.root / 'storage/object-storage' / (hashlib.sha256(key.encode()).hexdigest() + '.json')
    metadata = sidecar.read_bytes()
    sidecar.unlink()
    original = path.read_bytes()
    stamp = path.stat().st_mtime_ns
    try:
        etag = raw_get(public.base, url)[1]['ETag']
        changed = original[:-1] + bytes([original[-1] ^ 1])
        path.write_bytes(changed)
        os.utime(path, ns=(stamp, stamp))
        status, headers, body = raw_get(public.base, url, {'If-None-Match': etag})
        check(status == 200 and headers['ETag'] != etag and body == changed, 'legacy ETag follows bytes for ' + url)
        status = raw_get(public.base, url, {'If-Modified-Since': 'Tue, 01 Jan 2030 00:00:00 GMT'})[0]
        check(status == 200, 'legacy mtime cannot authorize 304')
    finally:
        path.write_bytes(original)
        sidecar.write_bytes(metadata)


mock_server, api_base, cf_state = start_cloudflare_mock()
try:
    cf_env = {
        'APP_ENV': 'test',
        'PUBLIC_APP_URL': 'https://aeesgs.example',
        'CLOUDFLARE_ZONE_ID': ZONE_ID,
        'CLOUDFLARE_API_TOKEN': TOKEN,
        'CLOUDFLARE_CACHE_ENABLED': '1',
        'CLOUDFLARE_API_BASE': api_base,
    }
    with disposable_apps('public', 'staff', storage_env=cf_env) as (public, admin):
        # 1. Anonymous homepage is session-free, Cloudflare-cacheable (browser revalidates to keep flash banners fresh), and supports 304 Not Modified.
        status, headers, body = raw_get(public.base, '/')
        etag = headers.get('ETag', '')
        check(
            status == 200
            and 'Set-Cookie' not in headers
            and headers.get('Cache-Control', '') == 'public, max-age=0, must-revalidate'
            and 'max-age=3600' in headers.get('CDN-Cache-Control', '')
            and 'stale-while-revalidate=' in headers.get('Cloudflare-CDN-Cache-Control', '')
            and etag.startswith('"')
            and 'Accept-Encoding' in headers.get('Vary', ''),
            'anonymous homepage emits session-free browser-revalidating + Cloudflare edge cache headers and strong ETag',
        )
        status304, headers304, body304 = raw_get(public.base, '/', {'If-None-Match': etag})
        check(status304 == 304 and body304 == b'' and headers304.get('ETag') == etag,
              'anonymous homepage returns 304 Not Modified on matching If-None-Match')

        # 2. Anonymous legal-notice is session-free and supports 304.
        status, headers, _ = raw_get(public.base, '/legal-notice.php')
        legal_etag = headers.get('ETag', '')
        check(
            status == 200
            and 'Set-Cookie' not in headers
            and 'public' in headers.get('Cache-Control', '')
            and 'max-age=86400' in headers.get('CDN-Cache-Control', '')
            and legal_etag.startswith('"'),
            'legal-notice emits 24h Cloudflare CDN cache headers without session cookie',
        )
        check(raw_get(public.base, '/legal-notice.php', {'If-None-Match': legal_etag})[0] == 304,
              'legal-notice returns 304 Not Modified on matching ETag')

        # 3. Anonymous journal index emits Cloudflare edge headers (shorter TTL for upcoming).
        status, headers, _ = raw_get(public.base, '/journal.php')
        check(status == 200 and 'Set-Cookie' not in headers and 'max-age=1800' in headers.get('CDN-Cache-Control', ''),
              'journal index emits Cloudflare edge cache headers')
        status, headers, _ = raw_get(public.base, '/journal.php?view=upcoming')
        check(status == 200 and 'max-age=300' in headers.get('CDN-Cache-Control', ''),
              'time-sensitive journal upcoming view uses shorter 5-minute edge TTL')

        for path in ('/?source=bookmark', '/legal-notice.php?source=bookmark',
                     '/journal.php?page=2&view=all', '/journal.php?view=all&page=4'):
            status, headers, _ = raw_get(public.base, path)
            check(status == 200 and headers.get('Cache-Control') == 'no-store'
                  and headers.get('Cloudflare-CDN-Cache-Control') == 'no-store',
                  f'URL outside the purge set remains readable without being cached: {path}')

        # 4. Stateful public registration and staff login remain strictly private, no-store.
        status, headers, _ = raw_get(public.base, '/register.php')
        check(
            status == 200
            and 'private, no-store' == headers.get('Cache-Control', '')
            and 'no-store' == headers.get('CDN-Cache-Control', '')
            and 'AMEA_PUBLIC=' in headers.get('Set-Cookie', ''),
            'register.php starts AMEA_PUBLIC session and emits private, no-store + CDN no-store',
        )
        pub_cookie = headers.get('Set-Cookie', '').split(';')[0]
        status, headers, _ = raw_get(public.base, '/', {'Cookie': pub_cookie})
        check(
            status == 200
            and headers.get('Cache-Control') == 'private, no-store'
            and headers.get('CDN-Cache-Control') == 'no-store',
            'homepage with AMEA_PUBLIC cookie bypasses public caching with private, no-store',
        )

        status, headers, _ = raw_get(admin.base, '/login.php')
        check(
            status == 200
            and headers.get('Cache-Control') == 'private, no-store'
            and headers.get('CDN-Cache-Control') == 'no-store',
            'staff login emits private, no-store + CDN no-store',
        )

        # 5. Staff slider upload + public media caching + 304 + live Cloudflare purge call.
        staff = client()
        token = admin.csrf(staff, '/login.php')
        check(admin.request(staff, '/login.php', {'csrf_token': token, 'username': 'fixture-admin', 'password': 'fixture-password'})[0] == 302,
              'staff signs in for cache purge checks')

        token = admin.csrf(staff, '/manage-slider.php')
        check(admin.request(staff, '/manage-slider.php', {'csrf_token': token, 'caption': 'Cached slide', 'display_order': '1'}, True, True)[0] == 302,
              'slider upload succeeds and triggers purge')

        status, headers, home_body = raw_get(public.base, '/')
        check(status == 200 and b'Cached slide' in home_body, 'origin AppCache immediately reflects newly uploaded slider image')
        slide_path = '/' + re.search(rb'(uploads/slider/[^"<> ]+)', home_body).group(1).decode()

        with cf_state.lock:
            check(
                len(cf_state.calls) == 1
                and cf_state.calls[0]['path'] == f'/client/v4/zones/{ZONE_ID}/purge_cache'
                and cf_state.calls[0]['auth'] == f'Bearer {TOKEN}'
                and f'https://aeesgs.example{slide_path}' in cf_state.calls[0]['body']['files']
                and 'https://aeesgs.example/' in cf_state.calls[0]['body']['files'],
                'slider upload fires authenticated Cloudflare purge for homepage and slide URL',
            )

        status, headers, slide_bytes = raw_get(public.base, slide_path)
        slide_etag = headers.get('ETag', '')
        check(
            status == 200
            and len(slide_bytes) > 0
            and 'Set-Cookie' not in headers
            and 'public' in headers.get('Cache-Control', '')
            and 'max-age=604800' in headers.get('CDN-Cache-Control', '')
            and headers.get('X-Content-Type-Options') == 'nosniff'
            and slide_etag.startswith('"'),
            'active carousel media emits Cloudflare public media headers and strong ETag',
        )
        status304, _, body304 = raw_get(public.base, slide_path, {'If-None-Match': slide_etag})
        check(status304 == 304 and body304 == b'', 'active carousel media returns 304 Not Modified on matching ETag')
        status, headers, variant_bytes = raw_get(public.base, slide_path + '?source=bookmark')
        check(status == 200 and variant_bytes == slide_bytes and headers.get('CDN-Cache-Control') == 'no-store',
              'carousel URL with extra parameters serves the image without being cached')

        # Staff host must NEVER emit public/CDN cache headers for carousel media.
        staff_slide_status, staff_slide_headers, staff_slide_bytes = raw_get(admin.base, slide_path)
        check(
            staff_slide_status == 200
            and staff_slide_bytes == slide_bytes
            and staff_slide_headers.get('Cache-Control') == 'private, no-store'
            and staff_slide_headers.get('CDN-Cache-Control') == 'no-store',
            'carousel media requested on staff host fails closed to private, no-store',
        )

        check_legacy_media(public, slide_path.lstrip('/'), slide_path)

        # Deleting slider image immediately returns 404 with no-store and purges Cloudflare edge URL.
        page = admin.request(staff, '/manage-slider.php')[2]
        image_id = re.search(rb'name="id"\s+value="(\d+)"', page).group(1).decode()
        token = admin.csrf(staff, '/manage-slider.php')
        check(admin.request(staff, '/manage-slider.php', {'csrf_token': token, '_action': 'delete', 'id': image_id})[0] == 302,
              'slider delete succeeds')

        status, headers, _ = raw_get(public.base, slide_path)
        check(status == 404 and headers.get('Cache-Control') == 'no-store' and headers.get('CDN-Cache-Control') == 'no-store',
              'deleted carousel media fails closed with 404 and no-store')
        with cf_state.lock:
            check(
                len(cf_state.calls) == 2
                and f'https://aeesgs.example{slide_path}' in cf_state.calls[1]['body']['files'],
                'slider delete purges deleted slide URL from Cloudflare edge',
            )

        # 6. Draft/invalid journal cover always returns 404 with no-store.
        status, headers, _ = raw_get(public.base, '/journal-cover.php?slug=nonexistent-article')
        check(
            status == 404
            and headers.get('Cache-Control') == 'no-store'
            and headers.get('CDN-Cache-Control') == 'no-store'
            and headers.get('X-Content-Type-Options') == 'nosniff',
            'missing journal cover returns 404 with fail-closed no-store and nosniff',
        )

        # 7. Journal publish -> public article + cover caching + 304 -> withdraw -> 404 no-store + Cloudflare purge.
        db = sqlite3.connect(public.root / 'fixture.sqlite')
        db.execute("UPDATE users SET permissions = ? WHERE username = 'fixture-admin'", ('["slider","journal","settings"]',))
        db.commit()
        db.close()

        token = admin.csrf(staff, '/edit-journal.php')
        img_bytes = (ROOT / 'assets/img/presidents.jpg').read_bytes()
        status, headers, _ = admin.request(
            staff,
            '/edit-journal.php',
            {
                'csrf_token': token,
                '_action': 'publish',
                'type': 'ANNOUNCEMENT',
                'title': 'Annonce cache Cloudflare',
                'summary': 'Résumé pour le cache.',
                'body': 'Corps de l annonce.',
                'cover_alt': 'Photo couverture',
            },
            multipart=True,
            upload=('cover.jpg', 'cover', 'image/jpeg', img_bytes),
        )
        check(status == 302 and 'id=' in headers.get('Location', ''), 'journal publish succeeds')
        post_id = re.search(r'id=(\d+)', headers['Location']).group(1)
        edit_body = admin.request(staff, f'/edit-journal.php?id={post_id}')[2]
        slug = re.search(rb'journal-article\.php\?slug=([^"]+)', edit_body).group(1).decode()
        revision = re.search(rb'name="revision" value="(\d+)"', edit_body).group(1).decode()

        with cf_state.lock:
            check(
                len(cf_state.calls) == 3
                and f'https://aeesgs.example/journal-article.php?slug={slug}' in cf_state.calls[2]['body']['files']
                and f'https://aeesgs.example/journal-cover.php?slug={slug}' in cf_state.calls[2]['body']['files']
                and 'https://aeesgs.example/journal.php?view=all&page=2' in cf_state.calls[2]['body']['files']
                and 'https://aeesgs.example/journal.php?view=all&page=3' in cf_state.calls[2]['body']['files'],
                'journal publish purges article, cover, listings (pages 1-3), and homepage on Cloudflare',
            )

        status, headers, article_body = raw_get(public.base, f'/journal-article.php?slug={slug}')
        article_etag = headers.get('ETag', '')
        check(
            status == 200
            and 'Set-Cookie' not in headers
            and 'max-age=3600' in headers.get('CDN-Cache-Control', '')
            and article_etag.startswith('"'),
            'published journal article emits Cloudflare edge headers and strong ETag',
        )
        check(raw_get(public.base, f'/journal-article.php?slug={slug}', {'If-None-Match': article_etag})[0] == 304,
              'published journal article returns 304 Not Modified on matching ETag')

        cover_match = re.search(rb'journal-cover\.php\?slug=[^"&]+(?:&amp;|&)v=([a-f0-9]+)', article_body)
        check(cover_match is not None, 'published journal article renders versioned cover URL')
        cover_v = cover_match.group(1).decode()

        status, headers, cover_bytes = raw_get(public.base, f'/journal-cover.php?slug={slug}&v={cover_v}')
        cover_etag = headers.get('ETag', '')
        check(
            status == 200
            and cover_bytes == img_bytes
            and 'public, max-age=300, must-revalidate' == headers.get('Cache-Control', '')
            and 'max-age=86400' in headers.get('CDN-Cache-Control', '')
            and headers.get('X-Content-Type-Options') == 'nosniff'
            and cover_etag.startswith('"'),
            'published journal cover emits public Cloudflare headers, must-revalidate, nosniff, and strong ETag',
        )
        check(raw_get(public.base, f'/journal-cover.php?slug={slug}&v={cover_v}', {'If-None-Match': cover_etag})[0] == 304,
              'published journal cover returns 304 Not Modified on matching ETag')
        for path, expected in (
            (f'/journal-article.php?slug={slug}&source=bookmark', article_body),
            (f'/journal-cover.php?v={cover_v}&slug={slug}', img_bytes),
            (f'/journal-cover.php?slug={slug}&v=old', img_bytes),
        ):
            status, headers, body = raw_get(public.base, path)
            check(status == 200 and body == expected and headers.get('CDN-Cache-Control') == 'no-store',
                  f'journal URL outside the purge set serves current content without being cached: {path}')

        cover = next((public.root / 'uploads/journal/covers').glob(f'{cover_v}*.jpg'))
        key = str(cover.relative_to(public.root))
        sidecar = public.root / 'storage/object-storage' / (hashlib.sha256(key.encode()).hexdigest() + '.json')
        metadata = json.loads(sidecar.read_text())
        metadata['mime'] = 'application/octet-stream'
        sidecar.write_text(json.dumps(metadata))
        url = f'/journal-cover.php?slug={slug}&v={cover_v}'
        status, headers, body = raw_get(public.base, url)
        check(status == 200 and headers.get('Content-Type') == 'image/jpeg' and body == img_bytes,
              'generic storage MIME falls back to verified JPEG bytes')
        cover.write_bytes(b'x' * len(img_bytes))
        check(raw_get(public.base, url, {'If-None-Match': cover_etag})[0] == 503,
              'generic storage MIME cannot authorize metadata-only 304')
        cover.write_bytes(img_bytes)

        check_legacy_media(public, key, url)

        # Withdraw publication -> immediate 404 + no-store + Cloudflare edge purge.
        token = admin.csrf(staff, f'/edit-journal.php?id={post_id}')
        status, _, _ = admin.request(staff, '/edit-journal.php', {
            'csrf_token': token,
            'id': post_id,
            'revision': revision,
            '_action': 'withdraw',
        })
        check(status == 302, 'journal withdrawal succeeds')
        status, headers, _ = raw_get(public.base, f'/journal-cover.php?slug={slug}&v={cover_v}')
        check(
            status == 404
            and headers.get('Cache-Control') == 'no-store'
            and headers.get('CDN-Cache-Control') == 'no-store',
            'withdrawn journal cover fails closed with 404 and no-store',
        )
        with cf_state.lock:
            check(
                len(cf_state.calls) == 4
                and f'https://aeesgs.example/journal-cover.php?slug={slug}&v={cover_v}' in cf_state.calls[3]['body']['files'],
                'journal withdrawal purges versioned cover URL on Cloudflare',
            )

        # 8. Settings update invalidates local AppCache immediately and purges affected HTML on Cloudflare.
        with sqlite3.connect(public.root / 'fixture.sqlite') as db:
            previous = db.execute("SELECT setting_value FROM settings WHERE setting_key='contact_email'").fetchone()
            db.execute("CREATE TRIGGER refuse_phone BEFORE INSERT ON settings WHEN NEW.setting_key='contact_phone' BEGIN SELECT RAISE(ABORT,'fixture'); END")
        token = admin.csrf(staff, '/settings.php')
        admin.request(staff, '/settings.php', {'csrf_token': token, 'contact_email': 'rollback@example.org', 'contact_phone': 'fail'})
        with sqlite3.connect(public.root / 'fixture.sqlite') as db:
            check(db.execute("SELECT setting_value FROM settings WHERE setting_key='contact_email'").fetchone() == previous,
                  'settings rollback restores earlier field')
            db.execute('DROP TRIGGER refuse_phone')
        check(len(cf_state.calls) == 4, 'failed settings save skips purge')
        token = admin.csrf(staff, '/settings.php')
        status, _, _ = admin.request(staff, '/settings.php', {
            'csrf_token': token,
            'contact_email': 'cached-contact@aeesgs.example',
            'contact_phone': '+221 77 000 00 00',
            'association_name': 'AEESGS',
        })
        check(status == 302, 'settings update succeeds')
        status, _, home_after_settings = raw_get(public.base, '/')
        check(status == 200 and b'cached-contact@aeesgs.example' in home_after_settings,
              'settings update immediately invalidates origin AppCache for public pages')
        with cf_state.lock:
            check(
                len(cf_state.calls) == 6
                and cf_state.calls[4]['body']['files'] == ['https://aeesgs.example/', 'https://aeesgs.example/index.php', 'https://aeesgs.example/legal-notice.php']
                and cf_state.calls[5]['body']['prefixes'] == ['aeesgs.example/journal.php', 'aeesgs.example/journal-article.php'],
                'settings update purges affected public HTML',
            )

    print('PASS: Cloudflare edge headers, 304 revalidation, session safety, and active purge verified')
finally:
    mock_server.shutdown()
    mock_server.server_close()
