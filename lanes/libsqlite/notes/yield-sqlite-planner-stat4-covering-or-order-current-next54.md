# SQLite planner STAT4 covering OR order current/next54

This slice adds `SQLiteStat4CoveringOrOrderPlan`, a bounded planner
composition helper for `OR` predicates where each arm is satisfied by a
STAT4-backed covering skip-scan and the arms can be merged in index order.

- Preserves per-arm STAT4 current/next sample evidence from the existing
  skip-scan planner.
- Requires every OR arm to be covering and STAT4-backed before admitting the
  rowid-union plan.
- Reports rowid-union, merge-order, temp-sort, range-column, and aggregate
  current/next evidence for copied `wp_options` planner previews.
- Rejects non-OR predicates, empty OR lists, scalar terms, non-covering arms,
  missing STAT4 arms, and non-skip-scan arms.

Application smoke:

- `examples/application-planner-stat4-covering-or-order-current-next54.php`
  reports a copied `wp_options` OR lookup over plugin and transient option
  names using the `(autoload, blog_id, option_name, option_value)` covering
  index with STAT4 samples and merge-order evidence.

Verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4CoveringOrOrderCurrentNext54Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 55 assertions, 0 failures
```

Non-overlap:

This avoids accepted batch49 STAT4 partial-expression ORDER, batch48 STAT4
skip-scan covering, expression-index range-cost ranking, expression ORDER BY,
SELECT SQL text/subquery/group/order clusters, JSON table source/cursor/
constraint work, VFS/WAL/B-tree storage clusters, and Unicode GLOB behavior.
The new behavior is STAT4-backed covering `OR` admission with per-arm
current/next evidence and order-merge diagnostics.

Dependency closure:

No new support component is needed. The slice reuses lane-local CREATE INDEX
parsing, multicolumn range/skip-scan planning, STAT4 sample costing, covering
column checks, and Application-shaped smoke execution.
