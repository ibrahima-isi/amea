<?php

namespace Amea\Controller;

use Amea\Core\Container;
use Amea\Repository\UserRepository;
use Amea\Service\UserService;

class UserController extends BaseController
{
    private UserRepository $userRepo;
    private UserService $userService;

    public function __construct()
    {
        parent::__construct();
        $db = Container::db();
        $this->userRepo    = new UserRepository($db);
        $this->userService = new UserService($this->userRepo);
    }

    // ─── index ─────────────────────────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAdmin();
        if (!\hasPermission('users')) {
            $this->flash->set('error', 'Accès refusé : vous n\'avez pas la permission de gérer les utilisateurs.');
            $this->redirect('dashboard.php');
            return;
        }

        $users = $this->userRepo->findAll();

        $this->render('admin/pages/users.html.twig', [
            'users'         => $users,
            'session_user'  => $this->session->get('user_id'),
        ]);
    }

    // ─── create ────────────────────────────────────────────────────────────────

    public function create(): void
    {
        $this->requireAdmin();
        if (!\hasPermission('users')) {
            $this->flash->set('error', 'Accès refusé : vous n\'avez pas la permission de gérer les utilisateurs.');
            $this->redirect('dashboard.php');
            return;
        }

        $this->render('admin/pages/add-user.html.twig', [
            'formData' => [],
            'errors'   => [],
            'modules'  => UserService::MODULES,
        ]);
    }

    // ─── store ─────────────────────────────────────────────────────────────────

    public function store(): void
    {
        $this->requireAdmin();
        if (!\hasPermission('users')) {
            $this->flash->set('error', 'Accès refusé : vous n\'avez pas la permission de gérer les utilisateurs.');
            $this->redirect('dashboard.php');
            return;
        }

        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash->set('error', 'La session a expiré. Veuillez réessayer.');
            $this->redirect('add-user.php');
            return;
        }

        $formData = [
            'username'         => trim($_POST['username'] ?? ''),
            'email'            => trim($_POST['email'] ?? ''),
            'last_name'        => trim($_POST['last_name'] ?? ''),
            'first_name'       => trim($_POST['first_name'] ?? ''),
            'password'         => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'role'             => $_POST['role'] ?? 'user',
            'is_active'        => (int)($_POST['is_active'] ?? 1),
            'permissions'      => $_POST['permissions'] ?? [],
        ];

        $errors = $this->validateCreate($formData);

        if (!empty($errors)) {
            $this->render('admin/pages/add-user.html.twig', [
                'formData' => $formData,
                'errors'   => $errors,
                'modules'  => UserService::MODULES,
            ]);
            return;
        }

        $this->userService->createUser($formData);

        $this->flash->set('success', 'L\'utilisateur a été ajouté avec succès.');
        $this->redirect('users.php');
    }

    // ─── edit ──────────────────────────────────────────────────────────────────

    public function edit(): void
    {
        $this->requireAdmin();
        if (!\hasPermission('users')) {
            $this->flash->set('error', 'Accès refusé : vous n\'avez pas la permission de gérer les utilisateurs.');
            $this->redirect('dashboard.php');
            return;
        }

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('users.php');
            return;
        }

        $user = $this->userRepo->findById($id);
        if (!$user) {
            $this->redirect('users.php');
            return;
        }

        // Only Super Admin (ID 1) can edit Super Admin
        if ($id === 1 && (int)$this->session->get('user_id') !== 1) {
            $this->flash->set('error', 'Accès refusé : seul le Super Administrateur peut modifier son propre compte.');
            $this->redirect('users.php');
            return;
        }

        $this->render('admin/pages/edit-user.html.twig', [
            'user'       => $user,
            'formData'   => [],
            'errors'     => [],
            'modules'    => UserService::MODULES,
            'isSuperAdmin' => ($id === 1),
        ]);
    }

    // ─── update ────────────────────────────────────────────────────────────────

    public function update(): void
    {
        $this->requireAdmin();
        if (!\hasPermission('users')) {
            $this->flash->set('error', 'Accès refusé : vous n\'avez pas la permission de gérer les utilisateurs.');
            $this->redirect('dashboard.php');
            return;
        }

        $id = (int)($_GET['id'] ?? 0);

        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash->set('error', 'La session a expiré. Veuillez réessayer.');
            $this->redirect('edit-user.php?id=' . $id);
            return;
        }

        $user = $this->userRepo->findById($id);
        if (!$user) {
            $this->redirect('users.php');
            return;
        }

        $formData = [
            'username'    => trim($_POST['username'] ?? ''),
            'email'       => trim($_POST['email'] ?? ''),
            'last_name'   => trim($_POST['last_name'] ?? ''),
            'first_name'  => trim($_POST['first_name'] ?? ''),
            'role'        => $_POST['role'] ?? 'user',
            'is_active'   => (int)($_POST['is_active'] ?? 0),
            'permissions' => $_POST['permissions'] ?? [],
        ];

        $errors = $this->validateUpdate($formData, $id);

        if (!empty($errors)) {
            $this->render('admin/pages/edit-user.html.twig', [
                'user'        => $user,
                'formData'    => $formData,
                'errors'      => $errors,
                'modules'     => UserService::MODULES,
                'isSuperAdmin' => ($id === 1),
            ]);
            return;
        }

        $isSelf           = ($id === (int)$this->session->get('user_id'));
        $isSuperAdminSession = ((int)$this->session->get('user_id') === 1);

        $this->userService->updateUser($id, $formData, $isSelf, $isSuperAdminSession);

        $roleDemoted = ($user->getRole() === 'admin' && $formData['role'] === 'user');
        if ($roleDemoted) {
            $this->flash->set('warning', 'Les informations ont été mises à jour. Le rôle a été changé en Utilisateur — toutes les permissions de ce compte ont été effacées.');
        } else {
            $this->flash->set('success', 'Les informations de l\'utilisateur ont été mises à jour avec succès.');
        }

        $this->redirect('users.php');
    }

    // ─── delete ────────────────────────────────────────────────────────────────

    public function delete(): void
    {
        $this->requireAdmin();
        if (!\hasPermission('users')) {
            $this->flash->set('error', 'Accès refusé : vous n\'avez pas la permission de gérer les utilisateurs.');
            $this->redirect('dashboard.php');
            return;
        }

        if (!\verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $this->flash->set('warning', 'La session a expiré. Veuillez réessayer.');
            $this->redirect('users.php');
            return;
        }

        $userId    = (int)($_POST['id'] ?? 0);
        $sessionId = (int)$this->session->get('user_id');

        if ($userId === 1) {
            $this->flash->set('error', 'Le Super Administrateur ne peut pas être supprimé.');
            $this->redirect('users.php');
            return;
        }

        if ($userId === $sessionId) {
            $this->flash->set('warning', 'Vous ne pouvez pas supprimer votre propre compte.');
            $this->redirect('users.php');
            return;
        }

        $this->userRepo->delete($userId);
        $this->flash->set('success', 'L\'utilisateur a été supprimé avec succès.');
        $this->redirect('users.php');
    }

    // ─── Validation helpers ────────────────────────────────────────────────────

    private function validateCreate(array $data): array
    {
        $errors = [];

        if (empty($data['username'])) {
            $errors['username'] = 'Le nom d\'utilisateur est requis.';
        }
        if (empty($data['email'])) {
            $errors['email'] = 'L\'adresse email est requise.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'L\'adresse email n\'est pas valide.';
        }
        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Le nom est requis.';
        }
        if (empty($data['first_name'])) {
            $errors['first_name'] = 'Le prénom est requis.';
        }
        if (empty($data['password'])) {
            $errors['password'] = 'Le mot de passe est requis.';
        }
        if ($data['password'] !== $data['confirm_password']) {
            $errors['confirm_password'] = 'Les mots de passe ne correspondent pas.';
        }

        if (empty($errors)) {
            if ($this->userRepo->existsByUsername($data['username'])) {
                $errors['username'] = 'Ce nom d\'utilisateur est déjà utilisé.';
            }
            if ($this->userRepo->existsByEmail($data['email'])) {
                $errors['email'] = 'Cet email est déjà utilisé.';
            }
        }

        return $errors;
    }

    private function validateUpdate(array $data, int $id): array
    {
        $errors = [];

        if (empty($data['username'])) {
            $errors['username'] = 'Le nom d\'utilisateur est requis.';
        }
        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Le nom est requis.';
        }
        if (empty($data['first_name'])) {
            $errors['first_name'] = 'Le prénom est requis.';
        }
        if (empty($data['email'])) {
            $errors['email'] = 'L\'adresse email est requise.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'L\'adresse email n\'est pas valide.';
        }

        if (empty($errors)) {
            if ($this->userRepo->existsByUsername($data['username'], $id)) {
                $errors['username'] = 'Ce nom d\'utilisateur est déjà utilisé.';
            }
            if ($this->userRepo->existsByEmail($data['email'], $id)) {
                $errors['email'] = 'Cet email est déjà utilisé.';
            }
        }

        return $errors;
    }
}
