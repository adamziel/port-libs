# Real Upstream Autoindex Dynamic Corpus

Slice: `real-upstream-corpus-btree-index-dynamic-20260530T194807Z-0`

Base accepted HEAD: `4fa72fa71b26a19fe54f9ce85268cd96396282ab`

Added a lane-local automatic-index corpus derived from hydrated upstream SQLite files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/autoindex1.test`
  - `autoindex1-100` through `autoindex1-113`: join results are preserved while `PRAGMA automatic_index=ON` admits a transient covering index, lowers stmt step count from 63 to 7, records 7 automatic-index inserts, and emits the automatic-index warning.
  - `autoindex1-200` through `autoindex1-212`: correlated scalar subquery form admits the transient covering index and preserves the same result rows.
  - `autoindex1-400` through `autoindex1-401`: ten-way equality-chain join returns `row_count - 9` rows when transient indexes are available.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/autoindex2.test`
  - `autoindex2-100` through `autoindex2-120`: real-world stat1/catalog case suppresses a transient automatic index when declared indexes and `ANALYZE sqlite_master` statistics dominate cost.

Focused movement:

- New focused TestRunner PASS cases: `2604`.
- Focused assertions: `63534`.
- Expected dashboard movement: `phpPass +2604` if accepted as non-overlapping PASS-line growth.

Non-overlap:

- This does not repeat explicit `CREATE INDEX` build tests, partial-index theorem/proof rows, expression-index range-cost ranking, B-tree page relocation/root-collapse/overflow freeblock coverage, or JSON/WAL/VFS accepted clusters. It covers transient automatic-index admission, result preservation, stmt-status counters, automatic-index warning evidence, and stat-driven automatic-index suppression.

Dependency closure:

- No new support component needed. The patch reuses lane-local PHP row-array planning and adds a small native automatic-index plan model for upstream planner behavior.

Verification:

- `php -l lanes/libsqlite/src/SQLiteAutoIndexDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamAutoIndexDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamAutoIndexDynamicCorpusTest.php`
