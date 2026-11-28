#!/usr/bin/env bash
# Pinned-SSH publication and rollback driver for Namecheap Stellar split releases.
#
# Shared by GitHub Actions (staging and production environments) and local
# operator qualification runs. Never prints secret values and always cleans up
# temporary local credentials and remote staging directories.

set -euo pipefail
set +x
umask 077

MODE="deploy"
ARCHIVE=""
CONFIG_SOURCE=""
RELEASE_ID=""
PUBLIC_URL=""
STAFF_URL=""
BASIC_AUTH_ENV=""
LEGAL_ETAG_FLAG=""
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY_SCRIPT="${SCRIPT_DIR}/deploy.sh"

usage() {
    cat >&2 <<'EOF'
Usage:
  publish.sh --mode=deploy --archive=FILE --config-source=FILE --release-id=ID
  publish.sh --mode=rollback
  publish.sh --mode=verify-http --public-url=URL --staff-url=URL [--basic-auth-env=VAR] [--check-legal-etags]
EOF
    exit 2
}

for arg in "$@"; do
    case "$arg" in
        --mode=*) MODE="${arg#*=}" ;;
        --archive=*) ARCHIVE="${arg#*=}" ;;
        --config-source=*) CONFIG_SOURCE="${arg#*=}" ;;
        --release-id=*) RELEASE_ID="${arg#*=}" ;;
        --public-url=*) PUBLIC_URL="${arg#*=}" ;;
        --staff-url=*) STAFF_URL="${arg#*=}" ;;
        --check-legal-etags) LEGAL_ETAG_FLAG=" --check-legal-etags" ;;
        --basic-auth-env=*) BASIC_AUTH_ENV="${arg#*=}" ;;
        *) usage ;;
    esac
done

[[ "$MODE" == "deploy" || "$MODE" == "rollback" || "$MODE" == "verify-http" ]] || usage
[[ -z "$LEGAL_ETAG_FLAG" || "$MODE" == "verify-http" ]] || usage

for required_var in STELLAR_SSH_HOST STELLAR_SSH_USER STELLAR_BASE_DIR STELLAR_PUBLIC_DOCROOT STELLAR_STAFF_DOCROOT; do
    if [[ -z "${!required_var:-}" ]]; then
        echo "Missing required environment variable: ${required_var}" >&2
        exit 2
    fi
done

[[ "${STELLAR_PUBLIC_DOCROOT}" != "${STELLAR_STAFF_DOCROOT}" ]] || {
    echo "STELLAR_PUBLIC_DOCROOT and STELLAR_STAFF_DOCROOT must be distinct." >&2
    exit 2
}

SSH_PORT="${STELLAR_SSH_PORT:-21098}"
[[ "$SSH_PORT" =~ ^[0-9]+$ ]] || {
    echo "STELLAR_SSH_PORT must be numeric." >&2
    exit 2
}

PHP_BIN="${STELLAR_PHP_CLI:-/opt/alt/php85/usr/bin/php}"
PHP_INI_SCAN="${STELLAR_PHP_INI_SCAN_DIR:-}"
PHP_HANDLER="${STELLAR_PHP_HANDLER:-1}"
STAGING_AUTH_FILE="${STELLAR_STAGING_AUTH_FILE:-}"

if [[ "$MODE" == "deploy" ]]; then
    [[ -n "$ARCHIVE" && -f "$ARCHIVE" && -n "$CONFIG_SOURCE" && -f "$CONFIG_SOURCE" && -n "$RELEASE_ID" ]] || usage
    [[ "$RELEASE_ID" =~ ^[A-Za-z0-9._-]{1,64}$ ]] || { echo "Invalid --release-id format." >&2; exit 2; }
    [[ -f "$DEPLOY_SCRIPT" ]] || { echo "Missing deploy.sh alongside publish.sh." >&2; exit 1; }
elif [[ "$MODE" == "verify-http" ]]; then
    [[ -n "$PUBLIC_URL" && -n "$STAFF_URL" ]] || usage
    if [[ -n "$BASIC_AUTH_ENV" ]]; then
        [[ "$BASIC_AUTH_ENV" =~ ^[A-Za-z_][A-Za-z0-9_]*$ ]] || {
            echo "Invalid --basic-auth-env variable name." >&2
            exit 2
        }
        if [[ -z "${!BASIC_AUTH_ENV:-}" ]]; then
            echo "Environment variable ${BASIC_AUTH_ENV} is required when --basic-auth-env is specified." >&2
            exit 2
        fi
    fi
