# Separate web document roots in one Composer project

Use one Composer package for the public website and administration, with independent document roots and fixed application identities. Both supported Compose stacks already enforce the application split, so adopting dedicated entry points in the source tree lets us retire the combined root Docker, Apache, and PHP development launch paths once both stacks qualify. Shared business code, the database, and member files stay shared as established in [ADR 0001](0001-independent-public-and-staff-entry.md).
