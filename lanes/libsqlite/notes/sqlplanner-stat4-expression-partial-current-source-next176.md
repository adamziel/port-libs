# sqlplanner-stat4-expression-partial-current-source-next176

Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a bounded
planner refinement for STAT4-backed partial expression indexes when the usable
range has an exclusive lower bound and inclusive upper bound.

The WordPress path models copied `wp_options` plugin scans using
`lower(option_name)` over a partial expression index. The plan keeps stale
prepared-statement fences from next164, but emits exact cursor boundaries:
`SeekGT` for `lower(option_name) > 'plugin_cache'` and `IdxLE` for
`lower(option_name) <= 'plugin_t'`. It also records a boundary row audit so
the lower edge is excluded, the inclusive upper edge is admitted, and partial
predicate residual rows remain filtered.

Focused evidence:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext176Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 59 assertions, 0 failures
```

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next176.php --self-test
```

Expected:

```text
wordpress-sqlplanner-stat4-expression-partial-current-source-next176 self-test passed
```

Non-overlap: avoids accepted next173 duplicate STAT4 sample fanout, next172
selectivity refresh, next168 LIKE prefix conversion, expression ORDER BY,
range-cost, JSON, WAL, VFS, and B-tree clusters. This slice only covers exact
exclusive/inclusive expression-range cursor boundaries.

Dependency closure: no new support component is needed. The slice reuses native
PHP STAT4 expression partial current-source planning and adds a bounded cursor
boundary audit.
