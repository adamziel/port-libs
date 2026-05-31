## real-upstream-corpus-vfs-io-dynamic-20260531T032538Z-0

Scope: real upstream SQLite VFS I/O dynamic corpus, focused on hydrated
`test/ioerr5.test`.

Added behavior:

- `ioerr5.test ioerr5-1.normal` and `ioerr5-1.exclusive`: persistent commit
  I/O errors leave the pager in error state while a read cursor is open, and
  memory reclamation through UTF-16 prepare must not flush dirty pages.
- `ioerr5.test ioerr5-2.normal` and `ioerr5-2.exclusive`: release-memory before
  COMMIT either reports disk I/O error or reaches a clean commit without
  corrupting pager state.
- `ioerr5.test ioerr5-1.X` and `ioerr5-2.X`: zero open files after cleanup.

Focused test growth:

- New test file:
  `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerr5MemoryReclaimDynamicTest.php`.
- Dynamic matrix: 800 generated behavior cases plus source-citation and
  malformed-input guard cases.
- Source profile:
  `SQLiteVfsIoDynamicPlan::pagerErrorMemoryReclaimProfile()`.

Non-overlap:

- Does not repeat accepted atomic page-size, quick-balance, lock byte-range,
  sync/write/rollback-commit, WAL checkpoint, mmap, pointer-map, or ioerr2/3/4
  clusters.
- Uses real upstream source truth from
  `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr5.test`.

Dependency closure:

- No new support component is needed. The slice reuses existing VFS dynamic
  corpus and pager cache-pressure modeling in native PHP.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerr5MemoryReclaimDynamicTest.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerr5MemoryReclaimDynamicTest.php`:
  1 file, 21605 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicExpandedCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamVfsIoDynamicRemainderTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoErrorDynamicTest.php`:
  3 files, 44696 assertions, 0 failures.
- `git diff --check -- lanes/libsqlite`: passed.
- `SQLiteNoWordPressSpecificApiTest.php`: not present in this worktree.