fi

TMP_SSH_DIR="$(mktemp -d "${TMPDIR:-/tmp}/amea-stellar-ssh.XXXXXX")"
KEY_FILE=""
KNOWN_HOSTS_FILE=""

cleanup() {
    rm -rf "${TMP_SSH_DIR}"
}
trap cleanup EXIT

if [[ -n "${STELLAR_SSH_KEY_FILE:-}" ]]; then
    [[ -f "${STELLAR_SSH_KEY_FILE}" ]] || { echo "STELLAR_SSH_KEY_FILE does not exist." >&2; exit 2; }
    KEY_FILE="${STELLAR_SSH_KEY_FILE}"
elif [[ -n "${STELLAR_SSH_PRIVATE_KEY:-}" ]]; then
    KEY_FILE="${TMP_SSH_DIR}/deploy_key"
    printf '%s\n' "${STELLAR_SSH_PRIVATE_KEY}" > "${KEY_FILE}"
    chmod 600 "${KEY_FILE}"
else
    echo "Either STELLAR_SSH_PRIVATE_KEY or STELLAR_SSH_KEY_FILE is required." >&2
    exit 2
fi

if [[ -n "${STELLAR_SSH_KNOWN_HOSTS_FILE:-}" ]]; then
    [[ -f "${STELLAR_SSH_KNOWN_HOSTS_FILE}" ]] || { echo "STELLAR_SSH_KNOWN_HOSTS_FILE does not exist." >&2; exit 2; }
    KNOWN_HOSTS_FILE="${STELLAR_SSH_KNOWN_HOSTS_FILE}"
elif [[ -n "${STELLAR_SSH_KNOWN_HOSTS:-}" ]]; then
    KNOWN_HOSTS_FILE="${TMP_SSH_DIR}/known_hosts"
    printf '%s\n' "${STELLAR_SSH_KNOWN_HOSTS}" > "${KNOWN_HOSTS_FILE}"
    chmod 600 "${KNOWN_HOSTS_FILE}"
else
    echo "Pinned host verification requires STELLAR_SSH_KNOWN_HOSTS or STELLAR_SSH_KNOWN_HOSTS_FILE." >&2
    exit 2
fi

SSH_COMMON_OPTS=(
    -i "${KEY_FILE}"
    -o BatchMode=yes
    -o StrictHostKeyChecking=yes
    -o UserKnownHostsFile="${KNOWN_HOSTS_FILE}"
    -o IdentitiesOnly=yes
)
SSH_OPTS=(-p "${SSH_PORT}" "${SSH_COMMON_OPTS[@]}")
SCP_OPTS=(-P "${SSH_PORT}" "${SSH_COMMON_OPTS[@]}")

q() {
    printf '%q' "$1"
}

REMOTE="${STELLAR_SSH_USER}@${STELLAR_SSH_HOST}"
REMOTE_STAGE=""

if [[ "$MODE" == "verify-http" ]]; then
    VERIFY_SCRIPT="${STELLAR_BASE_DIR}/current/scripts/stellar/verify-http.php"
    REMOTE_PREFIX="set -euo pipefail; umask 077;"
    if [[ -n "${PHP_INI_SCAN}" ]]; then
        REMOTE_PREFIX="${REMOTE_PREFIX} export PHP_INI_SCAN_DIR=$(q "${PHP_INI_SCAN}");"
    fi
    if [[ -n "${BASIC_AUTH_ENV}" ]]; then
        REMOTE_VERIFY="${REMOTE_PREFIX} IFS= read -r STELLAR_VERIFY_BASIC_AUTH; export STELLAR_VERIFY_BASIC_AUTH; $(q "${PHP_BIN}") $(q "${VERIFY_SCRIPT}") --public-url=$(q "${PUBLIC_URL}") --staff-url=$(q "${STAFF_URL}") --basic-auth-env=STELLAR_VERIFY_BASIC_AUTH${LEGAL_ETAG_FLAG}"
        printf '%s\n' "${!BASIC_AUTH_ENV}" | ssh "${SSH_OPTS[@]}" "${REMOTE}" "${REMOTE_VERIFY}"
    else
        REMOTE_VERIFY="${REMOTE_PREFIX} $(q "${PHP_BIN}") $(q "${VERIFY_SCRIPT}") --public-url=$(q "${PUBLIC_URL}") --staff-url=$(q "${STAFF_URL}")${LEGAL_ETAG_FLAG}"
        ssh "${SSH_OPTS[@]}" "${REMOTE}" "${REMOTE_VERIFY}" </dev/null
    fi
    exit 0
