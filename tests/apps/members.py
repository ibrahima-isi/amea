#!/usr/bin/env python3
"""Member administration through the two real HTTPS applications."""
import re

class Client:
    def __init__(self, app): self.app, self.cookie = app, None
    def get(self, path, data=None):
        status, headers, body = request(self.app, path, data, self.cookie)
        if "Set-Cookie" in headers: self.cookie = headers["Set-Cookie"].split(";")[0]
        return status, headers, body
    def csrf(self, path):
        status, _, body = self.get(path)
        check(status == 200, path + " renders")
        return re.search(rb'name="csrf_token"\s+value="([^"]+)"', body).group(1).decode()
from routes import request, check

admin = Client('staff')
token = admin.csrf('/login.php')
status, headers, _ = admin.get('/login.php', dict(csrf_token=token, username='expired', password='Fixture-password-123!'))
check(status == 302 and headers.get('Location') == 'dashboard.php', 'staff login opens administration dashboard')
status, _, body = admin.get('/dashboard.php')
check(status == 200 and b'Tableau' in body, 'authenticated staff dashboard available')

status, _, body = admin.get('/students.php?search=SharedMember')
check(status == 200 and b'SharedMember' in body, 'staff sees shared member')
member_id = re.search(rb'student-details.php\?id=(\d+)',body).group(1).decode()
for route in ['student-details.php','edit-student.php','kyc-detail.php']:
    response=admin.get('/'+route+'?id='+member_id)
    if response[0] != 200: print(response[0],response[2].decode())
    check(response[0] == 200, route+' available to admin')
for route in ['kyc-list.php','trash.php']:
    check(admin.get('/'+route)[0] == 200, route+' available to admin')
document='/download.php?id='+member_id+'&type=identite'
check(b'Shared synthetic identity' in admin.get(document)[2], 'authorized staff downloads shared document')
check(request('staff',document)[0] == 302, 'anonymous staff document denied')
check(request('public',document)[0] == 302 and request('public',document)[1].get('Location', '').startswith('https://staff.amea.test:'), 'public document bookmark requires staff host authentication')
reader=Client('staff'); token=reader.csrf('/login.php')
reader.get('/login.php',dict(csrf_token=token,username='member-reader',password='Fixture-password-123!'))
for route in ['/edit-student.php?id='+member_id,'/kyc-list.php','/trash.php',document]:
    check(reader.get(route)[0] == 302, 'ordinary user denied '+route)
token=admin.csrf('/kyc-detail.php?id='+member_id)
admin.get('/kyc-detail.php?id='+member_id,dict(csrf_token=token,action='clarify',kyc_notes='Confirmer les informations'))
public=Client('public'); correction='/kyc-correction.php?token='+'d'*64
token=public.csrf(correction)
status, headers, _=public.get(correction,dict(csrf_token=token,last_name='SharedMember',first_name='Corrigee',gender='Féminin',status='ETUDIANT',phone='771234568',email='shared@example.invalid',consent_privacy='1'))
check(status==302 and headers.get('Location')=='/', 'public correction resubmits shared record')
check(b'Corrigee' in admin.get('/student-details.php?id='+member_id)[2], 'staff sees public correction')
check(b'Shared synthetic identity' in admin.get(document)[2], 'public correction preserves existing private identity')
check(b'Shared synthetic identity' in admin.get(document.replace('identite','cv'))[2], 'public correction preserves existing CV')
token=admin.csrf('/edit-student.php?id='+member_id)
status, headers, _=admin.get('/edit-student.php?id='+member_id,dict(csrf_token=token,last_name='SharedMember',first_name='StaffEdited',gender='Féminin',status='ETUDIANT',phone='771234568',email='shared@example.invalid',consent_privacy='1'))
check(status==302, 'admin updates shared member')
check(b'StaffEdited' in admin.get('/student-details.php?id='+member_id)[2], 'staff edit visible in shared record')
check(b'Shared synthetic identity' in admin.get(document)[2], 'staff edit preserves private document')
token=admin.csrf('/students.php')
admin.get('/students.php',dict(csrf_token=token,id=member_id))
check(b'SharedMember' in admin.get('/trash.php')[2], 'archived member appears in staff trash')
check(admin.get(document)[0]==404, 'archived member document denied')
token=admin.csrf('/trash.php')
admin.get('/trash.php',dict(csrf_token=token,id=member_id,action='restore'))
check(b'StaffEdited' in admin.get('/student-details.php?id='+member_id)[2], 'admin restores archived member')
check(b'Shared synthetic identity' in admin.get(document)[2], 'restored member retains private document')
token=admin.csrf('/kyc-detail.php?id='+member_id)
admin.get('/kyc-detail.php?id='+member_id,dict(csrf_token=token,action='approve'))
check(b'SharedMember' not in admin.get('/kyc-list.php')[2], 'admin approval removes member from pending review')
