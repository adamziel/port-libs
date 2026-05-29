# SQLite b-tree vacuum pointer-map freeblock current-source next975-990

Extends the consolidated `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan` current-source handoff coverage from next974 through next990. The slice reuses the existing freelist splice/current-source helper instead of introducing numbered duplicate classes.

Focused coverage:

- `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext975990Test.php`
- `wordpress-btree-vacuum-pointermap-freeblock-current-source-next975-990.php`

The range preserves pointer-map/freeblock handoff ordering, freelist token continuity, current-source page parity, trunk-before-leaf receipt publication, and tail page exclusion over the same auto-vacuum fixture used by next959-974.
