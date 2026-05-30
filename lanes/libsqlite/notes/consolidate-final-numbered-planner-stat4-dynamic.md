# Planner STAT4 dynamic numbered method consolidation

Consolidated the dynamic STAT4 expression-partial production entrypoints and
private helpers for next238, next239, next240, next241, next242, next243,
next244, next246, and next248 into descriptive methods on
`SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`.

Observable planner metadata is intentionally preserved: statuses, `nextNN`
selected-plan keys, STAT4 fence keys, dependency strings, action labels,
proof names, and non-overlap text remain the accepted numbered values. Direct
tests and WordPress smokes now call the descriptive entrypoints.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l` for the 9 changed direct test files and 9 changed example files
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext238Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext239Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext240Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext241Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext242Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext243Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext244Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext246Test.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext248Test.php` -> `9 test files, 610 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php` -> `133 test files, 7537 assertions, 0 failures`
- Changed WordPress examples with `--self-test` passed.
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a production
identifier consolidation only.

Non-overlap: limited to STAT4 expression-partial numbered production methods
and direct callers. It does not change planner behavior, WAL/VFS, JSON table,
B-tree, PRAGMA, trigger, compound SELECT, suite evidence, dashboard files, or
root coordination files.
