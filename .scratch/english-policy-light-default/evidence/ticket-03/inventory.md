# Ticket 03 — internal-language inventory and boundary classification

Inventory baseline: `b139f57` (fetched remote `dev`). Final application/test source: signed `f0543102bcb599dad70ef0afd6ca299425c71d97`; the complete initial caller batch is `42c018b`. Ticket 02 is still a separate open PR and is not imported here.

## Owning source and complete caller batches

The inventory covers tracked PHP entry points, `src/`, `functions/`, `config/`, migrations, scripts, tests, first-party JavaScript/CSS, Twig/HTML, Apache/deployment configuration and SQL source comments. PHP tokenization classified method/class/variable/comment tokens rather than treating all French strings as identifiers. Template expressions/comments and JavaScript/configuration comments were inspected separately. Full-source searches then verified consumers and classified remaining French matches. Marker scans were discovery aids, not the completeness proof.

| Removed internal name | English name |
| --- | --- |
| `isDiplome` | `isGraduate` |
| `getDistinctEtablissements` | `getDistinctInstitutions` |
| `getDistinctDomaines` | `getDistinctStudyFields` |
| `getDistinctNiveaux` | `getDistinctStudyLevels` |
| `getTopEtablissements` | `getTopInstitutions` |
| `ensureEtablissementExists` | `ensureInstitutionExists` |
| `ensureDomaineExists` | `ensureStudyFieldExists` |
| `ensureNiveauExists` | `ensureStudyLevelExists` |
| `savePersonnePays` | `saveStudentCountries` |
| `getPaysByName` | `getCountriesByName` |
| `getGuineeCountry` | `getGuineaCountry` |
| `AcceptCguController` | `AcceptTermsController` |

- `Student` graduation detection and every model/standalone test use `isGraduate`; the stored status stays exactly `Diplômé(e)`.
- `StudentRepository` lookup/reporting APIs and their complete callers in registration, correction, student editing/listing, export, communications and dashboard use the English methods above. The consent controller filename/class, two internal router targets and dispatchability test move together; the `/accept-cgu.php` route and template path remain unchanged.
- Locals: `dateNaissance → birthDate`, `finalLieu/finalEtab/finalDom/finalNiv → finalResidence/finalInstitution/finalStudyField/finalStudyLevel`, `paysRows → countryRows`, `guinee/guineeNames → guinea/guineaNames`, `nom → name` for lookup arguments, `personneId/paysIds → studentId/countryIds`, `topEtabs → topInstitutions`, `domaines/niveaux → studyFields/studyLevels`, and the test fixture `diplome → graduate`. Complete local consumer sets were updated together.
- Internal view keys `domaines/niveaux → study_fields/study_levels`, and edit-form loop locals `dom/niv → studyField/studyLevel`, change with every local producer/consumer. Form/JSON keys `study_field` and `study_level` are unchanged.
- Statistics SQL result aliases, fallback keys, dashboard template/chart consumers and tests move together: `hommes/femmes/eleves/etudiants/stagiaires/diplomes → men/women/pupils/students/interns/graduates`. The top-institutions result key `nom → institution` is local to the repository/dashboard/test interface; no serialized endpoint consumes it. SQL predicates and stored French values are unchanged.
- Email placeholders are internal Twig rendering data, not queued JSON fields or HTTP/session payloads. `prenom/nom → first_name/last_name`; the two active password-reset producers change with both email consumers. Other legacy email placeholders become `student_id`, `status`, `institution`, `study_field`, `study_level`, `residence`, `housing_type`, `phone`, `registration_date`, `previous_study_level` and `new_study_level`. No tracked producer uses the old orphan-template keys. The active staff-update producer already uses English names; its template now consumes **`id` and `update_date`**, plus its existing English member fields. Review caught and resolved an initial `student_id/updated_at` mismatch. This alignment also fills values that the pre-existing mismatched aliases had left blank; French wording remains unchanged.
- Technical test names/descriptions for graduation, statistics, filtering and study levels are English. New tests exercise existing model/repository/rendering/HTTP boundaries; no production test API or compatibility adapter is introduced.

## Comment-only translations

`assets/js/main.js` general behavior comments; birth-date/read-only Guinea comments in registration/edit scripts; personal/contact/academic/future-plan/action/legal-consent section comments in the two member forms; Guinea inclusion in `StudentService`; the database-init heading; and root/upload Apache security comments. Effective JavaScript, Apache directives and SQL schema statements are identical after removing comments. Already-English technical comments remain unchanged; English comments may quote the exact French labels `Guinée`, `Autre`, and legacy literals where accurate.

## Preserved boundaries and exclusions

- `valid_pays_ids` is retained in the service result and staged registration session, including confirmation and staff-edit consumers. It is a persisted in-progress-session contract, unlike the renamed local `$countryIds`. The added disposable fixture exercises the pre-refactor pending-session shape through preview, CSRF, confirmation, staff editing and authorized download.
- Existing URLs/query names, form/JSON/session keys, identifiers such as document type `identite`, database tables/columns (`name_fr`, `student_country`, etc.), settings keys, queued-mail JSON shape, and public/persisted enum strings are preserved.
- French application/email text, legal sources, country labels/aliases (`Guinée`, `Guinee`, `Guinea`), synthetic member/editorial data and published history remain unchanged. `Fr` denotes the French locale; CGU remains an exact French UI/route/template acronym. “Dossier” is also an English word for a file of records; existing English prose/CSS classes using it are not French-only identifiers requiring cosmetic churn.
- `schema.sql`, migration logic, legal template, country JSON, third-party Tagify and configuration/session setup are byte-identical to the baseline. `database/init.sql` changes only its technical comment. The nine legacy email templates have equivalent HTML when supplied the corresponding old/new data vocabulary; actual active producers are separately audited and tested.
- Maintained documentation/glossary/ADR translations belong to ticket 04; historical planning/evidence translation belongs to ticket 05. No domain redesign, visual redesign, theme change, production deployment, live data or journal browser acceptance is included.

## Verification pointers

[Compatibility audit](compatibility-audit.txt) records static content/directive/route/immutable-source checks. Regression results and independent Standards/Spec reviews are in [completion](../../completion.md#ticket-03--english-internals-with-compatible-member-workflows). The approved model/repository/HTTP/rendering seams are reused. SMTP remains suppressed; the notification test checks rendered data at the public View boundary and the caller payload is source-audited, rather than invoking private methods.
