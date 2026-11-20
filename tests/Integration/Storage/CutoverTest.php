<?php

namespace Amea\Tests\Integration\Storage;

use Amea\Core\FileWriteGate;
use Amea\Storage\LocalStorage;
use Amea\Storage\Migration\Copier;
use Amea\Storage\Migration\Cutover;
use Amea\Storage\Migration\Inventory;
use Amea\Storage\Migration\ReferenceAudit;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../storage/migration-fixture.php';

class CutoverTest extends TestCase
{
    public function testActualCliRequiresFreshRoleReportsAndAnUnchangedFinalCheckpoint(): void
    {
        $root = sys_get_temp_dir() . '/amea-cutover-cli-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $root = realpath($root);
        foreach (['source', 'source/uploads', 'source/storage', 'destination', 'sessions', 'sessions/public', 'sessions/staff', 'evidence'] as $name) {
            mkdir($root . '/' . $name, 0700);
        }
        makeTestDb()->exec('VACUUM INTO ' . (new \PDO('sqlite::memory:'))->quote($root . '/db.sqlite'));
        $db = new \PDO('sqlite:' . $root . '/db.sqlite');
        $source = new LocalStorage($root . '/source');
        $destination = new LocalStorage($root . '/destination');
        $identity = ['environment' => 'synthetic', 'database' => ReferenceAudit::databaseIdentity($db),
            'source' => $source->migrationIdentity(), 'destination' => $destination->migrationIdentity(),
            'sessions' => $root . '/sessions', 'serializer' => 'php'];
        $arguments = ['--environment=synthetic', '--database=' . $identity['database'], '--source=' . $identity['source'],
            '--destination=' . $identity['destination'], '--manifest=' . $root . '/evidence/final.jsonl'];
        $environment = array_merge(getenv(), ['MIGRATION_ENVIRONMENT' => 'synthetic', 'MIGRATION_DB_DSN' => 'sqlite:' . $root . '/db.sqlite',
            'MIGRATION_SOURCE_DRIVER' => 'local', 'MIGRATION_SOURCE_ROOT' => $root . '/source',
            'MIGRATION_DESTINATION_DRIVER' => 'local', 'MIGRATION_DESTINATION_ROOT' => $root . '/destination',
            'MIGRATION_SESSION_PATH' => $root . '/sessions', 'MIGRATION_SESSION_SERIALIZER' => 'php',
            'FILE_WRITE_GATE_PATH' => $root . '/gate']);
        $run = static function (string $command, array $extra = []) use ($arguments, $environment, $root): int {
            $process = proc_open([PHP_BINARY, dirname(__DIR__, 3) . '/scripts/storage-migrate.php', $command, ...$arguments, ...$extra],
                [0 => ['file', '/dev/null', 'r'], 1 => ['file', $root . '/cli.log', 'a'], 2 => ['file', $root . '/cli.log', 'a']], $pipes, null, $environment);
            return proc_close($process);
        };
        self::assertSame(0, $run('inventory'));
        $manifest = \Amea\Storage\Migration\Manifest::load($root . '/evidence/final.jsonl', $identity);
        self::assertSame(0, $run('copy', ['--review-manifest=' . $manifest->reviewIdentity()]));
        self::assertSame(0, $run('pause'));
        self::assertSame(2, $run('final-check'));
        $gate = new FileWriteGate($root . '/gate');
        $reports = [];
        foreach (['public', 'staff', 'jobs'] as $role) {
            $reports[] = ['role' => $role, 'backend' => $identity['destination'], 'database' => $identity['database'],
                'gate' => $gate->identity(), 'code' => Cutover::codeIdentity(dirname(__DIR__, 3)), 'serializer' => 'php',
                'observed_at' => microtime(true), 'challenge' => $gate->status()['challenge'],
                'session_store' => $role === 'jobs' ? null : Cutover::sessionStoreIdentity($root . '/sessions/' . $role)];
        }
        file_put_contents($root . '/evidence/targets.json', json_encode($reports, JSON_THROW_ON_ERROR));
        chmod($root . '/evidence/targets.json', 0600);
        $selection = ['--targets=' . $root . '/evidence/targets.json'];
        self::assertSame(0, $run('final-check', $selection));
        self::assertSame(1, $run('resume', $selection), 'A coordinated recovery point is required before reopening.');
        file_put_contents($root . '/evidence/final.jsonl.checkpoint', "\n", FILE_APPEND);
        self::assertSame(1, $run('resume', $selection));
        self::assertTrue($gate->status()['paused']);
        self::assertSame(0, $run('final-check', $selection));
        (new \Amea\Storage\Migration\RecoverySnapshot($gate))->capture($root . '/backup', $db, $source, 'test-owner',
            ['public' => $root . '/sessions/public', 'staff' => $root . '/sessions/staff'], $root . '/source/storage',
            $root . '/evidence/final.jsonl', ['serializer' => 'php']);
        self::assertSame(0, $run('final-check', $selection));
        self::assertSame(0, $run('resume', $selection));
        self::assertFalse($gate->status()['paused']);
    }

