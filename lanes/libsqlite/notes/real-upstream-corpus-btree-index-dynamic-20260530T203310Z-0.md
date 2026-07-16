# Real Upstream Corpus: B-tree / Index Dynamic indexfault Recovery

- Slice: `real-upstream-corpus-btree-index-dynamic-20260530T203310Z-0`
- Base accepted HEAD: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexfault.test`
- Upstream scenarios covered: `indexfault-1.1`, `indexfault-2.1`, `indexfault-2.2`, and `indexfault-3.1` through `indexfault-3.5`.

Behavior delta:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::indexFaultCreateIndexRecoveryCases()`.
- Wired 1,000 focused real-upstream TestRunner cases into `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`.
- The cases preserve CREATE INDEX recovery behavior under ordinary faultsim attempts, soft-heap-limit retries, custom `xOpen` failures, custom temp `xWrite` failures, and release-memory temporary b-tree spill retry behavior.
- Focused file verification now passes `1 test files / 114585 assertions / 0 failures`.

Non-overlap:

- This does not repeat accepted `index5` write-order behavior, `index7` partial-index stat mutation, `indexA` partial-affinity matrix, `index3` quoted identifier compatibility, `autoindex5` coroutine subquery behavior, `autoindex1` automatic-index planner coverage, expression-index range costs, PRAGMA index metadata, B-tree page relocation/root collapse, overflow freelist release, VFS writer, or source-neutral cleanup.
- The new surface is specifically upstream `indexfault.test` CREATE INDEX fault-injection recovery and integrity preservation.

Dependency closure:

- No new support component is needed. The slice reuses existing native B-tree/index corpus planning and focused PHP TestRunner infrastructure.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
