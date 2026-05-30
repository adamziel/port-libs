# sqlplanner-stat4-expression-partial-current-source-next155

- Added `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` for a STAT4-backed partial expression range scan where current-source schema/stat4/row generations and the partial predicate drift after reprepare.
- Focused behavior: stale prepared `lower(option_name)` partial index rows are fenced out, current rows satisfying `autoload = 'yes' AND blog_id = 1 AND option_value IS NOT NULL` are admitted, and covering index reads remain table-lookup-free.
- Application smoke: `application-stat4-expression-partial-current-source-next155.php` models copied `wp_options` plugin option scans after an ANALYZE/DDL refresh.
- Dependency closure: no new support component needed; this composes lane-local `SQLiteSelectExpressionIndexPlan`, expression-index metadata, STAT4 estimates, and current-source row fences.
- Non-overlap: avoids accepted next133 row-generation partial expression STAT4, next148 non-STAT4 partial covering, next114 collation STAT4 boundaries, next122/128/134/146 covering range, expression ORDER BY, and range-cost ranking surfaces.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext155Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 73 assertions, 0 failures

php lanes/libsqlite/examples/application-stat4-expression-partial-current-source-next155.php
application-stat4-expression-partial-current-source-next155 self-test passed
```
