# Final Numbered Production Suffix Cleanup Dynamic

Consolidated the STAT4 expression partial relevant-row-churn entrypoint and
private helper names inside
`SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`. Observable result
keys, dependency strings, and receipt keys remain unchanged so accepted
behavior is preserved.

The exact user-named source-next150 suffix is absent from current production,
tests, and examples in this worktree.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialRelevantRowChurnTest.php`
- `php -l lanes/libsqlite/examples/wordpress-planner-stat4-expression-partial-relevant-row-churn.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialRelevantRowChurnTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*.php`
- `php lanes/libsqlite/examples/wordpress-planner-stat4-expression-partial-relevant-row-churn.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a source-only
helper-name consolidation over accepted STAT4 expression partial behavior.
