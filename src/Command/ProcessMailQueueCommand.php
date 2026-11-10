<?php

declare(strict_types=1);

namespace Amea\Command;

use Amea\Config\Environment;
use Amea\Service\EmailService;

final class ProcessMailQueueCommand
{
    public function run(string $projectRoot): int
    {
        Environment::load($projectRoot);

        $batchSize  = max(1, (int)self::mailEnv('MAIL_BATCH_SIZE', '50'));
        $dailyLimit = max(1, (int)self::mailEnv('MAIL_DAILY_LIMIT', '300'));
        $maxAttempts = max(1, (int)self::mailEnv('MAIL_MAX_ATTEMPTS', '5'));
        $pauseMs    = max(0, (int)self::mailEnv('MAIL_SEND_PAUSE_MS', '250'));

        $queueDir  = $projectRoot . '/storage/mail-queue';
        $failedDir = $queueDir . '/failed';

        if (!is_dir($queueDir)) {
            // Nothing has ever been queued — normal idle state.
            return 0;
        }
        if (!is_dir($failedDir) && !mkdir($failedDir, 0775, true) && !is_dir($failedDir)) {
            JobLog::write('FATAL: cannot create dead-letter directory: ' . $failedDir);
            return 1;
        }

        // Single-instance lock
        $lockHandle = fopen($queueDir . '/.lock', 'c');
        if ($lockHandle === false) {
            JobLog::write('FATAL: cannot open lock file.');
            return 1;
        }
        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            // Another run is in progress; quietly exit.
            return 0;
        }

        // Daily counter
        $counterFile = $queueDir . '/.sent-' . date('Ymd');
        $sentToday   = is_file($counterFile) ? (int)file_get_contents($counterFile) : 0;

        if ($sentToday >= $dailyLimit) {
            JobLog::write("Daily limit reached ({$sentToday}/{$dailyLimit}); deferring remaining queue.");
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            return 0;
        }

        // Build the email service
        $service = new EmailService(
            self::mailEnv('MAIL_USER'),
            self::mailEnv('MAIL_PASS'),
            'noreply@aeesgs.org',
            'AEESGS Platform',
            null,
            $projectRoot
        );

        // Drain the queue (oldest first)
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
                JobLog::write('Daily limit reached mid-run; stopping.');
                break;
            }

            $raw     = file_get_contents($file);
            $payload = $raw !== false ? json_decode($raw, true) : null;

            if (!is_array($payload) || empty($payload['to'])) {
                // Corrupt entry — dead-letter it so the queue keeps moving.
                @rename($file, $failedDir . '/' . basename($file));
                $failed++;
                JobLog::write('Dead-lettered corrupt entry: ' . basename($file));
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
                JobLog::write('Delivered to ' . $payload['to'] . ' (' . basename($file) . ')');
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
                JobLog::write('Dead-lettered after ' . $attempts . ' attempts: ' . $payload['to'] . ' (' . basename($file) . ')');
                continue;
            }

            // Exponential backoff: 2min, 4min, 8min, … capped at 1h.
            $delay = min(60 * (2 ** $attempts), 3600);
            $payload['next_attempt_at'] = time() + $delay;
            file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            JobLog::write('Retry ' . $attempts . "/{$maxAttempts} scheduled in {$delay}s: " . $payload['to']);
        }

        // Persist daily counter
        if ($delivered > 0) {
            file_put_contents($counterFile, (string)($sentToday + $delivered), LOCK_EX);
        }

        JobLog::write("Run complete: delivered={$delivered} failed={$failed} deferred={$deferred} (sent today: " . ($sentToday + $delivered) . "/{$dailyLimit})");

        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        return 0;
    }

    private static function mailEnv(string $key, string $default = ''): string
    {
        $value = $_ENV[$key] ?? getenv($key);
        return ($value === false || $value === '') ? $default : (string) $value;
    }
}
