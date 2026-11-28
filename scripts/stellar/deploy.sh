#!/usr/bin/env bash
# Atomic release installer, verifier, guarded Doctrine migration runner, and
# rollback helper for Namecheap Stellar split deployments.
#
# Never prints secret values. Refuses to deploy while the file write gate is
# paused for a cutover. Retains at least the previous compatible release.

set -euo pipefail
umask 077

MODE="deploy"
BASE_DIR=""
PUBLIC_DOCROOT=""
STAFF_DOCROOT=""
ARCHIVE=""
CONFIG_SOURCE=""
RELEASE_ID=""
PHP_CLI="${PHP_CLI:-php}"
KEEP_RELEASES="${KEEP_RELEASES:-3}"
SKIP_MIGRATIONS="0"
STELLAR_PHP_HANDLER="0"
STAGING_AUTH_FILE=""

usage() {
    cat >&2 <<'EOF'
Usage:
  deploy.sh --base-dir=DIR --public-docroot=DIR --staff-docroot=DIR --archive=FILE --config-source=FILE --release-id=ID [--php-cli=BIN] [--stellar-php-handler] [--staging-auth-file=FILE] [--keep-releases=N]
  deploy.sh --rollback --base-dir=DIR --public-docroot=DIR --staff-docroot=DIR [--php-cli=BIN] [--stellar-php-handler] [--staging-auth-file=FILE]
EOF
    exit 2
}

for arg in "$@"; do
    case "$arg" in
        --rollback) MODE="rollback" ;;
        --skip-migrations) SKIP_MIGRATIONS="1" ;;
        --stellar-php-handler) STELLAR_PHP_HANDLER="1" ;;
        --staging-auth-file=*) STAGING_AUTH_FILE="${arg#*=}" ;;
        --base-dir=*) BASE_DIR="${arg#*=}" ;;
        --public-docroot=*) PUBLIC_DOCROOT="${arg#*=}" ;;
        --staff-docroot=*) STAFF_DOCROOT="${arg#*=}" ;;
        --archive=*) ARCHIVE="${arg#*=}" ;;
        --config-source=*) CONFIG_SOURCE="${arg#*=}" ;;
        --release-id=*) RELEASE_ID="${arg#*=}" ;;
        --php-cli=*) PHP_CLI="${arg#*=}" ;;
        --keep-releases=*) KEEP_RELEASES="${arg#*=}" ;;
        *) usage ;;
    esac
done

[[ -n "$BASE_DIR" && -n "$PUBLIC_DOCROOT" && -n "$STAFF_DOCROOT" ]] || usage
[[ "$PUBLIC_DOCROOT" != "$STAFF_DOCROOT" ]] || { echo "Public and staff document roots must be distinct." >&2; exit 2; }
[[ "$KEEP_RELEASES" =~ ^[0-9]+$ ]] && (( KEEP_RELEASES >= 2 )) || { echo "KEEP_RELEASES must be >= 2." >&2; exit 2; }

if [[ -n "$STAGING_AUTH_FILE" ]]; then
    [[ "$STAGING_AUTH_FILE" =~ ^/[A-Za-z0-9_./-]+$ && -f "$STAGING_AUTH_FILE" && ! -L "$STAGING_AUTH_FILE" ]] || {
        echo "Staging authentication requires a private password file." >&2
        exit 2
    }
    "$PHP_CLI" -r '
        $auth = realpath($argv[1]);
        foreach (array_slice($argv, 2) as $docroot) {
            $root = realpath($docroot);
            if ($root === false || str_starts_with($auth, $root . "/")) { exit(1); }
        }
        exit((fileperms($auth) & 0022) === 0 ? 0 : 1);
    ' "$STAGING_AUTH_FILE" "$PUBLIC_DOCROOT" "$STAFF_DOCROOT" || {
        echo "Staging password file must be outside both roots and writable only by its owner." >&2
        exit 2
    }
fi

RELEASES_DIR="${BASE_DIR}/releases"
SHARED_DIR="${BASE_DIR}/shared"
CONFIG_DIR="${SHARED_DIR}/config"
CONFIG_FILE="${CONFIG_DIR}/runtime.env"
STATE_DIR="${SHARED_DIR}/state"
BIN_DIR="${BASE_DIR}/bin"
CURRENT_LINK="${BASE_DIR}/current"
PREVIOUS_LINK="${BASE_DIR}/previous"

# Cutover safety gate: refuse any deployment or rollback while writes are paused.
if [[ -e "${STATE_DIR}/write-gate/paused.json" || -L "${STATE_DIR}/write-gate/paused.json" ]]; then
    echo "Deployment blocked: file write gate is paused for a cutover." >&2
    exit 1
