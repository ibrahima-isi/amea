#!/usr/bin/env bash
# Disposable synthetic infrastructure only. Override project name to avoid collisions.
set -euo pipefail
cd "$(dirname "$0")/../.."
project="${AMEA_REHEARSAL_PROJECT:-amea-ui-test-${RANDOM}-$$}"
[[ "$project" =~ ^amea-ui-test-[a-z0-9-]+$ ]] || { echo "Use a fresh amea-ui-test-* project" >&2; exit 1; }
[[ -z "$(docker ps -aq --filter label=com.docker.compose.project="$project")" ]] || { echo "Project already exists" >&2; exit 1; }
[[ -z "$(docker volume ls -q --filter label=com.docker.compose.project="$project")" ]] || { echo "Project volumes already exist" >&2; exit 1; }
compose=(docker compose -p "$project" -f tests/apps/compose.yaml)
cleanup() { result=$?; if [[ "$result" != 0 ]]; then "${compose[@]}" logs --tail 40 public staff; fi; "${compose[@]}" down -v --remove-orphans; }
trap cleanup EXIT
independent_probe() {
    "${compose[@]}" exec -T "$1" php -r '
        $context=stream_context_create(["http"=>["timeout"=>2]]);
        for($attempt=0;$attempt<100;$attempt++) {
            $body=@file_get_contents($argv[1],false,$context);
            if($body!==false && str_contains($http_response_header[0],"200")) {
                echo "PASS: ".$argv[2]." target starts without sibling\n"; exit(0);
            }
            usleep(200000);
        }
        fwrite(STDERR,"Independent target did not become ready\n"); exit(1);
    ' "$2" "$1"
}
# Build targets independently; no app requires its sibling to start.
"${compose[@]}" build public
"${compose[@]}" build staff
"${compose[@]}" --profile jobs build jobs
"${compose[@]}" up -d mysql public
for migration in 00_init_schema.php 01_migration_add_settings_table.php 02_migration_add_user_session_version.php 03_migration_fix_role_enum.php 04_migration_add_kyc_workflow.php 05_migration_add_soft_delete.php; do
    "${compose[@]}" exec -T public php "migrations/$migration"
done
independent_probe public http://localhost/legal-notice.php
"${compose[@]}" stop public
"${compose[@]}" up -d staff
independent_probe staff http://localhost/login.php
"${compose[@]}" up -d public tls
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
python3 tests/apps/visual-refresh.py --empty
python3 tests/apps/cache.py "$project"
"${compose[@]}" exec -T public php /dev/stdin before < tests/apps/migration-proof.php
python3 tests/apps/registration.py --legacy-enums
"${compose[@]}" exec -T public php migrations/06_expand_member_enum_compatibility.php
"${compose[@]}" exec -T public php migrations/06_expand_member_enum_compatibility.php
"${compose[@]}" exec -T public php /dev/stdin after < tests/apps/migration-proof.php
"${compose[@]}" exec -T public php migrations/07_create_association_journal.php
"${compose[@]}" exec -u www-data -T staff php < tests/apps/fixture.php
python3 tests/apps/routes.py
python3 tests/apps/links.py
python3 tests/apps/journeys.py
python3 tests/apps/registration.py
"${compose[@]}" exec -u www-data -T staff php < tests/apps/member-fixture.php
python3 tests/apps/visual-refresh.py --populated
python3 tests/apps/members.py
if [[ "${AMEA_SKIP_BROWSER:-0}" != "1" ]]; then
    node tests/apps/browser.mjs "${AMEA_SCREENSHOT_DIR:-/private/tmp/amea-app-browser}"
fi

"${compose[@]}" exec -u www-data -T staff php < tests/apps/tools-fixture.php
python3 tests/apps/visual-refresh.py --tools
python3 tests/apps/tools.py
"${compose[@]}" exec -u www-data -T staff php < tests/apps/journal-fixture.php
python3 tests/apps/journal.py
if [[ "${AMEA_SKIP_BROWSER:-0}" != "1" ]]; then
    node tests/apps/tools-browser.mjs "${AMEA_SCREENSHOT_DIR:-/private/tmp/amea-app-browser}"
fi
"${compose[@]}" --profile jobs run --rm -T --no-deps jobs php /dev/stdin < tests/apps/jobs.php
python3 tests/apps/release.py "$project"
