# sqlplanner-stat4-expression-range-current-next81

Status: focused PHP behavior growth for STAT4 expression-index range planning.

This slice extends `SQLiteSelectExpressionIndexPlan` with additive
`stat4RangeCurrentNext` evidence. Existing row estimates and ranking remain
unchanged, while bounded expression ranges, `BETWEEN`, and single-ended range
constraints now expose the current/next STAT4 sample pair that brackets the
range lower and upper bounds.

Application path: copied `wp_options` imports can preview a
`lower(option_name)` expression-index range scan and see which sqlite_stat4
samples bracket plugin/transient option-name ranges before choosing a native
PHP query/import plan.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionRangeCurrentNext81Test.php
Focused test run: 1 selected test files (root lock skipped)
11 PASS lines
1 test files, 55 assertions, 0 failures
```

Dependency closure: no new support component is needed. The patch reuses
existing native PHP expression-index parsing, STAT4 sample normalization, and
planner estimate code.

Non-overlap: avoids accepted STAT1 paired range planning, expression-index
range-cost ranking, STAT4 JSON covering/order, SQL expression ORDER BY,
parser-level SELECT text dispatch, JSON table source/cursor/constraint
surfaces, WAL/VFS/B-tree storage clusters, and Unicode GLOB work. The new
surface is only STAT4 current/next boundary evidence for expression-index
range constraints.
