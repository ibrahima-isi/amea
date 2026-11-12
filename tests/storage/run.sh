#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."

export STORAGE_TEST_PORT="${STORAGE_TEST_PORT:-62941}"
export STORAGE_TEST_SECONDARY_PORT="${STORAGE_TEST_SECONDARY_PORT:-62942}"
project="amea-storage-test-$$"
compose=(docker compose -p "$project" -f tests/storage/compose.yml --profile secondary)
cleanup() {
    result=$?
    if (( result != 0 )); then
        "${compose[@]}" logs --tail=80
    fi
    "${compose[@]}" down --volumes
    exit "$result"
}
trap cleanup EXIT
"${compose[@]}" up -d --wait --wait-timeout 90
for port in "$STORAGE_TEST_PORT" "$STORAGE_TEST_SECONDARY_PORT"; do
    ready=0
    for attempt in {1..60}; do
        status=$(curl --silent --output /dev/null --write-out '%{http_code}' "http://127.0.0.1:$port/" || true)
        if [[ "$status" == 200 || "$status" == 403 ]]; then
            ready=1
            break
        fi
        sleep 1
    done
    if (( ready == 0 )); then
        echo "Disposable S3 service did not become ready on port $port" >&2
        exit 1
    fi
done

./vendor/bin/phpunit --no-configuration --bootstrap tests/bootstrap.php tests/Unit/Storage --colors=never
STORAGE_TEST_ENDPOINT="http://127.0.0.1:$STORAGE_TEST_PORT" STORAGE_TEST_REGION=us-east-1 \
    ./vendor/bin/phpunit --no-configuration --bootstrap tests/bootstrap.php tests/Integration/Storage/S3StorageTest.php --colors=never
STORAGE_TEST_ENDPOINT="http://127.0.0.1:$STORAGE_TEST_SECONDARY_PORT" STORAGE_TEST_REGION=auto \
    ./vendor/bin/phpunit --no-configuration --bootstrap tests/bootstrap.php tests/Integration/Storage/S3StorageTest.php --colors=never
