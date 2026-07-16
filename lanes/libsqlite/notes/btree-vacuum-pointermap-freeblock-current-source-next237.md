# B-tree Vacuum Pointer-map Freeblock Current-source Next237

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext237Plan`.
- Extends accepted next234 current-source cursor admission with a reuse barrier for vacuumed freeblock/payload pages: pointer-map rows must be visible, the secure-delete leaf freeblock receipt must cross the barrier, payload pages become reusable only after that barrier, and fenced tail pages remain excluded.
- Application smoke models copied `wp_options` transient cleanup where obsolete overflow pages 109/110 are vacuum-truncated while payload pages 106/107/108 are not admitted for reuse until pointer-map and freeblock rows are visible.

Focused evidence:

```text
php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext237Plan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext237Plan.php

php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext237Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext237Test.php

php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next237.php
No syntax errors detected in lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next237.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext237Test.php
1 test files, 1281 assertions, 0 failures
```

Expected dashboard movement: `+137` focused libsqlite PASS lines if accepted.

Non-overlap: this slice adds post-cursor reuse-barrier admission after next234. It does not repeat next234 cursor construction, next231 handoff rows, accepted bulk overflow freeblocks, overflow freelist release, root collapse, page relocation, or queued non-B-tree current-source work.

Dependency closure: no new support component needed; the slice reuses existing native B-tree pages, pointer-map visibility, freeblock receipts, current-source cursor rows, and fenced-tail metadata.
