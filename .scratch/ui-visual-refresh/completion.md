# Maquettes de refonte visuelle — progression

- [x] Confirmer la direction de l'administration : claire, mate, bleu nuit et blanc.
- [x] Confirmer la priorité des dossiers à traiter.
- [x] Clarifier « trop de verre » : présence excessive du vert, et surfaces opaques retenues.
- [x] Préparer cinq écrans exploratoires : accueil administration, adhérents, dossier, connexion, accueil public.
- [x] Vérifier dans le navigateur la navigation, l'ouverture d'un dossier, la recherche, l'affichage de la demande de complément et le retour depuis la connexion simulée.
- [x] Vérifier le chargement du logo et de la photo existants et l'absence de débordement horizontal à 375 px de viewport (328 px pour le contenu).
- [x] Vérifier la syntaxe JavaScript ; aucune erreur de console relevée pendant l'exploration.
- [ ] Recueillir le retour sur les maquettes ; confirmer ou ajuster l'organisation et la densité.
- [x] Q4 : organisation du back-office confirmée.
- [x] Retenir le retour Q5 : accueil public plus chaleureux ; nouvelle maquette proposée.
- [x] Retirer l'accès Administration de l'ancienne maquette publique ; préparer une nouvelle maquette publique autonome, sans bouton de bascule vers le back-office.
- [x] Supprimer du code la passerelle public → staff, les liens publics, le global Twig associé et le constructeur d'anciennes destinations ; garder les contrôles d'accès.
- [x] Faire répondre les anciennes URL staff en 404 sur le public, sans Location ni lien ; retirer STAFF_APP_URL de l'environnement public.
- [x] Vérifier PHP, HTTP et répétition Docker complète ; reconstruire la stack locale sans réinitialiser ses données.
- [ ] Nouvelle vérification visuelle : outil navigateur désactivé dans les réglages Work le 28 octobre ; aucune nouvelle preuve visuelle revendiquée.
- [x] Maintien provisoire de la base et des fichiers partagés confirmé par réponse interactive le 28 octobre.
- [x] Rédiger le cadrage complet proposé pour la refonte ; validation finale attendue.
- [x] Relecture du cadrage par un agent indépendant : aucune question importante manquante pour planifier. Voir [rapport](builder-check.md).
- [ ] Terminer l'entretien, consigner le périmètre approuvé et obtenir la confirmation de compréhension commune.

Source des choix : [entretien](design.md). Aperçu local de travail : http://localhost:18444/ ; maquette interactive présentée dans la conversation.

Refonte visuelle produit, livraison Git/CI et déploiement : non effectués. Seule la suppression explicitement demandée des passerelles d'accès est implémentée et vérifiée localement ; voir l'entrée du 28 octobre dans le completion.md racine. Les modules secondaires grisés ne sont pas encore maquettés. Toutes les actions de dossier dans les maquettes sont simulées.
