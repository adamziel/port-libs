# B-tree Freelist Overflow Merge Current/Next 42

## Scope

Adds `SQLiteOverflowFreelistMergePlan` for obsolete overflow pages released by Application-style table and index deletes when the current freelist trunk is almost full. The plan records whether each obsolete overflow page merges into the current trunk as a leaf or becomes the new current trunk that points at the previous current/next chain.

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreelistOverflowMergeCurrentNext42Test.php
Focused test run: 1 selected test files (root lock skipped)
64 PASS lines
1 test files, 64 assertions, 0 failures
```

## Non-Overlap

This does not repeat accepted bulk overflow freeblocks or overflow freelist release. Those accepted slices materialized deleted overflow/freeblock pages and connected obsolete overflow pages into the freelist. This slice covers the narrower current/next freelist trunk merge boundary: one obsolete overflow page fills the current trunk, the next obsolete overflow page becomes the new current trunk, and following obsolete overflow pages merge into that new current trunk while preserving the prior next-trunk chain and auto-vacuum pointer-map free-page entries.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP B-tree, overflow-page, freelist-trunk, pointer-map, and SQLite database image primitives.

## Next

Continue B-tree work on non-overlapping delete/rebalance or freelist materialization with page-image assertions, avoiding accepted root collapse, page relocation, bulk overflow freeblocks, and overflow freelist release surfaces.
