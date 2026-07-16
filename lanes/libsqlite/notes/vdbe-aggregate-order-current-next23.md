# VDBE Aggregate ORDER BY Current/Next 23

This slice adds a bounded VDBE-style aggregate input cursor for
`group_concat(... ORDER BY ... FILTER ...)` and adjacent aggregate functions.
It filters rows before sorter insertion, orders aggregate inputs with existing
VDBE affinity/collation/NULL-placement comparison, and exposes current/next
cursor behavior plus aggregate helpers over the ordered stream.

Focused verification:

```text
php -l lanes/libsqlite/src/SQLiteVdbeAggregateOrderCursor.php
php -l lanes/libsqlite/tests/SQLiteVdbeAggregateOrderCurrentNext23Test.php
php -l lanes/libsqlite/examples/application-vdbe-aggregate-order-current-next.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeAggregateOrderCurrentNext23Test.php
php lanes/libsqlite/examples/application-vdbe-aggregate-order-current-next.php --self-test
git diff --check -- lanes/libsqlite
```

Status delta:

- New focused PASS lines: 57.
- `lane-status.json` `phpPass`: 8166 -> 8223.
- `benchmarkDenominator.mapped`: unchanged; no new upstream inventory unit is
  mapped by this focused native PHP behavior slice.

Non-overlap:

This avoids accepted batch21 VDBE aggregate DISTINCT cursors and VDBE
sorter DISTINCT/GROUP cursors. It also avoids accepted parser-level SELECT
GROUP BY/HAVING, SQL expression ORDER BY, JSON table source/cursor/constraint
work, VFS writer/lock/sync/rollback clusters, WAL checkpoint/savepoint byte
materialization, B-tree page move/root collapse/overflow freelist release, and
Unicode GLOB range behavior. The new surface is the aggregate ORDER BY
input-yield stream used before aggregate stepping.

Dependency closure:

No new support component is needed. The implementation reuses existing
lane-local `SQLiteVdbeSortCompare`, `SQLiteVdbeSorterCursor`, numeric
aggregate, text aggregate, and BLOB primitives.
