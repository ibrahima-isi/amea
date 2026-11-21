<?php
/** Seed a real interrupted retention purge in the disposable HTTP copy. */
$root = dirname(__DIR__, 2);
if (PHP_SAPI !== 'cli' || !str_starts_with(basename($root), 'amea-http-') || !is_file($root . '/fixture.sqlite')) exit(2);
require $root . '/vendor/autoload.php';
$db = new PDO('sqlite:' . $root . '/fixture.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$key = 'uploads/students/awaiting-backup.pdf';
$storage = \Amea\Storage\StorageFactory::fromEnvironment($root);
$source = fopen('php://temp', 'w+b'); fwrite($source, "%PDF-1.4\nRetained pending backup\n%%EOF"); rewind($source);
$storage->write($key, $source, 'application/pdf', 1234567890); fclose($source);
$db->prepare('INSERT INTO students (id, last_name, first_name, cv_path, deleted_at) VALUES (99, ?, ?, ?, ?)')->execute(['AwaitingBackup', 'Synthetic', $key, '2020-01-01']);
$failing = new \Amea\Tests\Support\FailingStorage($storage);
$failing->failDelete = true;
$lifecycle = new \Amea\Service\FileLifecycleService($db, $failing, \Amea\Storage\StorageFactory::identity($root));
if ($lifecycle->purgeMember(99, 30)) throw new RuntimeException('Expected an interrupted retention purge');
