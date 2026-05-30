# B-tree Vacuum Pointer-Map Freeblock Current Source Next194

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext194Plan`, layered on
the accepted next190 reader-lease model. The new behavior admits only scrubbed
leaf freeblocks and terminal overflow pages to the next writer, keeps ordinary
current-source pages read-only, and fences the vacuum-truncated tail page out
of writer reuse.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext194Test.php`
  - `1 test files, 1179 assertions, 0 failures`
  - `115` PASS lines
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next194.php`
  - self-test passed
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext194Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext194Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next194.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: this is a writer-admission layer after next190 reader leases. It
does not repeat next190 reader visibility, next187 publish barriers, overflow
freelist release, page relocation, root collapse, or bulk overflow freeblock
materialization.

Dependency closure: no new support component is needed; the slice reuses
existing native B-tree page images, pointer-map receipts, secure-delete
freeblock visibility, terminal overflow receipts, and tail-fence exclusion.
