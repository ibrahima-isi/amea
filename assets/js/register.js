document.addEventListener("DOMContentLoaded", function () {
  let tagify; // Variable to store Tagify instance

  // === Flatpickr Initialization for Date de naissance ===
  const dateNaissanceInput = document.getElementById("date_naissance");
  if (dateNaissanceInput) {
    flatpickr(dateNaissanceInput, {
      locale: "fr",
      dateFormat: "Y-m-d", // The format that will be submitted with the form
      altInput: true, // Show user-friendly date
      altFormat: "j F, Y", // How the user-friendly date will look
      maxDate: dateNaissanceInput.dataset.maxDate || new Date(), // Set maxDate from data attribute, or today if not found
    });
  }

  // === Tagify Initialization for Nationalities ===
  const input = document.querySelector('input[name="nationalites"]');
  if (input) {
    fetch("assets/json/countries.json")
      .then((response) => response.json())
      .then((countries) => {
        tagify = new Tagify(input, {
          whitelist: countries,
          enforceWhitelist: true, // Prevent custom inputs
          maxTags: 5,
          dropdown: {
            maxItems: 20,
            classname: "tags-look",
            enabled: 0,
            closeOnSelect: false,
          },
          hooks: {
            // Prevent removing the mandatory 'Guinée' tag from the UI
            beforeRemoveTag: function (tags) {
              try {
                var tagItem = tags && tags[0];
                var tagData = (tagItem && (tagItem.data || tagItem)) || {};
                var val = (tagData.value || "").toString().toLowerCase();
                if (val === "guinée" || val === "guinee" || val === "guinea") {
                  // reject removal by returning a rejected Promise
                  return Promise.reject();
                }
              } catch (err) {}
              return Promise.resolve();
            },
          },
        });

        // Prepopulate with Guinee (read-only) if not already present
        var existing =
          tagify.value &&
          tagify.value.map(function (t) {
            return (t.value || "").toLowerCase();
          });
        if (!existing || existing.indexOf("guinée") === -1) {
          tagify.addTags([{ value: "Guinée", readonly: true }], true);
        }

        // Clear error on change
        tagify.on("change", function () {
          if (tagify.value.length > 0) {
            tagify.DOM.scope.classList.remove("is-invalid");
            input.setCustomValidity("");
          }
        });
      });
  }

  // === Existing Logic ===
  const etablissementSelect = document.getElementById("etablissement");
  const autreEtablissementDiv = document.getElementById(
    "autre_etablissement_wrapper",
  );
  const autreEtablissementInput = document.getElementById(
    "autre_etablissement",
  );

  const domaineSelect = document.getElementById("domaine_etudes");
  const autreDomaineDiv = document.getElementById(
    "autre_domaine_etudes_wrapper",
  );
  const autreDomaineInput = document.getElementById("autre_domaine_etudes");

  const niveauSelect = document.getElementById("niveau_etudes");
  const autreNiveauDiv = document.getElementById("autre_niveau_etudes_wrapper");
  const autreNiveauInput = document.getElementById("autre_niveau_etudes");

  const typeLogementSelect = document.getElementById("type_logement");

  const lieuResidenceSelect = document.getElementById("lieu_residence");
  const autreLieuResidenceDiv = document.getElementById(
    "autre_lieu_residence_wrapper",
  );
  const autreLieuResidenceInput = document.getElementById(
    "autre_lieu_residence",
  );

  function toggleOtherField(selectElement, otherDiv, otherInput) {
    if (selectElement.value === "Autre") {
      otherDiv.style.display = "block";
      otherInput.required = selectElement.required;
    } else {
      otherDiv.style.display = "none";
      otherInput.required = false;
      otherInput.value = "";
    }
  }

  if (etablissementSelect) {
    etablissementSelect.addEventListener("change", () =>
      toggleOtherField(
        etablissementSelect,
        autreEtablissementDiv,
        autreEtablissementInput,
      ),
    );
    toggleOtherField(
      etablissementSelect,
      autreEtablissementDiv,
      autreEtablissementInput,
    );
  }

  if (domaineSelect) {
    domaineSelect.addEventListener("change", () =>
      toggleOtherField(domaineSelect, autreDomaineDiv, autreDomaineInput),
    );
    toggleOtherField(domaineSelect, autreDomaineDiv, autreDomaineInput);
  }

  if (niveauSelect) {
    niveauSelect.addEventListener("change", () =>
      toggleOtherField(niveauSelect, autreNiveauDiv, autreNiveauInput),
    );
    toggleOtherField(niveauSelect, autreNiveauDiv, autreNiveauInput);
  }

  if (lieuResidenceSelect) {
    lieuResidenceSelect.addEventListener("change", () =>
      toggleOtherField(
        lieuResidenceSelect,
        autreLieuResidenceDiv,
        autreLieuResidenceInput,
      ),
    );
    toggleOtherField(
      lieuResidenceSelect,
      autreLieuResidenceDiv,
      autreLieuResidenceInput,
    );
  }

  const sectionToggles = document.querySelectorAll(
    ".registration-section-toggle",
  );

  function panelHasUserData(panel) {
    return Array.from(panel.querySelectorAll("input, select, textarea")).some(
      (field) => {
        if (field.type === "file" || field.type === "hidden") {
          return false;
        }

        if (field.type === "checkbox" || field.type === "radio") {
          return field.checked;
        }

        return field.value.trim() !== "";
      },
    );
  }

  function panelHasErrors(panel) {
    return (
      panel.querySelector(".is-invalid, .text-danger:not(:empty)") !== null
    );
  }

  function setSectionState(toggle, panel, expanded, animate = true) {
    const label = toggle.querySelector(".registration-toggle-label");
    const startHeight = `${panel.scrollHeight}px`;

    toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
    panel.setAttribute("aria-hidden", expanded ? "false" : "true");
    panel.inert = !expanded;
    if (label) {
      label.textContent = expanded ? "Replier" : "Déplier";
    }

    if (!animate) {
      panel.classList.toggle("is-open", expanded);
      panel.style.height = expanded ? "auto" : "0px";
      return;
    }

    panel.style.height = expanded ? "0px" : startHeight;
    panel.classList.toggle("is-open", expanded);

    requestAnimationFrame(() => {
      panel.style.height = expanded ? `${panel.scrollHeight}px` : "0px";
    });

    const onTransitionEnd = (event) => {
      if (event.propertyName !== "height") {
        return;
      }

      if (expanded) {
        panel.style.height = "auto";
      }
      panel.removeEventListener("transitionend", onTransitionEnd);
    };
    panel.addEventListener("transitionend", onTransitionEnd);
  }

  sectionToggles.forEach((toggle) => {
    const panel = document.getElementById(toggle.getAttribute("aria-controls"));
    if (!panel) {
      return;
    }

    const shouldOpen = panelHasUserData(panel) || panelHasErrors(panel);
    setSectionState(toggle, panel, shouldOpen, false);

    toggle.addEventListener("click", () => {
      const expanded = toggle.getAttribute("aria-expanded") === "true";
      setSectionState(toggle, panel, !expanded);
    });
  });
});
