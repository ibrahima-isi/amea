# Publication préparée — en attente d'autorisation explicite

Destination configurée et vérifiée localement : `git@github-isi:ibrahima-isi/amea.git`, soit [ibrahima-isi/amea](https://github.com/ibrahima-isi/amea).

Action proposée : publier la fin de la branche `feat/public-backoffice-refresh` et créer une PR **draft vers dev**. La branche distante existe déjà au commit `d215b79`; aucun push supplémentaire n'a été exécuté après le rejet automatique de publication.

Contenu supplémentaire : correctif de l'aperçu d'export (Twig/CSS), couverture HTTP du parcours PDF et session, compte rendu des tests, suivi de complétion et rapports de revue, empreintes des ressources servies localement. Les comptes de test cités sont synthétiques. Le corps préparé de PR est conservé localement dans `local-runtime/pr-body.md` et précise les réserves de QA/CI.

Exclusions du payload : `.env`, uploads et documents d'adhérents, base de données/sauvegardes, vendor, caches, journaux bruts et tout `local-runtime/`.

Le contrôle automatique a estimé la destination non vérifiée et l'autorisation limitée aux travaux locaux/préparation. Il a rejeté le commit+push groupé. Les opérations locales sont préparées séparément ; l'envoi vers GitHub nécessite désormais une autorisation utilisateur explicite avant toute nouvelle tentative. La PR restera draft tant que la QA navigateur réelle est indisponible.
