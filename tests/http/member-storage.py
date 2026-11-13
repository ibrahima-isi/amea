"""Real member HTTP entry points with external-boundary fault injection."""
import json
import os
from harness import ROOT, SAFE_ENV, check, client, disposable_app
import secrets
import subprocess
import contextlib

PDF = b'%PDF-1.4\nSynthetic member document\n%%EOF\n'
@contextlib.contextmanager
def member_app(extra_fixtures=()):
    endpoint = os.environ.get('STORAGE_TEST_ENDPOINT')
    storage_env = {}
    bucket = 'amea-member-test-' + secrets.token_hex(8)
    if endpoint:
        storage_env = dict(STORAGE_DRIVER='s3', S3_ENDPOINT=endpoint, S3_REGION='us-east-1', S3_BUCKET=bucket,
            S3_ACCESS_KEY_ID='storage-test', S3_SECRET_ACCESS_KEY='disposable-storage-secret', S3_PATH_STYLE='true')
        command = ['php', str(ROOT / 'tests/http/storage-fixture.php')]
        subprocess.run([*command, 'create', bucket], check=True, env=dict(SAFE_ENV, STORAGE_TEST_ENDPOINT=endpoint))
    try:
        with disposable_app(extra_fixtures=extra_fixtures, storage_env=storage_env) as fixture:
            fixture.storage_bucket = bucket
            fixture.storage_env = storage_env
            yield fixture
    finally:
        if endpoint:
            subprocess.run([*command, 'delete', bucket], check=True, env=dict(SAFE_ENV, STORAGE_TEST_ENDPOINT=endpoint))

