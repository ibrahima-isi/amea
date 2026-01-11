document.addEventListener('DOMContentLoaded', function() {
    // === Flatpickr Initialization for Date de naissance ===
    const dateNaissanceInput = document.getElementById('date_naissance');
    if (dateNaissanceInput) {
        flatpickr(dateNaissanceInput, {
            locale: 'fr',
            dateFormat: 'Y-m-d', // The format that will be submitted with the form
            altInput: true, // Show user-friendly date
            altFormat: 'j F, Y', // How the user-friendly date will look
            maxDate: dateNaissanceInput.dataset.maxDate || new Date(), // Set maxDate from data attribute, or today if not found
            // You can add more options here like disable mobile, etc.
        });
    }

    // === Tagify Initialization for Nationalities ===
    const input = document.querySelector('input[name="nationalites"]');
    if (input) {
        fetch('assets/json/countries.json')
            .then(response => response.json())
            .then(countries => {
                new Tagify(input, {
                    whitelist: countries,
                    maxTags: 5,
                    dropdown: {
                        maxItems: 20,           // <- miximum allowed rendered suggestions
                        classname: "tags-look", // <- custom classname for this dropdown, so it could be targeted
                        enabled: 0,             // <- show suggestions on focus
                        closeOnSelect: false    // <- do not hide the suggestions dropdown once an item has been selected
                    }
                });
            });
    }

    // === Existing Logic ===
    const etablissementSelect = document.getElementById('etablissement');
    const autreEtablissementDiv = document.getElementById('autre_etablissement_div');
    const autreEtablissementInput = document.getElementById('autre_etablissement');

    const domaineSelect = document.getElementById('domaine_etudes');
    const autreDomaineDiv = document.getElementById('autre_domaine_div');
    const autreDomaineInput = document.getElementById('autre_domaine_etudes');

    const niveauSelect = document.getElementById('niveau_etudes');
    const autreNiveauDiv = document.getElementById('autre_niveau_div');
    const autreNiveauInput = document.getElementById('autre_niveau_etudes');

    const typeLogementSelect = document.getElementById('type_logement');
    const precisionLogementDiv = document.getElementById('precision_logement_div');

    const lieuResidenceSelect = document.getElementById('lieu_residence');
    const autreLieuResidenceDiv = document.getElementById('autre_lieu_residence_div');
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


    if (typeLogementSelect) {
        typeLogementSelect.addEventListener('change', function() {
            // Afficher le champ précision pour tous les choix sauf "En famille" (ou aucun) si désiré,
            // ou bien seulement pour "Autre".
            // Ici, supposons qu'on veut préciser l'adresse/quartier pour tout le monde sauf si vide.
            // OU suivre la logique précédente. Le code PHP semble juste stocker.
            // On va afficher 'precision_logement' tout le temps ou selon logique métier.
            // Dans le doute, on laisse affiché tout le temps (display: block dans le HTML ?)
            // Si le HTML a style="display:none", on le gère ici.
            // Vérifions le template... il n'a pas de style display:none inline, donc visible par défaut.
        });
    }

    // Validation Bootstrap
    const forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});