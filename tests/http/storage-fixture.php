<?php
/** Disposable loopback S3 fixture only. No application endpoint. */
if (PHP_SAPI !== 'cli' || !preg_match('/^amea-member-test-[a-f0-9]{16}$/D', $argv[2] ?? '')
    || !preg_match('#^http://127\.0\.0\.1:[0-9]+$#D', getenv('STORAGE_TEST_ENDPOINT') ?: '')) { exit(2); }
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$client = new \Aws\S3\S3Client([
    'version' => '2006-03-01', 'region' => 'us-east-1', 'endpoint' => getenv('STORAGE_TEST_ENDPOINT'),
    'credentials' => ['key' => 'storage-test', 'secret' => 'disposable-storage-secret'], 'use_path_style_endpoint' => true,
    'request_checksum_calculation' => 'when_required', 'response_checksum_validation' => 'when_required',
]);
$bucket = $argv[2];
if ($argv[1] === 'create') { $client->createBucket(['Bucket' => $bucket]); }
elseif ($argv[1] === 'unsafe') {
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, '<html>synthetic invalid document</html>'); rewind($stream);
    (new \Amea\Storage\S3Storage($client, $bucket))->write('uploads/students/unsafe.pdf', $stream, 'application/pdf', 1234);
    fclose($stream);
}
elseif ($argv[1] === 'delete') {
    foreach ($client->getPaginator('ListObjectsV2', ['Bucket' => $bucket]) as $page) {
        foreach ($page['Contents'] ?? [] as $object) { $client->deleteObject(['Bucket' => $bucket, 'Key' => $object['Key']]); }
    }
    $client->deleteBucket(['Bucket' => $bucket]);
} else { exit(2); }
