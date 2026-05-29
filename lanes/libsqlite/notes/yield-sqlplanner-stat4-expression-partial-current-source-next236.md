# sqlplanner-stat4-expression-partial-current-source-next236

## Behavior

Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, layered on the accepted current-source STAT4 expression partial planner chain through next233.

This slice validates `sqlite_stat4` density vectors for the selected partial expression index:

- parses the first `neq`, `nlt`, and `ndlt` count for each current STAT4 window sample;
- recomputes the same density vector from current rows that still satisfy the partial expression-index predicate;
- rejects reuse when sample rowids still resolve but duplicate counts or less-than/distinct-less-than counts are stale;
- appends `ValidateCurrentSourceStat4DensityVectors` only for a ready reusable cursor.

## Focused Evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext236Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 82 assertions, 0 failures
```

The 82 focused PASS lines cover ready reuse, duplicate `neq` density for `plugin_forms`, `nlt` and `ndlt` vectors for later sample keys, cursor-program admission, selected-plan/stat4 fence signatures, stale `neq`, stale `nlt`, stale `ndlt`, combined stale-vector rejection, malformed stat strings, and invalid input guards.

WordPress smoke:

```text
$ php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next236.php
```

The smoke models copied `wp_options` plugin rows where ANALYZE/current-source churn can leave valid sample rowids with stale density vectors.

## Non-Overlap

Avoids accepted next233 sample-row guards, next230 gap peers, expression ORDER BY, range-cost ranking, JSON table, WAL/VFS, B-tree, trigger, UTF/collation, and suite-runner clusters. This slice only proves `neq/nlt/ndlt` density-vector freshness for current-source STAT4 partial expression-index reuse.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local STAT4 expression partial planner fixtures and current PHP array row/source models.

## Next

If more STAT4 planner work is needed, the next disjoint target should be parser/executor integration that consumes this density guard during real SELECT planning, not another standalone sample-row or gap-density duplicate.
