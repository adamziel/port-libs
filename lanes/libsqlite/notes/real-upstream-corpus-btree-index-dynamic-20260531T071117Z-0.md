# real-upstream-corpus-btree-index-dynamic-20260531T071117Z-0

Status: ready for integration.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/autoindex2.test`

Ported sections:

- `autoindex2-100`: wide production-like schema with 3 tables, 27 declared
  indexes, and 30 `sqlite_master` rows.
- `autoindex2-110`: `sqlite_stat1` cardinalities for the declared indexes.
- `autoindex2-120`: three-way join plan must avoid an automatic covering index
  and avoid a temp B-tree for `ORDER BY t1.ptime desc LIMIT 500`.

Focused addition:

- `SQLiteBTreeIndexDynamicCorpusPlan::autoindex2StatCostingCases(1000)`
- `SQLiteRealUpstreamBtreeAutoindex2DynamicTest.php`
- Adds 1003 focused TestRunner PASS cases: 1000 distinct upstream-backed dynamic
  cases plus corpus-count, invalid-count, and dependency-closure guards.

Non-overlap:

- This targets upstream `autoindex2.test` only. It does not repeat accepted
  `autoindex1`, `autoindex3`, `autoindex4`, `autoindex5`, `bestindex*`,
  `index7`, `index8`, `index9`, `indexA`, `indexedby`, B-tree page
  relocation/root-collapse/overflow freelist/freeblock release, JSON, WAL,
  VFS, PRAGMA, SELECT, or source-neutral cleanup clusters.

Dependency closure:

- No new support component is needed. This reuses the lane-local B-tree/index
  dynamic corpus planner and models upstream `sqlite_stat1` planner-costing
  contracts as focused PHP arrays.

Root harness: not run - isolated micro-slice.
