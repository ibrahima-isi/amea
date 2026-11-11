#!/usr/bin/env python3
"""Existing staged-session compatibility through real isolated HTTP entry points."""
from harness import check, client, disposable_apps

with disposable_apps('public', 'staff', extra_fixtures=('staged-compatibility-fixture.php',)) as (public, admin):
    member, stranger, staff = client(), client(), client()
    member.addheaders = [('Cookie', 'AMEA_PUBLIC=amea-previous-registration')]
    request = public.request
    status, _, body = request(member, '/registration-preview.php?type=identite')
    check(status == 200 and b'Previous synthetic identity' in body, 'previous staged session retains its protected preview')
    check(request(stranger, '/registration-preview.php?type=identite')[0] == 404, 'foreign session cannot preview the previous staged document')
    token = public.csrf(member, '/registration-details.php')
    status, _, body = request(member, '/registration-details.php')
    check(b'PreviousFixture' in body and 'Féminin'.encode() in body, 'previous staged data renders unchanged French member values')
    status, _, _ = request(member, '/registration-details.php', {'csrf_token': 'invalid'})
    check(status != 200, 'previous staged session still requires valid CSRF')
    status, headers, _ = request(member, '/registration-details.php', {'csrf_token': token})
    check(status == 302 and headers.get('Location') == '/', 'previous staged session confirms without a session migration')
    token = admin.csrf(staff, '/login.php')
    admin.request(staff, '/login.php', {'csrf_token': token, 'username': 'fixture-admin', 'password': 'fixture-password'})
    status, _, body = admin.request(staff, '/edit-student.php?id=1')
    check(status == 200 and all(value.encode() in body for value in ['PreviousFixture', 'Université Fixture', 'Biologie', 'Licence', 'Guinée', 'Sénégal']), 'confirmed member and shared lookup options render through staff editing')
    status, _, body = admin.request(staff, '/download.php?id=1&type=identite')
    check(status == 200 and b'Previous synthetic identity' in body, 'authorized staff downloads the finalized previous staged document')
    status, _, body = request(member, '/registration-preview.php?type=identite')
    check(status == 404, 'confirmation consumes the previous staged session')
