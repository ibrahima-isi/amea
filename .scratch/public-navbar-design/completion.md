# Public navbar design

The public navbar uses the staff landing palette, rounded controls and shared sun/moon theme switch. It keeps the existing logo, French navigation labels and destinations. Registration uses the primary button style. Both portals continue to store the theme preference per origin.

## Local verification

- Chrome checks the four public navigation destinations with a synthetic contact setting, both themes from 320 to 1440 px, keyboard focus, theme persistence and the light fallback when scripts are disabled.
- The split-app Docker, HTTP route and public journey tests passed. PHPUnit under PHP 8.4 reported 373 tests, 950 assertions and 41 skips.
- The visual comparison uses the [supplied desktop capture](screenshots/before-desktop-dark.png) and the local [desktop dark](screenshots/after-desktop-dark.png) and [mobile light](screenshots/after-mobile-light.png) captures.
