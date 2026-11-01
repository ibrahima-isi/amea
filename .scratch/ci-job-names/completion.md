# CI check naming — execution

## Authorization and source

The user approved the exact names and coordinated ruleset-reference update on 1 November 2026 after the naming interview. Worktree: `/private/tmp/amea-ci-names-20261101`; branch: `ci/descriptive-check-names`; selected fetched `dev` baseline: `5729ab6422205ce40576351cd7c1ba101f38565b`. The original checkout is unchanged.

## Implementation

Only job IDs and explicit display names change in the single existing CI workflow. All three job bodies, steps and top-level behavior remain unchanged. The root delivery record's old names describe a historical PR and are retained.

The [ruleset snapshot](evidence/ruleset-before.json) preserves the original active settings. It covers default `main` and `dev`, requires `test` and `php-tests`, and does not require `apps-rehearsal`. No other open PR was present during setup. New checks must pass before only these two context strings are replaced; no protection is disabled or widened.

## Validation and delivery milestones

YAML parsing, structural before/after equivalence, exact labels and existing references will be checked locally. The exact signed PR head must run all three new checks successfully. Ruleset mutation and read-back verification remain pending until that result; actual merge and local integration are separate. No application behavior or test seam changes, so no new mirrored tests are introduced.

No production or journal browser QA is performed. Signing settings remain unchanged.


## Local validation checkpoint

Ruby/Psych parses the updated YAML. Structural comparison proves all three job bodies and top-level triggers/permissions/concurrency are identical to baseline; only the approved IDs and display labels differ. `git diff --check` passes. No executable or maintained consumer depends on the old IDs; their remaining mention in root `completion.md` is historical and unchanged.

Both existing regression groups completed with recorded exit 0: PHPUnit 171 tests / 338 assertions (10 pre-existing metadata deprecations), standalone suites 48/186/78/26, theme-script 5/5 and isolated HTTP 35 + 28 + 12. No independent typechecker is configured. The full Docker/system job will be verified through the actual renamed GitHub check before any ruleset mutation.

The original ruleset remains active and unchanged. Publication and final-head CI are next; do not treat the coordinated settings update as completed yet.


## Verified names and authorized ruleset migration

Draft [PR #33](https://github.com/ibrahima-isi/amea/pull/33) targets `dev`. Signed source **`02110ab19416f83179f277e47d2f64cfffa72cf9`** passed all three exact approved names: `Static checks: Syntax & access`, `Regression tests: PHP, JS & HTTP`, and `System tests: Docker & recovery`. [Verified head/checks](evidence/verified-new-check-head.json), [CI run](https://github.com/ibrahima-isi/amea/actions/runs/41255810564).

Only after that success, the active ruleset was re-read and compared to the original snapshot; no concurrent change was present and no other open PR existed. The user-authorized update replaced only the two required context strings. Full read-back of [the resulting ruleset](evidence/ruleset-after.json) equals the original mutable settings with exactly those two replacements. Applied-rule reads independently confirm the exact new required contexts and GitHub Actions integration ID 15368 on both [dev](evidence/dev-rules-after.json) and [main](evidence/main-rules-after.json).

The active enforcement, signatures, reviews, bypass actors, strict up-to-date policy, branch conditions and every other field remain unchanged. The system check remains non-required. No protection was temporarily disabled, widened or bypassed.

The evidence-only final commit must pass its own three checks before the draft is marked ready. Its workflow content is unchanged from the verified source. [Current PR checks](https://github.com/ibrahima-isi/amea/pull/33/checks) remain distinct from the first verified head and actual merge. Local-main integration/owned cleanup is conditional on a real remote `dev` merge; remote `main` is not pushed.
