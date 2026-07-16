# yield-sqlite-btree-overflow-autovacuum-pointermap-current-next53

## Status

- Added `SQLiteBTreeOverflowAutoVacuumPointerMapPlan` for a bounded B-tree overflow allocation path that consumes pages from the current freelist trunk and its next trunk, materializes the overflow chain, and applies auto-vacuum pointer-map ownership across both pointer-map pages.
- Added focused PHP coverage in `SQLiteBTreeOverflowAutoVacuumPointerMapCurrentNext53Test.php`: 53 PASS cases / 506 assertions / 0 failures.
- Added Application smoke `application-overflow-autovacuum-pointermap-current-next53.php` for copied `wp_options` overflow payload allocation without ext/sqlite.

## Verification

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowAutoVacuumPointerMapCurrentNext53Test.php
Focused test run: 1 selected test files (root lock skipped)
53 PASS lines
1 test files, 506 assertions, 0 failures
```

## Non-overlap

This slice does not repeat accepted overflow freelist release, bulk overflow freeblocks, table/index page relocation, root-collapse, page-move, or pointer-map diagnostic/status-only work. It covers the current/next freelist-trunk allocation side of overflow page creation and verifies the resulting first/next overflow pointer-map parent chain.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP `SQLiteDatabase`, `SQLiteFreelistTrunkPage`, `SQLiteOverflowPage`, and `SQLitePointerMapEntry` primitives.
