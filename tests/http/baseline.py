#!/usr/bin/env python3
"""HTTP journeys against a disposable copy, never an existing deployment."""
import json
import os
import re
import subprocess
from harness import ROOT, check, client, disposable_app

with disposable_app() as fixture:
    base = fixture.base
    request, csrf = fixture.request, fixture.csrf
    member, stranger, staff = client(), client(), client()
    status, _, home = request(stranger, '/')
    check(status == 200 and b'AEESGS' in home and b'community-empty' in home, 'homepage shows association identity and useful empty carousel state')
    status, _, registration = request(member, '/register.php')
    check(b'public-form-page' in registration and b'public-header' in registration and registration.count(b'<main') == 1, 'registration uses accessible shared public shell')
    token = csrf(member, '/register.php')
    status, headers, _ = request(member, '/register.php', {'csrf_token': 'invalid'})
    check(status == 302 and headers.get('Location') == 'register.php', 'registration rejects invalid CSRF')
    status, headers, _ = request(member, '/register.php', {'csrf_token': token, 'last_name': 'Retained <name>', 'first_name': 'Aminata'})
    status, _, invalid = request(member, '/register.php')
    check(status == 200 and b'Retained &lt;name&gt;' in invalid and b'is-invalid' in invalid, 'validation retains and escapes valid input alongside errors')
    status, _, countries = request(member, '/assets/json/countries.json')
    check(status == 200 and len(json.loads(countries)) > 1, 'nationalities enhancement receives real countries JSON')
    rejected = dict(csrf_token=token, last_name='UploadFixture', first_name='Aminata', gender='Féminin', status='ETUDIANT', phone='771234569', email='upload@example.invalid', consent_privacy='1')
    status, headers, _ = request(member, '/register.php', rejected, True, upload=('unsafe.html', 'photo', 'text/html', b'<html>unsafe</html>'))
    check(status == 302 and headers.get('Location') == 'register.php', 'registration rejects invalid upload MIME')
    status, _, rejected_page = request(member, '/register.php')
    check(b'UploadFixture' in rejected_page and b'is-invalid' in rejected_page, 'upload rejection retains member fields')
    status, headers, _ = request(member, '/register.php', dict(csrf_token=token, last_name='Fixture', first_name='Aminata', gender='Féminin', status='ETUDIANT', phone='771234567', email='member@example.invalid', consent_privacy='1'), True)
    check(status == 302 and headers.get('Location') == 'registration-details.php', 'valid registration reaches review')
    status, _, body = request(member, '/registration-preview.php?type=identite')
    check(status == 200 and b'Safe synthetic document' in body, 'member previews own staged document')
    status, _, _ = request(stranger, '/registration-preview.php?type=identite')
    check(status == 404, 'another session cannot preview staged document')
    status, _, review = request(member, '/registration-details.php')
    check(b'public-form-page' in review and b'public-header' in review and review.count(b'<main') == 1, 'review uses same public shell')
    status, _, _ = request(member, '/registration-details.php', {'csrf_token': 'invalid'})
    check(status != 200, 'review rejects invalid CSRF before confirmation')
    token = csrf(member, '/registration-details.php')
    status, headers, _ = request(member, '/registration-details.php', {'csrf_token': token})
    check(status == 302 and headers.get('Location') == '/', 'confirmation completes registration')
    status, _, body = request(member, '/')
    flash = re.search(rb'<script id="flash-data" type="application/json">(.*?)</script>', body, re.S)
    check(status == 200 and flash is not None and 'en cours de révision' in json.loads(flash.group(1))['message'], 'confirmation returns success message on homepage')
    status, headers, _ = request(stranger, '/download.php?id=1&type=identite')
    check(status == 302 and headers.get('Location') == 'login.php', 'anonymous download requires staff login')
    token = csrf(staff, '/login.php')
    status, headers, _ = request(staff, '/login.php', {'csrf_token': token, 'username': 'fixture-admin', 'password': 'fixture-password'})
    check(status == 302 and headers.get('Location') == 'dashboard.php', 'staff can sign in')
    status, _, edit = request(staff, '/edit-student.php?id=1')
    check(status == 200 and all(('name="' + field + '"').encode() in edit for field in ['last_name', 'first_name', 'gender', 'status', 'phone', 'email', 'nationalities']), 'staff editing retains aligned member field identities')
    status, headers, body = request(staff, '/download.php?id=1&type=identite')
    check(status == 200 and b'Safe synthetic document' in body and headers.get('Cache-Control') == 'private, no-store', 'authorized staff downloads persisted document')
    if os.environ.get('AMEA_REGISTRATION_BROWSER') == '1':
        subprocess.run(['node', str(ROOT / 'tests/http/registration-browser.mjs'), base, str(ROOT / 'docs/testing/screenshots')], check=True, timeout=120)
    token = csrf(staff, '/manage-slider.php')
    status, headers, _ = request(staff, '/manage-slider.php', {'csrf_token': token, 'caption': 'Une communauté <solidaire>', 'display_order': '1'}, True, True)
    status, _, body = request(stranger, '/')
    check(status == 200 and b'Une communaut' in body and b'&lt;solidaire&gt;' in body and b'uploads/slider/' in body and b'data-carousel-pause' not in body, 'staff photo and escaped caption appear publicly; single photo has no rotation controls')
    if os.environ.get('AMEA_BROWSER_CHECK') == '1':
        subprocess.run(['node', str(ROOT / 'tests/http/homepage-browser.mjs'), base, str(ROOT / 'docs/testing/screenshots'), 'single'], check=True, timeout=60)
    token = csrf(staff, '/manage-slider.php')
    request(staff, '/manage-slider.php', {'csrf_token': token, 'caption': 'Ensemble au Sénégal', 'display_order': '2'}, True, True)
    status, _, body = request(stranger, '/')
    check(status == 200 and b'data-carousel-pause' in body and b'Photo suivante' in body, 'multiple staff photos expose accessible carousel controls')
    if os.environ.get('AMEA_BROWSER_CHECK') == '1':
        subprocess.run(['node', str(ROOT / 'tests/http/homepage-browser.mjs'), base, str(ROOT / 'docs/testing/screenshots')], check=True, timeout=120)
    for image_id in ['1', '2']:
        token = csrf(staff, '/manage-slider.php')
        request(staff, '/manage-slider.php', {'csrf_token': token, '_action': 'delete', 'id': image_id})
    status, _, body = request(stranger, '/')
    check(status == 200 and b'community-empty' in body and b'uploads/slider/' not in body, 'staff removal restores useful public empty state')
    if os.environ.get('AMEA_BROWSER_CHECK') == '1':
        subprocess.run(['node', str(ROOT / 'tests/http/homepage-browser.mjs'), base, str(ROOT / 'docs/testing/screenshots'), 'empty'], check=True, timeout=60)
