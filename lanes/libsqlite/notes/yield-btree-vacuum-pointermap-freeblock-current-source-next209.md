# B-tree Vacuum Pointer-Map Freeblock Current-Source Next209

Date: 2026-05-28T16:30:42Z

## Behavior

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan`, a writer-source latch on top of the accepted next206 sealed current-source rows. The plan admits only sealed pointer-map/freeblock payload pages to the next writer source, preserves pointer-map-before-payload ordering per cursor, keeps leaf freeblock readiness carried forward, and rejects fenced tail pages before reuse.

This is intended for the WordPress copied `wp_options` delete/vacuum path where an overflow-backed transient is deleted, tail overflow pages are truncated, and the writer must not reuse payload pages until the matching pointer-map and freeblock source rows are latched.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext209Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 957 assertions, 0 failures
```

The focused run produced 137 PASS lines for the new next209 test file.

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next209.php
```

Expected self-test line:

```text
wordpress-btree-vacuum-pointermap-freeblock-current-source-next209 self-test passed
```

## Non-Overlap

This slice extends next206 sealed current-source rows with writer-source latch admission. It does not repeat next206 sealing, next203 cursor batching, accepted overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, index/interior merge, or accepted freelist/pointer-map reuse slices.

## Dependency Closure

No new support component is needed. The patch reuses existing B-tree page, pointer-map, table leaf, record, and next206 current-source sealing primitives.

## Next Task

Continue B-tree work on a distinct delete/rebalance/freelist materialization gap, preferably one that applies the latched source rows into a fuller page-image or freelist write path rather than adding another seal/latch wrapper.
