#!/usr/bin/env bash
# Real startup gates from both shipped Compose files, on disposable MariaDB only.
set -euo pipefail
cd "$(dirname "$0")/../.."
root="$PWD"
scratch=$(mktemp -d)
export DB_NAME=amea_doctrine_compose DB_USER=amea DB_PASS=synthetic-only
export MARIADB_ROOT_PASSWORD=synthetic-root-only APP_ENV=test
export PUBLIC_APP_URL=https://public.amea.test STAFF_APP_URL=https://staff.amea.test
export MAIL_USER=synthetic MAIL_PASS=synthetic
compose=()
cleanup() {
    result=$?
    if ((${#compose[@]})); then
        if [[ "$result" != 0 ]]; then "${compose[@]}" logs --tail 40; fi
        "${compose[@]}" --profile jobs down -v --remove-orphans
    fi
    rm -rf "$scratch"
}
trap cleanup EXIT

for stack in local dokploy; do
    project="amea-doctrine-test-${stack}-${RANDOM}-$$"
    [[ -z "$(docker ps -aq --filter label=com.docker.compose.project="$project")" ]]
    [[ -z "$(docker volume ls -q --filter label=com.docker.compose.project="$project")" ]]
    if [[ "$stack" == local ]]; then
        file=compose.yml; public=web; staff=backoffice
    else
        file=compose.dokploy.yml; public=public; staff=staff
    fi
    cat > "$scratch/override.yml" <<YAML
services:
  db:
    ports: !reset []
    healthcheck:
      interval: 1s
  $public:
    networks: !override [default]
  $staff:
    networks: !override [default]
networks:
  dokploy-network: !reset null
YAML
    compose=(docker compose -p "$project" -f "$file" -f "$scratch/override.yml")
    sql() { "${compose[@]}" exec -T db mariadb -uroot -psynthetic-root-only --batch --skip-column-names "$DB_NAME" -e "$1"; }
    expect_sql() {
        actual=$(sql "$1")
        [[ "$actual" == "$2" ]] || { echo "Unexpected SQL result: $actual (expected $2)" >&2; exit 1; }
    }
    stop_apps() { "${compose[@]}" --profile jobs rm -sf "$public" "$staff" jobs migrate; }
    blocked_start() {
        expected="$1"
        if "${compose[@]}" --profile jobs up -d "$public" "$staff" jobs > "$scratch/start.log" 2>&1; then
            cat "$scratch/start.log"; echo 'Failed migration allowed startup' >&2; exit 1
        fi
        "${compose[@]}" logs migrate > "$scratch/migrate.log"
        grep -Fq "$expected" "$scratch/migrate.log"
        id=$("${compose[@]}" ps -aq migrate)
        [[ "$(docker inspect --format '{{.State.ExitCode}}' "$id")" == 1 ]]
        for service in "$public" "$staff" jobs; do
            id=$("${compose[@]}" --profile jobs ps -aq "$service")
            [[ -n "$id" && "$(docker inspect --format '{{.State.Status}}' "$id")" == created ]]
        done
        echo "PASS: $stack migration failure blocks public, staff and jobs ($expected)"
    }
    probe() {
        "${compose[@]}" exec -T "$1" php -r '
            for($i=0;$i<60;$i++) {
                if(@file_get_contents($argv[1])!==false && str_contains($http_response_header[0],"200")) exit(0);
                usleep(200000);
            }
            exit(1);
        ' "$2"
    }
    "${compose[@]}" --profile jobs build "$public" "$staff" jobs migrate
    "${compose[@]}" --profile jobs up -d "$public" "$staff" jobs
    probe "$public" http://localhost/legal-notice.php
    probe "$staff" http://localhost/login.php
    "${compose[@]}" exec -T "$public" test ! -e /opt/amea/scripts
    "${compose[@]}" exec -T "$staff" test ! -e /opt/amea/scripts
    id=$("${compose[@]}" --profile jobs ps -aq jobs)
    [[ "$(docker inspect --format '{{.State.Status}}' "$id")" == running ]]
    expect_sql 'SELECT COUNT(*) FROM doctrine_migration_versions' 2
    expect_sql 'SELECT COUNT(*) FROM settings' 3
    expect_sql 'SELECT COUNT(*) FROM amea_migration_recovery' 0
    echo "PASS: $stack fresh Doctrine initialization starts both HTTP apps and jobs"
    sql "UPDATE settings SET setting_value='Preserved setting' WHERE setting_key='organization_name'"
    "${compose[@]}" run --rm -T migrate > "$scratch/noop.log" 2>&1
    grep -Fq 'Already at the latest version' "$scratch/noop.log"
    expect_sql "SELECT setting_value FROM settings WHERE setting_key='organization_name'" 'Preserved setting'
    echo "PASS: $stack second startup applies zero migrations and preserves settings"

    stop_apps
    sql 'DELETE FROM doctrine_migration_versions'
    blocked_start 'without a recorded baseline'
    cat > "$scratch/evidence.json" <<'JSON'
{"database":"amea_doctrine_compose","host":"db","port":3306,"source":"legacy-migrations","reference":"Disposable rehearsal initialized baseline, removed ledger only, preserved all application rows","kyc_backfill_verified":true}
JSON
    "${compose[@]}" run --rm -T -v "$scratch/evidence.json:/run/enrollment.json:ro" migrate php scripts/enroll-migrations.php --database="$DB_NAME" --writers-stopped --evidence=/run/enrollment.json
    "${compose[@]}" rm -f migrate
    "${compose[@]}" --profile jobs up -d "$public" "$staff" jobs
    "${compose[@]}" logs migrate > "$scratch/enrolled.log"
    grep -Fq 'Already at the latest version' "$scratch/enrolled.log"
    probe "$public" http://localhost/legal-notice.php
    probe "$staff" http://localhost/login.php
    expect_sql 'SELECT COUNT(*) FROM doctrine_migration_versions' 2
    expect_sql "SELECT setting_value FROM settings WHERE setting_key='organization_name'" 'Preserved setting'
    echo "PASS: $stack enrolled database starts with zero migrations"

    # The full command regressions use separate, named disposable databases.
    if [[ "$stack" == local ]]; then
        sql 'CREATE DATABASE amea_doctrine_fresh CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci; CREATE DATABASE amea_doctrine_baseline CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'
        "${compose[@]}" run --rm -T -v "$root:/opt/amea:ro" -e APP_ENV=test -e DB_USER=root -e DB_PASS=synthetic-root-only -e DB_NAME=amea_doctrine_fresh migrate php tests/test_doctrine_fresh_database.php
        for fixture in enrollment recovery; do
            sql "CREATE DATABASE amea_doctrine_${fixture} CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
            "${compose[@]}" run --rm -T -v "$root:/opt/amea:ro" -e APP_ENV=test -e DB_USER=root -e DB_PASS=synthetic-root-only -e DB_NAME="amea_doctrine_${fixture}" migrate php "tests/apps/doctrine-${fixture}.php"
        done
    fi

    stop_apps
    # Keep only the empty runner artifacts; fail the first version write after real DDL.
    tables=$(sql "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME NOT IN ('doctrine_migration_versions','amea_migration_recovery')")
    drop='SET FOREIGN_KEY_CHECKS=0;'
    while IFS= read -r table; do drop+="DROP TABLE \`$table\`;"; done <<< "$tables"
    sql "$drop DELETE FROM doctrine_migration_versions; SET FOREIGN_KEY_CHECKS=1; CREATE TRIGGER fail_version BEFORE INSERT ON doctrine_migration_versions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='injected Compose version failure'"
    blocked_start 'injected Compose version failure'
    expect_sql 'SELECT COUNT(*) FROM doctrine_migration_versions' 0
    expect_sql 'SELECT COUNT(*) FROM amea_migration_recovery' 1
    expect_sql "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='students'" 1
    echo "PASS: $stack failed version write retains DDL recovery marker and no version"
    stop_apps
    sql 'DROP TRIGGER fail_version'
    blocked_start 'Interrupted schema migration'
    "${compose[@]}" --profile jobs down -v --remove-orphans
    compose=()
done
