# Real Upstream Corpus: B-tree Indexed-By Dynamic

- Slice: `real-upstream-corpus-btree-index-dynamic-20260531T043518Z-0`
- Base: `7db59d242cf2590641e3217c1b87d71727256c92`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexedby.test`
- Ported scenarios: `indexedby-1.2`, `indexedby-2.1`, `indexedby-2.2`, `indexedby-2.4`, `indexedby-2.7`, `indexedby-3.1.2`, `indexedby-3.8`, `indexedby-3.11`, `indexedby-4.2`, `indexedby-5.1`, `indexedby-5.3`, `indexedby-5.5`, `indexedby-7.3`, `indexedby-7.5`, `indexedby-8.3`, `indexedby-8.5`, `indexedby-9.2`, `indexedby-10.3`, `indexedby-11.5`, `indexedby-11.10`, `indexedby-12.2`, and `indexedby-12.4`.
- Focused movement: `SQLiteRealUpstreamBtreeIndexedByPlannerDynamicTest.php` adds `1000` dynamic TestRunner PASS cases plus `3` source/dependency guard PASS cases, with `15548` assertions.
- Non-overlap: avoids already accepted `index2`, `index3`, `index4`, `index5`, `index7`, `index8`, `index9`, `indexA`, expression-index, bestindex, B-tree page relocation, overflow freelist, and root-collapse clusters. This slice covers the `INDEXED BY`/`NOT INDEXED` planner contract, DML forced-index usage, view dependency failure/recovery, identifier keyword compatibility, rowid-tail equality through index entries, and partial-index `no query solution` diagnostics.
- Dependency closure: no new support component needed; reuses lane-local B-tree/index dynamic corpus planner, schema catalog, planner detail, DML index forcing, view dependency, rowid-tail, and partial-index diagnostic fixtures.
- Verification:
  - `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexedByPlannerDynamicTest.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexedByPlannerDynamicTest.php` => `1 test files, 15548 assertions, 0 failures`
