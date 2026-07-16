# real-upstream-corpus-btree-index-dynamic-20260531T010653Z-0

- Base accepted HEAD: `714d8628d70df34f443545659c4afed0ff8c4b1b`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/indexA.test`.
- Owned upstream sections: `indexA-1.1` through `indexA-8.1`.
- Added focused PHP coverage: `SQLiteRealUpstreamBtreeIndexAPartialAffinityDynamicTest.php` with 1,203 TestRunner cases, including 1,200 dynamic `indexA` partial-index affinity/planner cases.
- Focused verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexAPartialAffinityDynamicTest.php` passed with `1 test files / 18956 assertions / 0 failures`.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexAPartialAffinityDynamicTest.php` passed with `2 test files / 347671 assertions / 0 failures`.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed with `1 test files / 3 assertions / 0 failures`.
  - `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` and `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeIndexAPartialAffinityDynamicTest.php` passed.
  - `git diff --check -- lanes/libsqlite` passed.
- Non-overlap: this batch does not repeat accepted index8 ORDER BY/LIMIT, index7 partial unique/WITHOUT ROWID, indexexpr expression-index, autoindex, B-tree page move/root collapse/overflow freelist release, or WAL/VFS slices. It owns `indexA.test` partial-index affinity and planner behavior.
- Dependency closure: no new support component needed; it reuses the lane-local B-tree/index dynamic corpus planner, partial-index affinity implication, result-row, collation-error, bloom-filter detail, and integrity helpers.
