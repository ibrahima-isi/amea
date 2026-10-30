<?php
namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Repository\JournalRepository;
use Amea\Service\JournalMediaService;

final class JournalPublicController extends BaseController
{
    private JournalRepository $posts;

    public function __construct()
    {
        parent::__construct();
        $this->posts = new JournalRepository(Container::db());
    }

    private function post(): array
    {
        $slug = $_GET['slug'] ?? null;
        if (!is_string($slug) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || strlen($slug) > 255) {
            http_response_code(404); exit('Publication introuvable.');
        }
        $post = $this->posts->findPublishedBySlug($slug);
        if (!$post) { http_response_code(404); exit('Publication introuvable.'); }
        return $post;
    }

    public function index(): void
    {
        $views = ['all', 'upcoming', 'reports', 'announcements', 'archives'];
        $view = $_GET['view'] ?? 'all';
        if (!is_string($view) || !in_array($view, $views, true)) { $view = 'all'; }
        $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('Africa/Dakar')))->format('Y-m-d H:i:s');
        $this->render('journal/index.html.twig', ['result' => $this->posts->findPublicPage($view, $page, 12, $now), 'view' => $view, 'page' => $page, 'now' => $now]);
    }

    public function show(): void
    {
        $post = $this->post();
        $this->render('journal/show.html.twig', ['post' => $post, 'cover_url' => $post['cover_path'] ? 'journal-cover.php?slug=' . rawurlencode($post['slug']) : null]);
    }

    public function cover(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        $post = $this->post();
        $file = $post['cover_path'] ? (new JournalMediaService(__DIR__ . '/../..'))->resolve($post['cover_path']) : null;
        if (!$file || $file['mime'] !== $post['cover_mime']) { http_response_code(404); exit; }
        header('Content-Type: ' . $file['mime']);
        header('Content-Length: ' . $file['length']);
        readfile($file['path']);
    }
}
