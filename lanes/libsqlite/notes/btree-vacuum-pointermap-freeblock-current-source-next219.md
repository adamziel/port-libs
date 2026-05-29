# B-tree Vacuum Pointer-Map Freeblock Current Source Next219

## Behavior

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext219()`, a post-write current-source readback verifier for the existing vacuum pointer-map/freeblock chain. It consumes the accepted next217 write rows and verifies that the publishable readback stream:

- preserves pointer-map-before-payload ordering;
- keeps duplicate pointer-map rewrites visible for page 105;
- matches write pages and write tokens exactly;
- excludes fenced vacuum tail pages 109 and 110;
- carries leaf freeblock and overflow payload readback metadata.

## WordPress Smoke

`examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next219.php` models a copied `wp_options` database after deleting an overflow-backed transient and vacuuming tail pages, then emits only the safe current-source pointer-map/freeblock readback rows.

## Verification

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext219Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 914 assertions, 0 failures
```

PASS-line delta from this focused file: `122`.

## Non-Overlap

This slice does not repeat next217 page-write materialization, next212 apply ordering, next209 source latching, overflow freelist release, page relocation, root collapse, index-interior merge, or bulk overflow freeblock materialization. It only adds readback verification after the existing write rows.

## Dependency Closure

No new support component is needed. The slice reuses existing B-tree, pointer-map, overflow, table-leaf, and current-source write planning primitives.
