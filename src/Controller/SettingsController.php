<?php

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Repository\SettingRepository;

class SettingsController extends BaseController
{
    private SettingRepository $settingRepo;

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        if (!\hasPermission('settings')) {
            $this->flash->set('error', 'Accès refusé : vous n\'avez pas la permission de modifier les paramètres.');
            $this->redirect('dashboard.php');
            return;
        }
        $this->settingRepo = new SettingRepository(Container::db());
    }

    // ─── index ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->render('admin/pages/settings.html.twig', [
            'settings' => [
                'contact_email'    => $this->settingRepo->getValue('contact_email', 'admin@aeesgs.org'),
                'contact_phone'    => $this->settingRepo->getValue('contact_phone', '+221 XX XXX XX XX'),
                'association_name' => $this->settingRepo->getValue('association_name', 'AEESGS'),
            ],
        ]);
    }

    // ─── update ────────────────────────────────────────────────────────────────

    public function update(): void
    {
        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash->set('error', 'La session a expiré. Veuillez réessayer.');
            $this->redirect('settings.php');
            return;
        }

        foreach (['contact_email', 'contact_phone', 'association_name'] as $key) {
            if (isset($_POST[$key])) {
                $this->settingRepo->upsert($key, trim($_POST[$key]));
            }
        }

        $this->flash->set('success', 'Paramètres mis à jour avec succès.');
        $this->redirect('settings.php');
    }
}
