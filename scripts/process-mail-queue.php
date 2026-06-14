<?php

/**
 * scripts/process-mail-queue.php — durable mail queue worker (CLI only).
 *
 * Drains storage/mail-queue/*.json, sending each message via SMTP. Designed to
 * be run from cron (e.g. every minute). Safe properties:
 *   - Single-instance: holds an exclusive flock so overlapping cron runs no-op.
 *   - Rate-limited: respects a per-run batch size and a per-day cap so the Brevo
 *     free tier (300 emails/day) is never exceeded.
 *   - Durable: a message is only deleted after a confirmed send. Failures are
 *     retried with exponential backoff; after MAIL_MAX_ATTEMPTS the message is
 *     moved to storage/mail-queue/failed/ (dead-letter) for manual inspection.
 *
 * Tunable via .env:
 *   MAIL_BATCH_SIZE   (default 50)  — max messages delivered per run
 *   MAIL_DAILY_LIMIT  (default 300) — max successful sends per calendar day
 *   MAIL_MAX_ATTEMPTS (default 5)   — attempts before dead-lettering
 *   MAIL_SEND_PAUSE_MS(default 250) — pause between sends, in milliseconds
 *
 * Usage (cron):
 *   * * * * * /usr/local/bin/php /path/to/scripts/process-mail-queue.php >> /path/to/logs/mail-queue.log 2>&1
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

function mailEnv(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? getenv($key);
    return ($value === false || $value === '') ? $default : (string)$value;
}

function logLine(string $message): void
{
    fwrite(STDOUT, '[' . date('c') . '] ' . $message . PHP_EOL);
}

$batchSize  = max(1, (int)mailEnv('MAIL_BATCH_SIZE', '50'));
$dailyLimit = max(1, (int)mailEnv('MAIL_DAILY_LIMIT', '300'));
$maxAttempts = max(1, (int)mailEnv('MAIL_MAX_ATTEMPTS', '5'));
$pauseMs    = max(0, (int)mailEnv('MAIL_SEND_PAUSE_MS', '250'));

$queueDir  = $projectRoot . '/storage/mail-queue';
$failedDir = $queueDir . '/failed';

if (!is_dir($queueDir)) {
    // Nothing has ever been queued — normal idle state.
    exit(0);
}
if (!is_dir($failedDir) && !mkdir($failedDir, 0775, true) && !is_dir($failedDir)) {
    logLine('FATAL: cannot create dead-letter directory: ' . $failedDir);
    exit(1);
}

// ─── Single-instance lock ───────────────────────────────────────────────────
$lockHandle = fopen($queueDir . '/.lock', 'c');
if ($lockHandle === false) {
    logLine('FATAL: cannot open lock file.');
    exit(1);
}
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    // Another run is in progress; quietly exit.
    exit(0);
}

// ─── Daily counter ──────────────────────────────────────────────────────────
$counterFile = $queueDir . '/.sent-' . date('Ymd');
$sentToday   = is_file($counterFile) ? (int)file_get_contents($counterFile) : 0;

if ($sentToday >= $dailyLimit) {
    logLine("Daily limit reached ({$sentToday}/{$dailyLimit}); deferring remaining queue.");
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(0);
}

// ─── Build the email service ────────────────────────────────────────────────
$service = new \Amea\Service\EmailService(
    mailEnv('MAIL_USER'),
    mailEnv('MAIL_PASS'),
    'noreply@aeesgs.org',
    'AEESGS Platform',
    null,
    $projectRoot
);

// ─── Drain the queue (oldest first) ─────────────────────────────────────────
$files = glob($queueDir . '/*.json') ?: [];
sort($files); // timestamp-prefixed filenames sort chronologically

$now       = time();
$processed = 0;
$delivered = 0;
$failed    = 0;
$deferred  = 0;

foreach ($files as $file) {
    if ($processed >= $batchSize) {
        break;
    }
    if ($sentToday + $delivered >= $dailyLimit) {
        logLine('Daily limit reached mid-run; stopping.');
        break;
    }

    $raw     = file_get_contents($file);
    $payload = $raw !== false ? json_decode($raw, true) : null;

    if (!is_array($payload) || empty($payload['to'])) {
        // Corrupt entry — dead-letter it so the queue keeps moving.
        @rename($file, $failedDir . '/' . basename($file));
        $failed++;
        logLine('Dead-lettered corrupt entry: ' . basename($file));
        continue;
    }

    // Respect backoff scheduling.
    $nextAttemptAt = (int)($payload['next_attempt_at'] ?? 0);
    if ($nextAttemptAt > $now) {
        $deferred++;
        continue;
    }

    $processed++;

    $sent = $service->send(
        (string)$payload['to'],
        (string)($payload['subject'] ?? ''),
        (string)($payload['body'] ?? ''),
        isset($payload['replyTo']) ? (string)$payload['replyTo'] : null
    );

    if ($sent) {
        unlink($file);
        $delivered++;
        logLine('Delivered to ' . $payload['to'] . ' (' . basename($file) . ')');
        if ($pauseMs > 0) {
            usleep($pauseMs * 1000);
        }
        continue;
    }

    // Failed — increment attempts and either reschedule or dead-letter.
    $attempts = (int)($payload['attempts'] ?? 0) + 1;
    $payload['attempts'] = $attempts;

    if ($attempts >= $maxAttempts) {
        $payload['failed_at'] = time();
        file_put_contents($failedDir . '/' . basename($file), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        unlink($file);
        $failed++;
        logLine('Dead-lettered after ' . $attempts . ' attempts: ' . $payload['to'] . ' (' . basename($file) . ')');
        continue;
    }

    // Exponential backoff: 2min, 4min, 8min, … capped at 1h.
    $delay = min(60 * (2 ** $attempts), 3600);
    $payload['next_attempt_at'] = time() + $delay;
    file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    logLine('Retry ' . $attempts . "/{$maxAttempts} scheduled in {$delay}s: " . $payload['to']);
}

// ─── Persist daily counter ──────────────────────────────────────────────────
if ($delivered > 0) {
    file_put_contents($counterFile, (string)($sentToday + $delivered), LOCK_EX);
}

logLine("Run complete: delivered={$delivered} failed={$failed} deferred={$deferred} (sent today: " . ($sentToday + $delivered) . "/{$dailyLimit})");

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
exit(0);
