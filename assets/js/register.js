document.addEventListener('DOMContentLoaded', function() {
    let tagify; // Variable to store Tagify instance

    // === Flatpickr Initialization for Date de naissance ===
    const dateNaissanceInput = document.getElementById('date_naissance');
    if (dateNaissanceInput) {
        flatpickr(dateNaissanceInput, {
            locale: 'fr',
            dateFormat: 'Y-m-d', // The format that will be submitted with the form
            altInput: true, // Show user-friendly date
            altFormat: 'j F, Y', // How the user-friendly date will look
            maxDate: dateNaissanceInput.dataset.maxDate || new Date(), // Set maxDate from data attribute, or today if not found
        });
    }

    // === Tagify Initialization for Nationalities ===
    const input = document.querySelector('input[name="nationalites"]');
    if (input) {
        fetch('assets/json/countries.json')
            .then(response => response.json())
            .then(countries => {
                tagify = new Tagify(input, {
                    whitelist: countries,
                    enforceWhitelist: true, // Prevent custom inputs
                    maxTags: 5,
                    dropdown: {
                        maxItems: 20,           // <- miximum allowed rendered suggestions
                        classname: "tags-look", // <- custom classname for this dropdown, so it could be targeted
                        enabled: 0,             // <- show suggestions on focus
                        closeOnSelect: false    // <- do not hide the suggestions dropdown once an item has been selected
                    }
                });
                
                // Clear error on change
                tagify.on('change', function() {
                    if (tagify.value.length > 0) {
                        tagify.DOM.scope.classList.remove('is-invalid');
                        input.setCustomValidity('');
                    }
                });
            });
    }

    // === Existing Logic ===
    const etablissementSelect = document.getElementById('etablissement');
    const autreEtablissementDiv = document.getElementById('autre_etablissement_wrapper');
    const autreEtablissementInput = document.getElementById('autre_etablissement');

    const domaineSelect = document.getElementById('domaine_etudes');
    const autreDomaineDiv = document.getElementById('autre_domaine_etudes_wrapper');
    const autreDomaineInput = document.getElementById('autre_domaine_etudes');

    const niveauSelect = document.getElementById('niveau_etudes');
    const autreNiveauDiv = document.getElementById('autre_niveau_etudes_wrapper');
    const autreNiveauInput = document.getElementById('autre_niveau_etudes');

    const typeLogementSelect = document.getElementById('type_logement');
    
    const lieuResidenceSelect = document.getElementById('lieu_residence');
    const autreLieuResidenceDiv = document.getElementById('autre_lieu_residence_wrapper');
    const autreLieuResidenceInput = document.getElementById('autre_lieu_residence');


    function toggleOtherField(selectElement, otherDiv, otherInput) {
        if (selectElement.value === 'Autre') {
            otherDiv.style.display = 'block';
            otherInput.required = true;
        } else {
            otherDiv.style.display = 'none';
            otherInput.required = false;
            otherInput.value = '';
        }
    }

    if (etablissementSelect) {
        etablissementSelect.addEventListener('change', () => toggleOtherField(etablissementSelect, autreEtablissementDiv, autreEtablissementInput));
        toggleOtherField(etablissementSelect, autreEtablissementDiv, autreEtablissementInput);
    }

    if (domaineSelect) {
        domaineSelect.addEventListener('change', () => toggleOtherField(domaineSelect, autreDomaineDiv, autreDomaineInput));
        toggleOtherField(domaineSelect, autreDomaineDiv, autreDomaineInput);
    }

    if (niveauSelect) {
        niveauSelect.addEventListener('change', () => toggleOtherField(niveauSelect, autreNiveauDiv, autreNiveauInput));
        toggleOtherField(niveauSelect, autreNiveauDiv, autreNiveauInput);
    }

    if (lieuResidenceSelect) {
        lieuResidenceSelect.addEventListener('change', () => toggleOtherField(lieuResidenceSelect, autreLieuResidenceDiv, autreLieuResidenceInput));
        toggleOtherField(lieuResidenceSelect, autreLieuResidenceDiv, autreLieuResidenceInput);
    }
});
