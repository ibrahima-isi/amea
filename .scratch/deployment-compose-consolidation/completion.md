# Consolidation Compose — suivi

## Autorisation

Le 31 octobre 2026, les quatre choix et le [cadrage global](design.md) sont approuvés ; l’utilisateur autorise l’application au dépôt et la PR vers `dev`. Aucun déploiement réel n’est demandé.

Base : `origin/dev` à `34d8ea3`. Branche isolée : `infra/consolidate-dokploy-compose`, worktree `/private/tmp/amea-compose-consolidation-20261031`. Le checkout principal et les autres worktrees restent préservés.

## Exécution

- [x] Remplacer le contenu du fichier canonique, supprimer le doublon, préserver MariaDB/volumes, sessions/cache et défauts mail/build.
- [x] Actualiser le guide maintenu et vérifier les références : seul le cadrage historique nomme encore le fichier supprimé.
- [x] Valider la résolution Compose et les contrôles pertinents avec des données synthétiques.
- [x] Effectuer les revues indépendantes : Standards 0 / Spec 0 constat, aucun correctif demandé.
- [ ] Créer un commit signé, ouvrir la PR vers `dev` et vérifier la CI de sa tête finale.

Commit source signé : `26f0cf99777a3e0dd0f196fb0617cbd7f262d420`. La [PR #22](https://github.com/ibrahima-isi/amea/pull/22) cible `dev` ; la CI de sa tête finale reste à vérifier. Les commits de preuves suivants ne modifient pas la configuration validée. L’intégration locale et le nettoyage de cette branche resteront conditionnés à une fusion effective dans `dev`.

Aucune migration ou reconfiguration de production, modification DNS, activation d’ordonnanceur de production ou livraison SMTP réelle n’est effectuée. Les migrations citées ci-dessous concernent uniquement la base synthétique jetable.

## Contrôles locaux

Résolution réelle par Docker Compose : 29 assertions PASS avec un environnement synthétique, sans charger `.env`. Le service `db` résolu est identique à l’ancien ; noms des quatre volumes et réseaux conservés pour le même projet ; `public`/`staff` actifs et `jobs` optionnel ; sessions/cache séparés ; mail synchrone sans variable d’image obligatoire.

Suites existantes PASS : PHPUnit 159 tests / 310 assertions avec les 10 dépréciations de métadonnées préexistantes ; scripts PHP 48/186/78/24 ; parcours HTTP 35 et 28. Les fichiers de test et la configuration CI sont inchangés. Les liens Markdown modifiés et `git diff --check` sont vérifiés avant publication.

## Validation du manifeste canonique en fonctionnement

Docker Compose 5.3.0 et Docker Engine 29.6.1 : construction des cibles du dépôt et démarrage sur MariaDB `10.11.19-MariaDB-ubu2204`, dans un projet neuf contrôlé par labels exacts. Un override temporaire crée uniquement des réseaux internes propres au test, sans port publié ; `APP_ENV=test`, identifiants synthétiques, SMTP vide, mail synchrone et aucun job lancé.

Les huit migrations 00 à 07 sont appliquées manuellement à `amea_rehearsal`. PASS : démarrage public sans staff ; accueil/journal publics ; connexion staff, authentification synthétique, CSRF et publication retrouvée côté public ; quatre refus de routes de la mauvaise application ; visibilité des trois volumes de fichiers partagés ; volumes/cookies de session distincts ; caches isolés. Après recréation de public, la publication en base, les fichiers partagés et la session publique sont conservés. Tous les conteneurs, volumes et réseaux des deux tentatives jetables sont supprimés.

La première tentative a rencontré une erreur de citation et de nom de table dans le seul helper temporaire de persistance. Ce helper est corrigé hors dépôt puis la répétition complète est passée sur un nouveau projet ; aucun code produit n’a changé. Le manifeste re-résolu après le commit est identique au snapshot validé. SHA-256 de `compose.dokploy.yml` : `240e5d99a80fd7082876caac9fd3646070fe40c46feb0388fb0c0497c4ca9c00`.

Artefacts locaux : `/private/tmp/amea-compose-validation-20261031.md`, `/private/tmp/amea-compose-static-validation-20261031.log` et `/private/tmp/amea-compose-runtime-validation-20261031.log`. Cette vérification HTTP interne ne qualifie ni navigateur, ni proxy TLS, ni données/identités de ressources réelles, ni déploiement de production. La QA navigateur du journal reste reportée à l’utilisateur.

## Revues

Deux reviewers à contexte neuf ont inspecté séparément `34d8ea3...26f0cf9` en lecture seule : Standards 0 violation documentée / 0 heuristique matérielle ; Spec 0 constat. Rapports locaux `/private/tmp/amea-compose-standards-review-20261031.md` et `/private/tmp/amea-compose-spec-review-20261031.md`. Ces revues statiques ne sont pas présentées comme des tests runtime exécutés par les reviewers.
