<?php

declare(strict_types=1);

namespace Amea\Command;

use Amea\Migration\BaselineEnrollment;
use Amea\Migration\MigrationRuntime;
use Throwable;

final class EnrollMigrationsCommand
{
    public function run(int $argumentCount, string $autoload): int
    {
        $arguments = getopt('', ['database:', 'writers-stopped', 'evidence:'], $firstPositional);
        $database = $arguments['database'] ?? null;
        $evidence = $arguments['evidence'] ?? null;
        if ($argumentCount !== 4 || $firstPositional !== $argumentCount || !is_string($database) || $database === ''
            || !is_string($evidence) || $evidence === '' || ($arguments['writers-stopped'] ?? null) !== false) {
            fwrite(STDERR, "Usage: php scripts/enroll-migrations.php --database=NAME --writers-stopped --evidence=FILE\n");
            return 2;
        }

        if ($database !== getenv('DB_NAME')) {
            fwrite(STDERR, "Enrollment failed: --database does not match DB_NAME. Select the intended database before retrying.\n");
            return 1;
        }

        require_once $autoload;

        try {
            $runtime = MigrationRuntime::fromEnvironment();
            $enrolled = $runtime->withLock(static function () use ($runtime, $database, $evidence): bool {
                $runtime->assertNoInterruptedSchemaMigration();
                return (new BaselineEnrollment($runtime))->enroll($database, $evidence);
            });
            fwrite(STDOUT, $enrolled ? "Recorded 2 baseline versions without replaying historical migrations.\n" : "Baseline already enrolled; no versions changed.\n");
        } catch (Throwable $error) {
            fwrite(STDERR, 'Enrollment failed: ' . $error->getMessage() . "\n");
            return 1;
        }

        return 0;
    }
}
