<?php

namespace Amea\Tests\Integration\Storage;

use Amea\Core\FileWriteGate;
use Amea\Storage\LocalStorage;
use Amea\Storage\Migration\RecoverySnapshot;
use Amea\Storage\Migration\ReferenceAudit;
use Amea\Storage\Migration\Inventory;
use Amea\Storage\Migration\Manifest;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class RecoverySnapshotTest extends TestCase
{
    public function testCaptureRetainsReviewedHistoricalMissingReferences(): void
    {
        $fixture = $this->reviewedMissingFixture();
        $proof = $fixture['snapshot']->capture($fixture['root'] . '/backup', $fixture['db'], $fixture['backend'],
            'source-owner', $fixture['sessions'], $fixture['root'] . '/storage', $fixture['manifest'], ['serializer' => 'php']);
        self::assertSame(1, $proof['objects']);
        self::assertSame(hash_file('sha256', $fixture['manifest'] . '.checkpoint'),
            hash_file('sha256', $fixture['root'] . '/backup/checkpoint'));
    }

    #[DataProvider('captureChanges')]
    public function testCaptureRejectsUnapprovedOrStaleMissingReferences(string $change): void
    {
        $fixture = $this->reviewedMissingFixture();
        $root = $fixture['root'];
        match ($change) {
            'changed-value' => $fixture['db']->exec("UPDATE students SET cv_path='uploads/students/cvs/changed.pdf' WHERE id=11"),
            'different-owner' => $fixture['db']->exec("INSERT INTO students(id,cv_path) VALUES(12,'uploads/students/cvs/historical.pdf')"),
            'new-session' => file_put_contents($fixture['sessions']['public'] . '/sess_new',
                'pending_registration|' . serialize(['db_data' => ['cv_path' => 'uploads/students/pending/historical.pdf']])),
            'moved-session' => rename($fixture['sessions']['public'] . '/sess_historical', $fixture['sessions']['staff'] . '/sess_historical'),
            'missing-bytes' => $fixture['backend']->delete('uploads/students/required.pdf'),
            'tampered-checkpoint' => file_put_contents($fixture['manifest'] . '.checkpoint', 'tampered checkpoint'),
            'wrong-database' => $fixture['db'] = makeTestDb(),
        };
        try {
            $fixture['snapshot']->capture($root . '/backup', $fixture['db'], $fixture['backend'], 'source-owner',
                $fixture['sessions'], $root . '/storage', $fixture['manifest'], ['serializer' => 'php']);
            self::fail('Changed references or evidence must keep recovery paused.');
        } catch (\Amea\Storage\StorageException | \JsonException) {
            self::assertTrue($fixture['gate']->status()['paused']);
            self::assertArrayNotHasKey('backup', $fixture['gate']->status());
        }
    }

    public static function captureChanges(): array
    {
        return array_map(static fn ($change) => [$change], ['changed-value', 'different-owner', 'new-session',
            'moved-session', 'missing-bytes', 'tampered-checkpoint', 'wrong-database']);
    }

    #[DataProvider('reopeningChanges')]
    public function testRestoredReopeningRejectsChangedReferencesBytesAndApprovalEvidence(string $change): void
    {
        $fixture = $this->reviewedMissingFixture();
        $root = $fixture['root'];
        $proof = $fixture['snapshot']->capture($root . '/backup', $fixture['db'], $fixture['backend'], 'source-owner',
            $fixture['sessions'], $root . '/storage', $fixture['manifest'], ['serializer' => 'php']);
        $db = new \PDO('sqlite:' . $root . '/restore.sqlite', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $backend = new LocalStorage($root . '/target');
        $gate = new FileWriteGate($root . '/restore-storage/write-gate');
        $gate->pause();
        $snapshot = new RecoverySnapshot($gate);
        $sessions = ['public' => $root . '/restore-sessions/public', 'staff' => $root . '/restore-sessions/staff'];
        $snapshot->restore($root . '/backup', $proof['sha256'], $db, $backend, 'restore-owner',
            $sessions, $root . '/restore-storage', ReferenceAudit::databaseIdentity($db));
        match ($change) {
            'changed-value' => $db->exec("UPDATE students SET cv_path='uploads/students/cvs/changed.pdf' WHERE id=11"),
            'different-owner' => $db->exec("INSERT INTO students(id,cv_path) VALUES(12,'uploads/students/cvs/historical.pdf')"),
            'new-session' => file_put_contents($sessions['public'] . '/sess_new',
                'pending_registration|' . serialize(['db_data' => ['cv_path' => 'uploads/students/pending/historical.pdf']])),
            'moved-session' => rename($sessions['public'] . '/sess_historical', $sessions['staff'] . '/sess_historical'),
            'missing-bytes' => $backend->delete('uploads/students/required.pdf'),
            'tampered-manifest' => file_put_contents($root . '/backup/manifest', 'tampered manifest'),
            'tampered-checkpoint' => file_put_contents($root . '/backup/checkpoint', 'tampered checkpoint'),
        };
        try {
            $snapshot->resumeRestored($root . '/backup', $proof['sha256'], $db, $backend, $sessions,
                $this->targets($gate, $db, $backend, $sessions));
            self::fail('Changed references, bytes or approval evidence must block reopening.');
        } catch (\Amea\Storage\StorageException) {
            self::assertTrue($gate->status()['paused']);
        }
    }

    public static function reopeningChanges(): array
    {
        return array_map(static fn ($change) => [$change], ['changed-value', 'different-owner', 'new-session',
            'moved-session', 'missing-bytes', 'tampered-manifest', 'tampered-checkpoint']);
    }

    #[DataProvider('damagedSnapshotEvidence')]
    public function testRestoreRejectsTamperedApprovalEvidenceBeforeImport(string $change): void
    {
        $fixture = $this->reviewedMissingFixture();
        $root = $fixture['root'];
        $proof = $fixture['snapshot']->capture($root . '/backup', $fixture['db'], $fixture['backend'], 'source-owner',
            $fixture['sessions'], $root . '/storage', $fixture['manifest'], ['serializer' => 'php']);
        if ($change === 'original-identity') {
            $descriptor = json_decode(file_get_contents($root . '/backup/snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
            $descriptor['reference_identity']['database'] = 'another-original-database';
            file_put_contents($root . '/backup/snapshot.json', json_encode($descriptor, JSON_THROW_ON_ERROR));
            $proof['sha256'] = hash_file('sha256', $root . '/backup/snapshot.json');
        } else {
            file_put_contents($root . '/backup/' . $change, 'tampered approval evidence');
        }
        $db = new \PDO('sqlite:' . $root . '/restore.sqlite', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $gate = new FileWriteGate($root . '/restore-storage/write-gate');
        $gate->pause();
        try {
            (new RecoverySnapshot($gate))->restore($root . '/backup', $proof['sha256'], $db,
                new LocalStorage($root . '/target'), 'restore-owner',
                ['public' => $root . '/restore-sessions/public', 'staff' => $root . '/restore-sessions/staff'],
                $root . '/restore-storage', ReferenceAudit::databaseIdentity($db));
            self::fail('Approval evidence must be validated before importing the isolated database.');
        } catch (\Amea\Storage\StorageException) {
            self::assertSame([], $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN));
            self::assertTrue($gate->status()['paused']);
        }
    }

    public static function damagedSnapshotEvidence(): array
    {
        return [['manifest'], ['checkpoint'], ['original-identity']];
    }

    public function testIsolatedRestoreReopensWithTheExactReviewedDatabaseAndSessionExceptions(): void
    {
        $fixture = $this->reviewedMissingFixture();
        $proof = $fixture['snapshot']->capture($fixture['root'] . '/backup', $fixture['db'], $fixture['backend'],
            'source-owner', $fixture['sessions'], $fixture['root'] . '/storage', $fixture['manifest'], ['serializer' => 'php']);
        $root = $fixture['root'];
        $db = new \PDO('sqlite:' . $root . '/restore.sqlite', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $backend = new LocalStorage($root . '/target');
        $gate = new FileWriteGate($root . '/restore-storage/write-gate');
        $gate->pause();
        $snapshot = new RecoverySnapshot($gate);
        $sessions = ['public' => $root . '/restore-sessions/public', 'staff' => $root . '/restore-sessions/staff'];
        $snapshot->restore($root . '/backup', $proof['sha256'], $db, $backend, 'restore-owner',
            $sessions, $root . '/restore-storage', ReferenceAudit::databaseIdentity($db));
        self::assertTrue($gate->status()['paused']);
        $snapshot->resumeRestored($root . '/backup', $proof['sha256'], $db, $backend, $sessions,
            $this->targets($gate, $db, $backend, $sessions));
        self::assertFalse($gate->status()['paused']);
        self::assertSame('uploads/students/cvs/historical.pdf', $db->query('SELECT cv_path FROM students WHERE id=11')->fetchColumn());
        self::assertSame(file_get_contents($fixture['sessions']['public'] . '/sess_historical'),
            file_get_contents($sessions['public'] . '/sess_historical'));
        self::assertSame(file_get_contents($fixture['manifest'] . '.checkpoint'), file_get_contents($root . '/backup/checkpoint'));
    }

    private function targets(FileWriteGate $gate, \PDO $db, LocalStorage $backend, array $sessions): array
    {
        $targets = [];
        foreach (['public', 'staff', 'jobs'] as $role) {
            $targets[] = ['role' => $role, 'backend' => Manifest::backendIdentity($backend),
                'database' => ReferenceAudit::databaseIdentity($db), 'gate' => $gate->identity(),
                'code' => \Amea\Storage\Migration\Cutover::codeIdentity(dirname(__DIR__, 3)), 'serializer' => 'php',
                'observed_at' => microtime(true), 'challenge' => $gate->status()['challenge'],
                'session_store' => $role === 'jobs' ? null : \Amea\Storage\Migration\Cutover::sessionStoreIdentity($sessions[$role])];
        }
        return $targets;
    }

    private function reviewedMissingFixture(): array
    {
        $root = sys_get_temp_dir() . '/amea-disposition-backup-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $root = realpath($root);
        foreach (['source', 'source/uploads', 'target', 'target/uploads', 'sessions', 'sessions/public', 'sessions/staff', 'restore-sessions',
            'restore-sessions/public', 'restore-sessions/staff', 'storage', 'restore-storage', 'evidence'] as $directory) {
            mkdir($root . '/' . $directory, 0700);
        }
        makeTestDb()->exec("VACUUM INTO '" . $root . "/source.sqlite'");
        $db = new \PDO('sqlite:' . $root . '/source.sqlite', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $db->exec("INSERT INTO students(id,cv_path,identity_document) VALUES(11,'uploads/students/cvs/historical.pdf','uploads/students/required.pdf')");
        file_put_contents($root . '/sessions/public/sess_historical', 'pending_registration|' . serialize([
            'db_data' => ['cv_path' => 'uploads/students/pending/historical.pdf']]));
        $backend = new LocalStorage($root . '/source');
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "%PDF-1.4\nrequired current identity bytes\n");
        rewind($stream);
        $backend->write('uploads/students/required.pdf', $stream, 'application/pdf', 1600000000);
        fclose($stream);
        $identity = ['environment' => 'synthetic-global-fix', 'database' => ReferenceAudit::databaseIdentity($db),
            'source' => Manifest::backendIdentity($backend), 'destination' => Manifest::backendIdentity(new LocalStorage($root . '/target')),
            'sessions' => $root . '/sessions', 'serializer' => 'php'];
        $manifest = (new Inventory($backend, $identity, $root . '/source'))->scan();
        (new ReferenceAudit($db, $backend, $identity, $root . '/sessions', 'php'))->audit($manifest);
        $path = $root . '/evidence/final.jsonl';
        $manifest->save($path);
        $dispositions = array_map(static fn ($reference) => ['type' => 'reference', 'owner_type' => $reference['owner_type'],
            'table' => $reference['table'], 'record' => $reference['record'], 'column' => $reference['column'],
            'index' => $reference['index'], 'expected_old' => $reference['original_value'],
            'decision' => 'Approved synthetic historical missing content, retain this exact reference.'],
            array_values(array_filter($manifest->references, static fn ($reference) => $reference['state'] === 'missing')));
        $manifest->locked(function (Manifest $manifest) use ($dispositions) {
            $manifest->dispose($dispositions, hash('sha256', json_encode($dispositions, JSON_THROW_ON_ERROR)));
            $manifest->checkpoint();
        });
        $gate = new FileWriteGate($root . '/gate');
        $gate->pause($identity);
        return ['root' => $root, 'db' => $db, 'backend' => $backend, 'manifest' => $path,
            'identity' => $identity, 'gate' => $gate, 'snapshot' => new RecoverySnapshot($gate),
            'sessions' => ['public' => $root . '/sessions/public', 'staff' => $root . '/sessions/staff']];
    }

    public function testCoordinatedRestorePreservesCurrentObjectsBothSessionsLedgerAndOriginalAge(): void
    {
        $root = sys_get_temp_dir() . '/amea-backup-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $root = realpath($root);
        foreach (['source', 'target', 'public', 'staff', 'restore-public', 'restore-staff', 'storage', 'restore-storage', 'evidence'] as $directory) {
            mkdir($root . '/' . $directory, 0700);
        }
        $db = makeTestDb();
        $db->exec("INSERT INTO students(id,identity_document) VALUES(11,'uploads/students/pending/current.pdf')");
        $db->exec("INSERT INTO file_operations(operation_id,backend,reason,keys_json) VALUES('pending-intent','source-owner','replacement','[\"uploads/students/pending/current.pdf\"]')");
        $backend = new LocalStorage($root . '/source');
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, "%PDF-1.4\ncurrent after reopening\n");
        rewind($stream);
        $backend->write('uploads/students/pending/current.pdf', $stream, 'application/pdf', 1600000000);
        fclose($stream);
        mkdir($root . '/source/uploads/slider', 0700);
        file_put_contents($root . '/source/uploads/slider/synthetic.php.jpg', 'retained legacy unsafe-key bytes');
        file_put_contents($root . '/public/sess_preserved', 'pending_registration|' . serialize(['db_data' => ['identity_document' => 'uploads/students/pending/current.pdf']]));
        file_put_contents($root . '/staff/sess_staff', 'user_id|i:1;');
        file_put_contents($root . '/storage/mail.json', '{"synthetic":"queued"}');
        $identity = ['environment' => 'synthetic', 'database' => ReferenceAudit::databaseIdentity($db),
            'source' => Manifest::backendIdentity($backend), 'destination' => Manifest::backendIdentity(new LocalStorage($root . '/target')),
            'sessions' => $root, 'serializer' => 'php'];
        $manifest = (new Inventory($backend, $identity, $root . '/source'))->scan();
        (new ReferenceAudit($db, $backend, $identity, $root, 'php'))->audit($manifest);
        $manifest->save($root . '/evidence/manifest.jsonl');
        $manifest->locked(static fn (Manifest $manifest) => $manifest->checkpoint());
        $gate = new FileWriteGate($root . '/gate');
        $gate->pause();
        $snapshot = new RecoverySnapshot($gate);
        $proof = $snapshot->capture($root . '/backup', $db, $backend, 'source-owner',
            ['public' => $root . '/public', 'staff' => $root . '/staff'], $root . '/storage',
            $root . '/evidence/manifest.jsonl', ['environment' => 'synthetic', 'serializer' => 'php']);
        self::assertGreaterThan(0, $proof['completed_at']);
        $restore = new \PDO('sqlite:' . $root . '/restored.sqlite', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $restoreGate = new FileWriteGate($root . '/restore-storage/write-gate');
        $restoreGate->pause();
        $descriptor = json_decode(file_get_contents($root . '/backup/snapshot.json'), true, 512, JSON_THROW_ON_ERROR);
        // Existing version-one archives have complete bytes and no disposition extension.
        unset($descriptor['reference_identity'], $descriptor['sessions']);
        file_put_contents($root . '/backup/snapshot.json', json_encode($descriptor, JSON_THROW_ON_ERROR));
        $proof['sha256'] = hash_file('sha256', $root . '/backup/snapshot.json');
        $objectFile = $root . '/backup/' . $descriptor['objects']['uploads/students/pending/current.pdf']['file'];
        $original = file_get_contents($objectFile);
        file_put_contents($objectFile, 'damaged backup content');
        try {
            (new RecoverySnapshot($restoreGate))->restore($root . '/backup', $proof['sha256'], $restore,
                new LocalStorage($root . '/target'), 'target-owner',
                ['public' => $root . '/restore-public', 'staff' => $root . '/restore-staff'], $root . '/restore-storage',
                ReferenceAudit::databaseIdentity($restore));
            self::fail('Damaged bytes must be rejected before any target DB import.');
        } catch (\Amea\Storage\StorageException) {
            self::assertSame([], $restore->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN));
            self::assertTrue($restoreGate->status()['paused']);
        }
        file_put_contents($objectFile, $original);
        file_put_contents($root . '/restore-staff/sess_unrelated', 'user_id|i:999;');
        try {
            (new RecoverySnapshot($restoreGate))->restore($root . '/backup', $proof['sha256'], $restore,
                new LocalStorage($root . '/target'), 'target-owner',
                ['public' => $root . '/restore-public', 'staff' => $root . '/restore-staff'], $root . '/restore-storage',
                ReferenceAudit::databaseIdentity($restore));
            self::fail('Restore must reject unrelated existing sessions before mutating its database.');
        } catch (\Amea\Storage\StorageException) {
            self::assertSame([], $restore->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN));
        }
        unlink($root . '/restore-staff/sess_unrelated');
        (new RecoverySnapshot($restoreGate))->restore($root . '/backup', $proof['sha256'], $restore,
            new LocalStorage($root . '/target'), 'target-owner',
            ['public' => $root . '/restore-public', 'staff' => $root . '/restore-staff'], $root . '/restore-storage',
            ReferenceAudit::databaseIdentity($restore));
        self::assertSame('uploads/students/pending/current.pdf', $restore->query('SELECT identity_document FROM students WHERE id=11')->fetchColumn());
        self::assertSame('target-owner', $restore->query('SELECT backend FROM file_operations')->fetchColumn());
        self::assertSame(1600000000, (new LocalStorage($root . '/target'))->stat('uploads/students/pending/current.pdf')['createdAt']);
        self::assertSame(file_get_contents($root . '/public/sess_preserved'), file_get_contents($root . '/restore-public/sess_preserved'));
        self::assertSame('user_id|i:1;', file_get_contents($root . '/restore-staff/sess_staff'));
        self::assertTrue($restoreGate->status()['paused']);
        self::assertSame('{"synthetic":"queued"}', file_get_contents($root . '/restore-storage/mail.json'));
        self::assertSame('retained legacy unsafe-key bytes', file_get_contents($root . '/target/uploads/slider/synthetic.php.jpg'));
        $restoreGate->exclusive(function () use ($restore, $root) {
            (new \Amea\Service\FileLifecycleService($restore, new LocalStorage($root . '/target'), 'target-owner'))->retryDeletions(100);
        });
        self::assertSame(0, (int)$restore->query('SELECT COUNT(*) FROM file_operations')->fetchColumn());
        self::assertFileExists($root . '/target/uploads/students/pending/current.pdf');
        self::assertFileExists($root . '/source/uploads/students/pending/current.pdf');
        $db->exec('CREATE VIEW unsupported_backup_view AS SELECT id FROM students');
        try {
            $snapshot->capture($root . '/refused-view-backup', $db, $backend, 'source-owner',
                ['public' => $root . '/public', 'staff' => $root . '/staff'], $root . '/storage',
                $root . '/evidence/manifest.jsonl', ['environment' => 'synthetic', 'serializer' => 'php']);
            self::fail('A database view must produce an explicit unsupported-schema refusal.');
        } catch (\Amea\Storage\StorageException $error) {
            self::assertStringContainsString('Views', $error->getMessage());
            self::assertTrue($gate->status()['paused']);
        }
    }
}
