# Planner STAT4 Numbered Method Consolidation Fiftieth Pass

Consolidated `SQLiteStat4ExpressionPartialCurrentSourceNextPlan` by removing the
numbered production method/helper suffixes and exposing stable
canonical method names. The direct focused test and Application smoke were renamed
to stable non-numbered paths while preserving the same STAT4 expression-partial
current-source assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteStat4ExpressionPartialCurrentSourceTest.php`
- `php -l lanes/libsqlite/examples/application-stat4-expression-partial-current-source.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteStat4ExpressionPartialCurrentSourceTest.php` -> `1 test files, 66 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-stat4-expression-partial-current-source.php` -> ready JSON for `application-stat4-expression-partial-current-source`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a production
name consolidation only.
