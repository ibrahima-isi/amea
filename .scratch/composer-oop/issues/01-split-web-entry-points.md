# 01: Boot both Compose apps through dedicated web entry points

Status: ready-for-agent

Blocked by: None.

## What to build

Start the public website and administration through independent document roots in the existing Composer package. Route each host through one class-owned inventory and put environment and session initialization behind classes.

## Acceptance criteria

- [x] Both Compose web targets boot independently through a thin entry point with a fixed application identity. Only the front controller and allowed public assets can be served from the document root.
- [x] Published routes, methods, Allow headers, redirects, and the root alias match the split Compose baseline. Unknown and wrong-app paths receive a local 404 before session handling.
- [x] The existing session expiry, revocation, cookie scope, staff return destinations, and session-free journal reads still work.
- [x] HTTP and split-app regression coverage exercises the real entry behavior. Temporary compatibility for callers migrating in later tickets remains usable.
