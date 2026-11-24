<?php

declare(strict_types=1);

namespace Amea\Tests\Integration\Storage;

use Amea\Core\FileWriteGate;
use Amea\Storage\LocalStorage;
use Amea\Storage\Migration\Cutover;
use Amea\Storage\Migration\Inventory;
use Amea\Storage\Migration\Manifest;
use Amea\Storage\Migration\RecoverySnapshot;
use Amea\Storage\Migration\ReferenceAudit;
use Amea\Storage\StorageException;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../storage/migration-fixture.php';

class LegacySessionTransitionTest extends TestCase
{
    public function testSingleAppSnapshotRestoresToSplitStoresKeepingPendingRegistrationAndExcludingLegacyStaffAuth(): void
    {
        $root = sys_get_temp_dir() . '/amea-single-to-split-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $root = (string) realpath($root);

        foreach ([
            'source',
            'source/uploads',
            'target',
            'target/uploads',
            'sessions',
            'restore-sessions',
            'restore-sessions/public',
            'restore-sessions/staff',
            'storage',
            'restore-storage',
            'evidence',
        ] as $directory) {
            mkdir($root . '/' . $directory, 0700);
        }

        makeTestDb()->exec("VACUUM INTO '" . $root . "/source.sqlite'");
        $sourceDb = new \PDO('sqlite:' . $root . '/source.sqlite', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $sourceDb->exec("INSERT INTO students(id,identity_document) VALUES(11,'uploads/students/confirmed.pdf')");

        $sourceBackend = new LocalStorage($root . '/source');
        $writePdf = static function (LocalStorage $storage, string $key, string $bytes, int $createdAt): void {
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, $bytes);
            rewind($stream);
            $storage->write($key, $stream, 'application/pdf', $createdAt);
            fclose($stream);
        };
        $confirmedPdf = "%PDF-1.4\nConfirmed synthetic document\n%%EOF\n";
        $pendingPdf = "%PDF-1.4\nPending synthetic registration document\n%%EOF\n";
        $writePdf($sourceBackend, 'uploads/students/confirmed.pdf', $confirmedPdf, 1590000000);
        $writePdf($sourceBackend, 'uploads/students/pending/member-id.pdf', $pendingPdf, 1600000000);
        $writePdf($sourceBackend, 'uploads/students/pending/cvs/member-cv.pdf', $pendingPdf, 1600000000);

        $pendingPayload = [
            'input' => ['first_name' => 'Amina', 'last_name' => 'Bah'],
            'db_data' => [
                'first_name' => 'Amina',
                'last_name' => 'Bah',
                'identity_document' => 'uploads/students/pending/member-id.pdf',
                'cv_path' => 'uploads/students/pending/cvs/member-cv.pdf',
            ],
        ];
        file_put_contents(
            $root . '/sessions/sess_memberonly',
            'last_activity|' . serialize(1700000000)
            . 'csrf_token|' . serialize('member-csrf-token')
            . 'pending_registration|' . serialize($pendingPayload)
        );
        file_put_contents(
            $root . '/sessions/sess_staffonly',
            'last_activity|' . serialize(1700000000)
            . 'user_id|' . serialize(1)
            . 'role|' . serialize('admin')
            . 'session_version|' . serialize(1)
        );
        file_put_contents(
            $root . '/sessions/sess_mixedbrowser',
            'last_activity|' . serialize(1700000000)
            . 'user_id|' . serialize(2)
            . 'role|' . serialize('admin')
            . 'session_version|' . serialize(3)
            . 'staff_return_to|' . serialize('/dashboard.php')
            . 'pending_registration|' . serialize($pendingPayload)
        );

        $targetBackend = new LocalStorage($root . '/target');
        $identity = [
            'environment' => 'single-to-split-rehearsal',
            'database' => ReferenceAudit::databaseIdentity($sourceDb),
            'source' => Manifest::backendIdentity($sourceBackend),
            'destination' => Manifest::backendIdentity($targetBackend),
            'sessions' => $root . '/sessions',
            'serializer' => 'php',
            'topology' => 'single',
        ];

        $manifest = (new Inventory($sourceBackend, $identity, $root . '/source'))->scan();
        self::assertSame([], $manifest->issues, 'Known staged pending/cvs directory must not raise unknown-directory issues.');
        $unresolved = (new ReferenceAudit($sourceDb, $sourceBackend, $identity, $root . '/sessions', 'php'))->audit($manifest);
        self::assertSame([], $unresolved);
        $manifestPath = $root . '/evidence/final.jsonl';
        $manifest->save($manifestPath);
        $manifest->locked(static fn (Manifest $m) => $m->checkpoint());

        $sourceGate = new FileWriteGate($root . '/source-gate');
        $sourceGate->pause($identity);
        $proof = (new RecoverySnapshot($sourceGate))->capture(
            $root . '/backup',
            $sourceDb,
            $sourceBackend,
            'legacy-single-owner',
            ['app' => $root . '/sessions'],
            $root . '/storage',
            $manifestPath,
            ['serializer' => 'php']
        );

        $restoreDb = new \PDO('sqlite:' . $root . '/restore.sqlite', options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $restoreGate = new FileWriteGate($root . '/restore-storage/write-gate');
        $restoreGate->pause();
        $restoreSnapshot = new RecoverySnapshot($restoreGate);
        $splitSessions = [
            'public' => $root . '/restore-sessions/public',
            'staff' => $root . '/restore-sessions/staff',
        ];

        $restoreState = $restoreSnapshot->restore(
            $root . '/backup',
            $proof['sha256'],
            $restoreDb,
            $targetBackend,
            'stellar-split-owner',
            $splitSessions,
            $root . '/restore-storage',
            ReferenceAudit::databaseIdentity($restoreDb)
        );

        self::assertSame('verified', $restoreState['state']);
        self::assertFileExists($splitSessions['public'] . '/sess_memberonly');
        self::assertFileExists($splitSessions['public'] . '/sess_mixedbrowser');
        self::assertFileDoesNotExist($splitSessions['public'] . '/sess_staffonly');
        self::assertSame([], array_values(array_diff(scandir($splitSessions['staff']) ?: [], ['.', '..'])));

        $mixedContents = (string) file_get_contents($splitSessions['public'] . '/sess_mixedbrowser');
        self::assertStringContainsString('pending_registration|', $mixedContents);
        self::assertStringNotContainsString('user_id|', $mixedContents);
        self::assertStringNotContainsString('role|', $mixedContents);
        self::assertStringNotContainsString('session_version|', $mixedContents);
        self::assertStringNotContainsString('staff_return_to|', $mixedContents);

        self::assertSame(1600000000, $targetBackend->stat('uploads/students/pending/member-id.pdf')['createdAt']);
        self::assertSame(1590000000, $targetBackend->stat('uploads/students/confirmed.pdf')['createdAt']);

        $targets = [];
        foreach (['public', 'staff', 'jobs'] as $role) {
            $targets[] = [
                'role' => $role,
                'backend' => Manifest::backendIdentity($targetBackend),
                'database' => ReferenceAudit::databaseIdentity($restoreDb),
                'gate' => $restoreGate->identity(),
                'code' => Cutover::codeIdentity(dirname(__DIR__, 3)),
                'serializer' => 'php',
                'observed_at' => microtime(true),
                'challenge' => $restoreGate->status()['challenge'],
                'session_store' => $role === 'jobs' ? null : Cutover::sessionStoreIdentity($splitSessions[$role]),
            ];
        }

        // Tampering with restored public session must block reopening
        $originalMember = (string) file_get_contents($splitSessions['public'] . '/sess_memberonly');
        file_put_contents($splitSessions['public'] . '/sess_memberonly', $originalMember . 'extra|N;');
        try {
            $restoreSnapshot->resumeRestored($root . '/backup', $proof['sha256'], $restoreDb, $targetBackend, $splitSessions, $targets);
            self::fail('Tampered restored public session must block reopening.');
        } catch (StorageException) {
            self::assertTrue($restoreGate->status()['paused']);
        }
        file_put_contents($splitSessions['public'] . '/sess_memberonly', $originalMember);

        $restoreSnapshot->resumeRestored($root . '/backup', $proof['sha256'], $restoreDb, $targetBackend, $splitSessions, $targets);
        self::assertFalse($restoreGate->status()['paused']);
    }
}
