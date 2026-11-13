"""Real carousel HTTP save/read behavior with external read failure injection."""
import contextlib
import json
import os
import re
import secrets
import subprocess
from harness import ROOT, SAFE_ENV, check, client, disposable_app

@contextlib.contextmanager
def media_app():
    endpoint = os.environ.get('STORAGE_TEST_ENDPOINT')
    storage_env = {}
    bucket = 'amea-member-test-' + secrets.token_hex(8)
    command = ['php', str(ROOT / 'tests/http/storage-fixture.php')]
    if endpoint:
        storage_env = dict(STORAGE_DRIVER='s3', S3_ENDPOINT=endpoint, S3_REGION='us-east-1', S3_BUCKET=bucket,
            S3_ACCESS_KEY_ID='storage-test', S3_SECRET_ACCESS_KEY='disposable-storage-secret', S3_PATH_STYLE='true')
        subprocess.run([*command, 'create', bucket], check=True, env=dict(SAFE_ENV, STORAGE_TEST_ENDPOINT=endpoint))
    try:
        with disposable_app(storage_env=storage_env) as fixture:
            yield fixture
    finally:
        if endpoint:
            subprocess.run([*command, 'delete', bucket], check=True, env=dict(SAFE_ENV, STORAGE_TEST_ENDPOINT=endpoint))

with media_app() as fixture:
    staff, public = client(), client()
    token = fixture.csrf(staff, '/login.php')
    check(fixture.request(staff, '/login.php', dict(csrf_token=token, username='fixture-admin', password='fixture-password'))[0] == 302, 'media staff signs in')
    token = fixture.csrf(staff, '/manage-slider.php')
    check(fixture.request(staff, '/manage-slider.php', dict(csrf_token=token, caption='Readable published image'), True, True)[0] == 302, 'readable carousel image saves')
    home = fixture.request(public, '/')[2]
    path = '/' + re.search(rb'(uploads/slider/[^"<> ]+)', home).group(1).decode()
    status, headers, content = fixture.request(public, path)
    check(status == 200 and headers.get('Content-Type') == 'image/jpeg' and content == (ROOT / 'assets/img/presidents.jpg').read_bytes(), 'old carousel URL reads verified selected-backend bytes')
    (fixture.app / 'storage-fault.json').write_text(json.dumps({'failRead': True}))
    token = fixture.csrf(staff, '/manage-slider.php')
    check(fixture.request(staff, '/manage-slider.php', dict(csrf_token=token, caption='Unreadable rejected image'), True, True)[0] == 302, 'unreadable upload returns through the staff form')
    check(b'Unreadable rejected image' not in fixture.request(public, '/')[2], 'failed storage read cannot publish a carousel reference')
    check(fixture.request(public, path)[0] == 200, 'failed save preserves the previous readable carousel image')

    page = fixture.request(staff, '/manage-slider.php')[2]
    image_id = re.search(rb'name="id"\s+value="(\d+)"', page).group(1).decode()
    token = fixture.csrf(staff, '/manage-slider.php')
    check(fixture.request(staff, '/manage-slider.php', dict(csrf_token=token, _action='delete', id=image_id))[0] == 302, 'carousel removal commits through the staff form')
    check(fixture.request(public, path)[0] == 404, 'removed carousel reference immediately denies its old URL')
