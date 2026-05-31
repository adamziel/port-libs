# Real Upstream UPSERT/RETURNING Dynamic 20260531T095551Z-0

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test`
  - `returning1-9.1`: `UPDATE pragma_encoding SET encoding='UTF-8' RETURNING a, b, *` reports `table pragma_encoding may not be modified` before assignments or RETURNING rows are evaluated.
  - `returning1-13.1`: `INSERT INTO rtree(a,b,c) VALUES(1,2,3) RETURNING (SELECT b FROM t2)` admits the virtual-table insert and returns `NULL` for the empty scalar subquery.

## Lane Changes

- Added `SQLiteReturningVirtualTablePlan`, a generic virtual-table RETURNING plan for read-only PRAGMA update rejection and RTREE insert scalar-subquery projection.
- Added `SQLiteRealUpstreamReturningVirtualTableDynamicTest.php` with 1106 focused TestRunner PASS cases and 11008 assertions.

## Verification

- `php -l lanes/libsqlite/src/SQLiteReturningVirtualTablePlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteReturningVirtualTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamReturningVirtualTableDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamReturningVirtualTableDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamReturningVirtualTableDynamicTest.php`
  - `1 test files, 11008 assertions, 0 failures`

## Non-Overlap

This does not repeat existing UPSERT arm priority, SQL multi-arm, fault cleanup, writable-schema RETURNING, DDL/error-order, temp-trigger, correlated DELETE, repeated rowid stream, excluded-alias, generated-column, or foreign-key-before-yield batches. This slice owns the remaining virtual-table RETURNING edge cases from `returning1.test` sections `9.1` and `13.1`.

## Dependency Closure

No new support component is needed. The slice reuses lane-local virtual-table planning and adds generic RTREE/PRAGMA RETURNING semantics without introducing domain-specific APIs.
