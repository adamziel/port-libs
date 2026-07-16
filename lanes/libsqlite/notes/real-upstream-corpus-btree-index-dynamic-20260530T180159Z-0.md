# Real upstream corpus: btree index dynamic delete sequence

- Slice: `real-upstream-corpus-btree-index-dynamic-20260530T180159Z-0`
- Base accepted HEAD: `f66597de21a7c168178b6eec67c6e12b5daf324d`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/index.test`
- Ported scenarios: `index-10.4` through `index-10.8`, covering duplicate-key index deletes, range-style delete sequences, leading duplicate removal, full delete exhaustion, and descending/interior delete orders.
- Focused behavior: `SQLiteBTreeIndexDeleteSequenceCorpusPlan::applyIndexDeleteSequence()` applies real index-leaf delete sequences through `SQLiteIndexLeafPage`, records per-step page snapshots, verifies remaining index record order, freeblock growth, and B-tree page-header integrity.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusTest.php` passed `1 test files, 61 assertions, 0 failures`.
- Lint: `php -l lanes/libsqlite/src/SQLiteBTreeIndexDeleteSequenceCorpusPlan.php` and `php -l lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusTest.php` passed.
- Diff check: `git diff --check -- lanes/libsqlite` passed.
- API guard: `lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` is not present in this worktree; changed-file domain scan found no WordPress-specific names.
- Dependency closure: no new support component is needed; the slice reuses existing index-leaf, index-cell, record encoding, affinity comparison, and page-header freeblock primitives.
- Non-overlap: this does not repeat page relocation, root collapse, overflow freelist release, index-interior merge, VFS lock/write/sync, WAL checkpoint/savepoint, JSON table, SELECT SQL, or source-neutral cleanup work.
