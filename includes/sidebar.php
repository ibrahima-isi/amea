<!-- Sidebar -->
<div class="border-end" id="sidebar-wrapper">
    <div class="sidebar-heading border-bottom text-white">
        <i class="fas fa-graduation-cap"></i> AEESGS Admin
    </div>
    <div class="list-group list-group-flush">
        <a href="dashboard.php" class="list-group-item list-group-item-action text-white active">
            <i class="fas fa-tachometer-alt me-2"></i> Tableau de bord
        </a>
        <a href="students.php" class="list-group-item list-group-item-action text-white">
            <i class="fas fa-user-graduate me-2"></i> Étudiants
        </a>
        <a href="profile.php" class="list-group-item list-group-item-action text-white">
            <i class="fas fa-user-cog me-2"></i> Profil
        </a>
        <?php if ($role == 'admin'): ?>
            <a href="users.php" class="list-group-item list-group-item-action text-white">
                <i class="fas fa-users-cog me-2"></i> Utilisateurs
            </a>
        <?php endif; ?>
        <a href="export.php" class="list-group-item list-group-item-action text-white">
            <i class="fas fa-file-export me-2"></i> Export
        </a>
        <a href="logout.php" class="list-group-item list-group-item-action text-white">
            <i class="fas fa-sign-out-alt me-2"></i> Déconnexion
        </a>
    </div>
</div>
