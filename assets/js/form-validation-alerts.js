document.addEventListener('DOMContentLoaded', function () {
    const validationErrorsData = document.getElementById('validation-errors-data');

    if (validationErrorsData && validationErrorsData.textContent.trim() !== '') {
        try {
            const errors = JSON.parse(validationErrorsData.textContent);
            if (errors && Object.keys(errors).length > 0) {
                const errorMessages = Object.values(errors).join('<br>');
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur de validation',
                    html: 'Veuillez corriger les erreurs suivantes:<br><br>' + errorMessages,
                });
            }
        } catch (e) {
            console.error('Could not parse validation errors JSON:', e);
        }
    }
});
