// Basic client-side validation placeholder for register form
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('registrationForm');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    const requiredIds = ['nom','prenom','sexe','date_naissance','lieu_residence','etablissement','statut','domaine_etudes','niveau_etudes','telephone','email','type_logement'];
    let ok = true;
    requiredIds.forEach(id => {
      const el = document.getElementById(id);
      if (el && !el.value) ok = false;
    });
    if (!ok) {
      e.preventDefault();
      alert('Veuillez remplir tous les champs obligatoires.');
    }
  });
});
