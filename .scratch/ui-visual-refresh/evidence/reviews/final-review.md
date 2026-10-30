# Revue finale de branche — AEESGS

Base : `01e6a3b4f02417190b10182934f67c004eeddf19`. HEAD vérifié : `d215b79eab5e3da3948e343326f43eba7e5c1cdd`.

Revue indépendante, sans modification du produit ni nouvelle exécution générale des tests. Référence : spécification approuvée `docs/superpowers/specs/2026-10-28-public-backoffice-visual-refresh-design.md`. Skill senior-software-engineering appliqué, en lecture seule conformément au mandat. Le dossier local `local-runtime/` non suivi n'a pas été modifié.

## Finding actionnable

### [P2] Inclure l'aperçu d'export dans le thème administratif

**Lieu :** `templates/export-preview.html.twig:7-21` (notamment les fonds fixes lignes 17–18). Il s'agit d'une omission du périmètre approuvé dans une page existante, pas d'une nouvelle vulnérabilité.

**Reproduction :** choisir le mode sombre dans le back-office, ouvrir Export, sélectionner le format « PDF (aperçu imprimable) » avec des résultats, puis soumettre. `ExportController::preview()` rend ce document autonome. Il ne charge ni `theme.js`, ni `admin.css`, ne porte pas la classe de thème administratif et impose un fond blanc aux conteneurs. L'aperçu écran reste donc l'ancien Bootstrap clair, indépendamment du thème choisi.

**Impact :** le module « exports et aperçu », explicitement inclus dans la refonte de tous les modules, échappe à la nouvelle identité et au mode sombre. Les tests de présence du shell couvrent `/export.php`, mais pas cet écran de résultat. Ce constat découle directement du document servi ; aucun rendu navigateur n'est revendiqué.

**Correction :** donner à l'aperçu écran le chargement de thème et les styles administratifs nécessaires, avec une portée adaptée au document autonome ; conserver explicitement le papier blanc et les dimensions A4 paysage dans `@media print`. Ajouter un contrôle ciblé de l'aperçu après un export réel, puis le vérifier visuellement en sombre et dans l'aperçu d'impression.

## Standards — PASS avec réserve de qualification

Aucun nouveau défaut bloquant de structure PHP, de SQL, d'authentification, de CSRF ou de confidentialité identifié dans le diff examiné. La requête de file reste dans le repository, lie le statut et la limite entière, borne la limite, exclut les suppressions et trie par date puis identifiant. Le contrôleur ne transmet la file nominative qu'aux administrateurs. La navigation conditionnelle correspond aux contrôles des routes examinées, sans remplacer les contrôles serveur.

Les documents utilisent `download.php`, dont les contrôles administrateur, membre actif, chemin réel sous uploads, MIME et cache privé sont conservés. Les décisions KYC, champs, jetons et confirmations ne sont pas retirés. Les nouveaux liens et attributs Twig restent échappés. Aucune nouvelle dépendance PHP ni migration n'est introduite.

## Spec — CHANGES REQUESTED

Un écart P2 reste à corriger : l'aperçu d'export ci-dessus. Les chemins examinés satisfont par ailleurs la sélection serveur des dossiers à examiner, la séparation des demandes de complément, la navigation selon les rôles, les documents autorisés côte à côte et la déclinaison publique chaleureuse. Les commandes et cas zéro/une image du carrousel sont conservés. Les parcours publics conservent leurs formulaires et jetons. La suppression des passerelles laisse le refus 404 avant le bootstrap ; les routes staff refusées ne divulguent plus de destination. Les liens staff vers les parcours publics restent conformes au sens de frontière approuvé.

## Sécurité

| Catégorie | Résultat sur le périmètre examiné |
|---|---|
| Injection SQL / XSS | Aucun nouveau défaut établi ; paramètres liés et échappement Twig conservés |
| Authentification / autorisation | Aucun nouveau défaut établi ; file admin et téléchargements contrôlés |
| Données sensibles | Aucun nouveau secret ni fuite de la file vers le rôle ordinaire identifié |
| Validation | Limite bornée ; contrôles métier et MIME existants conservés |
| Dépendances | Aucun nouveau paquet applicatif ; feuille Google Fonts publique ajoutée |
| Cryptographie | Sans changement dans le diff |
| Routes / API | Refus par défaut conservé ; ancienne passerelle public vers staff supprimée |
| Logique métier | Aucun nouveau contournement de décision identifié |

Constats sécurité : critique 0, élevé 0, moyen 0, faible 0. Cela ne constitue pas un audit exhaustif des parties inchangées.

## Preuves et limite de readiness

Les résultats fournis et consignés sont : répétition HTTP/Docker 404 PASS incluant rollback/restauration, PHPUnit 143 tests / 250 assertions et suites PHP 47/186/78/24, HTTP historique 35+28, smoke local 26 contrôles et quatre empreintes d'assets servis. Ces résultats n'ont pas été présentés comme une nouvelle exécution de cette revue. Les dix dépréciations PHPUnit sont signalées comme préexistantes.

La QA navigateur réelle reste indisponible : clair/sombre, mobile/desktop, clavier, PDF et captures des modules ne sont pas qualifiés. **La branche n'est pas prête à être déclarée livrée ; la PR doit rester draft**, même après correction de ce finding, jusqu'à cette QA et aux vérifications CI sur le dernier commit. Ni fusion ni déploiement de production ne sont attestés par cette revue.
