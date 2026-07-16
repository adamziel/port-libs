## B-tree Vacuum Pointer-Map Freeblock Current Source Next217

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan`, a page-write materialization layer over next212 current-source apply rows. It rewrites pointer-map pages before payload pages, carries the leaf freeblock receipt into the table leaf write, admits replacement overflow payload pages only below the fenced tail, and preserves token chaining for repeated pointer-map page writes.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext217Test.php`
- Result: `1 test files, 892 assertions, 0 failures` with 122 PASS lines.

Application smoke:

- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next217.php`
- Result: emits `application-btree-vacuum-pointermap-freeblock-current-source-next217 self-test passed`.

Syntax and diff checks:

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext217Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next217.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: focused `phpPass +122` from the new current-source B-tree test. Mapped upstream coverage remains unchanged; this is current-source PHP behavior over already mapped B-tree vacuum, pointer-map, freeblock, and overflow inventory.

Dependency closure: no new support component needed; next217 reuses next212 current-source apply rows, pointer-map apply pages, leaf freeblock receipts, and fenced-tail guards.

Non-overlap: does not repeat next212 writer apply ordering, next209 source latching, overflow freelist release, page relocation, root collapse, bulk overflow freeblock materialization, or accepted batch107-113 B-tree surfaces.
