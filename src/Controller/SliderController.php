<?php

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Core\FileUploader;
use Amea\Repository\SliderRepository;
use Amea\Service\SliderService;

class SliderController extends BaseController
{
    private SliderRepository $sliderRepo;
    private FileUploader $uploader;
    private SliderService $slider;

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
        $this->slider = new SliderService(Container::db(), Container::storage(), Container::fileLifecycle());
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
            $this->slider->publish($result['filepath'], trim($_POST['caption'] ?? ''), (int)($_POST['display_order'] ?? 0));
        } catch (\Amea\Storage\StorageException|\OutOfBoundsException|\UnexpectedValueException $error) {
            error_log('[SliderController] ' . $error->getMessage());
            $this->flash->set('error', 'Échec du téléchargement. Veuillez réessayer.');
            $this->redirect('manage-slider.php');
            return;
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
        if ($this->slider->remove($id)) {
            $this->flash->set('success', 'L\'image a été supprimée.');
        }

        $this->redirect('manage-slider.php');
    }
}
