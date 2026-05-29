# SQLite planner STAT4 expression partial current-source prepared handoff resume-window

Behavior: consolidates `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializePreparedHandoffResumeWindow()` so the prepared handoff resume-window no longer exposes the generated 862877 suffix in its direct test, example, note, production fence key, status string, dependency marker, or cursor opcode. The stable fence still threads the previous handoff signature, rechecks each carried current-source row projection, and preserves the same slice range internally.

Files:
- `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `SQLitePlannerStat4ExpressionPartialPreparedHandoffResumeWindowTest.php`
- `wordpress-sqlplanner-stat4-expression-partial-prepared-handoff-resume-window.php`

Validation:
- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffResumeWindowTest.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffValidationContinuationTest.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-prepared-handoff-resume-window.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-prepared-handoff-validation-continuation.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffResumeWindowTest.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialPreparedHandoffValidationContinuationTest.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-prepared-handoff-resume-window.php --self-test`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-prepared-handoff-validation-continuation.php --self-test`
- `git diff --check`

Next slice: continue with planner878-893 from the prepared handoff resume-window handoff fence.
