# real-upstream-corpus-btree-index-dynamic-20260530T225651Z-0

Status: ready for integration.

This slice adds a non-overlapping real upstream B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`.
- Upstream sections covered: `index-1.1` through `index-9.2`.
- Focus: CREATE INDEX catalog rows, DROP TABLE index cleanup, missing table/column diagnostics, sqlite_master index rejection, duplicate index/table-name collision errors, primary-key autoindex visibility, DROP INDEX missing-index errors, and `EXPLAIN CREATE INDEX` non-mutation.
- Added `SQLiteBTreeIndexDynamicCorpusPlan::indexCatalogLifecycleCases()` with 1000 dynamic real-upstream-backed cases.
- Added `SQLiteRealUpstreamBtreeIndexCatalogLifecycleDynamicTest.php` with 1001 focused TestRunner PASS cases.

Non-overlap:

- Existing accepted B-tree/index coverage already handles index7 partial-index sections, autoindex1/4/5 planner behavior, indexedby planner enforcement, index4 create-index stress, index5 write-order, index8 order/limit, indexA affinity, index expression, without-rowid redundant-key behavior, and b-tree storage mutation/page-move/overflow/freelist/pointer-map batches.
- This slice covers the earlier `index.test` schema/catalog lifecycle and error behavior only.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexCatalogLifecycleDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexCatalogLifecycleDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses lane-local B-tree/index dynamic corpus and schema-catalog behavior helpers.
