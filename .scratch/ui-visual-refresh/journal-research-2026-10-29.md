# Association journal — research of 29 October 2026

Documentary research only; this note authorizes no implementation, CMS decision or publication. External pages are those returned by the web tool on that date, sometimes from its index: they are neither browser QA nor evidence of the instantaneous production state.

## Repository state

- The future blog, published by administrators to describe past activities and announce upcoming ones, is already explicitly deferred: [initial design, line 64](https://github.com/ibrahima-isi/amea/blob/23b5c556221c432084288c85988718e5e1f245ef/docs/superpowers/specs/2026-10-23-ui-and-app-separation-design.md#L64), [requirement 35](https://github.com/ibrahima-isi/amea/blob/23b5c556221c432084288c85988718e5e1f245ef/.scratch/ui-and-app-separation/spec.md#L51) and [out of scope](https://github.com/ibrahima-isi/amea/blob/23b5c556221c432084288c85988718e5e1f245ef/.scratch/ui-and-app-separation/spec.md#L87). The announcements requested today clarify this editorial need.
- No journal route in the public/staff lists of [docker/apps/routes.php](../../docker/apps/routes.php#L4) or [src/bootstrap.php](../../src/bootstrap.php#L22). Bounded search of PHP/Twig/SQL filenames and the contents of `src/Controller`, `src/Service`, `src/Repository`, `templates`, `database`, `migrations`, `schema.sql`, `composer.json`: no publication module, editor or journal schema identified. `slider_images` serves the carousel, not articles ([schema](../../schema.sql#L92)). This describes the inspected repository, not possible content outside it.
- [ADR 0001](../../docs/adr/0001-independent-public-and-staff-entry.md#L3) requires independent entry points. A future public journal must preserve that boundary: public reading and management directly in administration, without a public → administration button, link or redirect.

## Comparison of official sources

| Site consulted | Useful observation | Limit |
| --- | --- | --- |
| [AEESGS — homepage](https://www.aeesgs.org/) | Registration/introduction page, with a promise of activity information. No article feed exposed in the retrieved content. | The result still contains an old login link: it does not qualify the local redesign or its deployment. `aeesgs.org` without `www` was inaccessible to the tool. |
| [ASEEGS — news](https://www.aseegs.com/actualites) and [events](https://www.aseegs.com/evenements) | Close regional peer: illustrated/dated news, search, pagination, detail pages; a separate agenda with date, place and displayed state. The cultural-day announcement has an editorial date of 27 May 2026 and announces an activity on 13 June 2026. | Announcement and event details were not retrieved. The “En cours” state of a tournament dated 18 July is only a label observed in the indexed result; its freshness is not established. |
| [ESN France — news](https://esnfrance.org/en/last-news/) | Article feed with image, date, excerpt, categories and search. Useful example: a report published on 8 November 2024 describes the 25–27 October meeting, separating publication and activity dates. | Article details returned 403. Sections and controls were observed; their interactive behavior was not tested. |
| [FAGE — homepage](https://fage.org/) and [Assoliades 2026 report](https://fage.org/les-assoliades-2026-les-universites-dete-de-la-fage/) | The homepage distinguishes news and press releases; categories and excerpts lead to articles. The report, dated 22 June on the homepage, describes 18–21 June with structured text and photos. | The [recurring Assoliades page](https://fage.org/projet-educatif/evenements/les-assoliades/) mixes 2025/2026 references: avoid treating an institutional page as an up-to-date agenda. |

No consulted source sufficiently documents the writers' private workflow. Their CMS, roles or approval process are therefore not inferred from appearance. No external private access was requested.

## Proposal for discussion, not approved

A **“Journal de l’amicale”** space could combine three clear uses: **À venir** (date, time, place, participation), **Nos activités** (stories/photos after the activity), **Annonces** (official information, potentially with a deadline). A filterable public feed and one page per publication are enough to begin; the homepage could show a few. Publication and activity dates must remain distinct, and an ended activity must remain available in the archives. This proposal synthesizes the observations above; these labels/rules are not established decisions.

Publication from administration is consistent with the recorded need. Integrated PHP/Twig or a separate editorial tool remains an open choice: public examples justify no technical choice. Before a specification, the only necessary product decision is to confirm this editorial scope, then identify who writes and approves. Implementation will require separate scoping and authorization.

## Interview continuation

On 29 October, the user confirmed a single section with upcoming activities, reports and announcements, managed only from the independent staff application. The [journal design](../association-journal/design.md) continues publication/archiving decisions; this confirmation adds no CMS choice or code.
