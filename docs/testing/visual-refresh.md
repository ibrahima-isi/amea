# Qualification du rafraîchissement AEESGS

Les scénarios HTTP synthétiques sont exécutés par `tests/apps/run.sh` sur un projet Docker jetable. `visual-refresh.py --empty` intervient après la fixture initiale, avant toute inscription. `--populated` intervient après `member-fixture.php`, avant les mutations de `members.py`. `--tools` utilise ensuite la fixture existante des administrateurs disposant des permissions des outils, avant `tools.py`.

La qualification vérifie la file vide, le dossier à examiner et son lien KYC privé, la navigation française, le filtrage des modules et dossiers pour un utilisateur ordinaire, ses accès autorisés aux adhérents et au profil, et la disponibilité HTTP des ressources communes dans les modules existants et l’authentification. Les fixtures et les anciens scénarios sont conservés.

Cette preuve HTTP ne qualifie pas le rendu visuel, le clavier, les points de rupture responsive ou les thèmes dans un navigateur. Le rapport d’exécution exact est dans `.superpowers/sdd/2026-10-28-public-backoffice-refresh/task-4-report.md`; les journaux locaux ne sont pas versionnés.

Exécution locale du 28 octobre 2026 sur la source `4ab4f3b` et les nouveaux tests : runner complet sans navigateur, code de sortie 0, 404 assertions `PASS:`. Retour arrière sur l’image conservée, redéploiement et restauration SQL/fichiers séparée validés. Les projets jetables et leurs volumes sont supprimés. Les quatre scripts PHP passent (47, 186, 78 et 24 tests), PHPUnit passe (143 tests, 250 assertions, 10 dépréciations existantes), et les scénarios HTTP historiques passent (35 + 28 assertions).

Le scénario `--tools` soumet aussi un export PDF réel du champ `last_name` avec CSRF, vérifie la redirection, la valeur synthétique `Legacy`, les commandes retour/impression et les ressources de thème de l’aperçu autonome. Une seconde lecture vérifie la redirection existante après consommation des données de session. Ce contrôle HTTP ne qualifie pas le rendu sombre ni l’impression dans un navigateur.
