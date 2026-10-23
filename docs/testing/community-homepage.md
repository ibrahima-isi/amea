# Ticket 02 — Community homepage evidence

Selected direction B uses neutral light/dark surfaces, Guinea red/yellow/green accents, bold system sans-serif type, a large backend-managed community photograph and the existing public association copy. The independent `layouts/public.html.twig` and `public.css` are reusable for subsequent public journeys. Staff templates/styles are unchanged.

The existing slider upload could not publish photos: `FileUploader` returns `filepath`, while `SliderController` read `path`. The real HTTP staff-upload test failed first with a database NOT NULL exception. Reading `filepath` fixes publication. HTTP checks now prove photo/caption publication, escaped captions, a single-photo display without rotation controls, multiple-photo controls and deletion back to the empty state. The homepage identity/empty-state tracer also failed before the new template and passed afterward.

Final combined run: 27 HTTP assertions and 26 real-browser assertions passed. The browser suite uses fresh headless Chrome against the disposable application, not a user profile or deployed site. It verifies real published photo rendering, autoplay after 6.5 seconds, pause, previous/next wrapping, both device theme preferences, explicit switching, 1440px/390px overflow, reduced-motion startup and non-rotation, and keyboard skip/focus and next-photo activation. Single/empty states are checked separately. Captures contain synthetic staff captions and the approved existing public photo, not a production database snapshot.

- [Desktop light](screenshots/homepage-desktop-light.png)
- [Desktop dark](screenshots/homepage-desktop-dark.png)
- [Mobile light](screenshots/homepage-mobile-light.png)
- [Mobile dark](screenshots/homepage-mobile-dark.png)

Asset blocker: although the user approved the intended `assets/img/logo.png`, its current bytes are the nine-character ASCII text `fakeimage`, not a decodable PNG. Public templates render a textual AEESGS identity until a valid official asset is provided. This is an explicit incomplete ticket criterion; no invented logo or unrelated private upload is substituted. Other public JPEG assets were verified as valid image files. Parent requested the valid logo path from the user; dependent tickets remain gated.

Local PHP runtime is 8.5.12. PHPUnit: 140 tests/240 assertions pass with 10 existing deprecations; standalone permissions 47/47, OOP unit 186/186, OOP integration 78/78, password security 24/24 pass. Changed PHP syntax and whitespace pass. See completion.md for integration/CI status. This evidence does not qualify production, TLS cookies, real delivery, MySQL or the later two-app deployment.

## Review follow-up — 320px boundary

The suspected narrow-screen clipping was not reproduced. Real Chrome at 320 × 844px, with the longer paused-state label « Reprendre le défilement », verifies that the complete document has no horizontal overflow and every carousel button rectangle remains inside the gallery rectangle. These two retained browser assertions cover the heading width and clipped-control concern without a speculative CSS change. The full relevant run passed 27 HTTP assertions and 26 browser assertions.
