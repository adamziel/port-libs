# real-upstream-corpus-btree-index-dynamic-20260530T195754Z-0

Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/autoindex1.test`.

Ported behavior:

- `autoindex1-100` through `autoindex1-113`: automatic indexes preserve join
  results while reducing probe steps and recording the automatic-index warning.
- `autoindex1-200` through `autoindex1-212`: correlated scalar subqueries may
  build an automatic covering index while non-indexed execution returns the
  same row values.
- `autoindex1-299` through `autoindex1-310`: a join reads from the automatic
  index snapshot even when the right-hand table is updated while the join is in
  progress.
- `autoindex1-400` through `autoindex1-401`: chained joins over unindexed
  tables rely on automatic lookup behavior and produce `row_count - depth + 1`
  paths.
- `autoindex1-500.1` through `autoindex1-502`: non-correlated `IN` subqueries
  scan the RHS, correlated subqueries use an automatic covering index, and an
  outer rowid point lookup suppresses that automatic index.

Focused assertion count:

- `SQLiteRealUpstreamBTreeIndexAutoindexDynamicTest.php` adds 1,605 distinct
  TestRunner cases from real upstream autoindex scenarios.

Non-overlap:

- This does not repeat accepted B-tree overflow freeblock/freelist release,
  page relocation, root collapse, expression-index range costs, JSON table
  source/cursor/constraint behavior, VFS lock/write/sync, or WAL checkpoint /
  savepoint byte behavior.
- The new source class uses generic table names from upstream `autoindex1.test`
  and introduces no domain-specific application API.

Dependency closure:

- No new support component is needed. The slice reuses native PHP array rowsets
  to model the upstream automatic-index planner/executor behavior.