fi

mkdir -p "$RELEASES_DIR" "$CONFIG_DIR" "$STATE_DIR" "$BIN_DIR" \
    "${STATE_DIR}/sessions/public" \
    "${STATE_DIR}/sessions/staff" \
    "${STATE_DIR}/cache/twig/public" \
    "${STATE_DIR}/cache/twig/staff" \
    "${STATE_DIR}/cache/app" \
    "${STATE_DIR}/write-gate" \
    "${STATE_DIR}/mail-queue" \
    "${STATE_DIR}/logs" \
    "$PUBLIC_DOCROOT" "$STAFF_DOCROOT"

chmod 700 "$CONFIG_DIR" "$STATE_DIR" "$BIN_DIR" \
    "${STATE_DIR}/sessions" \
    "${STATE_DIR}/sessions/public" \
    "${STATE_DIR}/sessions/staff" \
    "${STATE_DIR}/write-gate"

atomic_symlink() {
    local target="$1"
    local link_path="$2"
    "$PHP_CLI" -r '
        $target = $argv[1];
        $link = $argv[2];
        $tmp = $link . ".tmp." . getmypid() . "." . bin2hex(random_bytes(4));
        if (!symlink($target, $tmp)) {
            fwrite(STDERR, "Unable to create temporary symlink.\n");
            exit(1);
        }
        if (!rename($tmp, $link)) {
            @unlink($tmp);
            fwrite(STDERR, "Unable to atomically replace symlink.\n");
            exit(1);
        }
    ' "$target" "$link_path"
}

sync_docroot_assets() {
    local release_dir="$1"
    local docroot="$2"
    local role="$3"
    local staging_assets="${docroot}/assets.new.$$"
    local old_assets="${docroot}/assets.old.$$"

    rm -rf "$staging_assets" "$old_assets"
    mkdir -p "$staging_assets"
    cp -R "${release_dir}/assets/." "$staging_assets/"
    chmod -R a+rX "$staging_assets"

    if [[ -d "${docroot}/assets" ]]; then
        mv "${docroot}/assets" "$old_assets"
    fi
    mv "$staging_assets" "${docroot}/assets"
    rm -rf "$old_assets"

    local htaccess_tmp="${docroot}/.htaccess.new.$$"
    {
        if [[ "$STELLAR_PHP_HANDLER" == "1" ]]; then
            printf '%s\n' '<FilesMatch "\.php$">' '    SetHandler application/x-httpd-alt-php85___lsphp' '</FilesMatch>'
        fi
        if [[ -n "$STAGING_AUTH_FILE" ]]; then
            printf '%s\n' 'AuthType Basic' 'AuthName "AMEA staging"' \
                "AuthUserFile $STAGING_AUTH_FILE" 'Require valid-user'
        fi
        cat "${release_dir}/scripts/stellar/htaccess.template"
        if [[ "$role" == public ]]; then
            cat <<'HTACCESS'

# Preserve origin validators for the public legal notice.
<IfModule mod_headers.c>
    SetEnvIf Request_URI "^/legal-notice[.]php$" AMEA_LEGAL_NO_TRANSFORM=1
    Header always merge Cache-Control "no-transform" env=AMEA_LEGAL_NO_TRANSFORM
</IfModule>
HTACCESS
        fi
    } > "$htaccess_tmp"
    chmod 644 "$htaccess_tmp"
    mv -f "$htaccess_tmp" "${docroot}/.htaccess"
}

stage_docroot_entry() {
    local role="$1"
    local release_dir="$2"
    local docroot="$3"
    local entry_tmp="${docroot}/index.php.new.$$"

    "$PHP_CLI" "${release_dir}/scripts/stellar/render-entry.php" \
        --role="$role" \
        --release-root="$release_dir" \
        --config="$CONFIG_FILE" \
        --state-dir="$STATE_DIR" \
        --output="$entry_tmp"
    "$PHP_CLI" -l "$entry_tmp" >/dev/null
    echo "$entry_tmp"
}

