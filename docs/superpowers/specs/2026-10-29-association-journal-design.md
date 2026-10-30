# Journal de l’amicale AEESGS

Statut : cadrage complet approuvé par réponse interactive le 30 octobre 2026 ; réalisation autorisée. Les détails proposés ci-dessous font partie de la première version validée.

## Décisions utilisateur confirmées

- Une rubrique publique « Journal de l’amicale » avec trois types : activités à venir, comptes rendus des activités passées et annonces.
- Rédaction et publication uniquement dans le back-office indépendant, par les administrateurs autorisés, avec brouillon puis publication directe.
- Une activité terminée disparaît automatiquement des « À venir », conserve sa page dans les archives et ne devient pas automatiquement un compte rendu.
- Aucun lien, bouton, contrôle de connexion ou redirection du public vers l’administration, même lorsqu’un administrateur consulte le public.
- Design existant validé : palette publique chaleureuse et administration mate bleu nuit. Les projets et la base ne sont pas séparés physiquement dans cette étape ; l’accord de partage provisoire reste applicable.

Références : [entretien](../../../.scratch/association-journal/design.md), [recherche de sites](../../../.scratch/ui-visual-refresh/journal-research-2026-10-29.md), [frontière d’accès](../../adr/0001-independent-public-and-staff-entry.md). La maquette publique est exploratoire, ses contenus et dates sont fictifs.

## Première version proposée

### Lecture publique

La navigation publique reçoit un lien Journal. Le journal présente les publications publiées, leur type, titre, résumé, date et image de couverture lorsqu’elle existe. Un filtre permet de choisir À venir, Comptes rendus ou Annonces ; les activités terminées disposent d’une vue Archives distincte, sans créer un quatrième type de publication. La liste reste paginée et offre un état vide utile.

Chaque publication possède une page à URL stable. Les dates de publication et d’activité sont distinctes. La signature publique est celle de l’amicale ; les noms des comptes administrateurs ne sont pas exposés. Les brouillons, prévisualisations privées et leurs images restent absents des listes publiques et inaccessibles par URL devinée.

Proposition d’intégration à l’accueil : montrer au plus trois publications récentes lorsque du contenu existe, avec un accès au journal complet. Le carrousel et les parcours d’inscription/correction/consentement existants restent intacts. Aucune publication fictive n’est créée dans l’application livrée.

### Rédaction dans le back-office

Une entrée Journal apparaît uniquement pour les administrateurs disposant du droit de gestion correspondant. Elle ouvre la liste des publications, filtrable par type et brouillon/publié. Les personnes autorisées peuvent gérer les publications du journal de l’association ; il n’y a pas de workflow de validation ni de limitation par auteur dans cette première version.

L’écran de rédaction propose : type, titre, résumé court, texte en paragraphes et image de couverture facultative avec texte alternatif. Pour une activité, il ajoute début, fin et lieu. L’utilisateur peut enregistrer un brouillon, prévisualiser en privé, publier, modifier une publication ou la retirer du public. Retirer signifie revenir au brouillon ; une suppression définitive de contenu n’est pas proposée dans cette version.

Proposition de saisie pour commencer : un éditeur simple en paragraphes, sans HTML libre. La mise en forme enrichie et les galeries multiples pourront être ajoutées après un besoin explicite. Les données et messages sont en français. Les écrans reprennent les composants admin déjà validés, en clair/sombre, mobile et clavier.

### Dates et archives

Les dates et heures d’activité utilisent le fuseau Africa/Dakar. Pour une activité sur une seule journée, la fin est préremplie au même jour ; les heures sont facultatives. Sans heure de fin, l’activité est terminée à la fin de la journée indiquée. La fin ne peut pas précéder le début.

L’appartenance à À venir ou Archives découle de la date de fin à la lecture, sans intervention d’un administrateur ni tâche planifiée obligatoire. Une activité en cours reste affichée avec un libellé clair jusqu’à sa fin. Les comptes rendus restent des publications rédigées séparément ; aucune annonce ou histoire n’est fabriquée automatiquement. Modifier les dates met à jour le classement, sans changer l’URL.

