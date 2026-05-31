# real-upstream-corpus-btree-index-dynamic-20260531T001222Z-0

Status: ready for integration.

This slice adds a non-overlapping real upstream B-tree/index dynamic corpus batch from the hydrated SQLite upstream checkout:

- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`.
- Upstream sections covered: `index-4.2` through `index-4.12`.
- Focus: index create/drop/recreate lookup behavior over the `cnt` / `power` power-of-two table while active indexes change from two usable indexes, to one usable index, to no indexes.
- Focused PASS growth: 1000 distinct TestRunner PASS cases in `SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php`.

Non-overlap:

- Existing accepted B-tree/index coverage already includes `btree01`, `btree02`, `index4`, `index5`, `index6`, `index7`, `index8`, `index9`, `indexA`, `indexedby`, `indexexpr`, `indexfault`, `autoindex`, and `numindex` dynamic sections.
- This batch specifically extends the older small `index.test` lookup coverage into a 1000-case create/drop/recreate active-index matrix and avoids page relocation, overflow freelist release, root collapse, index-interior merge, expression ORDER BY, JSON table, WAL/VFS, and source-neutral cleanup surfaces.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBTreeIndexDynamicCorpusTest.php` passed: 1 test files, 328715 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: 1 test files, 3 assertions, 0 failures.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure: no new support component is needed. This reuses lane-local B-tree/index dynamic corpus helpers and the existing row lookup model for upstream `index.test`.
