<?php

/**
 * scripts/purge-pending-uploads.php — remove abandoned registration uploads (CLI only).
 *
 * Files uploaded during registration are staged in uploads/students/pending/ and
 * only moved to their final location when the user confirms. If a visitor uploads
 * documents but never confirms, the staged files are left behind. This sweep
 * deletes staged files older than PENDING_UPLOAD_TTL_HOURS (default 24).
 *
 * Run from cron, e.g. hourly:
 *   0 * * * * /usr/local/bin/php /path/to/scripts/purge-pending-uploads.php >> /path/to/logs/purge.log 2>&1
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    exit(1);
}

$projectRoot = dirname(__DIR__);

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

$pendingDir = $projectRoot . '/uploads/students/pending';
if (!is_dir($pendingDir)) {
    logLine('No pending-uploads directory; nothing to do.');
    exit(0);
}

$ttlHours = max(1, (int)($_ENV['PENDING_UPLOAD_TTL_HOURS'] ?? getenv('PENDING_UPLOAD_TTL_HOURS') ?: 24));
$cutoff   = time() - ($ttlHours * 3600);

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pendingDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

$deleted = 0;
foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    if ($file->isFile() && $file->getMTime() < $cutoff) {
        if (@unlink($file->getPathname())) {
            $deleted++;
            logLine('Removed stale staged file: ' . $file->getFilename());
        }
    }
}

logLine("Pending-uploads sweep complete: removed={$deleted} (TTL {$ttlHours}h).");
exit(0);
