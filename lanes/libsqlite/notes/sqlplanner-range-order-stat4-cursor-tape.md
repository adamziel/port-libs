# sqlplanner-range-order-stat4-cursor-tape

Implements a bounded STAT4 range ORDER BY current-source cursor tape for copied
Application `wp_options` option-name scans.

Behavior covered:

- stale prepared statement fences for schema cookie, STAT4 generation, index
  signature, predicate, ORDER BY, and projection changes;
- selected current-source range plan materialization from existing
  `SQLiteMultiColumnRangePlan` STAT4 evidence;
- inclusive/exclusive lower and upper range seek/stop opcodes for ascending and
  descending scans;
- STAT4 lower/upper current/next boundary evidence for range ORDER BY;
- covering-index column reads, deferred table seeks when non-covering, and
  temp-sort opening when ORDER BY is not satisfied by the range source.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerRangeOrderStat4CursorTapeCurrentSourceTest.php`
  - `1 test files, 60 assertions, 0 failures`
  - 60 focused PASS lines.

Dependency closure: no new support component needed; this composes existing
STAT4 multicolumn range planning into current-source cursor tape diagnostics.

Non-overlap: avoids accepted expression-index range-cost, expression ORDER BY,
STAT4 order-covering, JSON, B-tree, WAL, VFS, and encoding clusters by focusing
on plain indexed range ORDER BY cursor opcodes and current-source fences.
