# Temp-Store Sorter DISTINCT/LIMIT Yield

This slice adds bounded VDBE-style yield behavior to the existing temp-store
sorter B-tree planner without changing accepted SELECT SQL DISTINCT, expression
ORDER BY, comma-LIMIT, or grouped SELECT text execution.

`SQLiteTempStoreSorterBTreePlan::forDistinctLimitRows()` now sorts row-array
records with the existing temp sorter, validates scalar/BLOB/NULL DISTINCT
keys, skips duplicate keys while scanning in sorter order, and applies
OFFSET/LIMIT as rows are yielded. When the estimated sorter memory exceeds the
threshold, the same temporary index-leaf page images and run summaries remain
available for spill diagnostics.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTempStoreSorterDistinctLimitTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS yields distinct sorter rows after order with offset and limit
PASS uses distinct keys without spilling when the sorter stays in memory
PASS stops yielding after zero limit but still validates sorted distinct input
PASS rejects malformed sorter distinct limit yield requests

1 test files, 41 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-temp-store-sorter-distinct-limit.php
```

Non-overlap: avoids accepted standalone SELECT SQL DISTINCT/ORDER/LIMIT,
expression `ORDER BY`, comma `LIMIT`, grouped SELECT text, SELECT SQL
subqueries, JSON table cursor/source/constraint pushdown, VFS writer/sync/lock
clusters, WAL byte truncation/savepoint rollback, rollback-journal commit/apply,
B-tree page move/root-collapse/overflow freelist release, and Unicode GLOB
range behavior.

Dependency closure: no new support component is required. The slice reuses
existing lane-local temp sorter, record, index-cell, B-tree page, and BLOB
value primitives.
