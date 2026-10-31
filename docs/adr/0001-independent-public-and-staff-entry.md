# Independent public and staff entry points

On 28 October 2026, the user explicitly replaced the legacy URL compatibility planned in ticket 08: the public website must no longer provide links, menus, forms or redirects to the staff application. An administration URL requested on the public host returns a local 404 without revealing the staff destination, regardless of the HTTP method. The staff application has its own direct entry point and retains its authentication, permissions, session and private-document protections.

The stated target is a monorepo containing two distinct subprojects, public/web and back-office. Physical directory separation is deferred. On 28 October 2026, the user confirmed that the member database and files remain shared for now. Business code also remains shared at this stage; this decision authorizes no database separation or data migration.

This decision abandons old bookmarks and administration forms opened on the public domain in favor of an explicit access boundary. The historical ticket-08 redirect criteria are superseded for this behavior; their earlier evidence remains historical. Member correction links remain public, while staff account recovery takes place directly in the staff application.
