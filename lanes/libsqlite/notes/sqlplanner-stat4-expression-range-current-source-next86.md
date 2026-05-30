# sqlplanner-stat4-expression-range-current-source-next86

Status: focused PHP behavior growth for STAT4 expression-index range planning.

This slice tightens `SQLiteSelectExpressionIndexPlan::boundedRangePlans()` so
AND-connected range predicates on the same expression collapse to the current
effective scan interval before STAT4 cost and current/next boundary evidence is
derived. Redundant looser lower/upper bounds no longer produce alternate
bounded plans or looser `stat4RangeCurrentNext` evidence; same-value bounds use
SQLite-style exclusivity rules, and empty exclusive point intervals are
rejected.

Application path: copied `wp_options` queries frequently stack option-name range
guards from user filters and importer safety clauses. The new smoke proves a
`lower(option_name)` expression-index range scan uses the tight current-source
interval (`home` to `transient_timeout`) rather than older looser bounds before
choosing native PHP query/import planning.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionRangeCurrentSourceNext86Test.php
Focused test run: 1 selected test files (root lock skipped)
12 PASS lines
1 test files, 58 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionRangeCurrentNext81Test.php
Focused test run: 1 selected test files (root lock skipped)
11 PASS lines
1 test files, 55 assertions, 0 failures

php lanes/libsqlite/examples/application-planner-stat4-expression-range-current-source-next86.php --self-test
application-planner-stat4-expression-range-current-source-next86 self-test passed
```

Dependency closure: no new support component is needed. The patch reuses
existing native PHP expression-index parsing, STAT4 sample normalization, and
planner estimate code.

Non-overlap: avoids accepted expression-index range-cost ranking, next81 STAT4
single-pair boundary evidence, parser-level SELECT text, SQL expression
`ORDER BY`, JSON table planner/cursor/source work, WAL/VFS/B-tree storage
clusters, and Unicode GLOB work. The new surface is only current-source
effective interval selection for redundant expression-index range terms.
