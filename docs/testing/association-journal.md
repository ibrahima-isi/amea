# Qualification du journal associatif

Les fixtures utilisent exclusivement des comptes et publications synthétiques dans un projet Docker jetable. Elles ne doivent jamais être exécutées sur une base réelle ou sur la démo conservée.

```sh
AMEA_SKIP_BROWSER=1 AMEA_PUBLIC_PORT=18445 AMEA_STAFF_PORT=19445 \
AMEA_REHEARSAL_PROJECT=amea-ui-test-journal-final-20261030 bash tests/apps/run.sh
```

Choisir un nom de projet neuf à chaque répétition. Le lanceur refuse les conteneurs ou volumes préexistants et supprime uniquement ceux du projet choisi, y compris le projet de restauration associé. Les migrations et fixtures passent par les commandes existantes ; les assertions de contenu du journal passent par HTTPS et les formulaires staff avec CSRF et révision.

`tests/apps/journal.py` vérifie les permissions, les requêtes dans la mauvaise application (GET et POST), les brouillons, l’échappement, les images contrôlées et leurs en-têtes, les sessions publiques anciennes, les dates invalides, les conflits de révision et les images invalides. Publication, retrait et republication conservent l’URL et la première date publique. Les lectures publiques, y compris avec des cookies staff copiés, ne contiennent pas de passerelle vers le staff.

Les dates de parcours sont relatives à l’horloge UTC, qui partage le décalage de Dakar. De vraies activités futures, passées et en cours, annonces et comptes rendus alimentent les rubriques. Quinze activités archivées permettent de vérifier les deux pages, leur contenu exact, leur ordre, leur stabilité et la conservation du filtre. La page d’accueil est limitée à trois publications. Les cartes et l’article exposent type, signature AEESGS, première date et couverture lorsqu’elle existe ; les heures artificielles des dates sans heure restent absentes.

`tests/apps/release.py` relance deux fois migration07 après publication, puis compare les observations publiques et les octets de la couverture. Il vérifie également les remplacements de conteneurs, le retour vers l’image historique puis le retour vers l’image courante, et une restauration SQL/fichiers dans un projet neuf. L’image historique n’implémente pas le journal : la preuve du journal se fait avant son remplacement et après retour à l’image courante, pas pendant ce rollback. La restauration compare article et couverture via HTTPS, sans lecture SQL des publications.

Les suites PHP de `.github/workflows/ci.yml` et les parcours `tests/http/baseline.py` et `tests/http/tokens.py` complètent cette répétition. `AMEA_SKIP_BROWSER=1` exclut explicitement la validation navigateur : responsive, thèmes, clavier et rendu réel restent une qualification séparée. Les tests HTTP ne prouvent pas le comportement visuel d’un navigateur ni une livraison distante.