install_job_wrapper() {
    local wrapper_tmp="${BIN_DIR}/run-job.php.new.$$"
    "$PHP_CLI" -r '
        $current = $argv[1];
        $config = $argv[2];
        $state = $argv[3];
        $out = $argv[4];
        $exp = static fn (string $v): string => "\x27" . str_replace(["\\", "\x27"], ["\\\\", "\\\x27"], $v) . "\x27";
        $code = "<?php\n"
            . "declare(strict_types=1);\n"
            . "if (PHP_SAPI !== \x27cli\x27) { http_response_code(404); exit(1); }\n"
            . "define(\x27AMEA_APP\x27, \x27jobs\x27);\n"
            . "define(\x27AMEA_CONFIG_FILE\x27, " . $exp($config) . ");\n"
            . "define(\x27AMEA_STATE_DIR\x27, " . $exp($state) . ");\n"
            . "putenv(\x27AMEA_APP=jobs\x27);\n"
            . "putenv(\x27AMEA_CONFIG_FILE=\x27 . AMEA_CONFIG_FILE);\n"
            . "putenv(\x27AMEA_STATE_DIR=\x27 . AMEA_STATE_DIR);\n"
            . "require " . $exp($current) . " . \x27/scripts/run-shared-jobs.php\x27;\n";
        if (file_put_contents($out, $code) === false) { exit(1); }
        chmod($out, 0700);
    ' "$CURRENT_LINK" "$CONFIG_FILE" "$STATE_DIR" "$wrapper_tmp"
    "$PHP_CLI" -l "$wrapper_tmp" >/dev/null
    mv -f "$wrapper_tmp" "${BIN_DIR}/run-job.php"
}

promote_release() {
    local target_release="$1"

    sync_docroot_assets "$target_release" "$PUBLIC_DOCROOT" public
    sync_docroot_assets "$target_release" "$STAFF_DOCROOT" staff

    local public_entry staff_entry
    public_entry="$(stage_docroot_entry public "$target_release" "$PUBLIC_DOCROOT")"
    staff_entry="$(stage_docroot_entry staff "$target_release" "$STAFF_DOCROOT")"

    local previous_target=""
    if [[ -L "$CURRENT_LINK" ]]; then
        previous_target="$("$PHP_CLI" -r 'echo realpath($argv[1]) ?: "";' "$CURRENT_LINK")"
    fi

    mv -f "$public_entry" "${PUBLIC_DOCROOT}/index.php"
    mv -f "$staff_entry" "${STAFF_DOCROOT}/index.php"
    atomic_symlink "$target_release" "$CURRENT_LINK"
    install_job_wrapper

    if [[ -n "$previous_target" && "$previous_target" != "$target_release" && -d "$previous_target" ]]; then
        atomic_symlink "$previous_target" "$PREVIOUS_LINK"
    fi

    # Best-effort post-promotion origin + Cloudflare edge cache purge (no-op if Cloudflare token is unset).
    local helper="${target_release}/scripts/cloudflare-cache.php"
    if [[ ! -f "$helper" && -n "$previous_target" ]]; then
        helper="${previous_target}/scripts/cloudflare-cache.php"
    fi
    if [[ -f "$helper" ]]; then
        AMEA_CONFIG_FILE="$CONFIG_FILE" AMEA_STATE_DIR="$STATE_DIR" \
            "$PHP_CLI" "$helper" purge --all >/dev/null 2>&1 || \
            echo "Warning: post-promotion cache purge returned non-zero (non-fatal)." >&2
    fi
}

run_guarded_migrations() {
    local release_dir="$1"
    local config_path="$2"
    # Load only DB_* keys from the staged runtime config into a subshell without echoing values.
    "$PHP_CLI" -r '
        $config = $argv[1];
        $release = $argv[2];
        $php = $argv[3];
        $allowed = ["DB_HOST", "DB_PORT", "DB_NAME", "DB_USER", "DB_PASS"];
        $env = array_intersect_key($_SERVER + getenv(), array_flip(["PATH", "HOME", "TMPDIR", "TMP", "TEMP", "PHP_INI_SCAN_DIR", "AMEA_SCHEMA_BACKUP_CONFIRMED"]));
        foreach (file($config, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === "" || str_starts_with($trimmed, "#") || !str_contains($line, "=")) {
                continue;
            }
            [$k, $v] = explode("=", $line, 2);
            $k = trim($k);
            if (in_array($k, $allowed, true)) {
                $env[$k] = trim($v);
            }
        }
        $proc = proc_open([$php, $release . "/scripts/migrate.php", "migrate"], [STDIN, STDOUT, STDERR], $pipes, $release, $env);
        exit(is_resource($proc) ? proc_close($proc) : 1);
    ' "$config_path" "$release_dir" "$PHP_CLI"
}

