# real-upstream-corpus-vfs-io-dynamic avfs growth/shell handoff

- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/avfs.test`.
- Ported sections: `avfs-3.1` through `avfs-3.5` grow/shrink/reopen integrity and `avfs-4.1` through `avfs-4.3` shell append/reopen lifecycle.
- Focused growth: 1002 distinct TestRunner cases in `SQLiteRealUpstreamCorpusVfsIoDynamicAvfsGrowthShell20260531Test.php`.
- Behavior assertions: 24,454 focused assertions.
- Non-overlap: this owns append-VFS grow/shrink and shell lifecycle matrix admission. It does not repeat `io.test` traffic/sync/default-page-size/quick-balance, `mmap*`, `ioerr*`, `journal2`, `delete_db`, `8_3_names`, WAL, rollback commit/apply, sync-plan/apply, lock-state, file-writer, savepoint rollback, or crash8 hot-journal clusters.
- Dependency closure: no new support component is needed; this reuses existing native `SQLiteVfsIoDynamicPlan` append-VFS layout, growth, and shell lifecycle primitives.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicAvfsGrowthShell20260531Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicAvfsGrowthShell20260531Test.php`
- No-WordPress API guard: not run; `lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` is absent in this worktree.
- `git diff --check -- lanes/libsqlite`
