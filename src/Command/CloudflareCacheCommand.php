<?php

declare(strict_types=1);

namespace Amea\Command;

use Amea\Cache\AppCache;
use Amea\Cache\CacheInvalidator;
use Amea\Cache\CloudflareCachePurger;
use Amea\Core\Container;
use Amea\Core\Runtime;

/**
 * CLI operations for AMEA origin AppCache and Cloudflare edge cache management.
 */
final class CloudflareCacheCommand
{
    public function __construct(
        private string $projectRoot,
        private ?AppCache $cache = null,
        private ?CloudflareCachePurger $cloudflare = null
    ) {
    }

    /**
     * @param list<string> $argv
     * @return array{exit: int, output: string}
     */
    public function run(array $argv): array
    {
        $subcommand = $argv[1] ?? '';
        $options = $this->parseOptions(array_slice($argv, 2));

        return match ($subcommand) {
            'status' => $this->status(),
            'purge' => $this->purge($options),
            'export-rules' => $this->exportRules($options),
            default => [
                'exit' => 2,
                'output' => "Usage:\n"
                    . "  php scripts/cloudflare-cache.php status\n"
                    . "  php scripts/cloudflare-cache.php purge --all\n"
                    . "  php scripts/cloudflare-cache.php purge --urls=/,/journal.php\n"
                    . "  php scripts/cloudflare-cache.php export-rules [--public-host=aeesgs.org] [--staff-host=admin.aeesgs.org]\n",
            ],
        };
    }

    /**
     * @return array{exit: int, output: string}
     */
    private function status(): array
    {
        $cache = $this->resolveCache();
        $cf = $this->resolveCloudflare();

        $payload = [
            'app_cache' => $cache->stats(),
            'cloudflare' => $cf->status(),
            'public_app_url' => Runtime::env('PUBLIC_APP_URL') ?: Runtime::env('WEB_APP_URL') ?: null,
            'staff_app_url' => Runtime::env('STAFF_APP_URL') ?: Runtime::env('BACKOFFICE_APP_URL') ?: null,
        ];

        return [
            'exit' => 0,
            'output' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        ];
    }

    /**
     * @param array<string, string|bool> $options
     * @return array{exit: int, output: string}
     */
    private function purge(array $options): array
    {
        $cache = $this->resolveCache();
        $cf = $this->resolveCloudflare();
        $invalidator = CacheInvalidator::fromEnvironment($cache, $cf);

        if (($options['all'] ?? false) === true) {
            $result = $invalidator->purgeAll();
            $ok = (bool) ($result['cloudflare']['ok'] ?? true);
            return [
                'exit' => $ok ? 0 : 1,
                'output' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
            ];
        }

        if (is_string($options['urls'] ?? null) && trim($options['urls']) !== '') {
            $paths = array_values(array_filter(array_map('trim', explode(',', $options['urls'])), static fn (string $v): bool => $v !== ''));
            $cleared = $cache->clear();
            $result = [
                'local_cleared' => $cleared,
                'cloudflare' => $invalidator->purgePaths($paths),
            ];
            $ok = (bool) ($result['cloudflare']['ok'] ?? true);
            return [
                'exit' => $ok ? 0 : 1,
                'output' => json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
            ];
        }

        return [
            'exit' => 2,
            'output' => "Specify --all or --urls=/,/journal.php for purge.\n",
        ];
    }

