#!/usr/bin/env python3
"""Operational tools exercised through real split-host HTTP, using synthetic data."""
from routes import request, check
import re, json, pathlib, hashlib
class Client:
    def __init__(self, username):
        self.cookie = None
        token = self.csrf('/login.php')
        status, _, _ = self.go('/login.php', {'csrf_token':token,'username':username,'password':'Fixture-password-123!'})
        check(status == 302, username + ' authenticates')
    def go(self,path,data=None,content_type=None):
        result=request('staff',path,data,self.cookie,content_type)
        if 'Set-Cookie' in result[1]: self.cookie=result[1]['Set-Cookie'].split(';')[0]
        return result
    def csrf(self,path):
        status,_,body=self.go(path)
        check(status==200,path+' renders')
        match=re.search(rb'name="csrf_token"\s+value="([^"]+)"',body)
        assert match, path+' CSRF token missing'
        return match[1].decode()
admin=Client('toolsadmin')
restricted=Client('toolsrestricted')
user=Client('toolsuser')
paths=['/manage-slider.php','/communications.php','/communications-count.php','/consent.php','/consent-members.php','/export.php','/export-preview.php','/settings.php','/users.php','/add-user.php','/edit-user.php?id=999999','/upgrade-levels.php']
for path in paths:
    status, headers, _ = request('public',path)
    check(status == 404 and 'Location' not in headers, 'public rejects staff tool without a redirect '+path)
    check(request('staff',path)[0]==302,'anonymous denied '+path)
    check(restricted.go(path)[0]==302,'restricted admin denied '+path)
    check(user.go(path)[0]==302,'ordinary user denied '+path)
for path in ['/manage-slider.php','/communications.php','/communications-count.php','/consent.php','/consent-members.php','/export.php','/settings.php','/users.php','/add-user.php','/upgrade-levels.php','/profile.php']:
    check(admin.go(path)[0]==200,'authorized tools admin sees '+path)
check(user.go('/profile.php')[0]==200,'ordinary user retains own profile')
for path in ['/manage-slider.php','/communications.php','/consent.php','/export.php','/settings.php','/users.php','/add-user.php','/profile.php','/confirm-upgrade.php']:
    status,headers,_=admin.go(path,{'csrf_token':'invalid'})
    check(status==302 and 'Content-Disposition' not in headers,'CSRF blocks action '+path)
for path in ['/communications-dispatch.php','/consent-dispatch.php']:
    status,headers,_=request('public',path,{'csrf_token':'invalid'})
    check(status==404 and 'Location' not in headers,'public rejects dispatch endpoint without redirect '+path)
    check(request('staff',path,{'csrf_token':'invalid'})[0]==302,'anonymous denied dispatch '+path)
    check(restricted.go(path,{'csrf_token':'invalid'})[0]==302,'restricted admin denied dispatch '+path)
    check(user.go(path,{'csrf_token':'invalid'})[0]==302,'ordinary user denied dispatch '+path)
    check(admin.go(path,{'csrf_token':'invalid'})[0]==403,'CSRF blocks JSON dispatch '+path)
# Valid export returns real synthetic member bytes; unauthorized POST never exports.
token=admin.csrf('/export.php')
status,headers,body=admin.go('/export.php',{'csrf_token':token,'fields[]':'last_name','export_format':'csv'})
check(status==200 and 'attachment' in headers.get('Content-Disposition','') and b'Legacy' in body,'authorized CSV contains shared synthetic member')
check(user.go('/export.php',{'csrf_token':token,'fields[]':'email'})[0]==302,'ordinary user cannot POST export')
# Decline changed audiences without creating a communication, then verify the no-JS route.
token=admin.csrf('/communications.php')
status,headers,_=admin.go('/communications.php',{'csrf_token':token,'subject':'Stale audience fixture','body':'Synthetic safe message','confirmed_recipient_count':'0'})
_,_,page=admin.go('/communications.php')
check(status==302 and 'campaign=' not in headers.get('Location','')
      and b'Le nombre de destinataires a chang' in page and b'Stale audience fixture' not in page,
      'a stale confirmation is rejected without creating a campaign')
