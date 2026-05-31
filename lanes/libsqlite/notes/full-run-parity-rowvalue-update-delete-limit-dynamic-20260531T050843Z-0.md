# Row-value UPDATE/DELETE LIMIT Dynamic Parity

Micro-slice: `full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T050843Z-0`

Base accepted HEAD: `7174979f2808c9ccf08c3331545660695c77e192`

Upstream sources cited by the focused parity file:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_update.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test`

Behavior added:

- `SQLiteUpdateDeleteReturningSql` now evaluates SQLite `quote()` and `typeof()` constant expressions in `UPDATE` / `DELETE` `LIMIT` and `OFFSET` clauses.
- The dynamic row-value parity test covers outer update/delete windows and row-value subquery tuple windows that use `quote()` / `typeof()` in limit expressions.

Focused evidence:

- Before this slice, `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php` passed `1 test files, 9335 assertions, 0 failures`.
- After this slice, `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php` passed `2 test files, 9448 assertions, 0 failures`.
- Focused pair assertion movement is `9437 -> 9448`, plus 11 focused assertions.

Dependency closure:

- No new support component is needed. This extends the existing native PHP constant-expression evaluator used by the row-value UPDATE/DELETE LIMIT executor.

Non-overlap:

- This does not add generated numbered helper classes, WordPress-specific APIs, metadata-only rows, or duplicate accepted dynamic LIMIT arithmetic/cast/CASE/minmax/length coverage. It adds a distinct scalar-function constant-expression family.
