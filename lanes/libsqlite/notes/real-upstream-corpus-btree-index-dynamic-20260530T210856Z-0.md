# real-upstream-corpus-btree-index-dynamic-20260530T210856Z-0

Base accepted HEAD: `140c9861a340b8e75fdc8ea93863883edb030323`.

This slice adds a non-overlapping B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/autoindex1.test`.
- Upstream sections covered: `autoindex1-100` through `autoindex-1211`.
- Focus: automatic-index planner gating, transient covering-index targets, status/insert counters, mutation snapshot stability, correlated subquery/list-subquery admission, materialized view/subquery automatic indexes, no-autoindex ORDER BY sort behavior, NULL-sensitive LEFT JOIN guards, unary-plus LEFT JOIN behavior, and WITHOUT ROWID automatic-index admission.
- New focused TestRunner PASS cases: `1000`.
- Focused behavior assertions: `16354`.
- Non-overlap: avoids accepted B-tree page relocation, overflow freelist release, bulk overflow freeblocks, index-interior merge, indexA join planner, index expression, index5 write-order, indexfault, and numindex batches already present in the accepted B-tree/index corpus.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeAutoindexDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeAutoindexDynamicTest.php`
  - `1 test files, 16354 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Dependency closure: no new support component is required. The batch reuses existing lane-local corpus-plan arrays and the existing focused PHP TestRunner.

Root harness: not run - isolated micro-slice.