fi

if [[ "$MODE" == "deploy" ]]; then
    REMOTE_STAGE="${STELLAR_BASE_DIR}/tmp-deploy-${RELEASE_ID}"

    ssh "${SSH_OPTS[@]}" "${REMOTE}" "bash -s -- $(q "${REMOTE_STAGE}")" <<'PREP_EOF'
set -euo pipefail
umask 077
mkdir -p "$1"
PREP_EOF

    scp "${SCP_OPTS[@]}" "${ARCHIVE}" "${REMOTE}:${REMOTE_STAGE}/release.tar.gz"
    scp "${SCP_OPTS[@]}" "${CONFIG_SOURCE}" "${REMOTE}:${REMOTE_STAGE}/runtime.env"
    scp "${SCP_OPTS[@]}" "${DEPLOY_SCRIPT}" "${REMOTE}:${REMOTE_STAGE}/deploy.sh"
fi

REMOTE_CMD="bash -s -- $(q "${MODE}") $(q "${STELLAR_BASE_DIR}") $(q "${STELLAR_PUBLIC_DOCROOT}") $(q "${STELLAR_STAFF_DOCROOT}") $(q "${PHP_BIN}") $(q "${PHP_INI_SCAN}") $(q "${PHP_HANDLER}") $(q "${STAGING_AUTH_FILE}") $(q "${REMOTE_STAGE}") $(q "${RELEASE_ID}")"

ssh "${SSH_OPTS[@]}" "${REMOTE}" "${REMOTE_CMD}" <<'REMOTE_EOF'
set -euo pipefail
umask 077
MODE="$1"
BASE_DIR="$2"
PUBLIC_DOCROOT="$3"
STAFF_DOCROOT="$4"
PHP_BIN="$5"
PHP_INI_SCAN="$6"
PHP_HANDLER="$7"
STAGING_AUTH_FILE="$8"
REMOTE_STAGE="$9"
RELEASE_ID="${10}"

if [[ -n "${REMOTE_STAGE}" ]]; then
    trap 'rm -rf "${REMOTE_STAGE}"' EXIT
fi
if [[ -n "${PHP_INI_SCAN}" ]]; then
    export PHP_INI_SCAN_DIR="${PHP_INI_SCAN}"
fi

ARGS=(
    --base-dir="${BASE_DIR}"
    --public-docroot="${PUBLIC_DOCROOT}"
    --staff-docroot="${STAFF_DOCROOT}"
    --php-cli="${PHP_BIN}"
)
if [[ "${PHP_HANDLER}" == "1" ]]; then
    ARGS+=(--stellar-php-handler)
fi
if [[ -n "${STAGING_AUTH_FILE}" ]]; then
    ARGS+=(--staging-auth-file="${STAGING_AUTH_FILE}")
fi

if [[ "${MODE}" == "rollback" ]]; then
    DEPLOY_BIN="${BASE_DIR}/current/scripts/stellar/deploy.sh"
    if [[ ! -f "${DEPLOY_BIN}" ]]; then
        echo "Rollback failed: active release is missing scripts/stellar/deploy.sh." >&2
        exit 1
    fi
    bash "${DEPLOY_BIN}" --rollback "${ARGS[@]}"
    exit 0
fi

chmod 600 "${REMOTE_STAGE}/runtime.env"
chmod 700 "${REMOTE_STAGE}/deploy.sh"
"${REMOTE_STAGE}/deploy.sh" \
    --archive="${REMOTE_STAGE}/release.tar.gz" \
    --config-source="${REMOTE_STAGE}/runtime.env" \
    --release-id="${RELEASE_ID}" \
    "${ARGS[@]}"
REMOTE_EOF
