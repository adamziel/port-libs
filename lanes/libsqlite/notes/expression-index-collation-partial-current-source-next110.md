# Expression Index Collation Partial Current Source Next110

- Behavior: `SQLiteSelectExpressionIndexPlan` now carries query-side expression
  `COLLATE` metadata through point, IN, BETWEEN, and range constraints. Explicit
  query collations must match the indexed expression collation before the
  planner can use the expression index.
- Partial-index proof now evaluates expression predicates with the matched
  expression/index collation. This admits Application-style
  `lower(option_name) COLLATE NOCASE` partial indexes where mixed-case current
  source literals prove `lower(option_name) >= 'plugin_'`.
- STAT4 point/range estimates, sample ordering, matched samples, and
  current/next boundary evidence use the matched collation, so mixed-case
  samples such as `PLUGIN_ALPHA`, `plugin_beta`, and `Theme_Mods_TwentySix`
  are ranked with SQLite `NOCASE` semantics.
- Application smoke:
  `examples/application-expression-index-collation-partial-current-source-next110.php`
  models copied `wp_options` autoload/plugin scans over a partial expression
  index on `lower(option_name) COLLATE NOCASE`.

Verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteExpressionIndexCollationPartialCurrentSourceNext110Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 61 assertions, 0 failures
```

Dependency closure: no new support component is needed; this reuses the
existing native PHP expression-index planner, partial-predicate prover, and
STAT4 sample model.

Non-overlap: avoids accepted batch106 planner subquery partial-index routing,
accepted expression-index range-cost ranking, accepted expression `ORDER BY`,
accepted UTF-16/LIKE/GLOB collation work, and accepted VDBE
distinct/collation cursor refresh. This slice is narrowly the query-side
collation proof for partial expression indexes and STAT4 current/next
estimates.
