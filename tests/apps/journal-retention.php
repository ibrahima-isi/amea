<?php
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'amea_rehearsal') exit(1);
require '/opt/amea/vendor/autoload.php';
$storage = \Amea\Core\Container::storage();
$reader = new \Amea\Service\JournalMediaService('/opt/amea', $storage);
$page = $storage->listPage('uploads/journal/covers/', null, 1000);
if (count($page['objects']) !== 3 || $page['cursor'] !== null) throw new RuntimeException('Expected current and two retained historical cover objects');
$expected = hash_file('sha256', '/opt/amea/assets/img/presidents.jpg');
foreach ($page['objects'] as $object) {
    $image = $reader->open($object['key']);
    if (hash('sha256', stream_get_contents($image['stream'])) !== $expected) throw new RuntimeException('Historical image content changed');
    fclose($image['stream']);
}
echo "PASS journal: replacement and removal retain both verified historical objects\n";
