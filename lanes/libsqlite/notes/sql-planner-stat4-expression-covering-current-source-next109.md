# sql-planner-stat4-expression-covering-current-source-next109

This slice adds a bounded current-source covering row preview for STAT4-backed
expression indexes. `SQLiteSelectExpressionIndexPlan::stat4ExpressionCoveringCurrentSourcePlan()`
reuses the existing expression-index planner, requires a covering STAT4 plan,
then emits current/next rows from copied current-source rows whose expression
keys match the selected STAT4 samples.

Behavior covered:

- JSONB expression indexes over copied `wp_options.option_value` plugin channel
  payloads.
- `IN`, point, `BETWEEN`, and open range predicates with ordinary residual
  equality filtering such as `autoload = 'yes'`.
- Covering column payloads and covering expression payloads for current/next
  rows.
- Lower, length, and integer-cast expression row previews.
- Guarded rejection for non-covering indexes, indexes without STAT4 evidence,
  malformed current rows, missing expression columns, and invalid covering
  column requests.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionCoveringCurrentSourceNext109Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 51 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-planner-stat4-expression-covering-current-source-next109.php
application planner stat4 expression covering current-source next109: idx_wp_options_channel_covering_stat4_next109 rows=3 matched=2 keys=beta,beta,stable names=plugin_beta_a,plugin_beta_b,plugin_stable
```

Dependency closure: no new support component is needed. This reuses the native
PHP expression-index planner, STAT4 sample normalization, JSON path validation,
and row-array current-source fixtures.

Non-overlap: avoids accepted expression-index range-cost ranking, STAT4 JSON
covering-order evidence, STAT4 expression range current-source evidence,
partial-index/subquery planner routing, expression `ORDER BY`, JSON table
cursor/source/hidden/visible constraint clusters, WAL/VFS apply clusters, and
B-tree page/freeblock/freelist clusters. The new behavior is the current-source
covering row stream gated by matched STAT4 expression samples.
