#!/usr/bin/env python3
"""Compare new Doctrine migration versions on both sides of a Git merge base."""

import argparse
from pathlib import Path
import re
import subprocess
import sys


MIGRATIONS = 'src/Migration/Version'
VERSION = re.compile(r'Version\d{14}\.php')


def git(*args):
    return subprocess.check_output(['git', *args], cwd=ROOT)


def commit(ref):
    return git('rev-parse', '--verify', '--end-of-options', ref + '^{commit}').decode().strip()


def added_since(base, *refs):
    return set(git('diff', '--name-only', '-z', '--no-renames', '--diff-filter=A',
                   base, *refs, '--', MIGRATIONS).decode().split('\0'))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('upstream', nargs='?', default='origin/main')
    parser.add_argument('head', nargs='?', help='Commit to check. Defaults to the working tree.')
    args = parser.parse_args()
    upstream = commit(args.upstream)
    head = commit(args.head or 'HEAD')
    base = git('merge-base', upstream, head).decode().strip()
    upstream_added = added_since(base, upstream)
    if args.head:
        proposed = added_since(base, head)
    else:
        untracked = git('ls-files', '--others', '--exclude-standard', '-z', '--', MIGRATIONS)
        proposed = added_since(base) | set(untracked.decode().split('\0'))

    collisions = []
    for path in sorted(upstream_added & proposed):
        if not VERSION.fullmatch(Path(path).name):
            continue
        contents = git('show', f'{head}:{path}') if args.head else (ROOT / path).read_bytes()
        if contents != git('show', f'{upstream}:{path}'):
            collisions.append(path)
    if collisions:
        print('Doctrine versions assigned independently on both branches:', file=sys.stderr)
        for path in collisions:
            print(f'  {path}', file=sys.stderr)
        print('Integrate the upstream branch and renumber your new migrations before qualification.', file=sys.stderr)
        return 1
    print('Doctrine migration versions are compatible with the upstream branch.')
    return 0


if __name__ == '__main__':
    ROOT = Path(__file__).resolve().parents[1]
    sys.exit(main())
