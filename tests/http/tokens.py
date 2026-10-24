#!/usr/bin/env python3
"""HTTP journeys against a disposable copy, never an existing deployment."""
import json
import os
import re
import subprocess
from harness import ROOT, check, client, disposable_app

with disposable_app(extra_fixtures=('token-fixture.php',)) as fixture:
    base = fixture.base
    request, csrf = fixture.request, fixture.csrf
    member, stranger, staff = client(), client(), client()
    correction = '/kyc-correction.php?token=' + 'a' * 64
    consent = '/accept-cgu.php?token=' + 'a' * 64
    status, _, body = request(member, correction)
    check(status == 200 and b'&lt;identit' in body and b'<identit' not in body, 'correction preserves existing token and escapes review notes')
    token = csrf(member, correction)
    status, headers, _ = request(member, correction, {'csrf_token': 'invalid'})
    check(status == 302 and headers.get('Location') == correction[1:], 'correction rejects invalid CSRF without consuming token')
    payload = dict(csrf_token=token, last_name='Retained', first_name='Aminata', gender='Féminin', status='ETUDIANT', phone='', email='a@example.invalid')
    request(member, correction, payload)
    status, _, body = request(member, correction)
    check(status == 200 and b'value="Retained"' in body and b'is-invalid' in body, 'correction validation retains entered data and exposes field errors')
    for bad in ['', 'z' * 64, 'b' * 64, 'c' * 64, 'd' * 64]:
        status, headers, body = request(stranger, '/kyc-correction.php?token=' + bad)
        check(status == 302 and headers.get('Location') == '/', 'invalid, revoked, deleted or ineligible correction fails safely: ' + bad[:1])
    for bad in ['', 'z' * 64, 'c' * 64, 'd' * 64]:
        status, _, body = request(stranger, '/accept-cgu.php?token=' + bad)
        check(status == 200 and b'Lien invalide' in body and b'Aminata' not in body, 'invalid consent reveals no member data: ' + bad[:1])
    status, _, body = request(member, consent)
    check(status == 200 and b'Acceptation des CGU' in body and b'&lt;safe&gt;' in body, 'consent pending state escapes member name')
    token = csrf(member, consent)
    status, _, body = request(member, consent, dict(csrf_token='invalid', action='accept'))
    check(b'Lien invalide' in body, 'consent refuses invalid CSRF')
    status, _, body = request(member, consent, dict(csrf_token=token, action='refuse'))
    check(b'Vous avez refus' in body, 'consent refusal offers deletion and reconsideration')
    status, _, body = request(member, consent, dict(csrf_token=token, action='request_deletion'))
    check(b'Demande de suppression envoy' in body, 'deletion notification succeeds with suppressed test transport')
    status, _, body = request(member, consent, dict(csrf_token=token, action='accept'))
    check(b'Consentement enregistr' in body, 'consent acceptance is saved')
    status, _, body = request(member, consent)
    check('Mise à jour des CGU'.encode() in body, 'accepted member sees consent update state')
    status, headers, _ = request(stranger, '/download.php?id=1&type=identite')
    check(status == 302 and headers.get('Location') == 'login.php', 'token possession does not authorize private downloads')
    if os.environ.get('AMEA_BROWSER_CHECK') == '1':
        subprocess.run(['node', str(ROOT / 'tests/http/tokens-browser.mjs'), base, str(ROOT / 'docs/testing/screenshots')], check=True, timeout=120)
    payload.update(phone='771234567', csrf_token=csrf(member, correction), consent_privacy='1')
    status, headers, _ = request(member, correction, payload)
    check(status == 302 and headers.get('Location') == '/', 'valid correction resubmits record for review')
    status, headers, _ = request(member, correction)
    check(status == 302 and headers.get('Location') == '/', 'resubmitted token cannot reopen correction')
    status, _, body = request(stranger, '/legal-notice.php')
    check(status == 200 and b'id="privacy"' in body and b'public-header' in body, 'legal content uses public navigation')
