# Refonte visuelle AEESGS — entretien et maquettes

## Demande du 27 octobre 2026

L'utilisateur constate une présence excessive du vert sur le site public et demande une proposition de refonte du back-office. L'administration actuelle était explicitement conservée par le périmètre précédent ; cette demande ouvre sa conception visuelle.

## Décisions confirmées par réponses interactives

- Administration claire et mate : navigation latérale bleu nuit, surfaces blanches, tableaux lisibles et accents guinéens discrets.
- Écran d'accueil orienté dossiers d'adhérents à traiter.
- Public sur une base neutre avec rouge, jaune et vert plus équilibrés ; photos communautaires conservées.
- L'expression « trop de verre » désignait surtout la quantité de vert. L'utilisateur a également validé des surfaces opaques sans verre ni flou.
- Retour Q4 : l'organisation du back-office est validée, avec dossiers prioritaires et justificatif à côté des informations de l'adhérent.
- Retour Q5 : le public doit être plus chaleureux. Nouvelle proposition : fond papier chaud, section sable, texte brun foncé, rouge profond, photographie communautaire. Cette déclinaison reste à évaluer.

## Frontière d'accès — 28 octobre 2026

- Demande explicite : aucun accès de navigation du public vers le back-office, y compris dans le code et les mécanismes de redirection.
- Réponse interactive confirmée : anciennes URL admin sur le public en 404, sans redirection ni lien de secours.
- Sous-projets distincts public/web et back-office : cible future, sans déplacement de dossiers immédiat.
- Le 28 octobre, l'utilisateur confirme le partage provisoire de la base des adhérents et des fichiers. Leur séparation et les sous-projets seront traités ultérieurement ; aucune migration de données n'est engagée.
- Décision durable : [entrées indépendantes](../../docs/adr/0001-independent-public-and-staff-entry.md).
- Les protections d'accès staff et les refus de routes restent nécessaires. Supprimer une passerelle n'autorise pas de retirer les contrôles d'authentification.

## Proposition présentée puis approuvée

- Administration : tableau de bord opérationnel, liste des adhérents, dossier individuel avec justificatifs, connexion.
- Site public : accueil sobre avec photographie existante, texte bleu nuit, bouton rouge et repère tricolore ponctuel.
- Police : Manrope, avec repli système ; texte courant lisible et hiérarchie limitée.
- Palette proposée : bleu nuit #17263C, fond #F3F5F8, blanc #FFFFFF, texte #293447, rouge #B8383C, jaune #D5A72C, vert #28704E limité aux états positifs et au repère tricolore.
- Les informations et actions des maquettes sont fictives et locales. Aucun traitement de dossier réel, envoi de message ou changement de droits.
- « Adhérents » est utilisé dans les maquettes pour couvrir élèves, étudiants et stagiaires, conformément au sens existant de Member dans GLOSSARY.md. Le libellé de navigation final reste à valider.
- Aucune nouvelle règle métier ; la présentation des statuts doit conserver les statuts existants. La hiérarchie opérationnelle est une proposition à évaluer.

## Arbre de décisions

1. Direction visuelle : confirmée.
2. Priorité d'accueil : dossiers à traiter, confirmée.
3. Évaluation et cadrage final : validés le 28 octobre.
   - Densité de la liste, organisation du dossier et placement des justificatifs.
   - Équilibre des couleurs et photographie de l'accueil public.
4. Réalisation autorisée et exécutée ; qualification visuelle réelle encore en attente du navigateur.

## Limites

Cadrage approuvé : [refonte public et back-office](../../docs/superpowers/specs/2026-10-28-public-backoffice-visual-refresh-design.md).

La refonte visuelle et la suppression des passerelles sont implémentées dans le worktree isolé, avec commits signés et tests serveur documentés. Les maquettes restent des références exploratoires. La qualification visuelle ne peut pas être déclarée complète tant que le navigateur est indisponible ; publication/CI/fusion suivent leurs propres preuves.
