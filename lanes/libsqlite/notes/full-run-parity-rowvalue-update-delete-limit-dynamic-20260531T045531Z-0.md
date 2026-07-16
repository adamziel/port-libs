# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T045531Z-0

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE
LIMIT dynamic parity.

This slice adds row-value `COLLATE` expression handling to
`SQLiteUpdateDeleteReturningSql` so tuple equality, tuple inequality,
`IS NOT`, `RTRIM`, and row-value subquery `ORDER BY ... COLLATE nocase`
windows preserve SQLite collation semantics while applying UPDATE/DELETE
LIMIT selection.

Upstream sources:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue.test`
  collation rows around row-value `COLLATE nocase` comparisons.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET windowing semantics already covered by the target parity file.

Focused assertion movement:

- `SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` now reports `9077`
  assertions. The prior focused run for this file reported `8967` assertions,
  so this slice adds `+110` focused assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The slice reuses the
existing row-value UPDATE/DELETE RETURNING executor and row-array test harness.

Non-overlap: this does not repeat accepted dynamic LIMIT expression coverage,
row-value NULL tuple coverage, visible JSON/app-WAL work, or accepted
UPDATE/DELETE ORDER BY LIMIT selection behavior. The new surface is row-value
collation propagation inside tuple comparisons and ordered tuple subqueries.
