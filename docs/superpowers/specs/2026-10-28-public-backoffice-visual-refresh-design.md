# Refonte visuelle du public et du back-office AEESGS

Statut : approuvé par réponse interactive le 28 octobre 2026, réalisation autorisée. Entretien : [.scratch/ui-visual-refresh/design.md](../../../.scratch/ui-visual-refresh/design.md).

## Intention

L'administration a conservé son ancienne interface pendant la séparation des applications. L'utilisateur demande désormais une refonte cohérente de ce back-office et un public plus chaleureux, avec moins de vert. Les deux espaces ont leurs propres entrées : le public ne permet ni de découvrir ni de rejoindre le back-office par sa navigation ou ses réponses HTTP.

## Choix confirmés

- Back-office clair et mat, navigation bleu nuit, surfaces blanches, tableaux lisibles et touches guinéennes discrètes.
- Dossiers à traiter visibles dès l'accueil. Informations de l'adhérent et justificatif placés côte à côte sur écran large.
- Public avec photos communautaires, surfaces opaques, moins de vert ; ambiance plus chaleureuse demandée après la première maquette.
- Aucun lien, bouton, formulaire de récupération ou redirection du public vers le back-office. Anciennes URL admin sur le public : 404 locale, sans Location ni adresse de remplacement.
- Sous-projets séparés dans un monorepo comme cible ultérieure ; aucun déplacement de dossiers dans cette refonte.
- Base des adhérents et fichiers partagés conservés provisoirement, confirmé par réponse interactive le 28 octobre. La séparation des données sera un chantier distinct.

## Périmètre proposé

La refonte concerne les interfaces existantes et conserve leurs règles métier. Elle ne crée ni nouvel espace membre authentifié, ni nouveau rôle, ni nouvelle source de données.

### Back-office

Une seule identité visuelle couvre toutes les pages existantes : connexion et récupération du compte, tableau de bord, liste/détail/édition des adhérents, liste/revue des dossiers et justificatifs, corbeille, exports et aperçu, utilisateurs et permissions, profil, carrousel, progression des niveaux, communications et paramètres. Les modules secondaires ne restent pas dans l'ancienne interface.

- Navigation latérale regroupant travail sur les adhérents, vie de l'association et gestion. Tous les modules existants restent accessibles selon les permissions réelles.
- Tableau de bord : les dossiers en cours d'examen sont prioritaires ; les compléments demandés sont distingués comme dossiers en attente de réponse. Les dossiers validés ou rejetés ne sont pas présentés comme travail à traiter. Les statistiques existantes restent consultables plus bas, sans dominer l'accueil.
- Liste des adhérents : recherche, filtres, pagination et actions existantes conservés. États exprimés en français et compréhensibles sans dépendre de la couleur. Le libellé « Adhérents » couvre élèves, étudiants et stagiaires, sans modifier les enum stockées.
- Dossier : identité et informations d'un côté, document autorisé de l'autre. Sur mobile, ces sections s'empilent. Les actions existantes de validation, demande de complément et rejet gardent leurs confirmations, contrôles et retours.
- Édition et enregistrement publics gardent des champs cohérents, avec leurs validations et règles actuelles.
- Tables et montants, lorsqu'ils existent, restent stables pendant les interactions. Pas d'animation décorative qui déplacerait les valeurs ou gênerait la lecture.
- La maquette ne montre que certains champs et modules. L'implémentation doit conserver l'ensemble des informations et fonctions disponibles dans les pages actuelles ; elle ne doit pas recopier les limitations d'une démonstration.

### Site public

La déclinaison proposée utilise un fond papier chaud, une section sable, un texte brun foncé, un bouton rouge profond et des touches tricolores ponctuelles. La photographie, le logo officiel déjà validé et les textes de l'amicale restent au centre. La formulation finale peut reprendre les contenus existants ; aucune nouvelle promesse de service n'est créée.

- Navigation exclusivement publique : amicale, inscription, contact et mentions légales selon les pages. Aucun accès admin dans l'en-tête, le pied de page, une session préexistante ou un état d'erreur.
- Le carrousel conserve les photos publiées, leurs légendes, commandes manuelles et pause. Les cas sans image ou avec une seule image restent utiles et lisibles.
- La même palette s'applique à l'inscription, revue/confirmation, correction, consentement et mentions légales.
- Les liens publics valides et leurs jetons continuent de fonctionner. La suppression de la compatibilité concerne les anciennes URL d'administration sur le public.
- Les liens directs de récupération de compte staff sont envoyés par le back-office vers son propre domaine ; le public ne les redirige pas.

