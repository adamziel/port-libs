# planner-expression-index-stat4-covering-current-source

Adds `SQLiteStat4ExpressionCoveringCurrentSourceNextPlan`, a bounded
current-source fence over STAT4 expression covering index scans.

Behavior:

- compares prepared and current schema cookies, STAT4 generations, and index
  signatures before choosing the row stream;
- reuses `SQLiteSelectExpressionIndexPlan::stat4ExpressionCoveringCurrentSourcePlan()`
  for the actual STAT4 expression/covering row filtering;
- emits a VDBE-style cursor tape that opens the current index root, reads the
  expression key and covering columns from the index, elides table lookup and
  temp sorting, and carries the current source fence for reprepare diagnostics;
- falls back to a table/deferred-seek plan when the requested projection is not
  covered.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerExpressionIndexStat4CoveringCurrentSourceTest.php`
- Result: `1 test files, 64 assertions, 0 failures` with 56 PASS lines.
- `php lanes/libsqlite/examples/application-planner-expression-index-stat4-covering-current-source.php`
- Result: selected `current` source, `idx_wp_options_channel_covering_stat4_current_stable`, four covered rows, keys `alpha,beta,beta,stable`.

Non-overlap:

- Avoids accepted expression-index range-cost ranking, next109 standalone STAT4
  covering row filtering, current-source expression covering order current-source
  materialization, and batch109-113 STAT4 expression covering behavior. The new
  slice is the prepared/current source-fence and cursor tape layer over a STAT4
  expression covering scan.

Dependency closure:

- No new support component is needed. This composes existing native
  expression-index parsing, STAT4 sample matching, and lane-local planner
  diagnostics.
