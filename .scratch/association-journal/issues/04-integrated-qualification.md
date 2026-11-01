# 04 — Qualification and delivery

**Status:** ready-for-human
**Blocked by:** None remaining; ticket 03 was delivered in [PR #18](https://github.com/ibrahima-isi/amea/pull/18).

[Spec](https://github.com/ibrahima-isi/amea/blob/fdafa3b81a5e26c9fc6948bec228205679368ee3/.scratch/association-journal/spec.md), task 4 of the [plan](../../../docs/superpowers/plans/2026-10-30-association-journal.md).

- [x] PHP/HTTP, actual synthetic MySQL, repeated migration, images and permissions; existing scenarios preserved.
- [x] Demo updated after a targeted manual migration without resetting its data.
- [x] Partial browser QA and exact blocker explicitly recorded, without a bypass.
- [x] Global review, signatures, PR into dev, final CI and PR17 dependency kept distinct; no production.
- [ ] Complete browser QA: the user will resume it later, as instructed on 31 October.

## Comments

Do not equate local tests/CI with merge and production release.

2026-10-31: PR #18 merged, final-head CI verified. Browser resumption on `dev` after PR #19: an actual disposable fixture is available; dark desktop reading partially observed; mobile/keyboard/management remain open after native-control errors/timeouts and blank captures. [Qualification and blocker](../browser-qa.md). No certificate bypass or changes to the retained demo.

2026-10-31: PR #20 merged as `3033d4b`, 3/3 green CI on signed head `09a414c` and green `dev` CI. The user deferred QA and will perform it later. `ready-for-human` refers only to that resumption; implementation is not reopened and full QA is not claimed successful. [Current tracking](https://github.com/ibrahima-isi/amea/blob/fdafa3b81a5e26c9fc6948bec228205679368ee3/.scratch/association-journal/completion.md).

2026-10-31: Completed implementation tickets and historical context were removed from the active tracker after merge verification. Their records remain in the pinned Git history linked above. This ticket remains `ready-for-human` solely for the explicitly deferred browser acceptance; no new browser execution is authorized by tracker cleanup.
