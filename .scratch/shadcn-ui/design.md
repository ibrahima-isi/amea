# Shadcn UI for AMEA

## Intent

Give the Administration a consistent interface based on [Pietro Schirano’s shadcn/ui kit](https://www.figma.com/community/file/1203061493325953101). Use Inter and the kit’s typography on the Public website while preserving its layouts and colors.

## Approach

Adapt the kit in Twig, CSS, and Bootstrap. Keep the shared layouts and existing Bootstrap behavior, centralize common component styles, and update page markup where a shared style cannot express the intended hierarchy. This reuses AMEA’s working forms, dialogs, theme switch, and mobile drawer. A React, Radix, and Tailwind migration could use upstream component code directly but would add a frontend build and require rewiring these interactions.

The Community preview exposes the individual component and typography examples. Use it as the visual reference and use the [classic shadcn typography examples](https://v3.shadcn.com/docs/components/typography) to interpret the scale. Inter is the approved font. Attribute the kit in the implementation documentation under its CC BY 4.0 license.

## Administration

- Apply the kit’s restrained surfaces, borders, corners, spacing, typography, and component states across the shared sidebar and header, dashboard, every staff module, sign-in, and password recovery. Retain the AEESGS name, logo, and existing staff brand color for primary actions.
- Style the controls and content staff already use: buttons, links, inputs, selects, checkboxes, cards, tables, badges, pagination, tabs, menus, dialogs, alerts, and empty states. Adapt staff-specific charts, document panels, and editorial previews to the surrounding interface. Keep the existing data, actions, and French copy.
- Style the on-screen controls for the print preview. Preserve the printable document and downloadable export formats. Use Public website typography for the member-facing content rendered in the staff Journal preview so editorial preview remains representative.
- Preserve route and permission checks, forms and their field keys, CSRF, token flows, JavaScript hooks, uploads, search, pagination, and chart data. Keep the mobile drawer, keyboard access, visible focus, and the light-first theme with an explicit saved preference.

## Public website

- Use Inter for public interface text on the homepage, Journal listings and articles, legal pages, registration, secure member details and correction flows, and consent pages. Cover headings, body text, lead copy, labels, captions, and helper text. Map heading roles to the kit’s tightened tracking and 800/600 weight hierarchy. Maintain readable leading for prose and use compact typography for labels and controls.
- Preserve layout, responsive breakpoints, imagery, navigation, and existing color values in both themes. Verify the new font metrics with French copy on narrow screens. Keep transactional email templates outside this change.

## Delivery and verification

- Put Inter in a shared self-hosted asset with the font license and system fallbacks. Keep visual rules scoped to each application and preserve asset versioning in both split entry points. Reuse existing chart, icon, and interaction dependencies.
- Start with the shared components and shell, then apply them to staff pages and auth. Apply public typography through the shared public layout and adjust local overrides where they prevent the hierarchy from carrying through.
- Check every staff screen against the new styling. Exercise representative staff and public flows on the disposable split-app stack, including a permission-limited staff account, mobile navigation, auth and recovery, data tables and forms, and both themes. Capture representative desktop and mobile screenshots with synthetic data and run the relevant regression and CI commands.
