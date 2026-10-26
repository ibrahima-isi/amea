#!/usr/bin/env python3
"""Check independent-image template compilation in an owned rehearsal project."""
import pathlib
import re
import subprocess
import sys

project = sys.argv[1]
if not re.fullmatch(r'amea-ui-test-[a-z0-9-]+', project):
    raise RuntimeError('Only an owned amea-ui-test-* project is accepted')
root = pathlib.Path(__file__).resolve().parents[2]
compose = ['docker', 'compose', '-p', project, '-f', str(root / 'tests/apps/compose.yaml')]
template = 'cache-isolation-rehearsal.html.twig'

def php(service, code, *args, user='www-data'):
    return subprocess.check_output(compose + ['exec', '-T', '-u', user, service,
                                             'php', '-r', code, *args], cwd=root).decode()

def render(service):
    return php(service, 'require "vendor/autoload.php"; echo (new Amea\\Core\\View(getcwd()))->render($argv[1]);', template)

try:
    # Same absolute path and Twig key, different source in two independent images.
    # Dates deliberately make the shared compiled candidate newer than old source.
    php('public', 'file_put_contents("templates/".$argv[1], "Synthetic old image"); touch("templates/".$argv[1], time()-120);', template, user='root')
    php('staff', 'file_put_contents("templates/".$argv[1], "Synthetic new image"); touch("templates/".$argv[1], time()+2);', template, user='root')
    if render('public') != 'Synthetic old image':
        raise AssertionError('Old image must initially render its own template')
    if render('staff') != 'Synthetic new image':
        raise AssertionError('New image must render its own template')
    if render('public') != 'Synthetic old image':
        raise AssertionError('Rollback image reused a template compiled by the newer image')
    print('PASS: independent and rollback images render their own compiled templates')
finally:
    for service in ['public', 'staff']:
        php(service, '@unlink("templates/".$argv[1]);', template, user='root')
