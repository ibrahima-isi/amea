# Ticket 02 — rendered theme evidence

Executed signed source revision: `b82a550f6905b5c22b0dbb29e70a1d23f90c992b`, based on `b139f57`. The exact-source final run completed successfully on 31 October 2026 with `python3 tests/http/theme.py`. Evidence-only commits after this source do not alter runtime or test code.

The [Chrome output](browser-output.txt) records observable theme/action state and actual computed background/text colors. Screenshots use 1280×900 desktop Chrome and synthetic disposable fixtures. Public/registration/member light backgrounds are `rgb(255, 250, 243)` and dark backgrounds `rgb(33, 30, 28)`; staff/authentication/preview use `rgb(243, 245, 248)` and `rgb(21, 27, 36)`. Assertions compare rendered colors between themes. Public light/dark, staff dark and preview light captures were also visually inspected.

Fresh visits under both system preferences render light; explicit light/dark choices survive navigation and reload. Empty/invalid saved values fall back to light. Missing system API and read/getter/write failures are deliberately injected **simulations**, not real browser privacy settings. Enter/Space activation, Tab reachability, visible focus, independent-origin storage and the actual light no-JavaScript background pass. The one-use export preview is reached through its real authorized form with synthetic permissions.

The loopback fixture combines route families for observable shared-theme checks; two disposable ports verify origin isolation, not production domain configuration. Independent public/staff boundaries are covered separately by the existing Docker rehearsal. The SQLite fixture cannot execute the MySQL dashboard recent-count query, so staff rendering is qualified on the existing member-edit page, not on the dashboard. No production deployment, real mail, certificate-warning bypass or live dataset is used. The deferred journal browser QA is unchanged.

## Captures

- [authentication-dark.png](authentication-dark.png) — SHA-256 `3d13fbc31fff98898580626775627cd4c58d2355b4755997364f203c0bf940ee`
- [authentication-light.png](authentication-light.png) — SHA-256 `834ec57e876daf59ef47ad5c8728fe56b8c36e5b94e3af1e7311d5fa325dc53a`
- [member-dark.png](member-dark.png) — SHA-256 `e4d66886b58c93620b2617a281bd65e2f2484c4c0cb663db780c03c462b036ee`
- [member-light.png](member-light.png) — SHA-256 `918d2b0bf5a0dc564dae3381b577587b4667d1f4861987c1f804041930716a61`
- [no-javascript-light.png](no-javascript-light.png) — SHA-256 `c970055dec18a63b8fe1873d8e4c4f241769009e45b88178a18b8a3d53f98ada`
- [preview-dark.png](preview-dark.png) — SHA-256 `c4e9645b78d8136a3c0c136e7d5726dac5dd8a57ccc3095a74061f9dcaa0cdd6`
- [preview-light.png](preview-light.png) — SHA-256 `00923d54634f5c17dac8df6b2141a3ae4e6131844fd7673c5d4bf510b16ded86`
- [public-dark.png](public-dark.png) — SHA-256 `8ca72d7741c842a258d7311df17d9e3a2fac1a67058a699066944177ef400de6`
- [public-light.png](public-light.png) — SHA-256 `7fd5b20beb1149dc5b254bbab6ba71f7612e6ddd75d87646f841677364bfb509`
- [registration-dark.png](registration-dark.png) — SHA-256 `13f65806585d196f0c8cf5ada552719013ab1b179e204fb37650e05f94a16727`
- [registration-light.png](registration-light.png) — SHA-256 `6946fa9b33dc5db4652b99513806bbd04204e75922c2249b54e768bd9e07010a`
- [staff-dark.png](staff-dark.png) — SHA-256 `04063d322657f593f871f03fa27b8e516c854e0932c6ddc6213d830b2f655cb0`
- [staff-light.png](staff-light.png) — SHA-256 `2e1dc923d58b20f9e11a5210f45f23768567de0443ca13369b30a251f70c2de3`
