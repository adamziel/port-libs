# real-upstream-corpus-btree-index-dynamic-20260531T022737Z-0

Implemented a focused B-tree/index corpus slice from upstream SQLite `index5.test`.

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index5.test`
- Upstream scenarios: `index5-1.1` through `index5-1.3`
- Ported behavior: large `CREATE INDEX i1 ON t1(x)` at 1024-byte page size preserves page-size/integrity after drop/recreate and writes database pages in mostly forward-contiguous order, matching the upstream `nForward > 2*(nBackward+nNoncont)` xWrite predicate.
- New PHP behavior surface: `SQLiteIndexBuildWriteOrderPlan::createIndexWriteOrder()`
- New focused test file: `SQLiteRealUpstreamCorpusBtreeIndex5WriteLocalityDynamicTest.php`
- Focused count: `1002` TestRunner PASS cases / `13012` assertions / `0` failures.

Non-overlap:

This slice covers upstream `index5.test` create-index write locality. It does not repeat accepted index2/index3 wide-schema, unique rollback, quoted identifier catalog, page relocation, root collapse, overflow freelist release, bestindex, expression-index range-cost, or accepted VFS/WAL rollback/sync writer clusters.

Dependency closure:

No new support component is needed. The slice uses a bounded native PHP planner for SQLite page-size, index-build ordering, drop-index preservation, and integrity invariants. It does not require external SQLite binaries, live services, or shared-cache mutation.

Verification:

- `php -l lanes/libsqlite/src/SQLiteIndexBuildWriteOrderPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusBtreeIndex5WriteLocalityDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusBtreeIndex5WriteLocalityDynamicTest.php`
- `git diff --check -- lanes/libsqlite`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` was not run because the guard file is not present in this worktree.
