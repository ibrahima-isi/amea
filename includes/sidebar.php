<!-- Sidebar -->
<div class="border-end" id="sidebar-wrapper" style="background-color: #213448; color: white;">
    <div class="sidebar-heading border-bottom text-white" style="background-color: #547792;">
        <i class="fas fa-graduation-cap"></i> AMEA Admin
    </div>
    <div class="list-group list-group-flush">
        <a href="dashboard.php" class="list-group-item list-group-item-action text-white active" 
           style="background-color: #213448; border-color: rgba(255,255,255,0.1);">
            <i class="fas fa-tachometer-alt me-2"></i> Tableau de bord
        </a>
        <a href="student-details.php" class="list-group-item list-group-item-action text-white" 
           style="background-color: #213448; border-color: rgba(255,255,255,0.1);">
            <i class="fas fa-user-graduate me-2"></i> Détails des étudiants
        </a>
        <a href="profile.php" class="list-group-item list-group-item-action text-white" 
           style="background-color: #213448; border-color: rgba(255,255,255,0.1);">
            <i class="fas fa-user-cog me-2"></i> Profil administrateur
        </a>
        <?php if ($role == 'admin'): ?>
        <a href="users.php" class="list-group-item list-group-item-action text-white" 
           style="background-color: #213448; border-color: rgba(255,255,255,0.1);">
            <i class="fas fa-users-cog me-2"></i> Gestion des utilisateurs
        </a>
        <?php endif; ?>
        <a href="export.php" class="list-group-item list-group-item-action text-white" 
           style="background-color: #213448; border-color: rgba(255,255,255,0.1);">
            <i class="fas fa-file-export me-2"></i> Exporter les données
        </a>
        <a href="logout.php" class="list-group-item list-group-item-action text-white" 
           style="background-color: #213448; border-color: rgba(255,255,255,0.1);">
            <i class="fas fa-sign-out-alt me-2"></i> Déconnexion
        </a>
    </div>
</div>