# Consolidate final numbered planner STAT4 dynamic handoff names

Renamed the remaining STAT4 prepared-handoff continuation production entry
points that were still tied to generated ordinal continuation names:

- `materializePreparedHandoffProjectedContinuation()` now owns the next766-781
  projected continuation behavior.
- `materializePreparedHandoffRangeContinuation()` now owns the next782-797
  range continuation behavior.
- `materializePreparedHandoffValidationRange()` now owns the next798-813
  validation range behavior.
- `materializePreparedHandoffWindowContinuation()` now owns the next814-829
  window continuation behavior.

The observable planner receipts remain unchanged: existing `stat4Next...`
result keys, dependency strings, cursor opcodes, cursor modes, status strings,
non-overlap text, and handoff signatures are preserved for downstream tests and
examples.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffSecondContinuationTest.php && php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffThirdContinuationTest.php && php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffFourthContinuationTest.php && php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffFifthContinuationTest.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-prepared-handoff-second-continuation.php && php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-prepared-handoff-third-continuation.php && php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-prepared-handoff-fourth-continuation.php && php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-prepared-handoff-fifth-continuation.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffSecondContinuationTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffThirdContinuationTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffFourthContinuationTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffFifthContinuationTest.php`: `4 test files, 156 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-prepared-handoff-second-continuation.php --self-test`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-prepared-handoff-third-continuation.php --self-test`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-prepared-handoff-fourth-continuation.php --self-test`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-prepared-handoff-fifth-continuation.php --self-test`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php`: `133 test files, 7537 assertions, 0 failures`.

Dependency closure: no new support component needed; this is a production-name
consolidation over the existing STAT4 prepared-handoff planner receipts.

Non-overlap: this changes only the STAT4 prepared-handoff continuation entry
names and direct call sites. It avoids JSON, WAL, VFS, B-tree, trigger, PRAGMA,
compound SELECT, UTF, upstream-suite evidence, and dashboard/status metadata.
