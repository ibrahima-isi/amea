<?php

declare(strict_types=1);

use Amea\Migration\MigrationRuntime;
use Amea\Migration\Version\Version20261127000002;
use Amea\Migration\Version\Version20261127000003;
use Doctrine\DBAL\Schema\Schema;
use Psr\Log\NullLogger;

if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'amea_rehearsal') {
    exit(1);
}

require '/opt/amea/vendor/autoload.php';

// The split-host rehearsal builds its historical schema before testing current routes.
// Doctrine enrollment and recovery are exercised independently on MariaDB.
$connection = MigrationRuntime::fromEnvironment()->connection();
foreach ([Version20261127000002::class, Version20261127000003::class] as $version) {
    $migration = new $version($connection, new NullLogger());
    $migration->up(new Schema());
    foreach ($migration->getSql() as $query) {
        $connection->executeStatement($query->getStatement());
    }
}
