<?php

declare(strict_types=1);

namespace Amea\Command;

use Amea\Migration\MigrationRuntime;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\Migrations\Tools\Console\Command\StatusCommand;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class MigrateDatabaseCommand
{
    public function run(array $arguments, string $autoload): int
    {
        $operation = $arguments[1] ?? '';
        if (count($arguments) !== 2 || !in_array($operation, ['migrate', 'status'], true)) {
            fwrite(STDERR, "Usage: php scripts/migrate.php migrate|status\n");
            return 2;
        }

        require_once $autoload;

        try {
            $output = new ConsoleOutput();
            $logger = new ConsoleLogger($output, [LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL]);
            $runtime = MigrationRuntime::fromEnvironment($logger);
            $input = new ArrayInput([]);
            $input->setInteractive(false);

            if ($operation === 'status') {
                return (new StatusCommand($runtime->doctrine))->run($input, $output);
            }

            return $runtime->withLock(static function () use ($runtime, $input, $output): int {
                $runtime->assertCanMigrate();
                return (new MigrateCommand($runtime->doctrine))->run($input, $output);
            });
        } catch (Throwable $error) {
            fwrite(STDERR, 'Migration command failed: ' . $error->getMessage() . "\n");
            return 1;
        }
    }
}
