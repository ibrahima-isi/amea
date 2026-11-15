<?php

namespace Amea\Tests\Integration\Storage;

use Amea\Core\FileWriteGate;
use Amea\Storage\LocalStorage;
use Amea\Storage\Migration\RecoverySnapshot;
use Amea\Storage\Migration\ReferenceAudit;
use PHPUnit\Framework\TestCase;

class RecoverySnapshotTest extends TestCase
{
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
        file_put_contents($root . '/evidence/manifest.jsonl', "private reviewed manifest\n");
        chmod($root . '/evidence/manifest.jsonl', 0600);
        file_put_contents($root . '/evidence/manifest.jsonl.checkpoint', "final state\n");
        chmod($root . '/evidence/manifest.jsonl.checkpoint', 0600);
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
