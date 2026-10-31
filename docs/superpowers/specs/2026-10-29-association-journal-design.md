# AEESGS association journal

Status: complete scope approved through interactive input on 30 October 2026; implementation authorized. The proposed details below are part of the approved first version.

## Confirmed user decisions

- One public “Journal de l’amicale” section with three types: upcoming activities, reports on past activities and announcements.
- Writing/publication only in the independent staff application, by authorized administrators, with draft followed by direct publication.
- An ended activity automatically disappears from “À venir”, retains its archive page and does not automatically become an activity report.
- No public → administration link, button, login control or redirect, even when an administrator visits the public website.
- Existing design approved: warm public palette and matte midnight-blue administration. Projects/database are not physically separated in this stage; the temporary sharing agreement remains applicable.

References: [interview](../../../.scratch/association-journal/design.md), [site research](../../../.scratch/ui-visual-refresh/journal-research-2026-10-29.md), [access boundary](../../adr/0001-independent-public-and-staff-entry.md). The public mockup is exploratory; its content/dates are fictional.

## Proposed first version

### Public reading

Public navigation receives a Journal link. The journal presents published publications, their type, title, summary, date and cover image when present. A filter selects À venir, Comptes rendus or Annonces; ended activities have a separate Archives view without creating a fourth publication type. The list remains paginated and provides a useful empty state.

Each publication has a page with a stable URL. Publication/activity dates are distinct. The public signature is the association's; administrator account names are not exposed. Drafts, private previews and their images remain absent from public lists and inaccessible through guessed URLs.

Proposed homepage integration: show at most three recent publications when content exists, with access to the complete journal. Existing carousel and registration/correction/consent journeys remain intact. No fictional publication is created in the delivered application.

### Writing in the staff application

A Journal entry appears only for administrators with the corresponding management permission. It opens the publications list, filterable by type and draft/published state. Authorized people can manage the association journal's publications; this first version has no approval workflow or author-based restriction.

The writing screen offers: type, title, short summary, paragraph text and optional cover image with alternative text. Activities additionally have start, end and place. Users can save a draft, preview privately, publish, edit a publication or withdraw it from public view. Withdrawal means returning to draft; permanent content deletion is not offered in this version.

Proposed initial input: a simple paragraph editor without unrestricted HTML. Rich formatting and multiple galleries can be added after an explicit need. Data/messages are French. Screens reuse the already-approved administration components in light/dark, mobile and keyboard interaction.

### Dates and archives

Activity dates/times use Africa/Dakar. For a single-day activity, the end is prefilled to the same day; times are optional. Without an end time, the activity ends at the close of the specified day. End cannot precede start.

Membership in À venir or Archives follows the end date at read time, without administrator intervention or a mandatory scheduled task. An ongoing activity remains displayed with a clear label until it ends. Reports remain separately written publications; no announcement or story is fabricated automatically. Editing dates updates classification without changing the URL.

The first publication date and URL are retained across correction or withdrawal/republication. Changes are dated separately. Announcements do not expire automatically in this version; the team can withdraw them when no longer useful.

## Technical choices proposed to the builder

The journal is a module of the existing PHP/Twig application: controllers, services and repositories, without WordPress or a new framework. A new idempotent PHP CLI migration and schema.sql hold the data; no applied migration is modified. Migration execution remains manual against an explicitly selected database.

Access controls follow the existing permissions model. Mutating operations use POST and CSRF with server validation; hiding a menu entry is not authorization. No new permission is indiscriminately granted to existing accounts. Public/staff retain separate sessions and their own route lists.

Covers are limited to actual JPEG/PNG/WebP images, with an explicit size limit and MIME/content validation. Files are served through controlled paths: a draft cover must remain private even if its identifier/filename is known. No direct access to a member document is introduced. Precise storage, endpoints, table names and upload limit are left to the builder within repository conventions.

Public text is escaped; the simple editor does not allow arbitrary executable HTML. Slugs are unique and remain stable after publication. A withdrawn publication returns a public 404, as does a draft URL; preview links are strictly staff-only.

Implementation must use a journal-owned worktree/branch, preserve local redesign changes and follow the signatures/PR-into-dev workflow required by AGENTS.md. Redesign PR #17 is the visual reference; any branch dependency must be explicit in the plan without claiming a draft PR has merged. No production promotion or change is included.

## Outside this first version

Event registration, ticketing/payment, comments, public contributor accounts, newsletters, automatic email, scheduled publication and an external CMS are excluded. This list bounds the requested journal; it removes no existing functionality.

## Expected qualification

- Integration tests for creation/editing, draft/publication/withdrawal and date validation, with deterministic values/test clock.
- Actual HTTP journeys: an authorized administrator writes, previews and publishes; public readers see the publication; an unauthorized user cannot edit or preview.
- Check undisclosed drafts/covers, guessed URLs, CSRF, text escaping and rejection of files outside allowed formats.
- Passage of time around activity end: removal from À venir and page retention in archives, without generating a report.
- Publication withdrawal: disappearance from the public journal and denial of its content/cover; existing member journeys unchanged.
- Negative checks on every new staff route from the public application: 404 without Location or a fallback link. No administration/login control in public pages.
- Actual browser QA on small/large screens, light/dark and keyboard interaction; mockups do not replace application journeys.
- Separate tracking of local implementation, tests, review, latest-commit CI, merge and potential deployment. No production without explicit approval.

## Next step

The builder reviewed the scope without an important missing question, then the user approved it completely on 30 October 2026 and authorized implementation, including the simple editor, single cover, homepage integration and date details. [Plan](../plans/2026-10-30-association-journal.md), [local tracking](../../../.scratch/association-journal/completion.md). Implementation is now verified on the server and in the local demo; browser/CI/merge milestones remain distinct.
