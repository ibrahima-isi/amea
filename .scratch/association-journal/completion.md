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
- [x] Rédiger le cadrage complet, relu puis validé avant réalisation.
- [x] Relecture indépendante du cadrage : aucune question importante manquante pour planifier ; [rapport](builder-check.md).
- [x] Cadrage complet validé le 30 octobre 2026, réalisation explicitement autorisée.
- [x] Plan et quatre tickets locaux établis avec dépendances01→02→03→04.
- [x] Implémenter les parcours réels et qualifier PHP/HTTP/images/archives en environnement synthétique.
- [ ] QA navigateur réelle et clôture de livraison distante : ouvertes, ne pas assimiler aux tests serveur.

Contexte et décisions : [design.md](design.md), [spec](spec.md), [plan approuvé](../../docs/superpowers/plans/2026-10-30-association-journal.md). Le journal est implémenté dans son worktree dédié et publié dans la [PR #18](https://github.com/ibrahima-isi/amea/pull/18), ciblant `dev`. Les anciens fichiers de cadrage locaux du worktree de refonte sont préservés séparément.

Maquette historique : `http://localhost:18444/journal.html`, [capture](../../docs/testing/evidence/2026-10-29/journal-public-maquette.jpg). Elle est distincte de l'application réelle : journal `https://localhost:18443/journal.php`, lecture HTTP locale uniquement `http://localhost:18447/journal.php`, gestion indépendante `https://localhost:19443/manage-journal.php`. La façade18447 ne permet aucun POST ou accès staff ; les formulaires de gestion restent HTTPS et protégés.

Réalisation : worktree géré dédié `/Users/ibrahimadiallo/.codex/worktrees/association-journal/amea`, branche `feat/association-journal`, base `4b16e1644bcf34c28db0d3922fd7687ddf9a0a8d` (ancienne tête de PR #17). PR #17 a été squash-fusionnée dans `dev` en `23b5c55` le 30 octobre ; cette dépendance est résolue. Aucun changement du checkout principal ou du worktree de démonstration.

| Ticket | Exécution | Preuves |
|---|---|---|
| [01](issues/01-editorial-core.md) | Implémenté/testé et relu | Commits signés1b8d3d7/ddbe909 ; CASconcurrent rouge→vert ; journal15/56, suite158/306 ; revuePASS ;10dépréciations existantes |
| [02](issues/02-private-management.md) | Implémenté/testé et relu | Signatures47c2b7e/8f03831 ; PHP159/310,permissions48 ; deuxP2 de422 corrigés et relecturePASS ; HTTPsynthétique vert |
| [03](issues/03-public-reading.md) | Implémenté/relu après correctif session | Signatures8132513/e7721a5 ;481PASS dernière répétition ; P2sessionexpired corrigé et revuePASS |
| [04](issues/04-integrated-qualification.md) | PHP/HTTP/démo et revues validés ; PR #18 publiée, CI `bb2745e` verte ; navigateur/clôture ouverts | Signé e4dc34f/7dc5faa ;534PASS+preuvejobs,159tests/310assertions,33smokeservi ; revue globale P3 corrigé et relecturePASS |

Le premier agent a atteint sa limite d'usage ; le parent a terminé le moteur et la revue indépendante a trouvé un P2 de concurrence, corrigé avec révision atomique. Les deux P2 de conservation du formulaire422 et le P2 de session anonyme ont ensuite été reproduits/corrigés/revus. La revue globale n'a trouvé qu'un P3 de libellés anglais dans la liste privée, corrigé en7dc5faa et relecturePASS. [Revue](evidence/reviews/final-review.md), [correctif vérifié](evidence/reviews/final-rereview.md).

Démo réelle : migration07 appliquée manuellement après vérification de la base synthétique amea_rehearsal, avant remplacement web. Deux adhérents et quatre comptes conservés ; journal_posts vide, aucune publication fictive ajoutée. [Preuve migration](../../docs/testing/evidence/2026-10-30/journal-demo-migration.json). Les quatre assets et33contrôles runtime correspondent au code7dc5faa : [preuve servie](../../docs/testing/evidence/2026-10-30/journal-served.json).

Limites : le navigateur automatisé a renvoyé « Local browser use is disabled in Work settings » lors de l'essai de lecture HTTP réelle ; aucune capture ou QA navigateur de l'application revendiquée. Le certificat HTTPS n'a pas été contourné et les sessions privées restent protégées. PR #18 est publiée et ses trois checks ont réussi sur `bb2745e` ; PR #17 est maintenant fusionnée. Après la correction de conflits, la CI de la nouvelle tête doit être vérifiée à nouveau. La QA navigateur et la fusion de PR #18 restent ouvertes ; aucune production autorisée. L'auto-fusion de PR #18 a été désactivée avant la correction. Conserver les fichiers local-runtime montés tant que la démonstration les utilise.

## Reprise vérifiée — 30 octobre 2026

Le worktree existant et tous ses changements ont été conservés sur `feat/association-journal`, code `7dc5faa`. Les vérifications GitHub en lecture seule confirment PR #17 ouverte/brouillon/non fusionnée, aucune branche ni PR journal distante ; `dev` reste `01e6a3b`, `main` `137c851`. Aucun fetch, push ou changement de ces branches.

**21 contrôles HTTP publics supplémentaires PASS**, sans création de publication ni réinitialisation : cinq vues anonymes vides sans cookie/lien privé, dix refus GET/POST des routes privées, deux ressources absentes en 404, POST du journal refusé et trois assets servis identiques au worktree. [Preuve de reprise](../../docs/testing/evidence/2026-10-30/journal-resumption.json). Cette vérification ne remplace pas les 33 contrôles historiques de la démo et ne répète ni HTTPS privé ni la suite Docker complète.

L'outil navigateur pris en charge est indisponible et l'inventaire ne contient aucun navigateur contrôlable ; QA navigateur toujours ouverte, sans contournement de réglage ou de certificat. Le contrôle d'approbation a de nouveau accepté des commandes ciblées, dont les lectures distantes et ces contrôles locaux. Le commit documentaire signé peut donc repasser par ce contrôle normal. La publication de la branche journal et la création de sa PR brouillon restent soumises à une autorisation explicite ; le corps de PR est préparé localement.

## PR #18 — résolution des conflits après PR #17

La publication de `bb2745e` a été explicitement autorisée, puis PR #18 créée et rattachée au chat. Ses trois checks ont réussi : [CI PHP/HTTP et applications](https://github.com/ibrahima-isi/amea/actions/runs/41135097285), [validation migrations](https://github.com/ibrahima-isi/amea/actions/runs/41135097506), avec 534 contrôles HTTPS et 159 tests/310 assertions PHP.

Après le squash de PR #17 dans `23b5c55`, Git reproduisait six conflits, alors que les arbres complets de cette tête de `dev` et de la base initiale `4b16e16` sont identiques. Une fusion de ces historiques conserve donc intégralement le code de `bb2745e`, sans force-push ni abandon de changements propres à `dev`. Seuls ce suivi, le suivi racine, le plan et la preuve de résolution sont actualisés. Vérification indépendante favorable. [Preuve](../../docs/testing/evidence/2026-10-30/journal-dev-merge.json).

Validation locale du résultat : PHPUnit 159/310 (10 dépréciations préexistantes), scripts PHP 48/186/78/24 et parcours HTTP historiques 35+28 PASS. La nouvelle tête nécessite ses propres checks distants avant toute conclusion. La QA navigateur reste ouverte, l'auto-fusion est désactivée et aucun déploiement n'est effectué.
