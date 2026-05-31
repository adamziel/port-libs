# Row-value UPDATE/DELETE LIMIT Dynamic Parity

Micro-slice: `full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T054558Z-0`

Base accepted HEAD: `db171f640e25dd929585c8e1b7a1c804219fdfee`

Status: focused PHP behavior growth for generic row-value UPDATE/DELETE LIMIT
dynamic parity.

Behavior added:

- `SQLiteUpdateDeleteReturningSql` now evaluates SQLite `printf()` and
  `format()` scalar constant expressions in `UPDATE` / `DELETE` `LIMIT` and
  `OFFSET` clauses.
- The row-value dynamic matrix covers formatted outer UPDATE windows, formatted
  row-value `IN (SELECT ...)` DELETE tuple windows, and malformed format
  diagnostics.

Upstream sources:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/printf.test`

Focused evidence:

- Red check before source change: `DELETE FROM app_settings RETURNING setting_id
  LIMIT printf('%d', 2)` raised `InvalidArgumentException: SQLite
  UPDATE/DELETE LIMIT expressions must evaluate to an integer`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php`
  passed `1 test files, 235 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php`
  passed `2 test files, 10644 assertions, 0 failures`.
- Focused growth in the edited matrix file is 25 new PASS cases and 133 new
  assertions.

Dependency closure:

- No new support component is needed. This extends the existing native PHP
  constant-expression evaluator used by row-value UPDATE/DELETE LIMIT parsing.

Non-overlap:

- This does not repeat accepted arithmetic, cast, CASE, min/max, length,
  `quote()`, or `typeof()` LIMIT expression coverage. It adds a distinct
  formatted-text scalar expression family and keeps all names generic.
