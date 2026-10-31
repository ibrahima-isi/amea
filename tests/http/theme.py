#!/usr/bin/env python3
"""Rendered theme checks on disposable loopback apps with synthetic data only."""
import os
import subprocess
from harness import ROOT, disposable_app

with disposable_app(extra_fixtures=('token-fixture.php',)) as public:
    with disposable_app() as other_origin:
        subprocess.run(['node', str(ROOT / 'tests/http/theme-browser.mjs'), public.base,
                        os.environ.get('AMEA_THEME_SCREENSHOTS', str(ROOT / 'docs/testing/screenshots/theme')),
                        other_origin.base], check=True, timeout=240)
