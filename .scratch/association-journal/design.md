# Journal de l’amicale — cadrage en cours

## Choix confirmés le 29 octobre 2026

L’utilisateur confirme par réponse interactive une rubrique publique unique avec trois types de publications : activités à venir, comptes rendus des activités passées et annonces.

La rédaction et la publication se font uniquement depuis le back-office indépendant. Le public n’offre ni lien de connexion, ni bouton d’administration, ni redirection vers la gestion. La séparation d’accès définie dans [l’ADR 0001](../../docs/adr/0001-independent-public-and-staff-entry.md) s’applique au journal.

Le design actuel du site est validé ; le journal doit s’y intégrer. La [recherche de sites associatifs](../ui-visual-refresh/journal-research-2026-10-29.md) sert de référence, sans imposer leur CMS ou leur organisation interne.

## Choix de fonctionnement confirmés

Le 29 octobre, l’utilisateur confirme les deux recommandations : administrateurs autorisés, brouillon puis publication directe ; retrait automatique des activités terminées des « À venir », en conservant leur page dans les archives. Le compte rendu est rédigé séparément.

Le [cadrage complet proposé](../../docs/superpowers/specs/2026-10-29-association-journal-design.md) précise les écrans et les règles nécessaires à une première version. Les détails nouveaux y sont distingués comme propositions, avant validation finale.

## État

Une maquette exploratoire publique est préparée le 29 octobre et disponible localement sur `http://localhost:18444/journal.html`. Elle conserve la palette validée, présente les trois types et permet de filtrer puis d'ouvrir une publication. Les contenus/dates sont fictifs et explicitement indiqués comme tels. Aucun accès Administration/Connexion n'est présent. Aucune gestion réelle, authentification, écriture ou publication n'est ajoutée.

Les boutons Accueil/Inscription expliquent leur future destination publique dans cette maquette ; ils ne remplacent pas les parcours existants. Le circuit éditorial et l’archivage ont depuis été confirmés par réponse interactive explicite, sans assimiler le message « continue » précédent à ces réponses.

Le périmètre éditorial, le circuit de publication et l’archivage ci-dessus sont confirmés. Les détails complémentaires du cadrage complet sont proposés à la validation finale. Aucune route, table, interface de gestion ou logique de publication n’est implémentée par cette étape. Aucun ajout au périmètre de la PR de refonte en cours ni déploiement.
