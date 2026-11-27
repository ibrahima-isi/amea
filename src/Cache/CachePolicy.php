<?php

declare(strict_types=1);

namespace Amea\Cache;

/**
 * Immutable HTTP caching policy separating browser and Cloudflare Edge (CDN) TTLs.
 */
final class CachePolicy
{
    public function __construct(
        public readonly int $browserMaxAge,
        public readonly int $cdnMaxAge,
        public readonly int $browserSwr = 0,
        public readonly int $cdnSwr = 86400,
        public readonly int $cdnSie = 604800,
        public readonly bool $mustRevalidate = false
    ) {
    }

    public static function publicHtml(
        int $browserMaxAge = 60,
        int $cdnMaxAge = 3600,
        int $browserSwr = 300,
        int $cdnSwr = 86400,
        int $cdnSie = 604800,
        bool $mustRevalidate = false
    ): self {
        return new self(
            max(0, $browserMaxAge),
            max(0, $cdnMaxAge),
            max(0, $browserSwr),
            max(0, $cdnSwr),
            max(0, $cdnSie),
            $mustRevalidate
        );
    }

    public static function publicMedia(
        int $browserMaxAge = 86400,
        int $cdnMaxAge = 604800,
        int $cdnSwr = 2592000,
        int $cdnSie = 2592000,
        bool $mustRevalidate = false
    ): self {
        $browser = max(0, $browserMaxAge);
        return new self(
            $browser,
            max(0, $cdnMaxAge),
            $mustRevalidate ? 0 : max(300, $browser),
            max(0, $cdnSwr),
            max(0, $cdnSie),
            $mustRevalidate
        );
    }

    public function browserCacheControl(): string
    {
        $header = 'public, max-age=' . $this->browserMaxAge;
        if ($this->mustRevalidate) {
            return $header . ', must-revalidate';
        }
        if ($this->browserSwr > 0) {
            $header .= ', stale-while-revalidate=' . $this->browserSwr;
        }
        return $header;
    }

    public function cdnCacheControl(): string
    {
        $header = 'public, max-age=' . $this->cdnMaxAge;
        if ($this->cdnSwr > 0) {
            $header .= ', stale-while-revalidate=' . $this->cdnSwr;
        }
        if ($this->cdnSie > 0) {
            $header .= ', stale-if-error=' . $this->cdnSie;
        }
        return $header;
    }
}
