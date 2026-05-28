document.addEventListener("DOMContentLoaded", function () {
  let tagify; // Variable to store Tagify instance

  // === Flatpickr Initialization for Date de naissance ===
  const birthDateInput = document.getElementById("birth_date");
  if (birthDateInput) {
    flatpickr(birthDateInput, {
      locale: "fr",
      dateFormat: "Y-m-d", // The format that will be submitted with the form
      altInput: true, // Show user-friendly date
      altFormat: "j F, Y", // How the user-friendly date will look
      maxDate: birthDateInput.dataset.maxDate || new Date(), // Set maxDate from data attribute, or today if not found
    });
  }

  // === Tagify Initialization for Nationalities ===
  const input = document.querySelector('input[name="nationalities"]');
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
  const institutionSelect = document.getElementById("institution");
  const otherInstitutionDiv = document.getElementById(
    "other_institution_wrapper",
  );
  const otherInstitutionInput = document.getElementById(
    "other_institution",
  );

  const studyFieldSelect = document.getElementById("study_field");
  const otherStudyFieldDiv = document.getElementById(
    "other_study_field_wrapper",
  );
  const otherStudyFieldInput = document.getElementById("other_study_field");

  const studyLevelSelect = document.getElementById("study_level");
  const otherStudyLevelDiv = document.getElementById("other_study_level_wrapper");
  const otherStudyLevelInput = document.getElementById("other_study_level");

  const housingTypeSelect = document.getElementById("housing_type");

  const residenceSelect = document.getElementById("residence");
  const otherResidenceDiv = document.getElementById(
    "other_residence_wrapper",
  );
  const otherResidenceInput = document.getElementById(
    "other_residence",
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

  if (institutionSelect) {
    institutionSelect.addEventListener("change", () =>
      toggleOtherField(
        institutionSelect,
        otherInstitutionDiv,
        otherInstitutionInput,
      ),
    );
    toggleOtherField(
      institutionSelect,
      otherInstitutionDiv,
      otherInstitutionInput,
    );
  }

  if (studyFieldSelect) {
    studyFieldSelect.addEventListener("change", () =>
      toggleOtherField(studyFieldSelect, otherStudyFieldDiv, otherStudyFieldInput),
    );
    toggleOtherField(studyFieldSelect, otherStudyFieldDiv, otherStudyFieldInput);
  }

  if (studyLevelSelect) {
    studyLevelSelect.addEventListener("change", () =>
      toggleOtherField(studyLevelSelect, otherStudyLevelDiv, otherStudyLevelInput),
    );
    toggleOtherField(studyLevelSelect, otherStudyLevelDiv, otherStudyLevelInput);
  }

  if (residenceSelect) {
    residenceSelect.addEventListener("change", () =>
      toggleOtherField(
        residenceSelect,
        otherResidenceDiv,
        otherResidenceInput,
      ),
    );
    toggleOtherField(
      residenceSelect,
      otherResidenceDiv,
      otherResidenceInput,
    );
  }

  const registrationForm = document.getElementById("registrationForm");
  const statusSelect = document.getElementById("status");

  function validateRequiredStatus(showAlert) {
    if (!statusSelect || statusSelect.value.trim() !== "") {
      if (statusSelect) {
        statusSelect.classList.remove("is-invalid");
        statusSelect.setCustomValidity("");
      }
      return true;
    }

    statusSelect.classList.add("is-invalid");
    statusSelect.setCustomValidity("Veuillez sélectionner votre statut.");

    if (showAlert && typeof Swal !== "undefined") {
      Swal.fire({
        icon: "error",
        title: "Erreur de validation",
        text: "Veuillez sélectionner votre statut.",
      });
    }

    statusSelect.focus();
    return false;
  }

  if (statusSelect) {
    statusSelect.addEventListener("change", () => validateRequiredStatus(false));
  }

  if (registrationForm) {
    registrationForm.addEventListener("submit", function (event) {
      if (!validateRequiredStatus(true)) {
        event.preventDefault();
      }
    });
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
