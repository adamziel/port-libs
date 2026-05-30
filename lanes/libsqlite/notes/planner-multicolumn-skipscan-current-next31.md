# SQLite planner multicolumn skip-scan current/next31

This slice extends the bounded multicolumn range planner with SQLite-style
skip-scan admission for composite indexes whose leading column is unconstrained
but later columns provide an equality prefix followed by the current range.

Behavior covered:

- Requires distinct-value evidence for each skipped leading column before
  admitting a skip-scan plan.
- Keeps later range terms after the current interval as residual predicates.
- Carries skip-scan loop counts, skipped columns, current index-column offset,
  estimated row/cost impact, and ORDER BY satisfaction only when the requested
  order includes the skipped leading prefix.
- Prefers a direct prefix/range index over the higher-cost skip-scan plan when
  both are available.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerMultiColumnSkipScanCurrentNext31Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 58 assertions, 0 failures
```

The focused run emits 50 PASS lines, so `lane-status.json` moves `phpPass`
from 10687 to 10737.

Application smoke:

```text
php lanes/libsqlite/examples/application-select-planner-skipscan-current-next31.php
```

Dependency closure: no new support component is needed. The slice reuses the
existing `SQLiteCreateIndex` column parser and `SQLiteMultiColumnRangePlan`
planner model.

Non-overlap: this does not repeat accepted expression-index range-cost ranking,
SQL expression ORDER BY, parser-level SELECT SQL text, JSON table cursor/source
work, B-tree page relocation/root collapse/overflow freelist release, VFS sync
or rollback-journal application, Unicode GLOB ranges, or earlier single-leading
column skip-scan row materialization. The new behavior is planner admission and
costing for current/next multicolumn skip-scan intervals.
