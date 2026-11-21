#!/usr/bin/env python3
"""Exercise the staff permission scenarios with disposable SQLite data."""
import subprocess

from harness import SAFE_ENV, disposable_apps

with disposable_apps('staff') as (staff,):
    subprocess.run(['php', 'tests/test_permissions_integration.php', staff.base],
                   cwd=staff.root, env=SAFE_ENV, check=True)
