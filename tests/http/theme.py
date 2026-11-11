#!/usr/bin/env python3
"""Rendered theme checks on disposable loopback apps with synthetic data only."""
import os
import subprocess
from harness import ROOT, disposable_apps

with disposable_apps('public', 'staff', extra_fixtures=('token-fixture.php', 'theme-fixture.php')) as (public, staff):
    subprocess.run(['node', str(ROOT / 'tests/http/theme-browser.mjs'), public.base,
                    os.environ.get('AMEA_THEME_SCREENSHOTS', str(ROOT / 'docs/testing/screenshots/theme')),
                    staff.base], check=True, timeout=240)
