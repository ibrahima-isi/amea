<?php

declare(strict_types=1);

/**
 * Packages a secret-free, versioned AMEA release archive for Namecheap Stellar.
 *
 * Includes only private application code, locked vendor dependencies, templates,
 * operational scripts, and static assets (copied selectively into docroots during
 * deployment). Excludes secrets, tests, Dockerfiles, Compose files, uploads,
 * mutable runtime state, and legacy root PHP entry points.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = [];
foreach (array_slice($argv, 1) as $argument) {
    if (!preg_match('/^--(source|output|release-id)=(.+)$/s', $argument, $match) || isset($options[$match[1]])) {
        fwrite(STDERR, "Usage: php scripts/stellar/package.php --output=ARCHIVE.tar.gz [--source=REPO_DIR] [--release-id=ID]\n");
        exit(2);
    }
    $options[$match[1]] = $match[2];
}

if (empty($options['output'])) {
    fwrite(STDERR, "Option --output is required.\n");
    exit(2);
}

$sourceDir = realpath($options['source'] ?? dirname(__DIR__, 2));
if ($sourceDir === false || !is_dir($sourceDir . '/src') || !is_file($sourceDir . '/vendor/autoload.php')) {
    fwrite(STDERR, "Source repository must exist and have locked vendor dependencies installed.\n");
    exit(1);
}

$releaseId = $options['release-id'] ?? gmdate('YmdHis');
if (!preg_match('/^[A-Za-z0-9._-]{1,64}$/', $releaseId)) {
    fwrite(STDERR, "Invalid --release-id format.\n");
    exit(2);
}

$allowedDirectories = [
    'src',
    'config',
    'templates',
    'scripts',
    'vendor',
    'assets',
];
$allowedFiles = [
    'composer.json',
    'composer.lock',
];

$forbiddenBasenames = [
    '.env',
    '.env.local',
    '.env.production',
    '.env.staging',
    'runtime.env',
    '.DS_Store',
    '.git',
];

$isForbiddenPath = static function (string $relative) use ($forbiddenBasenames): bool {
    $segments = explode('/', $relative);
    foreach ($segments as $segment) {
        if (in_array($segment, $forbiddenBasenames, true) || str_starts_with($segment, '.env')) {
            return true;
        }
        if (preg_match('/\.(key|pem|p12|pfx)$/i', $segment)) {
            return true;
        }
    }
    return false;
};

$stage = sys_get_temp_dir() . '/amea-stellar-pkg-' . bin2hex(random_bytes(8));
if (!mkdir($stage, 0700, true)) {
    fwrite(STDERR, "Unable to create packaging staging directory.\n");
    exit(1);
}

$cleanup = static function (string $dir): void {
    if (!is_dir($dir)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($dir);
};

try {
    foreach ($allowedFiles as $file) {
        $src = $sourceDir . '/' . $file;
        if (!is_file($src)) {
            throw new RuntimeException("Required release file missing: {$file}");
        }
        if (!copy($src, $stage . '/' . $file)) {
            throw new RuntimeException("Failed copying {$file}");
        }
    }

    foreach ($allowedDirectories as $dir) {
        $srcRoot = $sourceDir . '/' . $dir;
        if (!is_dir($srcRoot)) {
            throw new RuntimeException("Required release directory missing: {$dir}");
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        @mkdir($stage . '/' . $dir, 0755, true);
        foreach ($iterator as $item) {
            $relative = $dir . '/' . substr($item->getPathname(), strlen($srcRoot) + 1);
            if ($isForbiddenPath($relative)) {
                throw new RuntimeException("Forbidden secret or VCS artifact detected in release tree: {$relative}");
            }
            if ($item->isLink()) {
                throw new RuntimeException("Symlinks are forbidden inside release archives: {$relative}");
            }
            $target = $stage . '/' . $relative;
            if ($item->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0755, true)) {
                    throw new RuntimeException("Unable to create directory {$relative}");
                }
            } else {
                if (!copy($item->getPathname(), $target)) {
                    throw new RuntimeException("Unable to copy {$relative}");
                }
                if (is_executable($item->getPathname())) {
                    @chmod($target, 0755);
                }
            }
        }
    }

    $metadata = [
        'release_id' => $releaseId,
        'packaged_at' => gmdate('c'),
        'php_target' => '8.5',
    ];
    file_put_contents($stage . '/release-manifest.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    // Verify zero forbidden files exist in the staged tree before archiving.
    $verify = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS));
    $fileCount = 0;
    foreach ($verify as $item) {
        $relative = substr($item->getPathname(), strlen($stage) + 1);
        if ($isForbiddenPath($relative)) {
            throw new RuntimeException("Secret artifact check failed for {$relative}");
        }
        $fileCount++;
    }

    $outputPath = $options['output'];
    $outputDir = dirname($outputPath);
    if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true)) {
        throw new RuntimeException("Unable to create output directory {$outputDir}");
    }

    $process = proc_open(
        ['tar', '-C', $stage, '-czf', $outputPath, '.'],
        [0 => ['file', '/dev/null', 'r'], 1 => STDERR, 2 => STDERR],
        $pipes
    );
    $status = is_resource($process) ? proc_close($process) : 1;
    if ($status !== 0 || !is_file($outputPath)) {
        throw new RuntimeException('tar failed to create the release archive.');
    }

    echo json_encode([
        'release_id' => $releaseId,
        'archive' => realpath($outputPath) ?: $outputPath,
        'files' => $fileCount,
        'sha256' => hash_file('sha256', $outputPath),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
    $cleanup($stage);
    exit(0);
} catch (Throwable $error) {
    $cleanup($stage);
    fwrite(STDERR, 'Packaging failed: ' . $error->getMessage() . "\n");
    exit(1);
}
