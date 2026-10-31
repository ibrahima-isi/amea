# 04 — Qualification et livraison

**Status:** ready-for-human
**Blocked by:** 03

[Spec](../spec.md), tâche4 du [plan](../../../docs/superpowers/plans/2026-10-30-association-journal.md).

- [x] PHP/HTTP, MySQL synthétique réel, migration répétée, images et droits ; scénarios existants préservés.
- [x] Démonstration actualisée après migration manuelle ciblée, sans réinitialiser ses données.
- [x] QA navigateur partielle et blocage exact explicitement enregistrés, sans contournement.
- [x] Revue globale, signatures, PR vers dev, CI finale et dépendance PR17 distinguées ; aucune production.
- [ ] QA navigateur complète : reprise par l’utilisateur ultérieurement, selon son instruction du 31 octobre.

## Comments

Ne pas assimiler tests locaux/CI à la fusion et à la mise en production.

2026-10-31 : PR #18 fusionnée, CI de sa tête finale vérifiée. Reprise navigateur sur `dev` après PR #19 : fixture réelle jetable disponible, lecture sombre/grand écran partiellement observée ; mobile/clavier/gestion restent ouverts après erreurs/timeouts et captures vides du contrôle natif. [Qualification et blocage](../browser-qa.md). Aucun contournement de certificat ni modification de la démo conservée.

2026-10-31 : PR #20 fusionnée en `3033d4b`, CI 3/3 verte sur la tête signée `09a414c` et CI de `dev` verte. L’utilisateur reporte la QA et la réalisera plus tard. `ready-for-human` désigne uniquement cette reprise ; l’implémentation n’est pas rouverte et la QA complète n’est pas déclarée réussie. [Suivi actuel](../completion.md).
