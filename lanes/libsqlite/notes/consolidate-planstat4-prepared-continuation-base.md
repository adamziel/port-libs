## Planner STAT4 Prepared Continuation Base Consolidation

Consolidated the STAT4 expression-partial prepared-continuation base wrapper by
renaming the production `Next606621` entrypoint and private helpers to stable
descriptive names:

- `materializePreparedContinuationBase()`
- `handoffFencePreparedContinuationBase()`
- `rowsByRowidPreparedContinuationBase()`
- `intListPreparedContinuationBase()`
- `intValuePreparedContinuationBase()`
- `projectedColumnsPreparedContinuationBase()`
- `cursorProgramPreparedContinuationBase()`

Direct test/example files were migrated away from numbered filenames:

- `SQLitePlannerStat4ExpressionPartialPreparedContinuationBaseTest.php`
- `application-sqlplanner-stat4-expression-partial-prepared-continuation-base.php`

The following continuation consumer was updated to read the renamed base fence:
`SQLitePlannerStat4ExpressionPartialCurrentSourceNext622637Test.php`.

Verification:

```sh
php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedContinuationBaseTest.php
php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext622637Test.php
php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-prepared-continuation-base.php
php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next622-637.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedContinuationBaseTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext622637Test.php
php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-prepared-continuation-base.php --self-test
php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next622-637.php --self-test
```

Focused test result: `2 test files, 78 assertions, 0 failures`.

Dependency closure: no new support component needed; this is a production-name
consolidation over existing lane-local STAT4 handoff data.
