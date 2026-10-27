#!/usr/bin/env bash
# Ticket 07: fresh synthetic stack, no existing containers, no SMTP.
set -euo pipefail
cd "$(dirname "$0")/../.."
project="${AMEA_REHEARSAL_PROJECT:-amea-ui-test-07-$RANDOM-$$}"
[[ "$project" =~ ^amea-ui-test-07-[a-z0-9-]+$ ]] || exit 1
[[ -z "$(docker compose -p "$project" -f tests/apps/compose.yaml ps -aq)" ]] || { echo 'Project already exists'; exit 1; }
[[ -z "$(docker volume ls -q --filter label=com.docker.compose.project="$project")" ]] || { echo 'Project volumes already exist'; exit 1; }
export AMEA_PUBLIC_PORT="${AMEA_PUBLIC_PORT:-18445}" AMEA_STAFF_PORT="${AMEA_STAFF_PORT:-19445}"
compose=(docker compose -p "$project" -f tests/apps/compose.yaml)
trap '"${compose[@]}" down -v --remove-orphans' EXIT
"${compose[@]}" up -d --build
for migration in migrations/0[0-6]*.php; do "${compose[@]}" exec -T public php "$migration"; done
"${compose[@]}" exec -u www-data -T staff php < tests/apps/fixture.php
"${compose[@]}" exec -u www-data -T staff php < tests/apps/tools-fixture.php
python3 tests/apps/tools.py
if [[ "${AMEA_SKIP_BROWSER:-0}" != 1 ]]; then node tests/apps/tools-browser.mjs "${AMEA_SCREENSHOT_DIR:-/private/tmp/amea-tools-browser}"; fi
"${compose[@]}" --profile jobs build jobs
# Run only the synthetic queue proof, never the scheduler/purge loop.
"${compose[@]}" --profile jobs run --rm -T --no-deps jobs php /dev/stdin < tests/apps/jobs.php
