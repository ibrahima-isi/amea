<?php
$role   = $_SESSION['role']   ?? '';
$prenom = htmlspecialchars($_SESSION['prenom'] ?? '', ENT_QUOTES, 'UTF-8');
$nom    = htmlspecialchars($_SESSION['nom']    ?? '', ENT_QUOTES, 'UTF-8');

function sidebarActive(array $pages): string {
    return in_array(basename($_SERVER['PHP_SELF']), $pages) ? 'active' : '';
}
?>
<div id="sidebar-wrapper">

    <div class="sidebar-brand">
        <i class="fas fa-graduation-cap"></i>
        <span class="sidebar-brand-text">AEESGS Admin</span>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-section-label">Navigation</div>

        <a href="dashboard.php" class="sidebar-link <?= sidebarActive(['dashboard.php']) ?>">
            <i class="fas fa-chart-line"></i>
            <span>Tableau de bord</span>
        </a>

        <a href="students.php" class="sidebar-link <?= sidebarActive(['students.php', 'student-details.php', 'edit-student.php']) ?>">
            <i class="fas fa-user-graduate"></i>
            <span>Étudiants</span>
        </a>

        <a href="export.php" class="sidebar-link <?= sidebarActive(['export.php']) ?>">
            <i class="fas fa-file-export"></i>
            <span>Export</span>
        </a>

        <?php if ($role === 'admin'): ?>
        <div class="sidebar-section-label">Administration</div>

        <a href="users.php" class="sidebar-link <?= sidebarActive(['users.php', 'add-user.php', 'edit-user.php']) ?>">
            <i class="fas fa-users"></i>
            <span>Utilisateurs</span>
        </a>

        <a href="manage-slider.php" class="sidebar-link <?= sidebarActive(['manage-slider.php']) ?>">
            <i class="fas fa-images"></i>
            <span>Carrousel</span>
        </a>

        <a href="upgrade-levels.php" class="sidebar-link <?= sidebarActive(['upgrade-levels.php']) ?>">
            <i class="fas fa-arrow-up-right-dots"></i>
            <span>Mise à niveau</span>
        </a>

        <a href="settings.php" class="sidebar-link <?= sidebarActive(['settings.php']) ?>">
            <i class="fas fa-sliders-h"></i>
            <span>Paramètres</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="profile.php" class="sidebar-link <?= sidebarActive(['profile.php']) ?>">
            <i class="fas fa-user-circle"></i>
            <span><?= $prenom ?> <?= $nom ?></span>
        </a>
        <a href="logout.php" class="sidebar-link sidebar-link-logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Déconnexion</span>
        </a>
    </div>

</div>
