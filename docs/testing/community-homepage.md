# Ticket 02 — Community homepage evidence

Selected direction B uses neutral light/dark surfaces, Guinea red/yellow/green accents, bold system sans-serif type, a large backend-managed community photograph and the existing public association copy. The independent `layouts/public.html.twig` and `public.css` are reusable for subsequent public journeys. Staff templates/styles are unchanged.

The existing slider upload could not publish photos: `FileUploader` returns `filepath`, while `SliderController` read `path`. The real HTTP staff-upload test failed first with a database NOT NULL exception. Reading `filepath` fixes publication. HTTP checks now prove photo/caption publication, escaped captions, a single-photo display without rotation controls, multiple-photo controls and deletion back to the empty state. The homepage identity/empty-state tracer also failed before the new template and passed afterward.

Final logo-completion run (2026-10-24): 27 HTTP assertions and 32 real-browser assertions passed. The browser suite uses fresh headless Chrome against the disposable application, not a user profile or deployed site. It verifies real published photo rendering, autoplay after 6.5 seconds, pause, previous/next wrapping, both device theme preferences, explicit switching, 1440px/390px overflow, reduced-motion startup and non-rotation, and keyboard skip/focus and next-photo activation. Single/empty states are checked separately. Captures contain synthetic staff captions and the approved existing public photo, not a production database snapshot.

- [Desktop light](screenshots/homepage-desktop-light.png)
- [Desktop dark](screenshots/homepage-desktop-dark.png)
- [Mobile light](screenshots/homepage-mobile-light.png)
- [Mobile dark](screenshots/homepage-mobile-dark.png)

Historical asset blocker (resolved 2026-10-24): although the user approved the intended `assets/img/logo.png`, its bytes at the partial-delivery revision were the nine-character ASCII text `fakeimage`, not a decodable PNG. Public templates rendered a textual AEESGS identity while the valid official asset was awaited. This was an explicit incomplete ticket criterion; no invented logo or unrelated private upload is substituted. Other public JPEG assets were verified as valid image files. Parent requested the valid logo path from the user; dependent tickets were gated at that revision.

Local PHP runtime is 8.5.12. PHPUnit: 140 tests/240 assertions pass with 10 existing deprecations; standalone permissions 47/47, OOP unit 186/186, OOP integration 78/78, password security 24/24 pass. Changed PHP syntax and whitespace pass. See completion.md for integration/CI status. This evidence does not qualify production, TLS cookies, real delivery, MySQL or the later two-app deployment.

## Review follow-up — 320px boundary

The suspected narrow-screen clipping was not reproduced. Real Chrome at 320 × 844px, with the longer paused-state label « Reprendre le défilement », verifies that the complete document has no horizontal overflow and every carousel button rectangle remains inside the gallery rectangle. These two retained browser assertions cover the heading width and clipped-control concern without a speculative CSS change. The full relevant run passed 27 HTTP assertions and 26 browser assertions.

## Official logo completion — 2026-10-24

User supplied the approved original 960×677 PNG, copied unchanged to `assets/img/logo.png` (SHA-256 `7411e10e3ec99af4cd2a9d739b30921e85287dc7651e2a09a1001fb06f0e7d75`; exact source-byte comparison passed). The decoded-logo browser test failed with the previous invalid asset before replacement and passed afterward. Two logo assertions run in each single/multiple/empty state: original dimensions decode, and displayed images retain the full natural aspect ratio with no circular cropping. The header and empty-state images now use automatic height and the original aspect-ratio attributes. Refreshed desktop/mobile light/dark screenshots were visually inspected. Active logo blocker is resolved; ticket02 is locally complete and tickets03/04 can proceed. Historical partial-delivery and CI evidence above still applies to its recorded revisions.
