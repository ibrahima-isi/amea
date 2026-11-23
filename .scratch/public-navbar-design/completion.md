# Public navbar design

The public navbar uses the staff landing palette, rounded controls and shared sun/moon theme switch. It keeps the existing logo, French navigation labels and destinations. Registration uses the primary button style. Both portals continue to store the theme preference per origin.

## Local verification

- Chrome checks the four public navigation destinations with a synthetic contact setting, both themes from 320 to 1440 px, keyboard focus, theme persistence and the light fallback when scripts are disabled.
- The HTTP route and public journey tests passed. PHPUnit under PHP 8.4 reported 373 tests, 950 assertions and 41 skips.
- Desktop and mobile captures for both themes are in `/private/tmp/amea-public-navbar-screenshots`. The visual comparison used the supplied capture and the local application.
