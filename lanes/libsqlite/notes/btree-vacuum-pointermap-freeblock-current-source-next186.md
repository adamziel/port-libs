# B-tree Vacuum Pointer-Map Freeblock Current Source Next186

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan`,
which wraps the accepted next183 commit receipts with a post-commit
current-source cursor. The cursor exposes deterministic resume tokens and
visibility rows for pointer-map pages, the secure-delete table leaf freeblock,
and replacement overflow page images while keeping truncated/fenced pages and
the deleted transient cell out of the current source.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext186Test.php`
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next186.php`
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext186Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next186.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

- Does not repeat next183 commit receipts, next180 apply ordering, next177
  batch construction, overflow freelist release, root collapse, page relocation,
  or bulk overflow freeblock materialization.
- Adds only the current-source cursor/resume-token layer that a Application
  transient cleanup replay would use after vacuum pointer-map/freeblock pages
  are committed.

Dependency closure:

- No new support component is needed. The slice reuses native next183 commit
  receipts, page hashes, and auto-vacuum pointer-map/freeblock visibility
  metadata.
