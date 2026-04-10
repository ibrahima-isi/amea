<?php
/**
 * Document reconciliation helpers.
 * Used by reconcile-documents.php to detect and fix broken upload paths.
 */

/**
 * Check whether a DB-stored path resolves to an actual file.
 *
 * @param string $dbPath  Relative path stored in DB (e.g. "uploads/students/foo.jpg")
 * @param string $root    Absolute project root directory
 * @return bool
 */
function dbPathExists(string $dbPath, string $root): bool
{
    if ($dbPath === '') return false;
    return is_file(rtrim($root, '/') . '/' . ltrim($dbPath, '/'));
}

/**
 * Try to find a file in alternative upload directories when the DB path doesn't resolve.
 * Returns the relative path (from project root) where the file was found, or null.
 * Will NOT return the same path as $dbPath (only genuine alternatives).
 *
 * @param string   $dbPath     Relative path stored in DB
 * @param string   $root       Absolute project root directory
 * @param string[] $searchDirs Relative directories to search (e.g. ['uploads/students/'])
 * @return string|null
 */
function findAlternativePath(string $dbPath, string $root, array $searchDirs): ?string
{
    if ($dbPath === '') return null;

    $basename    = basename($dbPath);
    $normalizedDb = ltrim($dbPath, '/');

    foreach ($searchDirs as $dir) {
        $relPath  = rtrim($dir, '/') . '/' . $basename;
        // Skip if this is the same as the DB path (we only want alternatives)
        if ($relPath === $normalizedDb) continue;
        if (is_file(rtrim($root, '/') . '/' . $relPath)) {
            return $relPath;
        }
    }
    return null;
}

/**
 * Scan one or more upload directories and return all file paths (relative to project root).
 * Does not recurse — each $dir is scanned shallowly.
 *
 * @param string   $root Absolute project root directory
 * @param string[] $dirs Relative directories to scan
 * @return string[]
 */
function scanUploadFiles(string $root, array $dirs): array
{
    $files = [];
    foreach ($dirs as $dir) {
        $absDir = rtrim($root, '/') . '/' . rtrim($dir, '/');
        if (!is_dir($absDir)) continue;
        foreach (scandir($absDir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $rel = rtrim($dir, '/') . '/' . $entry;
            if (is_file(rtrim($root, '/') . '/' . $rel)) {
                $files[] = $rel;
            }
        }
    }
    return $files;
}

/**
 * Given a list of upload files and a list of DB-stored paths,
 * return files that are not referenced by any DB record (orphaned).
 * Matching is done by basename only (path-independent).
 *
 * @param string[]      $uploadFiles All files found by scanUploadFiles()
 * @param (string|null)[] $dbPaths   All paths currently in the DB
 * @return string[]
 */
function findOrphanedFiles(array $uploadFiles, array $dbPaths): array
{
    $linkedBasenames = [];
    foreach ($dbPaths as $p) {
        if (!empty($p)) {
            $linkedBasenames[] = basename($p);
        }
    }

    $orphans = [];
    foreach ($uploadFiles as $file) {
        if (!in_array(basename($file), $linkedBasenames, true)) {
            $orphans[] = $file;
        }
    }
    return $orphans;
}

/**
 * Classify a single DB document path into one of four statuses:
 *   'null'    — DB field is null/empty (no document recorded)
 *   'ok'      — file exists at the exact DB path
 *   'fixable' — file not at DB path but found in an alternative location
 *   'missing' — file cannot be found anywhere
 *
 * Returns ['status' => '...', 'found_at' => '...' (only for fixable)].
 *
 * @param string|null $dbPath
 * @param string      $root
 * @param string[]    $searchDirs
 * @return array{status: string, found_at?: string}
 */
function classifyDocument(?string $dbPath, string $root, array $searchDirs): array
{
    if (empty($dbPath)) {
        return ['status' => 'null'];
    }
    if (dbPathExists($dbPath, $root)) {
        return ['status' => 'ok'];
    }
    $alt = findAlternativePath($dbPath, $root, $searchDirs);
    if ($alt !== null) {
        return ['status' => 'fixable', 'found_at' => $alt];
    }
    return ['status' => 'missing'];
}
