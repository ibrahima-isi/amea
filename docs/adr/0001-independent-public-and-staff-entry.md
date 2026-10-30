# Entrées publiques et administration indépendantes

Le 28 octobre 2026, l'utilisateur remplace explicitement la compatibilité des anciennes URL prévue par le ticket 08 : le site public ne doit plus fournir de lien, de menu, de formulaire ou de redirection vers le back-office. Une URL d'administration sollicitée sur le public retourne une 404 locale, sans révéler la destination de l'administration, quelle que soit la méthode HTTP. Le back-office possède sa propre entrée directe et conserve ses protections d'authentification, de permissions, de session et de documents privés.

La cible exprimée est un monorepo contenant deux sous-projets distincts, public/web et back-office. La migration physique des dossiers est différée. Le 28 octobre 2026, l'utilisateur confirme le maintien provisoire de la base des adhérents et des fichiers partagés. Le code métier reste commun pour cette étape ; aucune séparation de base ou migration de données n'est autorisée par cette décision.

Cette décision abandonne les anciens favoris et formulaires admin ouverts sur le domaine public, au profit d'une frontière d'accès explicite. Les critères historiques de redirection du ticket 08 sont remplacés pour ce comportement ; leur ancienne preuve reste historique. Les liens de correction d'un adhérent restent publics, et la récupération de compte staff s'effectue directement dans le back-office.
