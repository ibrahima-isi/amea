document.addEventListener('DOMContentLoaded', function() {
    const flashDataElement = document.getElementById('flash-data');
    if (!flashDataElement || flashDataElement.textContent.trim() === '') {
        return;
    }

    try {
        const flashData = JSON.parse(flashDataElement.textContent);
        if (!flashData) {
            return;
        }

        let title = 'Information';
        if (flashData.type === 'success') title = 'Succès';
        if (flashData.type === 'error' || flashData.type === 'danger') title = 'Erreur';
        if (flashData.type === 'warning') title = 'Attention';
        const icon = flashData.type === 'danger' ? 'error' : flashData.type;

        Swal.fire({
            icon: icon,
            title: title,
            html: flashData.message,
        }).then(function() {
            if (flashData.redirect) {
                window.location.href = flashData.redirect;
            }
        });
    } catch (e) {
        console.error('Could not parse flash message JSON:', e);
    }
});
