<?php

declare(strict_types=1);

use Amea\Migration\MigrationRuntime;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Doctrine\Migrations\Tools\Console\Command\StatusCommand;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$operation = $argv[1] ?? '';
if ($argc !== 2 || !in_array($operation, ['migrate', 'status'], true)) {
    fwrite(STDERR, "Usage: php scripts/migrate.php migrate|status\n");
    exit(2);
}

require __DIR__ . '/../vendor/autoload.php';

try {
    $output = new ConsoleOutput();
    $logger = new ConsoleLogger($output, [LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL]);
    $runtime = MigrationRuntime::fromEnvironment($logger);
    $input = new ArrayInput([]);
    $input->setInteractive(false);

    if ($operation === 'status') {
        exit((new StatusCommand($runtime->doctrine))->run($input, $output));
    }

    $runtime->assertCanMigrate();
    exit((new MigrateCommand($runtime->doctrine))->run($input, $output));
} catch (Throwable $error) {
    fwrite(STDERR, 'Migration command failed: ' . $error->getMessage() . "\n");
    exit(1);
}
