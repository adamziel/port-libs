# real-upstream-corpus-btree-index-dynamic-20260531T044409Z-0

- Base accepted HEAD: `ea98db4ecded4356aee592549997cc44a35fab5b`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexedby.test`.
- Ported upstream sections: `indexedby-2.1` through `indexedby-12.4`.
- Added focused coverage: `SQLiteBTreeIndexDynamicCorpusPlan::indexedByDynamicPlannerEnforcementCases(1000)` plus `SQLiteRealUpstreamIndexedByDynamicPlannerTest.php`.
- Behavior covered: `INDEXED BY` and `NOT INDEXED` parser/planner enforcement for `SELECT`, `DELETE`, and `UPDATE`; named-index prepare errors; view dependency routing; rowid tail constraints through covering indexes; `indexed` identifier context; and unusable partial-index `no query solution` failures.
- Focused result: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamIndexedByDynamicPlannerTest.php` passed with `1 test files, 16589 assertions, 0 failures` and `1003` PASS lines.
- Proposed selected movement: `+1003` PASS lines, from `2125874` to `2126877` in `lane-status.json`.
- Non-overlap: this does not repeat accepted B-tree page relocation, root collapse, overflow freelist release, index sort-order, index8 ORDER BY LIMIT, tail schema-affinity, bestindex, or where dynamic corpus files already present in the accepted base.
- Dependency closure: no new support component needed; reuses lane-local B-tree/index dynamic planner fixtures.
- Domain specificity: no new domain-specific source text or APIs.
- Root harness: not run - isolated micro-slice.
