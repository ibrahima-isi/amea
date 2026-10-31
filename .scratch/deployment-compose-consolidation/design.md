# Consolidation de la configuration Docker Compose de déploiement

## Cadrage confirmé — 31 octobre 2026

Les quatre choix ci-dessous et le cadrage global sont confirmés par réponses interactives le 31 octobre 2026. L’utilisateur a autorisé leur application dans le dépôt et l’ouverture d’une PR vers `dev`. L’exécution est suivie dans [completion.md](completion.md).

1. **Un fichier canonique :** `compose.dokploy.yml` décrit la pile public/back-office. Supprimer `compose.apps.dokploy.yml` et mettre à jour ses références ; Git conserve l’ancienne configuration.
2. **Propriété des données conservée :** garder le service `db` avec MariaDB 10.11, les clés de volumes `amea_db`, `amea_uploads`, `amea_storage`, `amea_logs`, ainsi que le réseau de données Compose existant. Remplacer le service applicatif unique par `public` et `staff`. Ne pas externaliser la base ni renommer ses volumes dans cette consolidation.
3. **Comportement mail conservé :** `MAIL_ASYNC=0` par défaut. Le service `jobs` reste derrière un profil optionnel, désactivé par défaut ; son activation et la désignation d’un seul ordonnanceur seront une opération distincte.
4. **Construction depuis le dépôt :** Dokploy construit les cibles `public`, `staff` et, si demandé ultérieurement, `jobs` de `docker/Dockerfile.apps`. Les trois variables `AMEA_*_IMAGE` ne deviennent pas des prérequis de déploiement.

## Invariants de la séparation

Préserver les origines publiques et staff distinctes, les routes autorisées par application, les cookies et volumes de session séparés, ainsi que le cache Twig en tmpfs propre à chaque conteneur. Les volumes de données existants sont montés aux chemins attendus par les nouvelles images sous `/opt/amea`. Le back-office et le public partagent provisoirement les données et fichiers conformément à [l’ADR 0001](../../docs/adr/0001-independent-public-and-staff-entry.md).

## Périmètre approuvé

Modifier `compose.dokploy.yml` et le guide `docs/operations/public-staff-rollout.md`, supprimer `compose.apps.dokploy.yml`, puis réparer les références maintenues concernées. Conserver les configurations locales et de tests, dont `tests/apps/compose.yaml` : elles ont un rôle distinct du déploiement. Le Dockerfile monolithique racine reste hors de cette consolidation.

Valider la résolution Compose avec des valeurs synthétiques, y compris le profil `jobs`, les cibles de build, les noms/chemins de volumes et l’isolation des sessions. Vérifier les références et les contrôles existants utiles au périmètre, puis suivre les commits signés et la PR vers `dev` du dépôt.

Aucun lancement/reconfiguration de production, migration de données, modification de DNS, envoi réel d’e-mail ou changement du propriétaire d’une ressource en fonctionnement n’est inclus. Avant un déploiement futur, l’inventaire du projet Dokploy et des volumes réels reste nécessaire : la conservation des clés dans le dépôt ne prouve pas l’identité des ressources en production.
