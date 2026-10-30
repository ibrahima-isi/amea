# Maquettes de refonte visuelle — progression

- [x] Confirmer la direction de l'administration : claire, mate, bleu nuit et blanc.
- [x] Confirmer la priorité des dossiers à traiter.
- [x] Clarifier « trop de verre » : présence excessive du vert, et surfaces opaques retenues.
- [x] Préparer cinq écrans exploratoires : accueil administration, adhérents, dossier, connexion, accueil public.
- [x] Vérifier dans le navigateur la navigation, l'ouverture d'un dossier, la recherche, l'affichage de la demande de complément et le retour depuis la connexion simulée.
- [x] Vérifier le chargement du logo et de la photo existants et l'absence de débordement horizontal à 375 px de viewport (328 px pour le contenu).
- [x] Vérifier la syntaxe JavaScript ; aucune erreur de console relevée pendant l'exploration.
- [x] Retours recueillis et cadrage complet confirmé avant réalisation.
- [x] Q4 : organisation du back-office confirmée.
- [x] Retenir le retour Q5 : accueil public plus chaleureux ; nouvelle maquette proposée.
- [x] Retirer l'accès Administration de l'ancienne maquette publique ; préparer une nouvelle maquette publique autonome, sans bouton de bascule vers le back-office.
- [x] Supprimer du code la passerelle public → staff, les liens publics, le global Twig associé et le constructeur d'anciennes destinations ; garder les contrôles d'accès.
- [x] Faire répondre les anciennes URL staff en 404 sur le public, sans Location ni lien ; retirer STAFF_APP_URL de l'environnement public.
- [x] Vérifier PHP, HTTP et répétition Docker complète ; reconstruire la stack locale sans réinitialiser ses données.
- [ ] Nouvelle vérification visuelle : outil navigateur désactivé dans les réglages Work le 28 octobre ; aucune nouvelle preuve visuelle revendiquée.
- [x] Maintien provisoire de la base et des fichiers partagés confirmé par réponse interactive le 28 octobre.
- [x] Rédiger le cadrage complet ; validation finale reçue le 28 octobre.
- [x] Relecture du cadrage par un agent indépendant : aucune question importante manquante pour planifier. Voir [rapport](builder-check.md).
- [x] Entretien terminé : cadrage complet validé et passage à la réalisation autorisé le 28 octobre 2026.

## Réalisation autorisée

Branche : `feat/public-backoffice-refresh`, basée sur le même arbre que `origin/dev`. Séparation d'accès/cadrage enregistrés dans le commit signé et vérifié `5a9dcb4`.

| Ticket | Dépendance | Exécution | Preuve |
|---|---|---|---|
| [01 — Socle admin](issues/01-admin-shell.md) | Aucune | Implémenté et relu ; QA navigateur séparée | Commits signés vérifiés `5e37e4f`, `6a40e7e` ; 141 tests/241 assertions ; trois P2 corrigés, relecture ciblée PASS |
| [02 — Dossiers](issues/02-review-dossiers.md) | 01 | Implémenté et relu ; qualification intégrée séparée | Commit signé `235d45f` ; preuve rouge/verte ; PHPUnit 143 tests/250 assertions ; revue sans finding |
| [03 — Public chaleureux](issues/03-warm-public.md) | 02 | Implémenté et relu ; QA navigateur séparée | Commit signé `d0dd15a` ; 35+28 contrôles HTTP passent ; Twig compile ; revue sans finding |
| [04 — Qualification](issues/04-qualification.md) | 03 | HTTP/serveur validés et relus ; QA navigateur séparée | Commits signés `241e784`, `aeebdb4` ; 410 PASS + preuve jobs ; PHPUnit 143/250 ; revue globale/correctif PASS ; QA navigateur indisponible |

Point de reprise : tickets 01–03 implémentés/revus ; correctif staff → public signé/revu `4ab4f3b`. Qualification 04 : 410 PASS + preuve jobs, restauration/rollback/forward et nettoyage validés ; revue HTTP et relecture globale du correctif PASS. La stack sert le code applicatif `aeebdb4`, sans réinitialisation de base. 26 contrôles HTTPS passent ; cinq CSS/JS correspondent aux SHA256 locaux, parcours PDF final également vérifié par HTTP, preuve versionnée. L'outil signale toujours le navigateur désactivé malgré la confirmation utilisateur : aucune QA visuelle nouvelle. Aucun PR prêt à fusionner, auto-merge ou déploiement de production à revendiquer sans cette preuve.

Source des choix : [entretien](design.md). Aperçu local de travail : http://localhost:18444/ ; maquette interactive présentée dans la conversation.

Refonte produit implémentée dans les pages réelles, y compris les styles communs des modules secondaires. Tests HTTP/PHP et preuves de ressources servies consignés. Les actions des anciennes maquettes restent simulées. QA navigateur, PR/CI, fusion et déploiement sont des étapes distinctes des tests locaux.

Revue finale archivée : [finding initial](evidence/reviews/final-review.md), [correctif vérifié](evidence/reviews/final-rereview.md). La répétition à 410 PASS précède les petites retouches finales asset()/clavier/impression ; le Twig final et la stack reconstruite ont été vérifiés séparément. La CI distante sur le dernier commit reste à relever.

Publication finale bloquée le 28 octobre : le contrôle automatique a rejeté le commit+push groupé comme envoi de preuves privées vers un remote non vérifié, estimant l’autorisation limitée au local et à la préparation du draft. Aucune publication supplémentaire ni création de PR après ce rejet. Demander une autorisation explicite pour ibrahima-isi/amea, branche feat/public-backoffice-refresh et PR draft vers dev ; garder les actions locales séparées.
