#!/usr/bin/env python3
"""Keep a disposable real PHP journal available for manual browser qualification."""
import argparse
from datetime import datetime, timedelta
import html
import json
import os
import re
import signal
import threading
from urllib.parse import urljoin
from zoneinfo import ZoneInfo

from harness import ROOT, client, disposable_app

PASSWORD = 'Fixture-password-123!'


def require(condition, message):
    if not condition:
        raise RuntimeError(message)


def csrf(fixture, browser, path):
    status, _, body = fixture.request(browser, path)
    token = re.search(rb'name="csrf_token"\s+value="([^"]+)"', body)
    require(status == 200 and token is not None, path + ' must expose its real CSRF form')
    return token.group(1).decode()


def seed_publications(fixture, archive_count):
    browser = client()
    token = csrf(fixture, browser, '/login.php')
    status, _, _ = fixture.request(browser, '/login.php', {
        'csrf_token': token, 'username': 'journal-admin', 'password': PASSWORD,
    })
    require(status == 302, 'Synthetic journal administrator must authenticate')
    now = datetime.now(ZoneInfo('Africa/Dakar'))
    day = lambda offset: (now + timedelta(days=offset)).strftime('%Y-%m-%d')
    publications = [
        ('Activité à venir', 'ACTIVITY', day(7), day(7)),
        ('Activité archivée', 'ACTIVITY', day(-7), day(-7)),
        ('Activité en cours', 'ACTIVITY', day(-1), day(1)),
        ('Compte rendu', 'REPORT', None, None),
        ('Annonce', 'ANNOUNCEMENT', None, None),
    ]
    publications.extend((f'Archive de pagination {number + 1:02d}', 'ACTIVITY', day(-14 - number), day(-14 - number))
                        for number in range(archive_count))
    image = (ROOT / 'assets/img/presidents.jpg').read_bytes()
    result = []
    for title, kind, start, end in publications:
        title = '[QA synthétique] ' + title
        values = {
            'csrf_token': csrf(fixture, browser, '/edit-journal.php'),
            '_action': 'publish', 'type': kind, 'title': title,
            'summary': 'Publication synthétique pour la qualification locale du journal.',
            'body': 'Premier paragraphe de qualification, sans données réelles.\n\nSecond paragraphe du journal de test.',
        }
        if start:
            values.update({'location': 'Dakar — lieu synthétique', 'activity_start_date': start, 'activity_end_date': end})
        with_cover = len(result) < 5
        if with_cover:
            values['cover_alt'] = 'Photographie publique du dépôt pour la qualification synthétique'
        status, headers, _ = fixture.request(browser, '/edit-journal.php', values, multipart=with_cover,
                                            upload=('presidents.jpg', 'cover', 'image/jpeg', image) if with_cover else None)
        location = headers.get('Location', '')
        require(status == 302 and re.fullmatch(r'edit-journal\.php\?id=\d+', location), 'Publication failed: ' + title)
        status, _, body = fixture.request(browser, '/' + location)
        article = re.search(rb'href="([^"]*journal-article\.php\?slug=[^"]+)"', body)
        require(status == 200 and article is not None, 'Saved editor must expose the stable article URL')
        result.append({'title': title, 'type': kind, 'editor': fixture.base + '/' + location,
                       'article': urljoin(fixture.base + '/', html.unescape(article.group(1).decode()))})
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--seed', action='store_true', help='Publish five synthetic type/date examples through real HTTP and CSRF')
    parser.add_argument('--archive-count', type=int, default=0, help='Extra archived activities for pagination (0–50; requires --seed)')
    args = parser.parse_args()
    if not 0 <= args.archive_count <= 50 or (args.archive_count and not args.seed):
        parser.error('--archive-count must be 0–50 and requires --seed when nonzero')

    stop = threading.Event()
    for signum in (signal.SIGINT, signal.SIGTERM):
        signal.signal(signum, lambda *_: stop.set())
    with disposable_app(extra_fixtures=('journal-fixture.php',)) as fixture:
        browser = client()
        status, _, listing = fixture.request(browser, '/journal.php')
        empty = status == 200 and b'Aucune publication dans cette rubrique pour le moment.' in listing
        status, _, home = fixture.request(browser, '/')
        omitted = status == 200 and b'journal-home' not in home
        require(empty and omitted, 'Fresh public journal must be empty and omitted from the homepage')
        publications = seed_publications(fixture, args.archive_count) if args.seed else []
        print(json.dumps({
            'state': 'ready', 'pid': os.getpid(), 'base': fixture.base,
            'journal': fixture.base + '/journal.php', 'login': fixture.base + '/login.php',
            'editor': fixture.base + '/edit-journal.php',
            'cover_file': str(ROOT / 'assets/img/presidents.jpg'),
            'accounts': {'journal-admin': 'admin + journal', 'journal-denied': 'admin without journal', 'journal-reader': 'ordinary user + journal'},
            'synthetic_password': PASSWORD,
            'before_seed': {'public_empty_state': empty, 'homepage_journal_omitted': omitted},
            'seeded': publications,
            'limits': ['Single-origin loopback HTTP with SQLite; no split-host routing proof.',
                       'Test router starts the session; no Secure-cookie or TLS proof.',
                       'No mail worker or real account/data; all files and sessions are disposable.'],
            'stop': 'Send SIGINT (Ctrl+C) or SIGTERM to this runner; its context stops PHP and removes the disposable copy.',
        }, ensure_ascii=False, indent=2), flush=True)
        stop.wait()
    print(json.dumps({'state': 'stopped', 'base': fixture.base, 'cleanup': 'Disposable PHP server and copy removed'}), flush=True)


if __name__ == '__main__':
    main()
