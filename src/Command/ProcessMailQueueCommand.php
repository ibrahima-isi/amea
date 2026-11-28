<?php

declare(strict_types=1);

namespace Amea\Command;

use Amea\Config\Environment;
use Amea\Core\Container;
use Amea\Core\FileWriteGate;
use Amea\Core\Runtime;
use Amea\Core\View;
use Amea\Repository\CommunicationRepository;
use Amea\Repository\ConsentRepository;
use Amea\Repository\MailDailyUsageRepository;
use Amea\Repository\SettingRepository;
use Amea\Repository\StudentRepository;
use Amea\Service\CommunicationService;
use Amea\Service\ConsentService;
use Amea\Service\EmailService;
use Amea\Service\LegacyMailCounter;
use Amea\Service\MailBatchTransportInterface;
use Amea\Service\MailQuotaService;
use Closure;
use PDO;
use Throwable;

final class ProcessMailQueueCommand
{
    private Closure $clock;

    public function __construct(
        private ?PDO $pdo = null,
        private ?MailBatchTransportInterface $transport = null,
        ?Closure $clock = null
    ) {
        $this->clock = $clock ?? time(...);
    }

    public function run(string $projectRoot): int
    {
        Environment::load($projectRoot);
        FileWriteGate::admitProcess();

        $batchSize = max(1, (int) Runtime::mailSetting('MAIL_BATCH_SIZE', '50'));
        $dailyLimit = max(1, (int) Runtime::mailSetting('MAIL_DAILY_LIMIT', '300'));
        $maxAttempts = max(1, (int) Runtime::mailSetting('MAIL_MAX_ATTEMPTS', '5'));
        $pauseMs = max(0, (int) Runtime::mailSetting('MAIL_SEND_PAUSE_MS', '250'));

        $queueDir = Runtime::mailQueueDir($projectRoot);
        $failedDir = $queueDir . '/failed';

        try {
            $db = $this->resolveCommunicationDatabase();
        } catch (Throwable) {
            JobLog::write('FATAL: cannot access configured mail database.');
            return 1;
        }
        if (!is_dir($queueDir)) {
            if ($db === null) {
                return 0;
            }
            if (!mkdir($queueDir, 0775, true) && !is_dir($queueDir)) {
                JobLog::write('FATAL: cannot create mail queue lock directory: ' . $queueDir);
                return 1;
            }
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
            return 0;
        }

        $commRepo = $db !== null ? new CommunicationRepository($db) : null;
        $quotaService = $db !== null
            ? new MailQuotaService(new MailDailyUsageRepository($db), $commRepo, $dailyLimit, $queueDir)
            : null;
        if ($quotaService === null) {
            $legacyUsage = (new LegacyMailCounter($queueDir, gmdate('Y-m-d', ($this->clock)())))->read();
            if ($legacyUsage >= $dailyLimit) {
                JobLog::write("Daily limit reached ({$legacyUsage}/{$dailyLimit}); deferring remaining queue.");
                fclose($lockHandle);
                return 0;
            }
        }

        $view = is_dir($projectRoot . '/templates') ? new View($projectRoot) : null;
        $service = new EmailService(
            Runtime::mailSetting('MAIL_USER'),
            Runtime::mailSetting('MAIL_PASS'),
            'noreply@aeesgs.org',
            'AEESGS',
            $view,
            $projectRoot,
            $this->transport
        );

        // 1. Drain file-queued transactional emails first (oldest first), reserving & persisting per message
        $files = glob($queueDir . '/*.json') ?: [];
        sort($files);

        $now = ($this->clock)();
        $processed = 0;
        $delivered = 0;
        $failed = 0;
        $deferred = 0;

        $dueFiles = [];
        foreach ($files as $file) {
            if (count($dueFiles) >= $batchSize) {
                break;
            }
            $raw = file_get_contents($file);
            $payload = $raw !== false ? json_decode($raw, true) : null;

            if (!is_array($payload) || empty($payload['to'])) {
                @rename($file, $failedDir . '/' . basename($file));
                $failed++;
                JobLog::write('Dead-lettered corrupt entry: ' . basename($file));
                continue;
            }

            $nextAttemptAt = (int) ($payload['next_attempt_at'] ?? 0);
            if ($nextAttemptAt > $now) {
                $deferred++;
                continue;
            }

            $dueFiles[] = ['file' => $file, 'payload' => $payload];
        }

        if ($dueFiles !== []) {
            $dueCount = count($dueFiles);
            $service->runInBatchSession(
                function (callable $sendOne) use (
                    $dueFiles,
                    $dueCount,
                    $quotaService,
                    $queueDir,
                    $dailyLimit,
                    $maxAttempts,
                    $pauseMs,
                    $failedDir,
                    &$processed,
                    &$delivered,
                    &$failed
                ): void {
                    foreach ($dueFiles as $idx => $entry) {
                        $attemptDate = gmdate('Y-m-d', ($this->clock)());
                        $legacyCounter = null;
                        if ($quotaService !== null) {
                            if (!$quotaService->tryReserveOneSlot($attemptDate)) {
                                $quotaService->pausePendingCommunicationsForLimit();
                                JobLog::write('Daily limit reached mid-run; stopping.');
                                break;
                            }
                        } else {
                            $legacyCounter = new LegacyMailCounter($queueDir, $attemptDate);
                            $legacyUsage = $legacyCounter->read();
                            if ($legacyUsage >= $dailyLimit) {
                                JobLog::write('Daily limit reached mid-run; stopping.');
                                break;
                            }
                            $legacyCounter->write($legacyUsage + 1);
                        }

                        $file = $entry['file'];
                        $payload = $entry['payload'];
                        $processed++;

                        $result = $sendOne(
                            (string) $payload['to'],
                            (string) ($payload['subject'] ?? ''),
                            (string) ($payload['body'] ?? ''),
                            isset($payload['replyTo']) ? (string) $payload['replyTo'] : null
                        );

                        if ($result['ok']) {
                            @unlink($file);
                            $delivered++;
                            JobLog::write('Delivered to ' . $payload['to'] . ' (' . basename($file) . ')');
                        } else {
                            if ($legacyCounter !== null) {
                                $legacyCounter->write($legacyUsage);
                            } else {
                                $quotaService->releaseOneSlot($attemptDate);
                            }
                            $attempts = (int) ($payload['attempts'] ?? 0) + 1;
                            $payload['attempts'] = $attempts;

                            if ($attempts >= $maxAttempts) {
                                $payload['failed_at'] = time();
                                file_put_contents(
                                    $failedDir . '/' . basename($file),
                                    json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                                );
                                @unlink($file);
                                $failed++;
                                JobLog::write('Dead-lettered after ' . $attempts . ' attempts: ' . $payload['to'] . ' (' . basename($file) . ')');
                            } else {
                                $delay = CommunicationService::retryBackoffSeconds($attempts);
                                $payload['next_attempt_at'] = time() + $delay;
                                file_put_contents($file, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                                JobLog::write('Retry ' . $attempts . "/{$maxAttempts} scheduled in {$delay}s: " . $payload['to']);
                            }
                        }

                        if ($pauseMs > 0 && ($idx + 1) < $dueCount) {
                            usleep($pauseMs * 1000);
                        }
                    }
                }
            );
        }

        // 2. Drain DB-backed bulk & consent communications with remaining batch budget
        $remainingBatch = max(0, $batchSize - $processed);
        $dbDelivered = 0;
        $dbFailed = 0;
        $dbRetried = 0;

        if ($commRepo !== null && $quotaService !== null && $db !== null && $view !== null && $remainingBatch > 0) {
            $commService = new CommunicationService(
                $commRepo,
                $quotaService,
                new SettingRepository($db),
                $service,
                $view,
                $maxAttempts,
                $pauseMs,
                $this->clock
            );
            $consentService = new ConsentService(
                new ConsentRepository($db),
                $commService,
                new StudentRepository($db)
            );

            $chunkResult = $commService->dispatchChunk(
                null,
                $remainingBatch,
                $consentService->prepareConsentLink(...),
                $consentService->markConsentDelivered(...)
            );

            $dbDelivered = (int) ($chunkResult['delivered'] ?? 0);
            $dbFailed = (int) ($chunkResult['failed'] ?? 0);
            $dbRetried = (int) ($chunkResult['retried'] ?? 0);
            if (!empty($chunkResult['daily_limit_reached'])) {
                JobLog::write('Daily limit reached while draining DB communications; remaining recipients paused.');
            }
        }

        $totalDelivered = $delivered + $dbDelivered;
        $totalFailed = $failed + $dbFailed;
        $todayUtc = gmdate('Y-m-d', ($this->clock)());
        $finalSentToday = $quotaService !== null
            ? $quotaService->getUsageForDate($todayUtc)
            : (new LegacyMailCounter($queueDir, $todayUtc))->read();

        if ($quotaService !== null && $finalSentToday >= $dailyLimit) {
            $quotaService->pausePendingCommunicationsForLimit();
        }

        JobLog::write(
            "Run complete: delivered={$totalDelivered} (file={$delivered}, db={$dbDelivered}) "
            . "failed={$totalFailed} retried={$dbRetried} deferred={$deferred} "
            . "(sent today: {$finalSentToday}/{$dailyLimit})"
        );

        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        return 0;
    }

    private function resolveCommunicationDatabase(): ?PDO
    {
        if ($this->pdo === null && Runtime::env('DB_NAME') === '' && Runtime::env('DB_HOST') === ''
            && Runtime::env('DB_USER') === '' && Runtime::env('DB_PASS') === '' && Runtime::env('DB_PORT') === '') {
            return null;
        }
        $db = $this->pdo ?? Container::db();
        (new CommunicationRepository($db))->assertDeliverySchemaAvailable();
        (new MailDailyUsageRepository($db))->assertQuotaSchemaAvailable();
        return $db;
    }

}
