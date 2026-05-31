# Row-value UPDATE/DELETE LIMIT Dynamic Parity

Micro-slice: `full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T052725Z-0`

Base accepted HEAD: `e6f2f82c55065569a50189235fcdfbfbb9091c15`

Upstream sources cited by the focused parity file:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue7.test`

Behavior added:

- `SQLiteUpdateDeleteReturningSql` now evaluates deterministic SQLite math scalar functions in `UPDATE` / `DELETE` `LIMIT` and `OFFSET` clauses: `ceil()`, `ceiling()`, `floor()`, `trunc()`, `sqrt()`, `pow()`, and `power()`.
- The existing dynamic row-value parity file now covers those math scalar expressions in outer UPDATE/DELETE windows and row-value tuple subquery windows, plus malformed NULL, non-numeric, negative-square-root, and arity cases.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php`: `2 test files, 10111 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This extends the existing native PHP constant-expression evaluator used by the row-value UPDATE/DELETE LIMIT executor.

Non-overlap:

- This does not add generated numbered helper classes, WordPress-specific APIs, metadata-only rows, or duplicate accepted dynamic LIMIT arithmetic/cast/CASE/minmax/length/quote/typeof/text-function coverage. It adds a distinct math-scalar constant-expression family.
