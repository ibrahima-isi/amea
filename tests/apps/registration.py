#!/usr/bin/env python3
from routes import request, check
import re, sys
status, headers, body = request('public', '/register.php')
cookie = headers['Set-Cookie'].split(';')[0]
csrf = re.search(rb'name="csrf_token"\s+value="([^"]+)"', body).group(1).decode()
status, headers, _ = request('public', '/register.php', {'csrf_token': 'invalid'}, cookie)
check(status == 302 and headers.get('Location') == 'register.php', 'public registration retains CSRF rejection')
fields = dict(csrf_token=csrf, last_name='SyntheticFixture', first_name='Aminata', gender='Féminin', status='ETUDIANT', phone='771234567', email='member@example.invalid', consent_privacy='1')
boundary = 'SyntheticAMEABoundary'
parts = [f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode() for key, value in fields.items()]
parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="photo"; filename="synthetic.pdf"\r\nContent-Type: application/pdf\r\n\r\n%PDF-1.4\nSynthetic private document\n%%EOF\n\r\n--{boundary}--\r\n'.encode())
status, headers, _ = request('public', '/register.php', b''.join(parts), cookie, 'multipart/form-data; boundary=' + boundary)
check(status == 302 and headers.get('Location') == 'registration-details.php', 'MySQL public registration reaches review')
status, _, body = request('public', '/registration-preview.php?type=identite', cookie=cookie)
check(status == 200 and b'Synthetic private document' in body, 'owner previews staged document on public app')
check(request('public', '/registration-preview.php?type=identite')[0] == 404, 'foreign session cannot preview staged document')
status, _, body = request('public', '/registration-details.php', cookie=cookie)
csrf = re.search(rb'name="csrf_token"\s+value="([^"]+)"', body).group(1).decode()
status, headers, body = request('public', '/registration-details.php', {'csrf_token': csrf}, cookie)
if '--legacy-enums' in sys.argv:
    check(not (status == 302 and headers.get('Location') == '/'), 'legacy schema rejects French member confirmation before migration')
    raise SystemExit(0)
if status != 302: print(body.decode())
check(status == 302 and headers.get('Location') == '/', 'MySQL confirmation saves French enum member')
status, _, body = request('public', '/', cookie=cookie)
check(status == 200 and 'en cours de révision'.encode() in body, 'MySQL confirmation reports success')
check(request('public', '/uploads/students/synthetic.pdf')[0] == 404, 'final private document directory unavailable publicly')
