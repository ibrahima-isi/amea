<?php
/**
 * functions/document-reconcile.php — backward-compat wrappers
 */

require_once __DIR__ . '/../vendor/autoload.php';

function dbPathExists(string $dbPath, string $root): bool
{
    return (new \Amea\Service\DocumentReconcileService(null, $root))->dbPathExists($dbPath);
}

function findAlternativePath(string $dbPath, string $root, array $searchDirs): ?string
{
    return (new \Amea\Service\DocumentReconcileService(null, $root))->findAlternativePath($dbPath, $searchDirs);
}

function scanUploadFiles(string $root, array $dirs): array
{
    return (new \Amea\Service\DocumentReconcileService(null, $root))->scanUploadFiles($dirs);
}

function findOrphanedFiles(array $uploadFiles, array $dbPaths): array
{
    return (new \Amea\Service\DocumentReconcileService(null, ''))->findOrphanedFiles($uploadFiles, $dbPaths);
}

function classifyDocument(?string $dbPath, string $root, array $searchDirs): array
{
    return (new \Amea\Service\DocumentReconcileService(null, $root))->classifyDocument($dbPath, $searchDirs);
}