token=admin.csrf('/communications.php')
status,headers,_=admin.go('/communications.php',{'csrf_token':token,'subject':'Synthetic notice','body':'Synthetic safe message'})
location=headers.get('Location','')
check(status==302 and re.fullmatch(r'communications\.php\?campaign=[1-9]\d*', location)
      and b'email(s)' in admin.go('/'+location)[2], 'safe communications submission redirects to the campaign and reports result')
_,_,history=admin.go('/communications.php')
diagnostics=re.search(rb'communications\.php\?failures=(\d+)[^"]+">Voir toutes les erreurs \(26\)', history)
assert diagnostics, 'history links to every failed recipient'
status,_,second=admin.go('/communications.php?failures='+diagnostics[1].decode()+'&failure_page=2')
details=second.split(b'id="failure-details"', 1)[-1].split(b'</section>', 1)[0]
check(status==200 and b'Page 2 / 2' in details and b'Synthetic SMTP diagnostic 26' in details
      and b'Synthetic SMTP diagnostic 25' not in details, 'failure diagnostics paginate past the first 25 recipients')
# Profile and settings preserve existing forms and persistence.
token=admin.csrf('/settings.php')
admin.go('/settings.php',{'csrf_token':token,'association_name':'Synthetic AEESGS','contact_email':'synthetic@example.invalid','contact_phone':'000'})
check(b'Synthetic AEESGS' in admin.go('/settings.php')[2],'settings update persists')
token=admin.csrf('/profile.php')
admin.go('/profile.php',{'csrf_token':token,'action':'update_profile','last_name':'UpdatedTools','first_name':'Synthetic','email':'toolsadmin@example.invalid'})
check(b'UpdatedTools' in admin.go('/profile.php')[2],'profile update persists')
# Account creation and progression exercise preserved tools beyond rendering.
token=admin.csrf('/add-user.php')
status,_,_=admin.go('/add-user.php',{'csrf_token':token,'username':'createdtools','email':'createdtools@example.invalid','last_name':'CreatedTools','first_name':'Synthetic','password':'Fixture-password-123!','confirm_password':'Fixture-password-123!','role':'user','is_active':'1'})
check(status==302 and b'createdtools' in admin.go('/users.php')[2],'authorized account creation persists in listing')
created=Client('createdtools')
check(created.go('/profile.php')[0]==200,'new staff account can authenticate and read own profile')
token=admin.csrf('/upgrade-levels.php')
page=admin.go('/upgrade-levels.php')[2]
match=re.search(rb'name="student_id"[^>]*value="(\d+)"',page)
assert match,'progression member selector missing'
status,_,_=admin.go('/confirm-upgrade.php',{'csrf_token':token,'student_id':match[1].decode(),'new_status':'ETUDIANT'})
resultpage=admin.go('/upgrade-levels.php')[2]
check(status==302 and b'<span class="badge bg-secondary">ETUDIANT</span>' in resultpage,'authorized member progression persists visible status')
# A real multipart image upload must become available on the other app.
token=admin.csrf('/manage-slider.php')
boundary='AMEASyntheticMultipart07'
parts=[]
for name,value in [('csrf_token',token),('caption','Synthetic tools carousel'),('display_order','-50')]:
    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
image=pathlib.Path('assets/img/presidents.jpg').read_bytes()
parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="slider_image"; filename="synthetic.jpg"\r\nContent-Type: image/jpeg\r\n\r\n'.encode()+image+b'\r\n')
parts.append(f'--{boundary}--\r\n'.encode())
check(admin.go('/manage-slider.php',b''.join(parts),'multipart/form-data; boundary='+boundary)[0]==302,'staff carousel form accepts actual image')
status,_,home=request('public','/')
check(status==200 and b'Synthetic tools carousel' in home,'staff carousel caption appears publicly')
match=re.search(rb'(uploads/slider/[^"<> ]+)"[^>]*alt="Synthetic tools carousel',home)
if not match:
    match=re.search(rb'(uploads/slider/[^"<> ]+)',home)
assert match,'public carousel image URL missing'
status,_,published=request('public','/'+match[1].decode())
check(status==200 and hashlib.sha256(published).digest()==hashlib.sha256(image).digest(),'actual uploaded image bytes visible on public app')
