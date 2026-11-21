<?php

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$deadline = microtime(true) + 5;
while (!is_file($argv[3] . '/start')) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "Concurrent writer coordination timed out.\n");
        exit(1);
    }
    usleep(10000);
}
$source = fopen('php://temp', 'w+b');
fwrite($source, $argv[2]);
rewind($source);
try {
    \Amea\Storage\StorageFactory::fromEnvironment($argv[1])->write('uploads/raced.txt', $source, 'text/plain', 1234);
    echo 'accepted';
} catch (\Amea\Storage\StorageException) {
    echo 'conflict';
} finally {
    fclose($source);
}
