# sqlplanner-stat4-expression-partial-current-source-selectivity-fence

Status: focused PHP behavior growth for a current-source STAT4 expression
partial-index planner edge.

Behavior:

- Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, composing
  the accepted next206 current-source partial-OR proof and adding a fresh STAT4
  sample-window selectivity fence.
- Admits the current partial expression-index scan only when the matched
  partial-OR arm has current STAT4 sample keys for every selected expression
  key, duplicate keys are covered through the sample `neq` estimate, `nlt`
  counters remain monotonic, and the cursor program records a
  `VerifyStat4SelectivityWindow` opcode.
- Blocks stale current-source reuse when a selected expression key loses its
  STAT4 anchor, STAT4 counters become non-monotonic, or the inherited partial
  OR proof is not satisfied.

Application smoke:

- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-selectivity-fence.php --self-test`
- Output: `application-sqlplanner-stat4-expression-partial-current-source-next208 self-test passed`

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourcePartialOrSelectivityFenceTest.php`
- Result: `1 test files, 58 assertions, 0 failures`

Dependency closure:

- No new support component needed. This reuses lane-local STAT4 sample metadata,
  current-source partial expression-index materialization, and partial-OR
  predicate proof helpers.

Non-overlap:

- Avoids accepted next206 partial OR implication, next203 boundary-only
  fencing, next185 sample provenance, expression ORDER BY, range-cost ranking,
  JSON, WAL, VFS, B-tree, trigger, and UTF clusters. The new surface is the
  matched partial-OR arm selectivity fence over the current STAT4 sample
  window.
