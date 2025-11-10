document.addEventListener('DOMContentLoaded', function () {
    const registrationForm = document.getElementById('registrationForm');

    if (registrationForm) {
        registrationForm.addEventListener('submit', function (event) {
            if (!validateForm()) {
                event.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Erreur de validation',
                    text: 'Veuillez corriger les erreurs dans le formulaire avant de soumettre.',
                });
            }
        });
    }

    function validateForm() {
        let isValid = true;

        // Clear previous errors
        const errorMessages = document.querySelectorAll('.text-danger');
        errorMessages.forEach(error => error.textContent = '');

        const inputs = registrationForm.querySelectorAll('input[required], select[required]');
        inputs.forEach(input => {
            input.classList.remove('is-invalid');
            if (!input.value.trim()) {
                isValid = false;
                showError(input, 'Ce champ est requis.');
            }
        });

        // Specific validations
        const email = document.getElementById('email');
        if (email.value.trim() && !isValidEmail(email.value)) {
            isValid = false;
            showError(email, 'Veuillez fournir une adresse email valide.');
        }

        const telephone = document.getElementById('telephone');
        if (telephone.value.trim() && !isValidPhone(telephone.value)) {
            isValid = false;
            showError(telephone, 'Le numéro de téléphone doit contenir exactement 9 chiffres.');
        }
        
        const etablissement = document.getElementById('etablissement');
        if (etablissement.value === 'Autre') {
            const autreEtablissement = document.getElementById('autre_etablissement');
            if (!autreEtablissement.value.trim()) {
                isValid = false;
                showError(autreEtablissement, "Veuillez préciser l'établissement.");
            }
        }

        const domaineEtudes = document.getElementById('domaine_etudes');
        if (domaineEtudes.value === 'Autre') {
            const autreDomaineEtudes = document.getElementById('autre_domaine_etudes');
            if (!autreDomaineEtudes.value.trim()) {
                isValid = false;
                showError(autreDomaineEtudes, "Veuillez préciser le domaine d'études.");
            }
        }

        const niveauEtudes = document.getElementById('niveau_etudes');
        if (niveauEtudes.value === 'Autre') {
            const autreNiveauEtudes = document.getElementById('autre_niveau_etudes');
            if (!autreNiveauEtudes.value.trim()) {
                isValid = false;
                showError(autreNiveauEtudes, "Veuillez préciser le niveau d'études.");
            }
        }


        return isValid;
    }

    function showError(input, message) {
        input.classList.add('is-invalid');
        const errorContainer = input.nextElementSibling;
        if (errorContainer && errorContainer.classList.contains('text-danger')) {
            errorContainer.textContent = message;
        }
    }

    function isValidEmail(email) {
        const re = /^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$/;
        return re.test(String(email).toLowerCase());
    }

    function isValidPhone(phone) {
        const re = /^[0-9]{9}$/;
        return re.test(String(phone));
    }
});