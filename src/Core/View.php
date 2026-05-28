<?php

namespace Amea\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class View
{
    private Environment $twig;

    public function __construct(string $projectRoot)
    {
        $loader = new FilesystemLoader($projectRoot . '/templates');
        
        $isDebug = ($_ENV['APP_ENV'] ?? 'production') === 'dev';
        
        $this->twig = new Environment($loader, [
            'cache' => $projectRoot . '/storage/cache/twig',
            'debug' => $isDebug,
            'auto_reload' => true,
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
            $version = is_file($diskPath) ? filemtime($diskPath) : time();
            return '/' . $path . '?v=' . $version;
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
