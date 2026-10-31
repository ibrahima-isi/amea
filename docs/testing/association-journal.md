# Association journal qualification

Fixtures use only synthetic accounts and publications in a disposable Docker project. Never run them against a real database or the retained demo.

```sh
AMEA_SKIP_BROWSER=1 AMEA_PUBLIC_PORT=18445 AMEA_STAFF_PORT=19445 \
AMEA_REHEARSAL_PROJECT=amea-ui-test-journal-final-20261030 bash tests/apps/run.sh
```

Choose a fresh project name for each run. The runner rejects existing containers or volumes and removes only those belonging to the selected project, including its associated restore project. Migrations and fixtures use the existing commands; journal content assertions use HTTPS and staff forms with CSRF and revision checks.

`tests/apps/journal.py` checks permissions, wrong-application requests (GET and POST), drafts, escaping, controlled images and their headers, older public sessions, invalid dates, revision conflicts and invalid images. Publication, withdrawal and republication retain the URL and first public date. Public reads contain no bridge to staff, even with copied staff cookies.

Journey dates are relative to the UTC clock, which shares Dakar's offset. Actual future, past and ongoing activities, announcements and activity reports populate the sections. Fifteen archived activities exercise both pages, their exact content, ordering, stability and retained filter. The homepage is limited to three publications. Cards and the article expose the type, AEESGS signature, first date and cover when present; artificial times remain absent for dates without a specified time.

`tests/apps/release.py` reruns migration07 twice after publication, then compares public observations and cover bytes. It also checks container replacements, rollback to the historical image and forward to the current image, and an SQL/files restore in a fresh project. The historical image does not implement the journal: journal evidence is collected before replacing it and after returning to the current image, not during that rollback. Restoration compares the article and cover through HTTPS without reading publication rows through SQL.

The PHP suites in `.github/workflows/ci.yml` and the `tests/http/baseline.py` and `tests/http/tokens.py` journeys complement this rehearsal. `AMEA_SKIP_BROWSER=1` explicitly excludes browser validation: responsive layout, themes, keyboard interaction and actual rendering remain separate qualification work. HTTP tests do not prove visual browser behavior or remote delivery.
