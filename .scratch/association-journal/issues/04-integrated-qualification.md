# 04 — Qualification et livraison

**Status:** ready-for-agent
**Blocked by:** 03

[Spec](../spec.md), tâche4 du [plan](../../../docs/superpowers/plans/2026-10-30-association-journal.md).

- [ ] PHP/HTTP, MySQL réel, migration répétée, images et droits ; scénarios existants préservés.
- [ ] Démonstration actualisée après migration manuelle ciblée, sans réinitialiser ses données.
- [ ] QA navigateur réelle ou blocage exact explicitement enregistré, sans contournement.
- [ ] Revue globale, signatures, PR vers dev, CI finale et dépendance PR17 distinguées ; aucune production.

## Comments

Ne pas assimiler tests locaux/CI à la fusion et à la mise en production.

2026-10-31 : PR #18 fusionnée, CI de sa tête finale vérifiée. Reprise navigateur sur `dev` après PR #19 : fixture réelle jetable disponible, lecture sombre/grand écran partiellement observée ; mobile/clavier/gestion restent ouverts après erreurs/timeouts et captures vides du contrôle natif. [Qualification et blocage](../browser-qa.md). Aucun contournement de certificat ni modification de la démo conservée.