if [[ "$MODE" == "rollback" ]]; then
    if [[ ! -L "$PREVIOUS_LINK" ]]; then
        echo "Rollback failed: no previous release link found." >&2
        exit 1
    fi
    PREVIOUS_RELEASE="$("$PHP_CLI" -r 'echo realpath($argv[1]) ?: "";' "$PREVIOUS_LINK")"
    if [[ -z "$PREVIOUS_RELEASE" || ! -d "$PREVIOUS_RELEASE" ]]; then
        echo "Rollback failed: previous release directory is missing." >&2
        exit 1
    fi

    "$PHP_CLI" "${PREVIOUS_RELEASE}/scripts/stellar/verify-release.php" \
        --release-root="$PREVIOUS_RELEASE" \
        --config="$CONFIG_FILE" \
        --state-dir="$STATE_DIR" \
        --public-docroot="$PUBLIC_DOCROOT" \
        --staff-docroot="$STAFF_DOCROOT"

    promote_release "$PREVIOUS_RELEASE"
    echo "Rollback complete: active release is $(basename "$PREVIOUS_RELEASE")"
    exit 0
fi

# Deploy mode
[[ -n "$ARCHIVE" && -f "$ARCHIVE" && -n "$CONFIG_SOURCE" && -f "$CONFIG_SOURCE" && -n "$RELEASE_ID" ]] || usage
[[ "$RELEASE_ID" =~ ^[A-Za-z0-9._-]{1,64}$ ]] || { echo "Invalid release ID." >&2; exit 2; }

RELEASE_DIR="${RELEASES_DIR}/${RELEASE_ID}"
if [[ -e "$RELEASE_DIR" ]]; then
    echo "Release directory already exists: ${RELEASE_DIR}" >&2
    exit 1
fi

STAGED_CONFIG="${CONFIG_DIR}/.runtime.env.staged.$$"
PROMOTED="0"
cleanup_on_failure() {
    rm -f "$STAGED_CONFIG"
    if [[ "$PROMOTED" != "1" && -d "$RELEASE_DIR" ]]; then
        rm -rf "$RELEASE_DIR"
    fi
}
trap cleanup_on_failure EXIT

# 1. Stage private runtime configuration (0600, outside both docroots) without touching live config yet.
cp "$CONFIG_SOURCE" "$STAGED_CONFIG"
chmod 600 "$STAGED_CONFIG"
[[ -s "$STAGED_CONFIG" ]] || { echo "Runtime config source is empty." >&2; exit 1; }

# 2. Unpack secret-free release archive into a new immutable release directory.
mkdir -p "$RELEASE_DIR"
chmod 755 "$RELEASE_DIR"
tar -C "$RELEASE_DIR" -xzf "$ARCHIVE"

# 3. Verify platform, staged config load for public/staff/jobs, route isolation, and docroot hygiene.
"$PHP_CLI" "${RELEASE_DIR}/scripts/stellar/verify-release.php" \
    --release-root="$RELEASE_DIR" \
    --config="$STAGED_CONFIG" \
    --state-dir="$STATE_DIR" \
    --public-docroot="$PUBLIC_DOCROOT" \
    --staff-docroot="$STAFF_DOCROOT"

# 4. Run Doctrine migrations as their own guarded stage before promoting config or docroots.
if [[ "$SKIP_MIGRATIONS" != "1" ]]; then
    run_guarded_migrations "$RELEASE_DIR" "$STAGED_CONFIG"
fi

# 5. Atomically install private runtime config and promote both document roots + current/previous links.
mv -f "$STAGED_CONFIG" "$CONFIG_FILE"
promote_release "$RELEASE_DIR"
PROMOTED="1"
trap - EXIT

# 6. Retain current + previous compatible release (minimum KEEP_RELEASES).
ACTIVE_REAL="$("$PHP_CLI" -r 'echo realpath($argv[1]) ?: "";' "$CURRENT_LINK")"
PREV_REAL=""
if [[ -L "$PREVIOUS_LINK" ]]; then
    PREV_REAL="$("$PHP_CLI" -r 'echo realpath($argv[1]) ?: "";' "$PREVIOUS_LINK")"
fi

"$PHP_CLI" -r '
    $dir = $argv[1];
    $keep = (int) $argv[2];
    $protected = array_filter([$argv[3], $argv[4]]);
    $entries = [];
    foreach (scandir($dir) ?: [] as $name) {
        if ($name === "." || $name === "..") continue;
        $full = realpath($dir . "/" . $name);
        if ($full !== false && is_dir($full)) {
            $entries[$full] = filemtime($full);
        }
    }
    arsort($entries);
    $kept = 0;
    foreach (array_keys($entries) as $path) {
        if (in_array($path, $protected, true) || $kept < $keep) {
            $kept++;
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }
' "$RELEASES_DIR" "$KEEP_RELEASES" "$ACTIVE_REAL" "$PREV_REAL"

echo "Deploy complete: active release is ${RELEASE_ID}"