La première date de publication et l’URL sont conservées lors d’une correction ou d’un retrait/republication. Les changements sont datés séparément. Les annonces n’expirent pas automatiquement dans cette version ; l’équipe peut les retirer lorsqu’elles ne sont plus utiles.

## Choix techniques proposés au constructeur

Le journal est un module de l’application PHP/Twig existante : contrôleurs, services et repositories, sans ajout de WordPress ou de nouveau framework. Une nouvelle migration PHP CLI idempotente et schema.sql portent les données ; aucune migration appliquée n’est modifiée. L’exécution de la migration reste manuelle contre une base explicitement sélectionnée.

Les contrôles d’accès suivent le modèle de permissions existant. Les opérations de modification utilisent POST et CSRF, avec validation côté serveur ; masquer une entrée du menu ne constitue pas une autorisation. Aucune nouvelle permission n’est attribuée indistinctement aux comptes existants. Le public et le staff conservent leurs sessions séparées et leurs listes de routes propres.

Les couvertures sont limitées à de vraies images JPEG/PNG/WebP, avec limite de taille explicite et validation MIME/contenu. Les fichiers sont servis via des chemins contrôlés : une couverture de brouillon doit rester privée, même si son identifiant ou nom de fichier est connu. Aucun accès direct à un document d’adhérent n’est introduit. Le choix précis de stockage, des endpoints, des noms de tables et de la limite d’upload est laissé au constructeur, dans les conventions du dépôt.

Les textes publics sont échappés ; l’éditeur simple n’autorise pas de HTML arbitraire à exécuter. Les slugs sont uniques et restent stables après publication. Une publication retirée retourne une 404 côté public, tout comme une URL de brouillon ; les liens de prévisualisation sont strictement staff.

La réalisation devra utiliser un worktree et une branche propres au journal, préserver les modifications locales du chantier de refonte, et suivre les signatures/PR vers dev imposées par AGENTS.md. La refonte PR #17 sert de référence visuelle ; toute dépendance de branche devra être explicitée dans le plan sans prétendre qu’une PR draft a fusionné. Aucune promotion ou modification de production n’est comprise.

## Hors de cette première version

Inscription à un événement, billetterie/paiement, commentaires, espace contributeur public, newsletter, envoi automatique d’e-mails, publication programmée et CMS externe ne sont pas inclus. Cette liste borne le journal demandé ; elle n’enlève aucune fonction existante.

## Qualification attendue

- Tests d’intégration de création/modification, brouillon/publication/retrait et validation des dates, avec valeurs et horloge de test déterministes.
- Parcours HTTP réels : un administrateur autorisé rédige, prévisualise et publie ; le public voit la publication ; un utilisateur non autorisé ne peut pas modifier ni prévisualiser.
- Vérification des brouillons et couvertures non divulgués, URLs devinées, CSRF, échappement des textes et refus des fichiers hors formats autorisés.
- Passage du temps autour de la fin d’activité : retrait de À venir et maintien de la page dans les archives, sans génération de compte rendu.
- Retrait d’une publication : disparition du journal public et refus de son contenu et de sa couverture ; parcours d’adhérent existants inchangés.
- Contrôles négatifs sur toutes les nouvelles routes staff depuis le public : 404 sans Location ou lien de secours. Aucun contrôle admin/connexion dans les pages publiques.
- QA navigateur réelle en petit/grand écran, clair/sombre et au clavier ; les maquettes ne remplacent pas les parcours de l’application.
- Suivi séparé de l’implémentation locale, tests, revue, CI du dernier commit, fusion et éventuel déploiement. Aucune production sans approbation explicite.

## Prochaine étape

Le constructeur a relu le cadrage sans question importante manquante, puis l'utilisateur l'a intégralement validé le30octobre2026 et a autorisé la réalisation, y compris l'éditeur simple, la couverture unique, l'intégration à l'accueil et les détails de dates. [Plan](../plans/2026-10-30-association-journal.md), [suivi local](../../../.scratch/association-journal/completion.md). La réalisation est maintenant vérifiée côté serveur et dans la démonstration locale ; les étapes navigateur/CI/fusion restent distinctes.
