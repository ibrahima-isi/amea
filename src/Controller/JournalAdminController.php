<?php
namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Repository\JournalRepository;
use Amea\Service\JournalService;
use Amea\Service\JournalMediaService;
use Amea\Service\JournalConflictException;

final class JournalAdminController extends BaseController
{
    private JournalRepository $posts;
    private JournalService $journal;
    private JournalMediaService $media;

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        if (!\hasPermission('journal')) {
            $this->flash->set('error', 'Accès refusé : vous ne pouvez pas gérer le journal.');
            $this->redirect('dashboard.php');
        }
        $this->posts = new JournalRepository(Container::db());
        $this->journal = new JournalService($this->posts);
        $this->media = new JournalMediaService(__DIR__ . '/../..');
    }

    private function id($value): ?int
    {
        if (!is_string($value) && !is_int($value)) { return null; }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : $id;
    }

    private function post(): array
    {
        $id = $this->id($_GET['id'] ?? null);
        $post = $id ? $this->posts->findById($id) : null;
        if (!$post) { http_response_code(404); exit('Publication introuvable.'); }
        return $post;
    }

    public function index(): void
    {
        $page = $this->id($_GET['page'] ?? 1) ?? 1;
        $filters = [];
        foreach (['type', 'status'] as $key) {
            if (is_string($_GET[$key] ?? null)) { $filters[$key] = $_GET[$key]; }
        }
        $this->render('admin/pages/journal-list.html.twig', ['result' => $this->posts->findAdminPage($page, 20, $filters), 'page' => $page, 'filters' => $filters]);
    }

    public function edit(): void
    {
        $post = isset($_GET['id']) ? $this->post() : null;
        $this->editor($post, null);
    }

    private function editor(?array $post, ?string $error): void
    {
        $this->render('admin/pages/journal-edit.html.twig', ['post' => $post, 'error' => $error]);
    }

    public function save(): void
    {
        if (!is_string($_POST['csrf_token'] ?? null) || !\verifyCsrfToken($_POST['csrf_token'])) {
            http_response_code(403); exit('Session expirée.');
        }
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? $this->id($_POST['id']) : null;
        if (isset($_POST['id']) && $_POST['id'] !== '' && $id === null) { http_response_code(404); exit('Publication introuvable.'); }
        $post = $id ? $this->posts->findById($id) : null;
        if ($id && !$post) { http_response_code(404); exit('Publication introuvable.'); }
        $revision = $id ? $this->id($_POST['revision'] ?? null) : null;
        if ($id && !$revision) { http_response_code(409); exit('Cette publication a changé. Actualisez la page.'); }
        $action = $_POST['_action'] ?? '';
        if (!is_string($action) || !in_array($action, ['save', 'publish', 'withdraw'], true)) { http_response_code(422); exit('Action invalide.'); }
        $cover = null;
        try {
            if ($action === 'withdraw') {
                if (!$id) { throw new \InvalidArgumentException('Publication introuvable.'); }
                $saved = $this->journal->withdraw($id, (int)$_SESSION['user_id'], $revision);
            } else {
                $input = $_POST;
                $publish = $action === 'publish';
                $this->journal->validate($id, $input, (int)$_SESSION['user_id'], $publish);
                if (($_FILES['cover']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $cover = $this->media->store($_FILES['cover']);
                }
                $saved = $this->journal->save($id, $input, (int)$_SESSION['user_id'], $publish, $cover);
            }
            $this->flash->set('success', 'Publication enregistrée.');
            $this->redirect('edit-journal.php?id=' . $saved['id']);
        } catch (JournalConflictException $e) {
            if ($cover) { $this->media->discard($cover['path']); }
            http_response_code(409); exit('Cette publication a changé. Actualisez la page.');
        } catch (\InvalidArgumentException $e) {
            if ($cover) { $this->media->discard($cover['path']); }
            http_response_code(422);
            $values = $post ?? [];
            foreach (['type','title','summary','body','cover_alt','location','activity_start_date','activity_start_time','activity_end_date','activity_end_time'] as $key) {
                if (is_string($_POST[$key] ?? null)) { $values[$key] = $_POST[$key]; }
            }
            $values['remove_cover'] = is_string($_POST['remove_cover'] ?? null) && $_POST['remove_cover'] === '1' ? '1' : '';
            $this->editor($values, $e->getMessage());
        } catch (\Throwable $e) {
            error_log('Journal save failed: ' . $e);
            http_response_code(500); exit('Impossible d’enregistrer la publication.');
        }
    }

    public function preview(): void
    {
        $post = $this->post();
        $this->render('admin/pages/journal-preview.html.twig', ['post' => $post, 'cover_url' => $post['cover_path'] ? 'journal-preview-cover.php?id=' . $post['id'] : null]);
    }

    public function cover(): void
    {
        $post = $this->post();
        $file = $post['cover_path'] ? $this->media->resolve($post['cover_path']) : null;
        if (!$file || $file['mime'] !== $post['cover_mime']) { http_response_code(404); exit; }
        header('Content-Type: ' . $file['mime']);
        header('Content-Length: ' . $file['length']);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($file['path']);
    }
}
