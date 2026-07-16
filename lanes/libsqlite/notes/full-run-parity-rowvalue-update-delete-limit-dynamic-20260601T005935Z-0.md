# Row-Value UPDATE/DELETE Dynamic LIMIT `timediff()` Parity

Micro-slice: `full-run-parity-rowvalue-update-delete-limit-dynamic-20260601T005935Z-0`

Base accepted HEAD: `21ac2341908d8036647334639cc353ff11f0d89f`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/timediff1.test`
  - `timediff-3.*`: `timediff(A,B)` emits exact signed calendar-difference strings.
  - `timediff-4.*`: `datetime(B,timediff(A,B))` round-trips to `datetime(A)`.
  - `timediff-5.*`: timediff output is accepted as a date/time modifier.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test`
  - LIMIT and OFFSET expressions gate the selected mutation window.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test`
  - row-value tuple predicates can drive UPDATE/DELETE row selection.

## Delta

- Added `timediff()` to `SQLiteUpdateDeleteReturningSql` dynamic LIMIT scalar dispatch.
- Reused `SQLiteCoreScalarFunction::sqlFunctionArguments()` so the LIMIT evaluator follows the existing core timediff/date-time implementation.
- Added focused generic `app_settings` row-value UPDATE/DELETE tests covering:
  - outer UPDATE mutation windows whose LIMIT and OFFSET are computed from `length(timediff())`, `unicode(substr(timediff()))`, `instr(timediff())`, and `datetime(..., timediff(...))`;
  - DELETE row-value tuple subqueries whose LIMIT and OFFSET are computed with the same timediff-derived expressions;
  - direct timediff text rejection when it is not coerced to an integer expression;
  - NULL, invalid date, and wrong-arity timediff rejection through the dynamic LIMIT evaluator.

## Verification

- Baseline adjacent dynamic LIMIT family before this source delta:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBindParameterDynamicTest.php`
  - Result: `3 test files, 21576 assertions, 0 failures`
- Focused timediff test after source change:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitTimediffDynamicTest.php`
  - Result: `1 test files, 307 assertions, 0 failures`
- Adjacent dynamic LIMIT family plus no-domain guard after source change:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitTimediffDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBindParameterDynamicTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitUnistrDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `7 test files, 21967 assertions, 0 failures`

## Non-Overlap

This slice does not repeat accepted row-value dynamic LIMIT coverage for bind parameters, JSON/scalar helper functions, date/time `current_*` forms, `unistr()`, random blobs, LIKE/GLOB, or unhex dispatch. It only fills the deterministic `timediff()` date-difference gap inside row-value UPDATE/DELETE LIMIT/OFFSET expression evaluation.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local core scalar date/time implementation, row-value UPDATE/DELETE planner/executor, tuple-subquery selector, and dynamic LIMIT expression evaluator.
