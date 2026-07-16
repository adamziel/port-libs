# real-upstream-corpus-btree-index-dynamic-20260530T224332Z-0

Slice: `real-upstream-corpus-btree-index-dynamic-20260530T224332Z-0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexedby.test`
- Ported sections: `indexedby-2.1` through `indexedby-12.4`, excluding the
  already-covered rowid-affinity section `indexedby-11.2` through
  `indexedby-11.10`.

Behavior added:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::indexedByPlannerEnforcementCases()`
  with 1,000 focused dynamic cases for real `INDEXED BY` / `NOT INDEXED`
  planner enforcement.
- Covered SELECT, DELETE, UPDATE, joins, view invalidation after a forced index
  is dropped, keyword-name parsing, rowid lookup under `NOT INDEXED`, and
  unusable partial-index `no query solution` errors.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` passed with `1 test files, 267202 assertions, 0 failures`.

Non-overlap:

- This does not repeat accepted B-tree page relocation, overflow freelist,
  index-interior merge, `indexA`, `indexfault`, `numindex1`, `index9`,
  `index8`, partial-index `index6`/`index7`, or the existing
  `indexedby-11` rowid-affinity cases.

Dependency closure:

- No new support component is needed. The slice reuses the existing focused
  corpus plan and TestRunner.
