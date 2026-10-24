#!/usr/bin/env bash
# Disposable synthetic infrastructure only. Override project name to avoid collisions.
set -euo pipefail
cd "$(dirname "$0")/../.."
project="${AMEA_REHEARSAL_PROJECT:-amea-ui-test-${RANDOM}-$$}"
[[ "$project" =~ ^amea-ui-test-[a-z0-9-]+$ ]] || { echo "Use a fresh amea-ui-test-* project" >&2; exit 1; }
[[ -z "$(docker ps -aq --filter label=com.docker.compose.project="$project")" ]] || { echo "Project already exists" >&2; exit 1; }
[[ -z "$(docker volume ls -q --filter label=com.docker.compose.project="$project")" ]] || { echo "Project volumes already exist" >&2; exit 1; }
compose=(docker compose -p "$project" -f tests/apps/compose.yaml)
cleanup() { "${compose[@]}" down -v --remove-orphans; }
trap cleanup EXIT
"${compose[@]}" up -d --build
for migration in 00_init_schema.php 01_migration_add_settings_table.php 02_migration_add_user_session_version.php 03_migration_fix_role_enum.php 04_migration_add_kyc_workflow.php 05_migration_add_soft_delete.php; do
    "${compose[@]}" exec -T public php "migrations/$migration"
done
python3 - <<'PY'
import sys, time
sys.path.insert(0, 'tests/apps')
from routes import request
for attempt in range(60):
    try:
        if request('public', '/legal-notice.php')[0] == 200 and request('staff', '/login.php')[0] == 200:
            break
    except OSError:
        pass
    time.sleep(.5)
else:
    raise SystemExit('Isolated HTTPS services did not become ready')
PY
"${compose[@]}" exec -u www-data -T staff php < tests/apps/fixture.php
"${compose[@]}" exec -T public php /dev/stdin before < tests/apps/migration-proof.php
python3 tests/apps/registration.py --legacy-enums
"${compose[@]}" exec -T public php migrations/06_expand_member_enum_compatibility.php
"${compose[@]}" exec -T public php migrations/06_expand_member_enum_compatibility.php
"${compose[@]}" exec -T public php /dev/stdin after < tests/apps/migration-proof.php
"${compose[@]}" exec -u www-data -T staff php < tests/apps/fixture.php
python3 tests/apps/routes.py
python3 tests/apps/journeys.py
python3 tests/apps/registration.py
if [[ "${AMEA_SKIP_BROWSER:-0}" != "1" ]]; then
    node tests/apps/browser.mjs "${AMEA_SCREENSHOT_DIR:-/private/tmp/amea-app-browser}"
fi
