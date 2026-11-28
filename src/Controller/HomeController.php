<?php

namespace Amea\Controller;

use Amea\Cache\CachePolicy;
use Amea\Cache\HttpCache;
use Amea\Core\Container;
use Amea\Repository\JournalRepository;
use Amea\Repository\SliderRepository;

class HomeController extends BaseController
{
    private SliderRepository $sliderRepo;
    private JournalRepository $journalRepo;
    private bool $hasSession;

    public function __construct()
    {
        $this->hasSession = HttpCache::hasPublicSessionCookie();
        parent::__construct($this->hasSession);
        $db = Container::db();
        $this->sliderRepo = new SliderRepository($db);
        $this->journalRepo = new JournalRepository($db);
    }

    public function index(): void
    {
        $data = [
            'slider_images' => $this->sliderRepo->getActiveImages(),
            'journal_posts' => $this->journalRepo->findRecentPublished(3),
            'index_active' => true,
        ];

        if ($this->hasSession) {
            // Returning member with an active public session (e.g. post-registration/correction flash).
            $this->render('home/index.html.twig', $data);
            return;
        }

        // Browser max-age=0, must-revalidate ensures post-registration redirects to `/`
        // never serve a stale anonymous browser cache over a flash banner, while
        // Cloudflare Edge caches anonymous `/` globally for 1 hour (+ SWR/SIE).
        $this->renderPublicCached(
            'home/index.html.twig',
            $data,
            CachePolicy::publicHtml(
                browserMaxAge: 0,
                cdnMaxAge: 3600,
                browserSwr: 0,
                cdnSwr: 86400,
                cdnSie: 604800,
                mustRevalidate: true
            ),
            cacheable: HttpCache::hasQuery('')
        );
    }
}
