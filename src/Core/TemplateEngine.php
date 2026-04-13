<?php
namespace Amea\Core;

class TemplateEngine
{
    public function __construct(private string $baseDir) {}

    /**
     * Render a template file with {{key}} placeholder substitution.
     * If $templatePath is empty, returns an empty string (useful for applyVersions only).
     */
    public function render(string $templatePath, array $vars = []): string
    {
        if ($templatePath === '') {
            return '';
        }
        $fullPath = rtrim($this->baseDir, '/') . '/' . ltrim($templatePath, '/');
        if (!is_file($fullPath)) {
            throw new \RuntimeException("Template not found: {$fullPath}");
        }
        $template = file_get_contents($fullPath);
        if (!empty($vars)) {
            $template = strtr($template, $this->buildPairs($vars));
        }
        return $this->applyAssetVersions($template);
    }

    /**
     * Render a content template then inject it into a layout template.
     */
    public function renderLayout(
        string $layoutPath,
        array  $layoutVars,
        array  $contentVars = []
    ): string {
        // $layoutVars should already contain a pre-rendered 'content' key
        return $this->render($layoutPath, $layoutVars);
    }

    private function buildPairs(array $vars): array
    {
        $pairs = [];
        foreach ($vars as $key => $value) {
            $pairs['{{' . $key . '}}'] = (string)($value ?? '');
        }
        return $pairs;
    }

    /**
     * Appends ?v=<mtime> to local asset URLs so browsers bust caches.
     * Public static method so backward-compat wrapper in utility-functions.php can call it.
     */
    public static function versionAssets(string $html, string $root): string
    {
        return preg_replace_callback(
            '/(src|href)="([^"]+\.(css|js|png|jpg|jpeg|gif|svg|ico|webp))(\?[^"]*)?"/',
            static function (array $m) use ($root): string {
                $attr    = $m[1];
                $rawPath = $m[2];
                // Only version local (non-CDN) assets
                if (str_starts_with($rawPath, 'http') || str_starts_with($rawPath, '//')) {
                    return $m[0];
                }
                $diskPath = rtrim($root, '/') . '/' . ltrim($rawPath, '/');
                $version  = is_file($diskPath) ? filemtime($diskPath) : time();
                return "{$attr}=\"{$rawPath}?v={$version}\"";
            },
            $html
        ) ?? $html;
    }

    private function applyAssetVersions(string $html): string
    {
        return self::versionAssets($html, $this->baseDir);
    }
}
