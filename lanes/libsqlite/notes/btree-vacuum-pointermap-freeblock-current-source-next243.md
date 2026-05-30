# B-tree Vacuum Pointer-map Freeblock Current-source Next243

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext243Plan`,
an apply-window admission layer over the accepted next240 reuse rows.

Behavior:

- preserves current-source pointer-map apply gates before payload/freeblock
  pages are published;
- keeps duplicate pointer-map generation for page 105 visible instead of
  collapsing it;
- carries freeblock commit visibility through reusable payload pages;
- keeps vacuum-fenced tail pages 109 and 110 out of the next apply cursor.

Application smoke:

- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next243.php`
- Scenario: copied `wp_options` transient cleanup deletes an overflow-backed
  row, then admits current-source apply rows only after pointer-map pages are
  visible and reusable freeblock pages are committed.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext243Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext243Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next243.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext243Test.php`
  - `1 test files, 1672 assertions, 0 failures`
  - 142 PASS lines

Non-overlap:

This adds apply-window admission after next240 reuse rows. It does not repeat
next240 reuse admission, next236 cursor rows, next233 checkpoints, overflow
freelist release, page relocation, root collapse, bulk overflow freeblock
materialization, accepted next239/next240 B-tree vacuum pointer-map/freeblock
behavior, or queued non-B-tree surfaces.

Dependency closure:

No new support component is needed. The slice reuses native B-tree page,
pointer-map, overflow-chain, freeblock, and current-source reuse primitives.
