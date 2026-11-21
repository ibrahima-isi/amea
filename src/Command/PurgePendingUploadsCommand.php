<?php

declare(strict_types=1);

namespace Amea\Command;

use Amea\Config\Environment;
use Amea\Core\Container;
use Amea\Core\FileWriteGate;
use Amea\Service\PendingUploadCleanupService;
use RuntimeException;
use Throwable;

final class PurgePendingUploadsCommand
{
    public function run(string $projectRoot): int
    {
        Environment::load($projectRoot);

        FileWriteGate::admitProcess();
        $ttlHours = max(1, (int)($_ENV['PENDING_UPLOAD_TTL_HOURS'] ?? getenv('PENDING_UPLOAD_TTL_HOURS') ?: 24));

        try {
            $lifecycle = Container::fileLifecycle();
            $cleanup = new PendingUploadCleanupService(Container::db(), Container::storage(), $lifecycle);
            $completed = $cleanup->sweep($ttlHours);
            if ($lifecycle->hasPendingDeletions(false)) {
                throw new RuntimeException('Storage cleanup remains queued for retry.');
            }
            JobLog::write("Pending-uploads sweep complete: operations={$completed} (TTL {$ttlHours}h).");
            return 0;
        } catch (Throwable $error) {
            JobLog::write('ERROR: ' . $error->getMessage());
            return 1;
        }
    }
}