    public function testResumeRequiresCurrentVerificationAndEveryActualRoleSelection(): void
    {
        $fixture = migrationFixture(sys_get_temp_dir() . '/amea-cutover-' . bin2hex(random_bytes(8)));
        unlink($fixture['source'] . '/uploads/unknown/link');
        mkdir($fixture['sessions'] . '/public', 0700);
        mkdir($fixture['sessions'] . '/staff', 0700);
        rename($fixture['sessions'] . '/sess_synthetic-secret-token', $fixture['sessions'] . '/public/sess_synthetic-secret-token');
        $db = makeTestDb();
        $source = new LocalStorage($fixture['source']);
        $destination = new LocalStorage($fixture['destination']);
        $identity = ['environment' => 'synthetic', 'database' => ReferenceAudit::databaseIdentity($db),
            'source' => $source->migrationIdentity(), 'destination' => $destination->migrationIdentity(),
            'sessions' => $fixture['sessions'], 'serializer' => 'php'];
        $inventory = new Inventory($source, $identity, $fixture['source']);
        $manifest = $inventory->scan();
        $manifest->issues = [];
        $manifest->save($fixture['manifest']);
        (new Copier($source, $destination, $identity, $inventory))->copy($manifest);
        $manifest->locked(function ($manifest) {
            foreach ($manifest->issues as &$issue) {
                $issue['disposition'] = 'Synthetic retained fixture directory approved';
            }
            unset($issue);
            $manifest->checkpoint();
        });
        $gate = new FileWriteGate($fixture['root'] . '/gate');
        $cutover = new Cutover($gate, $db, $source, $destination, $identity, $fixture['manifest']);
        $cutover->pause();
        $targets = [];
        foreach (['public', 'staff', 'jobs'] as $role) {
            $targets[] = ['role' => $role, 'backend' => $identity['destination'], 'database' => $identity['database'],
                'gate' => $gate->identity(), 'code' => Cutover::codeIdentity(dirname(__DIR__, 3)),
                'serializer' => 'php', 'observed_at' => microtime(true), 'challenge' => $gate->status()['challenge'],
                'session_store' => $role === 'jobs' ? null : Cutover::sessionStoreIdentity($fixture['sessions'] . '/' . $role)];
        }
        try {
            $cutover->finalCheck(array_slice($targets, 0, 2));
            self::fail('Missing jobs selection must block.');
        } catch (\RuntimeException $error) {
            self::assertTrue($gate->status()['paused']);
        }
        $cutover->finalCheck($targets);
        file_put_contents($fixture['sessions'] . '/sess_late', 'pending_registration|' . serialize(['db_data' => ['identity_document' => 'uploads/students/pending/missing.pdf']]));
        try {
            $cutover->resume($targets);
            self::fail('New unresolved session must block reopening.');
        } catch (\RuntimeException $error) {
            self::assertTrue($gate->status()['paused']);
        }
        unlink($fixture['sessions'] . '/sess_late');
        $cutover->finalCheck($targets);
        (new \Amea\Storage\Migration\RecoverySnapshot($gate))->capture($fixture['root'] . '/backup', $db, $source, 'test-owner',
            ['public' => $fixture['sessions'] . '/public', 'staff' => $fixture['sessions'] . '/staff'], $fixture['source'] . '/storage',
            $fixture['manifest'], ['serializer' => 'php']);
        $cutover->finalCheck($targets);
        $db->exec("UPDATE users SET username='changed-after-backup' WHERE id=1");
        try {
            $cutover->resume($targets);
            self::fail('A changed database must invalidate the coordinated recovery point.');
        } catch (\RuntimeException) {
            self::assertTrue($gate->status()['paused']);
        }
        $db->exec("UPDATE users SET username='_superadmin' WHERE id=1");
        $cutover->resume($targets);
        self::assertFalse($gate->status()['paused']);
        self::assertSame('retained attachment', file_get_contents($fixture['destination'] . '/uploads/unknown/unreferenced.bin'));
    }
}