## Direction visuelle et déclinaisons

Proposition du concepteur, à confirmer avec le cadrage :

- Back-office : bleu nuit #17263C, fond #F3F5F8, surfaces #FFFFFF, texte #293447. Rouge, jaune et vert servent les repères d'identité ou les états ; ils ne remplissent pas tous les panneaux.
- Public chaleureux : fond #FFFAF3, surface #FFFCF7, section sable #F4E4CC, texte #302C2A, accent #A83434. Le vert reste limité aux accents tricolores et aux états utiles.
- Typographie Manrope avec repli système ; aucune dépendance au chargement d'une police pour accéder au contenu.
- Bordures fines, arrondis modérés, surfaces opaques ; pas de verre, flou d'arrière-plan ou dégradé vert généralisé.
- Mode sombre conservé : surfaces neutres sombres, texte contrasté et mêmes hiérarchies, sans teinte verte dominante.
- Sur petits écrans, navigation repliable, filtres empilés et actions accessibles. Les colonnes essentielles restent lisibles ; un défilement horizontal peut être contenu aux tableaux qui en ont réellement besoin.
- Clavier, focus visible, messages associés aux champs, contrastes lisibles et préférence de mouvement réduit sur les deux applications.

Le concepteur laisse les espacements précis, icônes cohérentes, organisation CSS et découpage Twig à l'implémentation. PHP/Twig, Bootstrap et JavaScript actuels sont conservés ; cette refonte ne nécessite pas de nouveau framework.

## Frontière d'accès et sécurité

Voir [ADR 0001](../../adr/0001-independent-public-and-staff-entry.md).

La frontière vérifiée est celle des cibles Docker public et staff, déployables séparément. Le serveur PHP historique à la racine reste une application combinée et ne constitue pas la manière de servir le public séparé. Les services partagent provisoirement le code métier, la base et les fichiers existants ; les sessions restent distinctes. La séparation d'accès ne constitue donc pas une séparation physique ou une indépendance des données.

Les listes de routes autorisées et le refus par défaut restent actifs. Authentification, permissions par ressource, CSRF, révocation/expiration des sessions et protection des fichiers privés sont conservés. Le retrait des passerelles ne supprime aucun de ces contrôles. Le public ne reçoit plus STAFF_APP_URL. La navigation interne après connexion reste limitée aux routes staff autorisées.

Cette règle porte sur le sens public → back-office. Les liens existants du staff vers les parcours publics nécessaires au traitement des adhérents restent autorisés ; ils ne créent aucun lien retour depuis le public.

## Vérification avant de déclarer la refonte terminée

- Régressions PHP et HTTP du dépôt ; répéter les tests touchés par les modifications.
- Scénarios réels public : navigation, carrousel, inscription et confirmation, compléments, consentement, liens invalides/expirés et refus de documents privés.
- Scénarios réels admin : connexion, rôles et permissions, recherche/liste/édition, décision sur dossier et justificatifs, puis contrôles pertinents sur chaque module secondaire.
- Refus 404 des routes staff sur le public pour GET/POST et autres méthodes ; aucune réponse ne révèle d'URL staff. Aucune navigation publique ne pointe vers un écran admin, avec ou sans ancienne session.
- QA navigateur en clair/sombre, petit/grand écran et au clavier. Les tests HTTP seuls ne remplacent pas la qualification visuelle.
- Le suivi distingue code local, tests locaux, revue, CI sur le dernier commit, fusion et éventuel déploiement. Aucun déploiement de production sans autorisation explicite.

## État actuel et suite

La suppression des passerelles est déjà implémentée et vérifiée localement, avec 357 lignes PASS dans la répétition Docker, 141 tests PHPUnit / 241 assertions, les suites PHP et HTTP habituelles et 26 contrôles sur la stack de démonstration. Ces preuves concernent la frontière d'accès, pas l'implémentation de la nouvelle identité visuelle.

L'utilisateur a validé le cadrage complet et demandé de passer à la réalisation : organisation du back-office, déclinaison publique chaleureuse, modes sombre/mobile et extension homogène à tous les modules. Les maquettes restent des références exploratoires et leurs actions sont simulées. Le navigateur automatisé est actuellement désactivé dans les réglages Work ; aucune nouvelle QA visuelle n'est revendiquée depuis cette désactivation.

Réalisation suivie par le [plan](../plans/2026-10-28-public-backoffice-refresh.md) et les tickets locaux, en worktree isolé. La réorganisation en sous-projets et la séparation des données restent hors de cette étape.
