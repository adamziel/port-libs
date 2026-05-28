# B-tree Vacuum Pointer-map Freeblock Current Source Next248

## Scope

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext248Plan`, a publication-seal layer over the accepted next235 checkpoint rows. The slice verifies that current-source freeblock publication only happens after pointer-map visibility, checkpoint tokens match, payload reuse waits for freeblock publication, reusable pages match checkpoint replay, and vacuum tail pages remain fenced.

## WordPress Scenario

`examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next248.php` models deletion of an overflow-backed copied `wp_options` transient before vacuum/reuse. The smoke reports sealed pages, final pointer-map pages, freeblock-publication pages, reusable payload pages, and tail-fence guards needed before a later writer can consume the current-source freeblock stream.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext248Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1251 assertions, 0 failures
```

The focused run emitted 131 PASS lines.

Example smoke:

```text
php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next248.php
wordpress-btree-vacuum-pointermap-freeblock-current-source-next248 self-test passed
```

## Non-overlap

This slice does not repeat next245 source-cursor admission, next235 reusable-payload checkpoints, next232 handoff admission, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, index-interior merge, rollback/VFS writer behavior, JSON table work, or WAL/checkpoint/savepoint clusters.

## Dependency Closure

No new support component is needed. The plan reuses existing native PHP B-tree, pointer-map, table-leaf delete, overflow-chain, current-source, freelist, and freeblock planning helpers.
