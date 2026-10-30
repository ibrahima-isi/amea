# Journal de l’amicale — recherche du 29 octobre 2026

Recherche documentaire uniquement ; aucune implémentation, décision de CMS ou publication autorisée par cette note. Les pages externes sont celles rendues par l’outil web à cette date, parfois depuis son index : elles ne constituent ni une QA navigateur ni une preuve de l’état instantané en production.

## État du dépôt

- Le futur blog, publié par les administrateurs pour raconter les activités passées et annoncer les prochaines, est déjà explicitement différé : [design initial, ligne 64](../../docs/superpowers/specs/2026-10-23-ui-and-app-separation-design.md#L64), [besoin 35](../ui-and-app-separation/spec.md#L51) et [hors périmètre](../ui-and-app-separation/spec.md#L87). Les annonces demandées aujourd’hui précisent ce besoin éditorial.
- Aucune route de journal dans les listes publiques/staff de [docker/apps/routes.php](../../docker/apps/routes.php#L4) ni dans [src/bootstrap.php](../../src/bootstrap.php#L22). Recherche bornée des noms de fichiers PHP/Twig/SQL et du contenu de `src/Controller`, `src/Service`, `src/Repository`, `templates`, `database`, `migrations`, `schema.sql`, `composer.json` : aucun module de publication, éditeur ou schéma de journal identifié. `slider_images` sert au carrousel, pas à des articles ([schéma](../../schema.sql#L92)). Cela décrit le dépôt inspecté, pas d’éventuels contenus hors dépôt.
- L’[ADR 0001](../../docs/adr/0001-independent-public-and-staff-entry.md#L3) impose des entrées indépendantes. Un futur journal public doit conserver cette frontière : lecture publique et gestion directement dans l’administration, sans bouton, lien ou redirection public → administration.

## Comparaison de sources officielles

| Site consulté | Observation utile | Limite |
| --- | --- | --- |
| [AEESGS — accueil](https://www.aeesgs.org/) | Page de recensement et présentation, avec promesse d’information sur les activités. Aucun fil d’articles exposé dans le contenu obtenu. | Le résultat contient encore un ancien lien de connexion : il ne qualifie pas la refonte locale ni son déploiement. `aeesgs.org` sans `www` n’était pas accessible par l’outil. |
| [ASEEGS — actualités](https://www.aseegs.com/actualites) et [événements](https://www.aseegs.com/evenements) | Pair régional proche : actualités illustrées et datées, recherche, pagination, pages de détail ; agenda distinct avec date, lieu et état affiché. L’annonce de la journée culturelle porte la date éditoriale du 27 mai 2026 et annonce une activité le 13 juin 2026. | Le détail de l’annonce et celui de l’événement n’ont pas été récupérés. L’état « En cours » d’un tournoi daté du 18 juillet est seulement un libellé observé dans le résultat indexé ; sa fraîcheur n’est pas établie. |
| [ESN France — actualités](https://esnfrance.org/en/last-news/) | Fil d’articles avec image, date, extrait, catégories et recherche. Exemple utile : un compte rendu publié le 8 novembre 2024 relate la rencontre des 25–27 octobre, ce qui sépare date de publication et date de l’activité. | Le détail de cet article a retourné 403. Les rubriques et contrôles sont observés ; leur comportement interactif n’a pas été testé. |
| [FAGE — accueil](https://fage.org/) et [bilan des Assoliades 2026](https://fage.org/les-assoliades-2026-les-universites-dete-de-la-fage/) | L’accueil distingue actualités et communiqués de presse ; catégories et extraits conduisent aux articles. Le bilan, daté du 22 juin sur l’accueil, raconte les 18–21 juin avec texte structuré et photos. | La [page récurrente des Assoliades](https://fage.org/projet-educatif/evenements/les-assoliades/) mélange des mentions 2025/2026 : éviter de prendre une page institutionnelle pour un agenda à jour. |

Aucune source consultée ne documente suffisamment le workflow privé des rédacteurs. Leur CMS, leurs rôles ou leur procédure de validation ne sont donc pas déduits de leur apparence. Aucun accès privé externe n’a été sollicité.

## Proposition à discuter, non approuvée

Un espace **« Journal de l’amicale »** peut réunir trois usages lisibles : **À venir** (date, heure, lieu, participation), **Nos activités** (récits et photos après l’activité), **Annonces** (information officielle, éventuellement avec échéance). Un fil public filtrable et une page par publication suffisent pour commencer ; l’accueil peut en montrer quelques-unes. Les dates de publication et d’activité doivent rester distinctes, et une activité terminée doit rester consultable dans les archives. Cette proposition synthétise les observations ci-dessus ; ces libellés et règles ne sont pas des décisions acquises.

La publication depuis l’administration est cohérente avec le besoin déjà enregistré. Le choix PHP/Twig intégré ou outil éditorial séparé reste ouvert : les exemples publics ne justifient aucun choix technique. Avant une spécification, seule décision de produit nécessaire : confirmer ce périmètre éditorial, puis identifier qui rédige et qui valide. La mise en œuvre fera l’objet d’un cadrage et d’une autorisation distincts.

## Suite de l’entretien

Le 29 octobre, l’utilisateur a confirmé une rubrique unique avec activités à venir, comptes rendus et annonces, gérée uniquement depuis le back-office indépendant. Le [cadrage du journal](../association-journal/design.md) poursuit les décisions de publication et d’archivage ; aucun choix de CMS ou code ajouté par cette confirmation.
