# B-tree Vacuum Pointer-Map Freeblock Current Source Next183

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan`,
which wraps the accepted next180 apply-order plan with current-source commit
receipts. The new receipts prove that pointer-map dependency pages are committed
before page images, the secure-delete table leaf freeblock reaches the current
source once, replacement overflow page images carry hashes, and truncated tail
pages remain fenced from publication.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext183Test.php`
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next183.php`
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext183Test.php`
- `php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next183.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

- Does not repeat next180 apply ordering, next177 batch construction, overflow
  freelist release, root collapse, page relocation, bulk overflow freeblock
  materialization, or the accepted next180 WordPress smoke.
- Adds only the current-source commit-receipt layer for the existing B-tree
  vacuum pointer-map/freeblock publication path.

Dependency closure:

- No new support component is needed. The slice reuses native next180 apply
  rows, next177 readable batches, page-image hashes, fenced tail pages, and
  auto-vacuum pointer-map dependency metadata.
