# B-tree Vacuum Pointer-Map Freeblock Current Source Next153

Slice: `btree-vacuum-pointermap-freeblock-current-source-next153`

## Behavior

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext153Plan`, a current-source B-tree plan that composes leaf freeblock repair, obsolete table/index overflow release, freelist allocation, and auto-vacuum pointer-map rewrites into one materialized transition table.

The slice covers the WordPress copy-path where large `wp_options` table values and secondary-index entries are deleted, their overflow pages are freed, and a replacement overflow chain reuses the released pages with fresh pointer-map parents and next-page links.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext153Test.php
Focused test run: 1 selected test files (root lock skipped)
74 PASS lines
1 test files, 314 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next153.php --self-test
wordpress-btree-vacuum-pointermap-freeblock-current-source-next153 self-test passed
```

Additional required checks:

```text
php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext153Plan.php
php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext153Test.php
php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next153.php
```

## Non-Overlap

This does not repeat accepted page relocation, root collapse, overflow freelist release, bulk overflow freeblock materialization, next147 overflow freeblock pointer-map allocation, or batch146 pointer-map/freeblock rebalance. The new surface is the combined current-source transition rows for a repaired leaf freeblock plus released overflow pages reused as a fresh replacement chain before incremental vacuum.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP `SQLiteDatabase`, `SQLiteOverflowFreelistReleasePlan`, `SQLiteFreelistAllocationPlan`, `SQLiteBTreeFreeblockCoalescePlan`, and pointer-map primitives.
