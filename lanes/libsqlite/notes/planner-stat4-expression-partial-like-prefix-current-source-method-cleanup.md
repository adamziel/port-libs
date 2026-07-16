# Planner STAT4 Expression Partial LIKE-Prefix Method Cleanup

This consolidation removes the numbered production method/helper names for the
STAT4 expression-partial LIKE-prefix current-source path. The canonical
entrypoint is now
`SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan::materializeStat4LikePrefixPartialCurrentSource()`;
its private helper group now uses descriptive `stat4LikePrefixPartial*` names.

Observable output is intentionally preserved. Existing `next168` status values,
dependency strings, proof keys, exception text, test labels, and Application
example filename remain unchanged so existing downstream evidence keeps the
same metadata contract.

Verification targets:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext168Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next168.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext168Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartial*Test.php`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next168.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this only renames
production PHP method symbols inside the existing STAT4 planner implementation.

Non-overlap: this touches only the LIKE-prefix current-source next168 method
group, its direct focused test, and its direct Application smoke. It does not
change STAT4 generated output keys, planner cost behavior, JSON, WAL/VFS,
B-tree, trigger, row-value, suite, or pager domains.
