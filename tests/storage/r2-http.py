#!/usr/bin/env python3
"""Read-only R2 media journey using a separate SQLite application and fabricated PDF."""
import hashlib
import os
import pathlib
import sqlite3
import sys

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[1] / 'http'))
from harness import check, client, disposable_app

storage = {key: os.environ[key] for key in ['STORAGE_DRIVER', 'S3_PATH_STYLE', 'S3_ENDPOINT', 'S3_REGION', 'S3_BUCKET', 'S3_ACCESS_KEY_ID', 'S3_SECRET_ACCESS_KEY']}
with disposable_app(storage_env=storage) as fixture:
    db = sqlite3.connect(fixture.app / 'fixture.sqlite')
    db.execute('INSERT INTO students(id, first_name, last_name, identity_document) VALUES(1,?,?,?)', ('Synthetic', 'R2', os.environ['R2_TEST_MEDIA_KEY']))
    db.commit()
    db.close()
    stranger, staff = client(), client()
    check(fixture.request(stranger, '/download.php?id=1&type=identite')[0] == 302, 'anonymous private media denied')
    token = fixture.csrf(staff, '/login.php')
    fixture.request(staff, '/login.php', {'csrf_token': token, 'username': 'fixture-admin', 'password': 'fixture-password'})
    status, headers, body = fixture.request(staff, '/download.php?id=1&type=identite')
    check(status == 200 and hashlib.sha256(body).hexdigest() == os.environ['R2_TEST_MEDIA_SHA256'], 'authorized download streams the R2 synthetic digest')
    check(headers.get('Content-Type') == 'application/pdf' and headers.get('X-Content-Type-Options') == 'nosniff' and headers.get('Cache-Control') == 'private, no-store', 'actual private media MIME and cache headers')
