# real-upstream-corpus-btree-index-dynamic-20260531T010237Z-0

Base accepted HEAD: `db598d2f37de4eb8809eabdfe8470ae863639e6e`.

Ported a non-overlapping real upstream B-tree/index-adjacent corpus slice from
`/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindex3.test`.

Upstream source sections:

- `bestindex3.test` `bestindex3-1.1` through `bestindex3-1.4`: virtual-table
  `xBestIndex` LIKE/equality constraints and multi-index OR plans.
- `bestindex3.test` `bestindex3-1.6.0.1` through `bestindex3-1.6.1.3`:
  omitted and non-omitted virtual-table `xFilter` constraint behavior and rowid
  result order.
- `bestindex3.test` `bestindex3-2.2`: ordinary table LIKE range plus equality
  multi-index OR planning.
- `bestindex3.test` `bestindex3-3.1` and `bestindex3-3.2`: virtual-table
  declared primary-key constraints are ignored for planning.

Focused movement:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::bestindex3VirtualTableLikeOrCases()`
  with `1000` real upstream dynamic behavior cases.
- Added `SQLiteRealUpstreamBestIndex3VirtualTableDynamicTest.php`.
- Focused new test result: `1 test files / 20586 assertions / 0 failures`.
- Related family result: `2 test files / 44164 assertions / 0 failures` across
  accepted `bestindex2` plus new `bestindex3` behavior.

Non-overlap:

- Does not repeat accepted `bestindex2.test` virtual-table join constraint
  behavior.
- Does not repeat JSON-specific `xBestIndex` helpers, JSON visible/hidden
  constraints, B-tree page relocation, overflow freelist, index-interior merge,
  or accepted SQL expression ORDER BY/range-cost planner slices.

Dependency closure:

- No new support component needed; this reuses lane-local dynamic corpus
  planning arrays and existing TestRunner behavior.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBestIndex3VirtualTableDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndex3VirtualTableDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndex2VirtualTableDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBestIndex3VirtualTableDynamicTest.php`
- `if [ -f lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php ]; then php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php; else echo 'no guard present'; fi`
- `git diff --check -- lanes/libsqlite`
