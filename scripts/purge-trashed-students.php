<?php

/**
 * scripts/purge-trashed-students.php — permanently delete trashed students whose
 * retention window has elapsed (CLI only).
 *
 * Designed to run daily from cron, AFTER the database backup, so purged dossiers
 * still exist in at least one backup:
 *   0 3 * * * /usr/local/bin/php /path/to/scripts/purge-trashed-students.php >> /path/to/logs/purge.log 2>&1
 *
 * For each expired student it removes the uploaded files (identity document, CV)
 * and then hard-deletes the row. Retention is configurable via TRASH_RETENTION_DAYS
 * (default 30).
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    exit(1);
}

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/vendor/autoload.php';

// ─── Load .env ──────────────────────────────────────────────────────────────
if (is_file($projectRoot . '/.env')) {
    foreach (file($projectRoot . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (str_contains($line, '=') && !str_starts_with(ltrim($line), '#')) {
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

function logLine(string $message): void
{
    fwrite(STDOUT, '[' . date('c') . '] ' . $message . PHP_EOL);
}

$retentionDays = max(1, (int)($_ENV['TRASH_RETENTION_DAYS'] ?? getenv('TRASH_RETENTION_DAYS') ?: 30));

try {
    $pdo        = \Amea\Core\Container::db();
    $repo       = new \Amea\Repository\StudentRepository($pdo);
    $uploader   = new \Amea\Core\FileUploader($projectRoot);
} catch (\Throwable $e) {
    logLine('FATAL: cannot initialise database: ' . $e->getMessage());
    exit(1);
}

$purgeable = $repo->findPurgeable($retentionDays);

if ($purgeable === []) {
    logLine("Nothing to purge (retention {$retentionDays} days).");
    exit(0);
}

$deleted = 0;
$failed  = 0;

foreach ($purgeable as $student) {
    $id = $student->getId();
    try {
        if ($student->getIdentityDocument()) {
            $uploader->safeDelete($student->getIdentityDocument());
        }
        if ($student->getCvPath()) {
            $uploader->safeDelete($student->getCvPath());
        }
        if ($repo->forceDelete($id)) {
            $deleted++;
            logLine("Purged student #{$id} ({$student->getEmail()}), trashed at {$student->getDeletedAt()}.");
        } else {
            $failed++;
            logLine("WARN: could not purge student #{$id} (no longer trashed?).");
        }
    } catch (\Throwable $e) {
        $failed++;
        logLine("ERROR purging student #{$id}: " . $e->getMessage());
    }
}

logLine("Purge complete: deleted={$deleted} failed={$failed} retention={$retentionDays}d.");
exit(0);
