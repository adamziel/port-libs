# B-tree Vacuum Pointer-Map Freeblock Current-Source Next212

Date: 2026-05-28T16:56:00Z

## Behavior

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan`, a current-source page-apply admission layer on top of next209 writer-source latch rows. The plan turns the latched pointer-map/freeblock source rows into ordered apply rows, requires pointer-map apply rows before payload apply rows for each cursor, carries leaf freeblock receipts, and keeps truncated tail pages fenced from the apply set.

This is intended for the WordPress copied `wp_options` delete/vacuum path where an overflow-backed transient is deleted, tail overflow pages are truncated, and the writer must apply only the latched current-source pointer-map/freeblock pages before reuse.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext212Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 936 assertions, 0 failures
```

The focused run produced 136 PASS lines for the new next212 test file.

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next212.php
```

Expected self-test line:

```text
wordpress-btree-vacuum-pointermap-freeblock-current-source-next212 self-test passed
```

## Non-Overlap

This slice extends next209 writer-source latch rows with page-apply ordering. It does not repeat next209 source latching, next206 sealing, next203 cursor batching, accepted overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, index/interior merge, or accepted freelist/pointer-map reuse slices.

## Dependency Closure

No new support component is needed. The patch reuses existing B-tree page, pointer-map, table leaf, record, and next209 writer-source latch primitives.

## Next Task

Continue B-tree work on a distinct delete/rebalance/freelist materialization gap, preferably one that uses the ordered apply rows to drive fuller page-image or freelist write application rather than adding another current-source wrapper.
