# Consolidation Compose — suivi

## Autorisation

Le 31 octobre 2026, les quatre choix et le [cadrage global](design.md) sont approuvés ; l’utilisateur autorise l’application au dépôt et la PR vers `dev`. Aucun déploiement réel n’est demandé.

Base : `origin/dev` à `34d8ea3`. Branche isolée : `infra/consolidate-dokploy-compose`, worktree `/private/tmp/amea-compose-consolidation-20261031`. Le checkout principal et les autres worktrees restent préservés.

## Exécution

- [x] Remplacer le contenu du fichier canonique, supprimer le doublon, préserver MariaDB/volumes, sessions/cache et défauts mail/build.
- [x] Actualiser le guide maintenu et vérifier les références : seul le cadrage historique nomme encore le fichier supprimé.
- [ ] Valider la résolution Compose et les contrôles pertinents avec des données synthétiques.
- [ ] Effectuer les revues indépendantes et corriger leurs constats éventuels.
- [ ] Créer un commit signé, ouvrir la PR vers `dev` et vérifier la CI de sa tête finale.

L’intégration locale et le nettoyage de cette branche resteront conditionnés à une fusion effective dans `dev`. Aucune migration, modification DNS, activation d’ordonnanceur ou livraison SMTP réelle n’est effectuée.

## Contrôles locaux

Résolution réelle par Docker Compose : 29 assertions PASS avec un environnement synthétique, sans charger `.env`. Le service `db` résolu est identique à l’ancien ; noms des quatre volumes et réseaux conservés pour le même projet ; `public`/`staff` actifs et `jobs` optionnel ; sessions/cache séparés ; mail synchrone sans variable d’image obligatoire. La qualification de démarrage MariaDB synthétique reste en cours.

Suites existantes PASS : PHPUnit 159 tests / 310 assertions avec les 10 dépréciations de métadonnées préexistantes ; scripts PHP 48/186/78/24 ; parcours HTTP 35 et 28. Les fichiers de test et la configuration CI sont inchangés. Les liens Markdown modifiés et `git diff --check` sont vérifiés avant publication.