    /**
     * Generate a declarative Cloudflare Cache Ruleset (http_request_cache_settings phase).
     *
     * @param array<string, string|bool> $options
     * @return array{exit: int, output: string}
     */
    private function exportRules(array $options): array
    {
        $publicHost = is_string($options['public-host'] ?? null) && trim($options['public-host']) !== ''
            ? strtolower(trim($options['public-host']))
            : $this->extractHost(Runtime::env('PUBLIC_APP_URL') ?: Runtime::env('WEB_APP_URL'), 'aeesgs.org');
        $staffHost = is_string($options['staff-host'] ?? null) && trim($options['staff-host']) !== ''
            ? strtolower(trim($options['staff-host']))
            : $this->extractHost(Runtime::env('STAFF_APP_URL') ?: Runtime::env('BACKOFFICE_APP_URL'), 'admin.aeesgs.org');

        if (!self::isValidHost($publicHost) || !self::isValidHost($staffHost)) {
            return ['exit' => 2, 'output' => "Hostnames must be valid DNS hostnames without schemes or paths.\n"];
        }

        $ruleset = self::buildCacheRuleset($publicHost, $staffHost);

        return [
            'exit' => 0,
            'output' => json_encode($ruleset, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildCacheRuleset(string $publicHost, string $staffHost): array
    {
        $staticAssetsExpression = sprintf(
            '(http.host in {"%s" "%s"}) and (http.request.method in {"GET" "HEAD"}) '
            . 'and starts_with(http.request.uri.path, "/assets/")',
            $publicHost,
            $staffHost
        );

        $publicCacheExpression = sprintf(
            '(http.host eq "%s") and (http.request.method eq "GET") '
            . 'and not (http.cookie contains "AMEA_PUBLIC=" or http.cookie contains "AMEA_STAFF=" or http.cookie contains "PHPSESSID=") '
            . 'and (http.request.uri.path in {"/" "/index.php" "/legal-notice.php" "/journal.php" "/journal-article.php" "/journal-cover.php"} '
            . 'or starts_with(http.request.uri.path, "/uploads/slider/"))',
            $publicHost
        );

        // Cloudflare Cache Rules evaluate sequentially and the LAST matching rule wins.
        // Placing the fail-closed bypass rule last guarantees staff, non-GET, cookie-bearing,
        // and stateful routes always bypass while explicitly exempting GET/HEAD /assets/*.
        $bypassExpression = sprintf(
            '(http.host in {"%s" "%s"}) and not (starts_with(http.request.uri.path, "/assets/") and http.request.method in {"GET" "HEAD"}) '
            . 'and ((http.host eq "%s") or (http.request.method ne "GET") '
            . 'or (http.cookie contains "AMEA_PUBLIC=" or http.cookie contains "AMEA_STAFF=" or http.cookie contains "PHPSESSID=") '
            . 'or (http.request.uri.path in {"/register.php" "/registration-details.php" "/registration-preview.php" "/kyc-correction.php" "/accept-cgu.php"}))',
            $publicHost, $staffHost, $staffHost
        );

        return [
            'name' => 'AMEA Edge Cache Rules',
            'description' => 'Cache static assets on both hosts; cache anonymous public GET HTML and media honoring origin Cloudflare-CDN-Cache-Control headers; fail closed to bypass on staff, non-GET, sessioned, and stateful routes.',
            'kind' => 'zone',
            'phase' => 'http_request_cache_settings',
            'rules' => [
                [
                    'ref' => 'amea_cache_static_assets',
                    'description' => 'Cache versioned and unversioned GET/HEAD static assets under /assets/ on both public and staff hosts regardless of cookies',
                    'expression' => $staticAssetsExpression,
                    'action' => 'set_cache_settings',
                    'action_parameters' => [
                        'cache' => true,
                        'edge_ttl' => [
                            'mode' => 'bypass_by_default',
                        ],
                        'browser_ttl' => [
                            'mode' => 'respect_origin',
                        ],
                        'serve_stale' => [
                            'disable_stale_while_updating' => false,
                        ],
                        'respect_strong_etags' => true,
                    ],
                    'enabled' => true,
                ],
                [
                    'ref' => 'amea_cache_public_pages_and_media',
                    'description' => 'Cache anonymous public GET pages, published journal covers, and carousel photos using origin CDN-Cache-Control headers (bypassing any response without cache headers)',
                    'expression' => $publicCacheExpression,
                    'action' => 'set_cache_settings',
                    'action_parameters' => [
                        'cache' => true,
                        'edge_ttl' => [
                            'mode' => 'bypass_by_default',
                        ],
                        'browser_ttl' => [
                            'mode' => 'respect_origin',
                        ],
                        'serve_stale' => [
                            'disable_stale_while_updating' => false,
                        ],
                        'respect_strong_etags' => true,
                    ],
                    'enabled' => true,
                ],
                [
                    'ref' => 'amea_bypass_staff_and_stateful',
                    'description' => 'Fail-closed final rule: never cache administration host routes, non-GET requests, sessioned browsers, or stateful public workflows (exempting GET/HEAD /assets/)',
                    'expression' => $bypassExpression,
                    'action' => 'set_cache_settings',
                    'action_parameters' => [
                        'cache' => false,
                    ],
                    'enabled' => true,
                ],
            ],
        ];
    }

    private function resolveCache(): AppCache
    {
        return $this->cache ?? Container::cache($this->projectRoot);
    }

    private function resolveCloudflare(): CloudflareCachePurger
    {
        return $this->cloudflare ?? CloudflareCachePurger::fromEnvironment();
    }

    private function extractHost(string $url, string $fallback): string
    {
        $host = parse_url(trim($url), PHP_URL_HOST);
        return is_string($host) && $host !== '' ? strtolower($host) : $fallback;
    }

    private static function isValidHost(string $host): bool
    {
        return preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/', $host) === 1;
    }

    /**
     * @param list<string> $args
     * @return array<string, string|bool>
     */
    private function parseOptions(array $args): array
    {
        $options = [];
        foreach ($args as $arg) {
            if ($arg === '--all') {
                $options['all'] = true;
            } elseif (preg_match('/^--(urls|public-host|staff-host)=(.*)$/s', $arg, $match) === 1) {
                $options[$match[1]] = $match[2];
            }
        }
        return $options;
    }
}
