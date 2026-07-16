# VDBE Aggregate FILTER ORDER Current/Next32

This isolated slice tightens `SQLiteVdbeAggregateOrderCursor` to apply an
aggregate `FILTER` predicate before validating aggregate value and `ORDER BY`
payload columns. That matches SQLite's VDBE aggregate stepping shape where
rows rejected by `FILTER (WHERE ...)` do not feed argument or sort-key
evaluation. Filtered-out copied `wp_options` rows with missing or malformed
payloads now no longer poison the ordered aggregate input cursor.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeAggregateFilterOrderCursorTest.php
Focused test run: 1 selected test files (root lock skipped)
52 PASS lines
1 test files, 74 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-vdbe-aggregate-filter-order-current-next.php --self-test
PASS application vdbe aggregate filter order current next
```

Status delta: `lane-status.json` `phpPass` moves from `10687` to `10739` for
the 52 newly verified focused PASS cases. No mapped upstream denominator
movement is claimed.

Non-overlap: this does not repeat accepted aggregate DISTINCT/ORDER helper
coverage, JSON object aggregate/window behavior, parser-level grouped SELECT
text, expression `ORDER BY`, JSON table cursor/source/constraint work, or
recent B-tree/WAL/VFS clusters. The behavior is specifically VDBE aggregate
input cursor `FILTER` gating before ordered current/next traversal.

Dependency closure: no new shared support component is needed. The slice reuses
the existing lane-local VDBE sorter comparison, aggregate cursor, scalar SQL
truthiness, and text/numeric aggregate helpers.
