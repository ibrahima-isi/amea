document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('role');
    if (roleSelect) {
        const originalRole = roleSelect.value;
        roleSelect.addEventListener('change', function () {
            if (this.value !== originalRole) {
                const confirmation = confirm('Êtes-vous sûr de vouloir changer le rôle de cet utilisateur ?');
                if (!confirmation) {
                    this.value = originalRole;
                }
            }
        });
    }
});