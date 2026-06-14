<?php

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Repository\UserRepository;

class ProfileController extends BaseController
{
    private UserRepository $userRepo;

    public function __construct()
    {
        parent::__construct();
        $this->userRepo = new UserRepository(Container::db());
    }

    // ─── show ──────────────────────────────────────────────────────────────────

    public function show(): void
    {
        $this->requireAuth();

        $userId = (int)$this->session->get('user_id');
        $user   = $this->userRepo->findById($userId);

        if (!$user) {
            $this->flash->set('error', 'Impossible de récupérer les informations de profil.');
            $this->redirect('dashboard.php');
            return;
        }

        $this->render('admin/pages/profile.html.twig', [
            'user' => $user,
        ]);
    }

    // ─── update ────────────────────────────────────────────────────────────────

    public function update(): void
    {
        $this->requireAuth();

        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash->set('error', 'La session a expiré. Veuillez réessayer.');
            $this->redirect('profile.php');
            return;
        }

        $action = $_POST['action'] ?? '';
        $userId = (int)$this->session->get('user_id');

        if ($action === 'update_profile') {
            $this->handleProfileUpdate($userId);
        } elseif ($action === 'change_password') {
            $this->handlePasswordChange($userId);
        } else {
            $this->flash->set('warning', 'Action non reconnue.');
            $this->redirect('profile.php');
        }
    }

    // ─── Private helpers ───────────────────────────────────────────────────────

    private function handleProfileUpdate(int $userId): void
    {
        $lastName  = trim($_POST['last_name'] ?? '');
        $firstName = trim($_POST['first_name'] ?? '');
        $email     = trim($_POST['email'] ?? '');

        if (empty($lastName) || empty($firstName) || empty($email)) {
            $this->flash->set('error', 'Tous les champs sont obligatoires.');
            $this->redirect('profile.php');
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash->set('error', 'Veuillez entrer une adresse email valide.');
            $this->redirect('profile.php');
            return;
        }

        if ($this->userRepo->existsByEmail($email, $userId)) {
            $this->flash->set('error', 'Cette adresse email est déjà utilisée par un autre utilisateur.');
            $this->redirect('profile.php');
            return;
        }

        $user = $this->userRepo->findById($userId);
        if (!$user) {
            $this->flash->set('error', 'Utilisateur introuvable.');
            $this->redirect('profile.php');
            return;
        }

        $this->userRepo->update($userId, [
            'username'    => $user->getUsername(),
            'email'       => $email,
            'last_name'   => $lastName,
            'first_name'  => $firstName,
            'role'        => $user->getRole(),
            'permissions' => $user->getRole() === 'admin'
                ? json_encode($user->getPermissions())
                : null,
            'is_active'   => (int)$user->isActive(),
        ]);

        // Update session display values
        $_SESSION['last_name']  = $lastName;
        $_SESSION['first_name'] = $firstName;

        $this->flash->set('success', 'Votre profil a été mis à jour avec succès.');
        $this->redirect('profile.php');
    }

    private function handlePasswordChange(int $userId): void
    {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $this->flash->set('error', 'Tous les champs de mot de passe sont obligatoires.');
            $this->redirect('profile.php');
            return;
        }

        if ($newPassword !== $confirmPassword) {
            $this->flash->set('error', 'Le nouveau mot de passe et sa confirmation ne correspondent pas.');
            $this->redirect('profile.php');
            return;
        }

        if (strlen($newPassword) < 8) {
            $this->flash->set('error', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
            $this->redirect('profile.php');
            return;
        }

        $user = $this->userRepo->findById($userId);
        if (!$user) {
            $this->flash->set('error', 'Utilisateur introuvable.');
            $this->redirect('profile.php');
            return;
        }

        if (!password_verify($currentPassword, (string)$user->getPassword())) {
            $this->flash->set('error', 'Le mot de passe actuel est incorrect.');
            $this->redirect('profile.php');
            return;
        }

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->userRepo->updatePassword($userId, $hashed);
        $this->userRepo->incrementSessionVersion($userId);

        $this->flash->set('success', 'Votre mot de passe a été changé avec succès. Veuillez vous reconnecter.');
        $this->redirect('login.php');
    }
}
