# B-tree vacuum pointer-map freeblock current-source next234

Status: focused PHP behavior growth for `btree-vacuum-pointermap-freeblock-current-source-next234`.

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext234Plan`. It composes the accepted next231 handoff rows and adds the current-source freeblock cursor admission boundary: pointer-map handoff pages must be visible before the table leaf freeblock source cursor opens, the leaf freeblock receipt must be carried into that cursor, overflow payload pages depend on that cursor, and vacuum-truncated tail pages remain fenced out of reuse.

Application smoke: `application-btree-vacuum-pointermap-freeblock-current-source-next234.php` models copied `wp_options` transient cleanup where an overflow-backed transient delete leaves a reusable leaf freeblock, but the next writer must not reuse overflow payload source pages until pointer-map rows and the freeblock cursor are current-source visible.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext234Test.php`
  - `1 test files, 1222 assertions, 0 failures`
  - `130` focused PASS lines.
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext234Plan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext234Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext234Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext234Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next234.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next234.php`
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next234.php`
  - `application-btree-vacuum-pointermap-freeblock-current-source-next234 self-test passed`
- `git diff --check -- lanes/libsqlite`
  - passed with no output.

Non-overlap: this is not another overflow freelist release, page relocation, root collapse, bulk overflow freeblock materialization, next227 seal, or next231 handoff patch. The new surface is the current-source freeblock cursor admission after next231 handoff rows and before payload-source reuse.

Dependency closure: no new support component is needed. The patch reuses existing native B-tree table leaf pages, pointer-map entries, overflow fixtures, and the accepted next231 handoff behavior.
