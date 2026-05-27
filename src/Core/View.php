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
        
        $this->twig = new Environment($loader, [
            'cache' => $projectRoot . '/storage/cache/twig',
            'debug' => true, // Set to false in production
            'auto_reload' => true,
        ]);

        $this->addAssetFunction($projectRoot);
    }

    public function render(string $template, array $data = []): string
    {
        return $this->twig->render($template, $data);
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
