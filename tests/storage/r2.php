<?php

declare(strict_types=1);

/** Explicit opt-in, synthetic data and exact owned-key cleanup on a dedicated bucket. */
require __DIR__ . '/../../vendor/autoload.php';

use Amea\Service\MemberDocumentService;
use Amea\Storage\Migration\Manifest;
use Amea\Storage\S3Storage;
use Amea\Storage\StorageFactory;
use Aws\S3\S3Client;

function r2Check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo 'PASS ' . $message . "\n";
}

function r2Input(string $bytes): mixed
{
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, $bytes);
    rewind($stream);
    return $stream;
}

$owned = [];
$storage = null;
$success = false;
try {
    if (PHP_SAPI !== 'cli' || count($argv) !== 3 || $argv[1] !== '--config') {
        throw new RuntimeException('Use php tests/storage/r2.php --config /private/canonical/r2-test.env');
    }
    $path = $argv[2];
    Manifest::assertPrivatePath($path);
    $allowed = ['R2_TEST_PURPOSE', 'R2_TEST_CONFIRM_DEDICATED_BUCKET', 'R2_TEST_ENDPOINT', 'R2_TEST_REGION',
        'R2_TEST_BUCKET', 'R2_TEST_ACCESS_KEY_ID', 'R2_TEST_SECRET_ACCESS_KEY'];
    $configuration = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(ltrim($line), '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        if (!in_array($key, $allowed, true) || isset($configuration[$key]) || trim($value) === '') {
            throw new RuntimeException('The dedicated config accepts only the seven documented nonempty R2_TEST fields.');
        }
        $configuration[$key] = trim($value);
    }
    if (count($configuration) !== count($allowed) || $configuration['R2_TEST_PURPOSE'] !== 'amea-dedicated-storage-test'
        || $configuration['R2_TEST_CONFIRM_DEDICATED_BUCKET'] !== $configuration['R2_TEST_BUCKET']
        || $configuration['R2_TEST_REGION'] !== 'auto') {
        throw new RuntimeException('Explicit dedicated-test marker, exact bucket confirmation and auto region are required.');
    }
    $endpoint = parse_url($configuration['R2_TEST_ENDPOINT']);
    if (($endpoint['scheme'] ?? '') !== 'https' || !str_ends_with($endpoint['host'] ?? '', '.r2.cloudflarestorage.com')
        || isset($endpoint['user'], $endpoint['pass']) || isset($endpoint['query']) || isset($endpoint['fragment'])
        || !in_array($endpoint['path'] ?? '', ['', '/'], true)) {
        throw new RuntimeException('An explicit HTTPS Cloudflare R2 S3 endpoint is required.');
    }
    // Never import application .env, database/SMTP variables or host storage credentials.
    $_ENV = ['STORAGE_DRIVER' => 's3', 'S3_PATH_STYLE' => 'false'];
    foreach (['ENDPOINT', 'REGION', 'BUCKET', 'ACCESS_KEY_ID', 'SECRET_ACCESS_KEY'] as $field) {
        $_ENV['S3_' . $field] = $configuration['R2_TEST_' . $field];
    }
    $storage = StorageFactory::fromEnvironment(dirname(__DIR__, 2));
    $client = new S3Client(['version' => '2006-03-01', 'region' => 'auto', 'endpoint' => $_ENV['S3_ENDPOINT'],
        'credentials' => ['key' => $_ENV['S3_ACCESS_KEY_ID'], 'secret' => $_ENV['S3_SECRET_ACCESS_KEY']],
        'use_path_style_endpoint' => false, 'request_checksum_calculation' => 'when_required', 'response_checksum_validation' => 'when_required',
        'http' => ['connect_timeout' => 5, 'timeout' => 120]]);
    $prefix = 'uploads/qualification/' . bin2hex(random_bytes(16)) . '/';
    $pdf = "%PDF-1.4\nAMEA fabricated qualification document\n%%EOF\n";
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j3ioAAAAASUVORK5CYII=', true);
    echo json_encode(['backend_identity' => $storage->migrationIdentity(), 'bucket' => $_ENV['S3_BUCKET'],
        'code_identity' => \Amea\Storage\Migration\Cutover::codeIdentity(dirname(__DIR__, 2)),
        'revision' => trim((string)shell_exec('git rev-parse HEAD')), 'started_at' => gmdate('c'), 'synthetic_prefix' => $prefix], JSON_THROW_ON_ERROR) . "\n";
    foreach (['document.pdf' => [$pdf, 'application/pdf'], 'pixel.png' => [$png, 'image/png']] as $name => [$bytes, $mime]) {
        $key = $prefix . $name;
        $owned[] = $key; // Register before request to clean upload-before-ack failures.
        $stream = r2Input($bytes);
        try {
            $storage->write($key, $stream, $mime, 1600000000);
        } finally {
            fclose($stream);
        }
        $metadata = $storage->stat($key);
        $opened = (new MemberDocumentService($storage))->open($key, [$mime]);
        r2Check(hash('sha256', stream_get_contents($opened['stream'])) === hash('sha256', $bytes), 'stream read-back digest ' . $name);
        fclose($opened['stream']);
        r2Check($metadata['createdAt'] === 1600000000 && $metadata['size'] === strlen($bytes), 'original lifecycle age and size ' . $name);
        $response = $client->headObject(['Bucket' => $_ENV['S3_BUCKET'], 'Key' => $key]);
        r2Check($response['ContentType'] === $mime, 'provider media Content-Type ' . $name);
    }
    $copy = $prefix . 'copy.pdf';
    $owned[] = $copy;
    $storage->copy($prefix . 'document.pdf', $copy);
    r2Check($storage->stat($copy)['createdAt'] === 1600000000, 'copy preserves original staging age');
    // Simulate losing the local acknowledgement after a real accepted provider write.
    $restarted = new S3Storage($client, $_ENV['S3_BUCKET']);
    $retry = r2Input($pdf);
    try {
        $restarted->write($prefix . 'document.pdf', $retry, 'application/pdf', 1600000000);
    } finally {
        fclose($retry);
    }
    r2Check($restarted->stat($prefix . 'document.pdf')['sha256'] === hash('sha256', $pdf), 'restart after upload-before-checkpoint acknowledgement is repeat-safe');
    $seen = [];
    $cursor = null;
    do {
        $page = $storage->listPage($prefix, $cursor, 1);
        foreach ($page['objects'] as $object) {
            $seen[] = $object['key'];
        }
        $cursor = $page['cursor'];
    } while ($cursor !== null);
    sort($seen);
    $expected = $owned;
    sort($expected);
    r2Check($seen === $expected, 'paginated listing returns exactly this synthetic namespace');
    $childEnvironment = ['PATH' => (string)getenv('PATH'), 'R2_TEST_MEDIA_KEY' => $prefix . 'document.pdf',
        'R2_TEST_MEDIA_SHA256' => hash('sha256', $pdf)];
    $process = proc_open(['python3', __DIR__ . '/r2-http.py'], [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR],
        $pipes, dirname(__DIR__, 2), array_merge($childEnvironment, $_ENV));
    r2Check(is_resource($process) && proc_close($process) === 0, 'actual application private download headers and role denial');
    foreach ($owned as $key) {
        $storage->delete($key);
        r2Check($storage->stat($key) === null, 'owned synthetic object deletion');
    }
    $success = true;
} catch (Throwable $error) {
    // Provider exception text can contain signed requests. Emit only a fixed failure.
    fwrite(STDERR, "R2 qualification failed. The selected dedicated config or synthetic operation requires inspection.\n");
} finally {
    if ($storage !== null) {
        foreach ($owned as $key) {
            try {
                $storage->delete($key);
            } catch (Throwable) {
                $success = false;
                fwrite(STDERR, "Owned synthetic namespace cleanup remains pending. Retain the emitted namespace identity.\n");
            }
        }
    }
}
exit($success ? 0 : 1);
