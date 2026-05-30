# B-tree Vacuum Pointer-Map Freeblock Current Source Next190

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext190Plan`,
which wraps the accepted next187 publish barriers with a reader-lease admission
layer. It verifies that next-source reader pages are contiguous, secure-delete
freeblock scrub receipts and terminal overflow next-pointer receipts are
complete before reuse, and truncated vacuum tail pages remain fenced from the
next reader lease.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext190Test.php`
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next190.php`
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext190Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext190Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next190.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

- Does not repeat next187 publish barriers, next184 cursor materialization,
  next183 commit receipts, overflow freelist release, page relocation, root
  collapse, or bulk overflow freeblocks.
- Adds only the reader-lease admission layer for the already published
  current-source B-tree vacuum pointer-map/freeblock path.

Dependency closure:

- No new support component is needed. The slice reuses native next187 publish
  rows, secure-delete freeblock receipts, overflow terminal next-pointer
  receipts, and truncated-tail pointer-map fences.
