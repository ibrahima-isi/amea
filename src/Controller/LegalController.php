<?php

namespace Amea\Controller;

use Amea\Cache\CachePolicy;
use Amea\Cache\HttpCache;

class LegalController extends BaseController
{
    public function __construct()
    {
        parent::__construct(false);
    }

    public function index(): void
    {
        $this->renderPublicCached(
            'legal-notice.html.twig',
            [],
            CachePolicy::publicHtml(
                browserMaxAge: 3600,
                cdnMaxAge: 86400,
                browserSwr: 86400,
                cdnSwr: 604800,
                cdnSie: 604800
            ),
            cacheable: HttpCache::hasQuery('')
        );
    }
}
