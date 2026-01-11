<!-- Sidebar -->
<div class="border-end bg-dark" id="sidebar-wrapper">
    <div class="sidebar-heading border-bottom text-white">
        <i class="fas fa-graduation-cap"></i> AEESGS Admin
    </div>
    <div class="list-group list-group-flush">
        <a href="dashboard.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt me-2"></i> Tableau de bord
        </a>
        <a href="students.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo (basename($_SERVER['PHP_SELF']) == 'students.php' || basename($_SERVER['PHP_SELF']) == 'student-details.php' || basename($_SERVER['PHP_SELF']) == 'edit-student.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-graduate me-2"></i> Étudiants
        </a>
        <a href="profile.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-cog me-2"></i> Profil
        </a>
        <?php if ($role == 'admin'): ?>
            <a href="users.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo (basename($_SERVER['PHP_SELF']) == 'users.php' || basename($_SERVER['PHP_SELF']) == 'add-user.php' || basename($_SERVER['PHP_SELF']) == 'edit-user.php') ? 'active' : ''; ?>">
                <i class="fas fa-users-cog me-2"></i> Utilisateurs
            </a>
            <a href="manage-slider.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo (basename($_SERVER['PHP_SELF']) == 'manage-slider.php') ? 'active' : ''; ?>">
                <i class="fas fa-images me-2"></i> Gérer le carrousel
            </a>
            <a href="settings.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'active' : ''; ?>">
                <i class="fas fa-cogs me-2"></i> Paramètres
            </a>
        <?php endif; ?>
        <a href="export.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo (basename($_SERVER['PHP_SELF']) == 'export.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-export me-2"></i> Export
        </a>
        <a href="logout.php" class="list-group-item list-group-item-action bg-dark text-white <?php echo (basename($_SERVER['PHP_SELF']) == 'logout.php') ? 'active' : ''; ?>">
            <i class="fas fa-sign-out-alt me-2"></i> Déconnexion
        </a>
    </div>
</div>