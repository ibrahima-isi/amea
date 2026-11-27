<?php

namespace Amea\Core;

use Amea\Cache\CacheInvalidator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class View
{
    /** @var array<string, int> */
    private static array $assetVersions = [];

    private Environment $twig;

    public function __construct(string $projectRoot)
    {
        $loader = new FilesystemLoader($projectRoot . '/templates');

        $appEnv = strtolower(trim(Runtime::env('APP_ENV', 'production')));
        $isDebug = $appEnv === 'dev';
        $isProduction = in_array($appEnv, ['prod', 'production'], true);

        $cacheDir = Runtime::twigCacheDir($projectRoot);
        if ($isProduction) {
            // Scope production compiled templates per release root so rollbacks
            // never reuse stale compiled PHP when auto_reload is disabled.
            $releaseFingerprint = substr(hash('sha256', (string) (realpath($projectRoot) ?: $projectRoot)), 0, 12);
            $cacheDir .= '/' . $releaseFingerprint;
        }

        $this->twig = new Environment($loader, [
            'cache' => $cacheDir,
            'debug' => $isDebug,
            'auto_reload' => !$isProduction,
        ]);

        $this->addAssetFunction($projectRoot);
    }

    public function render(string $template, array $data = []): string
    {
        // Senior Fix: Handle legacy absolute paths during migration
        $template = $this->normalizePath($template);
        return $this->twig->render($template, $data);
    }

    private function normalizePath(string $path): string
    {
        $templateDir = realpath($this->twig->getLoader()->getPaths()[0]);
        $absolutePath = realpath($path) ?: $path;

        if (str_starts_with($absolutePath, $templateDir)) {
            $relative = ltrim(substr($absolutePath, strlen($templateDir)), DIRECTORY_SEPARATOR);
            return $relative;
        }

        return $path;
    }

    private function addAssetFunction(string $projectRoot): void
    {
        $this->twig->addFunction(new TwigFunction('asset', function ($path) use ($projectRoot) {
            $path = ltrim($path, '/');
            $diskPath = $projectRoot . '/' . $path;
            if (!array_key_exists($diskPath, self::$assetVersions)) {
                self::$assetVersions[$diskPath] = is_file($diskPath) ? (int) filemtime($diskPath) : time();
            }
            return '/' . $path . '?v=' . self::$assetVersions[$diskPath];
        }));

        $this->twig->addFunction(new TwigFunction('journal_cover_version', static function (?string $coverPath): string {
            return CacheInvalidator::coverVersionToken($coverPath) ?? '';
        }));
    }

    /**
     * Expose twig for advanced customization if needed.
     */
    public function getTwig(): Environment
    {
        return $this->twig;
    }
}
