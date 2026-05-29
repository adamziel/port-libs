# Attach/schema numbered dependency cleanup, fifty-fifth pass

Consolidated the remaining numbered WordPress schema/JSON/WAL import dependency
markers in production attach/schema-adjacent planners into stable unsuffixed
dependency identifiers. Direct dependency assertions were migrated to the stable
names, and the WordPress JSON import savepoint smoke scenario now reports the
stable scenario id.

Dependency closure: no new support component is needed; this slice only removes
worker-numbered production dependency references from existing native PHP
schema/WAL import planners.

Non-overlap: cleanup-only consolidation. It avoids new behavior work and does
not touch accepted pager, WAL, B-tree, JSON table, SQL planner, or VFS behavior
clusters.
