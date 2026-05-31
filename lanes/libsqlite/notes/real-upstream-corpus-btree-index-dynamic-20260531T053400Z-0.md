# real-upstream-corpus-btree-index-dynamic-20260531T053400Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexedby.test`

Covered upstream sections:

- `indexedby-6.1` and `indexedby-6.2`: rowid-ordered reads use a secondary index normally, but `NOT INDEXED` forces a table scan.
- `indexedby-7.1` through `indexedby-7.6`: DELETE row discovery respects default, `NOT INDEXED`, and forced `INDEXED BY` planner requirements.
- `indexedby-8.1` through `indexedby-8.6`: UPDATE rowid rewrites respect default, `NOT INDEXED`, and forced `INDEXED BY` planner requirements.

Focused addition:

- `SQLiteBTreeIndexDynamicCorpusPlan::indexedByDmlAndRowidScanCases(1000)`
- `SQLiteRealUpstreamBtreeIndexedByDmlDynamicTest.php`

Non-overlap:

- Existing accepted `indexedby` coverage models broad SELECT/view/join/partial-index enforcement and rowid-tail constraints. This slice owns the upstream DML and rowid-ordered scan band `indexedby-6.1..8.6`, especially the previously unmodeled `6.1`, `6.2`, `7.1`, `7.2`, `7.4`, `7.6`, `8.1`, `8.2`, `8.4`, and `8.6` cases.
- This avoids accepted B-tree page relocation, root collapse, overflow freelist/freeblock release, index-interior merge, `btree02` skip-next, `autoindex1`, `index7`, `index9`, `indexA`, expression-index range-cost, VFS/WAL, JSON, PRAGMA, and SELECT SQL text clusters.

Focused assertion/PASS movement:

- Adds `1003` focused TestRunner PASS cases: `1000` dynamic upstream replay cases plus source-range, invalid-size, and dependency-closure guard cases.

Dependency closure:

- No new support component is needed. This reuses lane-local B-tree/index dynamic corpus planner, DML index forcing, `NOT INDEXED` scan, rowid rewrite, and planner-detail fixtures.
