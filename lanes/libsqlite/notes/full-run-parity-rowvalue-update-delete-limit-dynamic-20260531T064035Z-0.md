# Row-value UPDATE/DELETE LIMIT Dynamic Parity

Micro-slice: `full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T064035Z-0`

Base accepted HEAD: `adb26e7f16ecd89937cf2d16ad3f15841131934b`

Behavior added:

- `SQLiteUpdateDeleteReturningSql` now accepts SQLite two-argument `trim()`,
  `ltrim()`, and `rtrim()` constant expressions in UPDATE/DELETE LIMIT and
  OFFSET clauses.
- Focused coverage exercises outer UPDATE/DELETE windows and row-value
  `IN (SELECT ...)` tuple-source windows using two-argument trim expressions.
- NULL trim character arguments still evaluate to NULL and are rejected as
  non-integer LIMIT expressions.

Upstream parity sources:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/func.test` for
  two-argument trim/ltrim/rtrim scalar function behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test` for
  LIMIT/OFFSET integer-expression behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test` and
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for
  UPDATE/DELETE ORDER BY LIMIT selection behavior.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
  passed `1 test files, 12007 assertions, 0 failures`.
- Adds 5 focused TestRunner PASS cases to the existing dynamic parity file.

Dependency closure:

- No new support component is needed. This extends the existing native PHP
  constant-expression evaluator used by the row-value UPDATE/DELETE LIMIT
  executor.

Non-overlap:

- This does not repeat accepted arithmetic, cast, CASE, min/max, length,
  quote/typeof, printf/format, scalar SELECT, predicate, row-value NULL, or
  source-neutral API cleanup surfaces. It adds the distinct two-argument
  trim/ltrim/rtrim scalar-function family for LIMIT/OFFSET expressions.
