<?php

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Core\FileUploader;
use Amea\Repository\SliderRepository;

class SliderController extends BaseController
{
    private SliderRepository $sliderRepo;
    private FileUploader $uploader;

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        if (!\hasPermission('slider')) {
            $this->flash->set('error', 'Accès refusé : vous n\'avez pas la permission de gérer le carrousel.');
            $this->redirect('dashboard.php');
            return;
        }
        $this->sliderRepo = new SliderRepository(Container::db());
        $this->uploader   = new FileUploader(__DIR__ . '/../../', Container::storage());
    }

    // ─── index ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->render('admin/pages/manage-slider.html.twig', [
            'images' => $this->sliderRepo->findAll(),
        ]);
    }

    // ─── POST dispatcher ───────────────────────────────────────────────────────

    public function handlePost(): void
    {
        $action = $_POST['_action'] ?? '';
        if ($action === 'delete') {
            $this->delete();
        } else {
            $this->store();
        }
    }

    // ─── store ─────────────────────────────────────────────────────────────────

    public function store(): void
    {
        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash->set('error', 'La session a expiré. Veuillez réessayer.');
            $this->redirect('manage-slider.php');
            return;
        }

        $result = $this->uploader->handle(
            $_FILES['slider_image'] ?? [],
            ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            5 * 1024 * 1024,
            'uploads/slider'
        );

        if (!$result['success']) {
            $this->flash->set('error', $result['message'] ?? 'Erreur lors du téléchargement.');
            $this->redirect('manage-slider.php');
            return;
        }

        try {
            $image = (new \Amea\Service\MemberDocumentService(Container::storage()))->open(
                $result['filepath'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
            );
            fclose($image['stream']);
        } catch (\Amea\Storage\StorageException|\OutOfBoundsException|\UnexpectedValueException $error) {
            error_log('[SliderController] ' . $error->getMessage());
            $this->flash->set('error', 'Échec du téléchargement. Veuillez réessayer.');
            $this->redirect('manage-slider.php');
            return;
        }

        $db = Container::db();
        $db->beginTransaction();
        try {
            (new \Amea\Repository\FileOperationRepository($db))->lockMembers();
            $this->sliderRepo->save([
                'image_path' => $result['filepath'],
                'caption' => trim($_POST['caption'] ?? ''),
                'display_order' => (int)($_POST['display_order'] ?? 0),
            ]);
            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) { $db->rollBack(); }
            // The unreferenced verified object stays available for reconciliation.
            throw $error;
        }

        $this->flash->set('success', 'L\'image a été ajoutée avec succès.');
        $this->redirect('manage-slider.php');
    }

    // ─── delete ────────────────────────────────────────────────────────────────

    public function delete(): void
    {
        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash->set('error', 'La session a expiré. Veuillez réessayer.');
            $this->redirect('manage-slider.php');
            return;
        }

        $id  = (int)($_POST['id'] ?? 0);
        $img = $this->sliderRepo->findById($id);
        if ($img) {
            $db = Container::db();
            $db->beginTransaction();
            try {
                (new \Amea\Repository\FileOperationRepository($db))->lockMembers();
                $this->sliderRepo->delete($id);
                Container::fileLifecycle()->scheduleDeletion(bin2hex(random_bytes(16)), [$img['image_path']], 'slider-removal');
                $db->commit();
            } catch (\Throwable $error) {
                if ($db->inTransaction()) { $db->rollBack(); }
                throw $error;
            }
            Container::fileLifecycle()->retryDeletions(100, false);
            $this->flash->set('success', 'L\'image a été supprimée.');
        }

        $this->redirect('manage-slider.php');
    }
}
