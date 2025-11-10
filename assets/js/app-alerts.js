document.addEventListener('DOMContentLoaded', function() {
    const flashDataElement = document.getElementById('flash-data');
    if (flashDataElement) {
        const flashData = JSON.parse(flashDataElement.textContent);
        if (flashData) {
            let title = 'Information';
            if (flashData.type === 'success') title = 'Succès';
            if (flashData.type === 'error' || flashData.type === 'danger') title = 'Erreur';
            if (flashData.type === 'warning') title = 'Attention';

            Swal.fire({
                icon: flashData.type,
                title: title,
                html: flashData.message,
            });
        }
    }
});
