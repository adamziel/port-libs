## B-tree Vacuum Released Overflow Reuse Consolidation

This slice removes the remaining numbered B-tree vacuum released-overflow-reuse
test/example surface for the old current-source worker slice and keeps the
scenario on the canonical `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan`
entrypoint:

- renamed the focused test and WordPress smoke to descriptive released-overflow-reuse filenames;
- updated the smoke to call `tableAndIndexReleasedOverflowReuseFromCurrentSourceDeleteResults()`;
- replaced the numbered action/scenario/test labels with
  `btree-vacuum-pointermap-freeblock-released-overflow-reuse`;
- left behavior unchanged: the focused test still passes with 314 assertions
  and the example self-test passes.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan.php && php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockReleasedOverflowReuseTest.php && php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-released-overflow-reuse.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockReleasedOverflowReuseTest.php`
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-released-overflow-reuse.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this reuses the existing
B-tree, pointer-map, freelist, and overflow page helpers.
