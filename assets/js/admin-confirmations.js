document.addEventListener('DOMContentLoaded', function() {
    document.body.addEventListener('click', function(e) {
        const target = e.target;
        const form = target.closest('form');

        let title, text, confirmButtonText;

        if (target.matches('.btn-delete-user') || target.closest('.btn-delete-user')) {
            title = 'Êtes-vous sûr ?';
            text = "Cette action est irréversible !";
            confirmButtonText = 'Oui, supprimer !';
        } else if (target.matches('.btn-reset-password') || target.closest('.btn-reset-password')) {
            title = 'Êtes-vous sûr ?';
            text = "Vous êtes sur le point de réinitialiser le mot de passe de cet utilisateur.";
            confirmButtonText = 'Oui, réinitialiser !';
        } else if (target.matches('.btn-delete-student') || target.closest('.btn-delete-student')) {
            title = 'Êtes-vous sûr ?';
            text = "Voulez-vous vraiment supprimer cet étudiant ?";
            confirmButtonText = 'Oui, supprimer !';
        } else {
            return; // Not a confirmation button
        }

        e.preventDefault();

        Swal.fire({
            title: title,
            html: text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: confirmButtonText,
            cancelButtonText: 'Annuler'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});
