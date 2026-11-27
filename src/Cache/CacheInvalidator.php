<?php

declare(strict_types=1);

namespace Amea\Cache;

use Amea\Core\Runtime;

/**
 * Coordinates local origin AppCache invalidation and Cloudflare edge cache purges
 * whenever staff writes change cached public data or pages.
 */
final class CacheInvalidator
{
    public const JOURNAL_VIEWS = ['all', 'upcoming', 'reports', 'announcements', 'archives'];
    public const JOURNAL_PURGE_PAGES = 3;

    public function __construct(
        private AppCache $cache,
        private CloudflareCachePurger $cloudflare,
        private ?string $publicOrigin = null
    ) {
    }

    public static function fromEnvironment(AppCache $cache, ?CloudflareCachePurger $cloudflare = null): self
    {
        $origin = trim(Runtime::env('PUBLIC_APP_URL'));
        if ($origin === '') {
            $origin = trim(Runtime::env('WEB_APP_URL'));
        }
        if ($origin === '' && Runtime::appIdentity() === 'public') {
            $origin = trim(Runtime::env('APP_URL'));
        }
        $normalizedOrigin = self::normalizeOrigin($origin);

        return new self(
            $cache,
            $cloudflare ?? CloudflareCachePurger::fromEnvironment(),
            $normalizedOrigin
        );
    }

    public function onSliderChanged(?string $imagePath = null): array
    {
        $this->cache->invalidateNamespace(AppCache::NS_SLIDER);

        $paths = ['/', '/index.php'];
        if ($imagePath !== null && trim($imagePath) !== '') {
            $paths[] = '/' . ltrim(trim($imagePath), '/');
        }

        return $this->purgePaths($paths);
    }

    /**
     * Invalidate origin journal cache and purge affected Cloudflare edge URLs.
     *
     * Purges homepage, all journal views (pages 1–3, fitting cleanly in a single
     * 30-URL Cloudflare purge batch alongside article/cover URLs), the article URL,
     * and both unversioned and versioned cover URLs. The origin serves deeper pages uncached.
     *
     * @param list<string> $coverTokens Old and/or new cover cache-buster tokens.
     */
    public function onJournalChanged(?string $slug = null, array $coverTokens = []): array
    {
        $this->cache->invalidateNamespace(AppCache::NS_JOURNAL);

        $paths = ['/', '/index.php'];
        foreach (self::listingQueries() as $query) {
            $paths[] = '/journal.php' . ($query === '' ? '' : '?' . $query);
        }

        if ($slug !== null && trim($slug) !== '') {
            $encoded = rawurlencode(trim($slug));
            $paths[] = '/journal-article.php?slug=' . $encoded;
            $paths[] = '/journal-cover.php?slug=' . $encoded;
            foreach ($coverTokens as $token) {
                $cleanToken = trim($token);
                if ($cleanToken !== '') {
                    $paths[] = '/journal-cover.php?slug=' . $encoded . '&v=' . rawurlencode($cleanToken);
                }
            }
        }

        return $this->purgePaths($paths);
    }

    public static function listingQueries(): array
    {
        $queries = [''];
        foreach (self::JOURNAL_VIEWS as $view) {
            $queries[] = 'view=' . $view;
            for ($page = 1; $page <= self::JOURNAL_PURGE_PAGES; $page++) {
                $queries[] = 'view=' . $view . '&page=' . $page;
            }
        }

        return $queries;
    }

    public function onSettingsChanged(): array
    {
        $this->cache->invalidateNamespace(AppCache::NS_SETTINGS);

        $exact = $this->purgePaths(['/', '/index.php', '/legal-notice.php']);
        if ($this->publicOrigin === null) {
            return $exact;
        }
        $host = preg_replace('#^https?://#', '', $this->publicOrigin);
        $journal = $this->cloudflare->purgePrefixes([$host . '/journal.php', $host . '/journal-article.php']);
        return [
            'attempted' => $exact['attempted'] || $journal['attempted'],
            'ok' => $exact['ok'] && $journal['ok'],
            'purged' => $exact['purged'] + $journal['purged'],
            'batches' => $exact['batches'] + $journal['batches'],
            'errors' => array_merge($exact['errors'], $journal['errors']),
        ];
    }

    /**
     * @param list<string> $paths
     */
    public function purgePaths(array $paths): array
    {
        if ($this->publicOrigin === null) {
            return ['attempted' => false, 'ok' => true, 'purged' => 0, 'batches' => 0, 'errors' => []];
        }
        $urls = [];
        foreach ($paths as $path) {
            $trimmed = trim($path);
            if ($trimmed === '') {
                continue;
            }
            if (str_starts_with($trimmed, 'https://') || str_starts_with($trimmed, 'http://')) {
                $urls[] = $trimmed;
            } else {
                $urls[] = $this->publicOrigin . '/' . ltrim($trimmed, '/');
            }
        }
        return $this->cloudflare->purgeUrls($urls);
    }

    public function purgeAll(): array
    {
        $cleared = $this->cache->clear();
        $cloudflare = $this->cloudflare->purgeEverything();
        return ['local_cleared' => $cleared, 'cloudflare' => $cloudflare];
    }

    /**
     * Compute a stable cache-buster token for a journal cover relative path (without extension).
     */
    public static function coverVersionToken(?string $coverPath): ?string
    {
        if (!is_string($coverPath) || trim($coverPath) === '') {
            return null;
        }
        $stem = pathinfo($coverPath, PATHINFO_FILENAME);
        if (!is_string($stem) || $stem === '') {
            return null;
        }
        return substr($stem, 0, 12);
    }

    private static function normalizeOrigin(string $raw): ?string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        $scheme = strtolower((string) parse_url($trimmed, PHP_URL_SCHEME));
        $host = parse_url($trimmed, PHP_URL_HOST);
        $port = parse_url($trimmed, PHP_URL_PORT);
        if (!in_array($scheme, ['http', 'https'], true) || !is_string($host) || $host === '') {
            return null;
        }
        return $scheme . '://' . $host . ($port !== null ? ':' . $port : '');
    }
}