with member_app() as fixture:
    member = client()
    token = fixture.csrf(member, '/register.php')
    data = dict(csrf_token=token, last_name='Storage', first_name='Fixture', gender='Féminin', status='ETUDIANT', phone='771234567', email='storage@example.invalid', consent_privacy='1')
    status, headers, _ = fixture.request(member, '/register.php', data, True, upload=[('identity.pdf', 'photo', 'application/pdf', PDF), ('cv.pdf', 'cv_file', 'application/pdf', PDF)])
    check(headers.get('Location') == 'registration-details.php', 'both member documents are staged')
    token = fixture.csrf(member, '/registration-details.php')
    (fixture.app / 'storage-fault.json').write_text(json.dumps(dict(copiesUntilFailure=1)))
    status, headers, _ = fixture.request(member, '/registration-details.php', dict(csrf_token=token))
    check(status >= 400, 'second-document copy failure is exposed as retryable error')
    status, _, body = fixture.request(member, '/registration-preview.php?type=identite')
    check(status == 200 and body == PDF, 'first staged document survives partial finalization')
    status, _, body = fixture.request(member, '/registration-preview.php?type=cv')
    check(status == 200 and body == PDF, 'second staged document survives partial finalization')
    status, headers, _ = fixture.request(member, '/registration-details.php', dict(csrf_token=token))
    check(status == 302 and headers.get('Location') == '/', 'retry confirms complete member dossier')
    import sqlite3
    fixture_db = sqlite3.connect(fixture.app / 'fixture.sqlite')
    fixture_db.execute("UPDATE users SET permissions='[\"slider\",\"students\"]'")
    fixture_db.commit()
    fixture_db.close()
    staff = client()
    token = fixture.csrf(staff, '/login.php')
    fixture.request(staff, '/login.php', dict(csrf_token=token, username='fixture-admin', password='fixture-password'))
    status, headers, body = fixture.request(staff, '/download.php?id=1&type=cv')
    check(status == 200 and body == PDF and headers.get('X-Content-Type-Options') == 'nosniff', 'staff downloads verified private CV')

    class HttpDriver:
        def replaceCv(self, studentId, fixturePath):
            token = fixture.csrf(staff, '/edit-student.php?id=' + str(studentId))
            fields = dict(data, csrf_token=token)
            return fixture.request(staff, '/edit-student.php?id=' + str(studentId), fields, True, upload=('replacement.pdf', 'cv_file', 'application/pdf', fixturePath.read_bytes()))

    class Faults:
        def failNextWrite(self):
            (fixture.app / 'storage-fault.json').write_text(json.dumps(dict(failWrite=True)))

    import sqlite3
    db = sqlite3.connect(fixture.app / 'fixture.sqlite')
    before = db.execute('SELECT cv_path FROM students WHERE id=1').fetchone()[0]
    path = fixture.app / 'replacement.pdf'
    path.write_bytes(PDF + b'new content')
    faults, http = Faults(), HttpDriver()
    faults.failNextWrite()
    status, _, _ = http.replaceCv(1, path)
    check(status >= 400, 'write failure returns retryable form error')
    check(db.execute('SELECT cv_path FROM students WHERE id=1').fetchone()[0] == before, 'failed replacement preserves previous reference')
    status, _, body = fixture.request(staff, '/download.php?id=1&type=cv')
    check(status == 200 and body == PDF, 'failed replacement preserves previous downloadable document')

    db.execute("CREATE TRIGGER fail_member_save BEFORE UPDATE ON students BEGIN SELECT RAISE(ABORT, 'Injected save failure'); END")
    db.commit()
    status, _, _ = http.replaceCv(1, path)
    check(status >= 400 and db.execute('SELECT cv_path FROM students WHERE id=1').fetchone()[0] == before, 'DB failure after copies preserves previous member reference')
    status, _, body = fixture.request(staff, '/download.php?id=1&type=cv')
    check(status == 200 and body == PDF, 'DB rollback keeps previous private document readable')
    db.execute('DROP TRIGGER fail_member_save')
    db.commit()
    subprocess.run(['php', str(fixture.app / 'tests/http/purge-retry-fixture.php')], cwd=fixture.app,
        check=True, env=dict(SAFE_ENV, **fixture.storage_env))
    check(b'AwaitingBackup' in fixture.request(staff, '/trash.php')[2], 'interrupted purge is visible before ordinary staff save')
    status, _, _ = http.replaceCv(1, path)
    check(status == 302, 'staff retries replacement successfully')
    check(b'AwaitingBackup' in fixture.request(staff, '/trash.php')[2], 'ordinary staff save cannot resume a purge without backup acknowledgement')
    status, _, body = fixture.request(staff, '/download.php?id=1&type=cv')
    check(status == 200 and body == path.read_bytes(), 'successful replacement publishes new private document')

    if os.environ.get('STORAGE_TEST_ENDPOINT'):
        subprocess.run(['php', str(ROOT / 'tests/http/storage-fixture.php'), 'unsafe', fixture.storage_bucket], check=True,
            env=dict(SAFE_ENV, STORAGE_TEST_ENDPOINT=os.environ['STORAGE_TEST_ENDPOINT']))
    else:
        (fixture.app / 'uploads/students/unsafe.pdf').write_text('<html>synthetic invalid document</html>')
    current_cv = db.execute('SELECT cv_path FROM students WHERE id=1').fetchone()[0]
    db.execute("UPDATE students SET cv_path='uploads/students/unsafe.pdf' WHERE id=1")
    db.commit()
    check(fixture.request(staff, '/download.php?id=1&type=cv')[0] == 415, 'private download checks actual content despite PDF metadata')
    db.execute('UPDATE students SET cv_path=? WHERE id=1', (current_cv,))
    db.execute("INSERT INTO users (username,password,role,email) SELECT 'fixture-reader',password,'user','reader@example.invalid' FROM users WHERE username='fixture-admin'")
    db.commit()
    reader = client()
    token = fixture.csrf(reader, '/login.php')
    fixture.request(reader, '/login.php', dict(csrf_token=token, username='fixture-reader', password='fixture-password'))
    check(fixture.request(reader, '/download.php?id=1&type=cv')[0] == 302, 'ordinary staff role cannot download admin-only private documents')

    correction = 'synthetic-correction-token'
    db.execute("UPDATE students SET kyc_status='NEEDS_CLARIFICATION', review_token=? WHERE id=1", (correction,))
    db.commit()
    token = fixture.csrf(member, '/kyc-correction.php?token=' + correction)
    status, headers, _ = fixture.request(member, '/kyc-correction.php?token=' + correction, dict(data, csrf_token=token))
    check(status == 302 and headers.get('Location') == '/', 'public correction retains existing documents without replacement')
    status, _, body = fixture.request(staff, '/download.php?id=1&type=cv&inline=1')
    check(status == 200 and body == path.read_bytes(), 'correction retains the current CV')

    db.execute("UPDATE students SET kyc_status='NEEDS_CLARIFICATION' WHERE id=1")
    db.commit()
    token = fixture.csrf(member, '/kyc-correction.php?token=' + correction)
    status, headers, _ = fixture.request(member, '/kyc-correction.php?token=' + correction, dict(data, csrf_token=token), True,
        upload=('corrected.pdf', 'cv_file', 'application/pdf', PDF + b'corrected'))
    check(status == 302 and headers.get('Location') == '/', 'public correction publishes a verified replacement')
    check(fixture.request(staff, '/download.php?id=1&type=cv')[2] == PDF + b'corrected', 'corrected CV is readable through authorized download')

    token = fixture.csrf(staff, '/edit-student.php?id=1')
    fixture.request(staff, '/students.php', dict(csrf_token=token, id=1))
    check(db.execute('SELECT deleted_at FROM students WHERE id=1').fetchone()[0] is not None, 'soft-delete keeps member in trash')
    fixture.request(staff, '/trash.php', dict(csrf_token=token, id=1, action='restore'))
    check(db.execute('SELECT deleted_at FROM students WHERE id=1').fetchone()[0] is None, 'trash restore retains all files')
    fixture.request(staff, '/students.php', dict(csrf_token=token, id=1))
    (fixture.app / 'storage-fault.json').write_text(json.dumps(dict(failDelete=True)))
    fixture.request(staff, '/trash.php', dict(csrf_token=token, id=1, action='force_delete'))
    check(db.execute('SELECT COUNT(*) FROM students WHERE id=1').fetchone()[0] == 1, 'failed purge retains target and file keys')
    fixture.request(staff, '/trash.php', dict(csrf_token=token, id=1, action='restore'))
    check(db.execute('SELECT deleted_at FROM students WHERE id=1').fetchone()[0] is not None, 'in-progress purge cannot restore a partial dossier')
    fixture.request(staff, '/trash.php', dict(csrf_token=token, id=1, action='force_delete'))
    check(db.execute('SELECT COUNT(*) FROM students WHERE id=1').fetchone()[0] == 0, 'manual purge retries to completion')
    check(db.execute("SELECT COUNT(*) FROM file_operations WHERE reason NOT LIKE 'purge-%'").fetchone()[0] == 0, 'ordinary durable deletion intents complete')
    check(b'AwaitingBackup' in fixture.request(staff, '/trash.php')[2], 'targeted manual purge preserves the unrelated backup-gated purge')
    if os.environ.get('AMEA_REGISTRATION_BROWSER') == '1':
        subprocess.run(['node', str(ROOT / 'tests/http/registration-browser.mjs'), fixture.base,
            os.environ['AMEA_BROWSER_OUTPUT']], check=True, timeout=120)

with member_app(('staged-compatibility-fixture.php',)) as fixture:
    previous, stranger = client(), client()
    previous.addheaders = [('Cookie', 'PHPSESSID=amea-previous-registration')]
    status, _, body = fixture.request(previous, '/registration-preview.php?type=identite')
    check(status == 200 and b'Previous synthetic identity' in body, 'pre-existing staged session previews with its original serialized path')
    check(fixture.request(stranger, '/registration-preview.php?type=identite')[0] == 404, 'pre-existing staging remains session-owned')
    token = fixture.csrf(previous, '/registration-details.php')
    status, headers, _ = fixture.request(previous, '/registration-details.php', dict(csrf_token=token))
    check(status == 302 and headers.get('Location') == '/', 'pre-existing staged session confirms without changing its data contract')
