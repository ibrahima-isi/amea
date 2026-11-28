<?php
namespace Amea\Controller;

use Amea\Cache\CacheInvalidator;
use Amea\Cache\CachePolicy;
use Amea\Cache\HttpCache;
use Amea\Core\Container;
use Amea\Repository\JournalRepository;
use Amea\Service\JournalMediaService;

final class JournalPublicController extends BaseController
{
    private JournalRepository $posts;

    public function __construct()
    {
        // Public reading is session-free; defer Twig initialization so cover() skips view/settings overhead.
        parent::__construct(false, true);
        $this->posts = new JournalRepository(Container::db());
    }

    private function post(): array
    {
        $slug = $_GET['slug'] ?? null;
        if (!is_string($slug) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || strlen($slug) > 255) {
            HttpCache::sendNoStore(false);
            http_response_code(404);
            exit('Publication introuvable.');
        }
        $post = $this->posts->findPublishedBySlug($slug);
        if (!$post) {
            HttpCache::sendNoStore(false);
            http_response_code(404);
            exit('Publication introuvable.');
        }
        return $post;
    }

    public function index(): void
    {
        $views = CacheInvalidator::JOURNAL_VIEWS;
        $view = $_GET['view'] ?? 'all';
        if (!is_string($view) || !in_array($view, $views, true)) { $view = 'all'; }
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Dakar')))->format('Y-m-d H:i:s');
        $result = $this->posts->findPublicPage($view, $page, 12, $now);

        // Activity time-boundary views use a shorter edge TTL so ended activities transition promptly.
        $timeSensitive = in_array($view, ['upcoming', 'archives'], true);
        $policy = CachePolicy::publicHtml(
            browserMaxAge: 60,
            cdnMaxAge: $timeSensitive ? 300 : 1800,
            browserSwr: $timeSensitive ? 60 : 300,
            cdnSwr: $timeSensitive ? 600 : 86400,
            cdnSie: $timeSensitive ? 86400 : 604800
        );
        $cacheable = HttpCache::hasQuery(...CacheInvalidator::listingQueries());
        $this->renderPublicCached(
            'journal/index.html.twig',
            ['result' => $result, 'view' => $view, 'page' => $page, 'now' => $now],
            $policy,
            cacheable: $cacheable
        );
    }

    public function show(): void
    {
        $post = $this->post();
        $coverUrl = null;
        if (!empty($post['cover_path'])) {
            $token = CacheInvalidator::coverVersionToken((string) $post['cover_path']);
            $coverUrl = 'journal-cover.php?slug=' . rawurlencode($post['slug'])
                . ($token !== null ? '&v=' . rawurlencode($token) : '');
        }

        $this->renderPublicCached(
            'journal/show.html.twig',
            ['post' => $post, 'cover_url' => $coverUrl],
            CachePolicy::publicHtml(
                browserMaxAge: 60,
                cdnMaxAge: 3600,
                browserSwr: 300,
                cdnSwr: 86400,
                cdnSie: 604800
            ),
            cacheable: HttpCache::hasQuery('slug=' . rawurlencode($post['slug']))
        );
    }

    public function cover(): void
    {
        // Fail-closed privacy headers for draft, withdrawn, invalid, or error responses.
        header('X-Content-Type-Options: nosniff');
        HttpCache::sendNoStore(false);
        $post = $this->post();
        if (empty($post['cover_path']) || !is_string($post['cover_path'])) {
            http_response_code(404);
            exit;
        }

        $media = new JournalMediaService(__DIR__ . '/../..', Container::storage());
        try {
            $metadata = $media->inspect($post['cover_path']);
        } catch (\OutOfBoundsException|\UnexpectedValueException) {
            http_response_code(404);
            exit;
        } catch (\Amea\Storage\StorageException) {
            http_response_code(503);
            exit;
        }
        if ($metadata === null) {
            http_response_code(404);
            exit;
        }

        $etagPrefix = 'journal-cover:' . $post['id'] . ':' . $post['revision'] . ':' . $post['cover_path'] . ':';
        $lastModified = (int) ($metadata['createdAt'] ?? 0) ?: null;
        $policy = CachePolicy::publicMedia(
            browserMaxAge: 300,
            cdnMaxAge: 86400,
            cdnSwr: 604800,
            cdnSie: 604800,
            mustRevalidate: true
        );
        // Only short-circuit 304 before streaming when metadata carries a write-verified SHA-256.
        $allowMetadata304 = $metadata['sha256'] !== null
            && $metadata['mime'] === $post['cover_mime']
            && $metadata['mime'] === match (pathinfo($post['cover_path'], PATHINFO_EXTENSION)) {
                'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', default => null,
            };
        $query = 'slug=' . rawurlencode($post['slug']);
        $token = CacheInvalidator::coverVersionToken($post['cover_path']);
        $cacheable = HttpCache::hasQuery($query)
            || ($token !== null && HttpCache::hasQuery($query . '&v=' . rawurlencode($token)));

        try {
            HttpCache::respondPublicMedia(
                $etagPrefix,
                $metadata['sha256'],
                $lastModified,
                $policy,
                $allowMetadata304,
                static function () use ($media, $post, $metadata): array {
                    $file = $media->open($post['cover_path'], $metadata);
                    if ($file['mime'] !== $post['cover_mime']) {
                        fclose($file['stream']);
                        throw new \UnexpectedValueException('Image invalide.');
                    }
                    return $file;
                },
                cacheable: $cacheable
            );
        } catch (\OutOfBoundsException|\UnexpectedValueException) {
            http_response_code(404);
            exit;
        } catch (\Amea\Storage\StorageException) {
            http_response_code(503);
            exit;
        }
    }
}
