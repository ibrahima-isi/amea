# Journal de l’amicale — suivi

- [x] Retrouver le besoin antérieur, explicitement différé dans la première livraison.
- [x] Examiner des sites associatifs et enregistrer les sources/limites.
- [x] Confirmer une rubrique publique et les trois types de contenus par réponse interactive.
- [x] Confirmer la gestion depuis le seul back-office indépendant.
- [x] Préparer une maquette publique exploratoire du fil et des pages d'article, avec contenus fictifs signalés.
- [x] Vérifier les filtres, l'ouverture d'une annonce/activité/compte rendu, le retour au fil et l'absence de contrôles admin dans le navigateur réel.
- [x] Vérifier le chargement des images et l'absence de débordement à 375px de fenêtre (contenu 343px).
- [x] Administrateurs autorisés, brouillon puis publication directe confirmés.
- [x] Archivage automatique des activités terminées, conservation des pages et compte rendu séparé confirmés.
- [x] Rédiger le cadrage complet proposé ; relecture et validation finale à effectuer.
- [x] Relecture indépendante du cadrage : aucune question importante manquante pour planifier ; [rapport](builder-check.md).
- [x] Cadrage complet validé le 30 octobre 2026, réalisation explicitement autorisée.
- [x] Plan et quatre tickets locaux établis avec dépendances01→02→03→04.
- [ ] Implémenter et qualifier les parcours réels ; aucune implémentation commencée.

Contexte et décisions : [design.md](design.md). Les spécifications prêtes à réaliser et les tickets ne sont pas encore publiés. Les changements de ce cadrage restent locaux dans le worktree de refonte ; ils ne sont pas ajoutés à une livraison distante sans qualification et autorisation appropriées.

Maquette : `http://localhost:18444/journal.html` ; source interactive conservée dans le répertoire de visualisations du chat. [Capture navigateur](../../docs/testing/evidence/2026-10-29/journal-public-maquette.jpg). La page d'accueil publique seule sur `18444/` est conservée ; les chemins admin restent refusés. Aucun changement aux contrôleurs, à la base ou à la PR de refonte dans cette étape.

Réalisation : worktree géré dédié `/Users/ibrahimadiallo/.codex/worktrees/association-journal/amea`, branche `feat/association-journal`, base `4b16e1644bcf34c28db0d3922fd7687ddf9a0a8d` (PR #17 toujours draft/non fusionnée à la vérification du 30 octobre). Dépendance de fusion à préserver ; aucune modification du checkout principal ou du worktree de démonstration.

| Ticket | Exécution | Preuves |
|---|---|---|
| [01](issues/01-editorial-core.md) | Moteur implémenté/testé ; revue indépendante en attente | Rouge6tests/5erreurs classe manquante ; vert11tests/47assertions ; suite154tests/297assertions,10dépréciations existantes |
| [02](issues/02-private-management.md) | Attend01 | — |
| [03](issues/03-public-reading.md) | Attend02 | — |
| [04](issues/04-integrated-qualification.md) | Attend03 | Migration/QA/CI/fusion distinctes |

L’agent de réalisation a atteint sa limite d’usage ; le parent a terminé le moteur strictement défini et préservé le travail. La revue indépendante reste à faire avant02. Aucun écran réel du journal ni endpoint d’image n’est encore implémenté ; aucune migration MySQL/démo/production exécutée. HTTP18444 affiche la maquette ; HTTP18443 répond400 car il s’agit du portHTTPS. La configuration de sécurité reste inchangée.
