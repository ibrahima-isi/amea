document.addEventListener('DOMContentLoaded', function () {
    function setupOtherFieldListener(selectId, wrapperId, inputId) {
        const selectElement = document.getElementById(selectId);
        if (!selectElement) return;

        selectElement.addEventListener('change', function () {
            var wrapper = document.getElementById(wrapperId);
            var input = document.getElementById(inputId);
            if (this.value === 'Autre') {
                wrapper.style.display = 'block';
                input.setAttribute('required', 'required');
            } else {
                wrapper.style.display = 'none';
                input.removeAttribute('required');
            }
        });
    }

    setupOtherFieldListener('etablissement', 'autre_etablissement_wrapper', 'autre_etablissement');
    setupOtherFieldListener('domaine_etudes', 'autre_domaine_etudes_wrapper', 'autre_domaine_etudes');
    setupOtherFieldListener('niveau_etudes', 'autre_niveau_etudes_wrapper', 'autre_niveau_etudes');
    setupOtherFieldListener('lieu_residence', 'autre_lieu_residence_wrapper', 'autre_lieu_residence');

    const dateNaissance = document.getElementById('date_naissance');
    if (dateNaissance) {
        flatpickr(dateNaissance, {
            altInput: true,
            altFormat: "j F Y",
            dateFormat: "Y-m-d",
            maxDate: dateNaissance.getAttribute('max'),
            locale: "fr"
        });
    }
});
