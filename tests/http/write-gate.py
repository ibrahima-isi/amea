#!/usr/bin/env python3
"""Pause covers stateful GET/POST, session expiry and direct job entry points."""
import hashlib
import os
import subprocess
from harness import ROOT, SAFE_ENV, check, client, disposable_app

with disposable_app(extra_fixtures=('staged-compatibility-fixture.php',), php_options=('-d', 'session.gc_probability=1', '-d', 'session.gc_divisor=1')) as fixture:
    browser = client()
    browser.addheaders = [('Cookie', 'PHPSESSID=amea-previous-registration')]
    check(fixture.request(browser, '/registration-preview.php?type=identite')[0] == 200, 'preview works before pause')
    gate = fixture.app / 'storage/write-gate'
    subprocess.run(['php', '-r', "require 'vendor/autoload.php'; (new Amea\\Core\\FileWriteGate($argv[1]))->pause();", str(gate)], cwd=fixture.app, check=True, env=SAFE_ENV)
    session = fixture.app / 'sessions/sess_amea-previous-registration'
    before = hashlib.sha256(session.read_bytes()).hexdigest()
    mtime = session.stat().st_mtime_ns
    expired = fixture.app / 'sessions/sess_expiredgate'
    expired.write_text('last_activity|i:1;')
    os.utime(expired, (1, 1))
    session_count = len(list((fixture.app / 'sessions').glob('sess_*')))
    for path, data in [('/register.php', {}), ('/registration-details.php', {}), ('/logout.php', None), ('/reset-password.php?token=expired', None)]:
        status, headers, _ = fixture.request(browser, path, data)
        check(status == 503 and headers.get('Retry-After') == '60', path + ' is paused before session/DB mutation')
    status, headers, _ = fixture.request(browser, '/registration-preview.php?type=identite')
    check(status == 200 and 'Set-Cookie' not in headers, 'valid pre-pause session reads its private preview without cookie creation/rotation')
    check(fixture.request(client(), '/registration-preview.php?type=identite')[0] == 503, 'paused preview cannot create a new session')
    expired_browser = client()
    expired_browser.addheaders = [('Cookie', 'PHPSESSID=expiredgate')]
    check(fixture.request(expired_browser, '/registration-preview.php?type=identite')[0] == 503 and expired.read_text() == 'last_activity|i:1;', 'GET expiry remains paused without destroying its expired session')
    check(expired.exists() and len(list((fixture.app / 'sessions').glob('sess_*'))) == session_count, 'forced PHP session GC preserves all session files during paused reads')
    check(session.stat().st_mtime_ns == mtime, 'paused reads preserve session lifecycle mtime')
    check(hashlib.sha256(session.read_bytes()).hexdigest() == before, 'paused requests preserve staged session bytes')
    check(fixture.request(browser, '/journal.php')[0] == 200, 'stateless journal read remains available')
    for script, args in [('purge-pending-uploads.php', []), ('purge-trashed-students.php', []), ('process-mail-queue.php', []), ('run-shared-jobs.php', ['mail']), ('run-shared-jobs.php', ['pending']), ('run-shared-jobs.php', ['trash'])]:
        result = subprocess.run(['php', str(ROOT / 'scripts' / script), *args], cwd=ROOT,
                                env={**SAFE_ENV, 'FILE_WRITE_GATE_PATH': str(gate), 'AMEA_TRASH_BACKUP_CONFIRMED':'1'},
                                stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        check(result.returncode != 0 and b'paused' in result.stderr, script + ' ' + ' '.join(args) + ' refuses before work while paused')
