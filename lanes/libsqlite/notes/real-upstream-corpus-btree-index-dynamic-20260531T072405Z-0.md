# real-upstream-corpus-btree-index-dynamic-20260531T072405Z-0

Base accepted HEAD: `9d0b0fe07345f3693373fb79bddfe1aa2564a7a2`.

Added a real upstream B-tree/index delete corpus slice sourced from hydrated
SQLite upstream files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/delete2.test`
  sections `delete2-1.1` through `delete2-2.2`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/delete3.test`
  sections `delete3-1.1` through `delete3-1.3`.

The new focused corpus owns 1,200 dynamic cases plus 3 guard/source tests. It
covers the historical `delete2.test` corruption boundary where a DELETE while a
read cursor is active must not remove the primary-key index entry without the
table row, the row-callback delete case, and `delete3.test` large rowid
row-list deletion that preserves exactly the odd-key B-tree survivors.

Non-overlap: this does not repeat the existing `delete.test` indexed row-list
coverage, B-tree page relocation/root-collapse/overflow-freelist release, index
write-order, partial-index affinity/planner, REINDEX, or indexed-by dynamic
corpus files.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`:
  no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeDeleteCursorLargeDynamicTest.php`:
  no syntax errors.
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeDeleteCursorLargeDynamicTest.php`:
  `1 test files, 19447 assertions, 0 failures` with 1,203 PASS lines.
- `git diff --check -- lanes/libsqlite`: passed.
- `SQLiteNoWordPressSpecificApiTest.php`: not present in this worktree.

Dependency closure: no new support component is needed; this reuses the
existing lane-local B-tree/index dynamic corpus planner, cursor mutation,
row-list delete, integrity, and primary-key index consistency helpers.
