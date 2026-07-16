# real-upstream-corpus-btree-index-dynamic-20260531T045730Z-0

- Base accepted HEAD: `d470482ec8f04bd52049cae518f9a06a2103fe0c`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`.
- Owned upstream sections: `index-10.0` through `index-23.1`.
- Added focused PHP coverage: `SQLiteRealUpstreamBtreeIndexLateLifecycleAffinityDynamicTest.php`.
- Focused growth: `1003` TestRunner PASS cases and `14342` assertions.
- Non-overlap: this covers late `index.test` lifecycle/affinity behavior through the existing `indexLateLifecycleAndAffinityCases()` corpus. It does not repeat accepted `index8` ORDER BY/LIMIT, `indexA`, `indexedby`, `btree02`, `bestindex*`, B-tree page-move, overflow-freelist, root-collapse, or source-neutral cleanup clusters.
- Dependency closure: no new support component needed; the test reuses lane-local B-tree/index lifecycle, autoindex catalog, affinity ordering, conflict-policy, TEMP index scope, and expression-index REINDEX helpers.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexLateLifecycleAffinityDynamicTest.php`
  - Result: `1 test files, 14342 assertions, 0 failures`.

Root harness: not run - isolated micro-slice.
