# real-upstream-corpus-btree-index-dynamic-20260530T214750Z-0

Status: ready for integration.

This slice adds a non-overlapping real upstream B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index7.test`.
- Upstream sections covered: `index7-3.1` through `index7-8.1`.
- Focus: partial UNIQUE exclusion behavior, duplicate rejection, qualified-name partial-index predicate handling, partial-index routing through views, `IS TRUE` preservation after an `IS NOT TRUE` partial index is added, and incomplete `sqlite_stat1` planner admission for tiny tables.
- Focused assertion growth: `1002` TestRunner PASS cases in `SQLiteRealUpstreamBtreeIndex7PartialUniqueDynamicTest.php`.

Non-overlap:

- Existing accepted index7 coverage already handles sections `index7-1.1` through `index7-2.104`.
- This avoids accepted B-tree page relocation, overflow freelist release, bulk overflow freeblocks, root collapse, index-interior merge, index4 create-index stress, index5 write-order, index8 order/limit, indexA planner/affinity, autoindex1/autoindex5, indexfault, index expression, and numindex batches.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex7PartialUniqueDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndex7PartialUniqueDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses lane-local B-tree/index dynamic corpus planner, partial-index predicate, stat-row, integrity-result, and planner-detail helpers.
