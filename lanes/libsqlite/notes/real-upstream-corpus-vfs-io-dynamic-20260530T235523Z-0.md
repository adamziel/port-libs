# real-upstream-corpus-vfs-io-dynamic-20260530T235523Z-0

Ported a focused upstream VFS mmap/IO behavior cluster from the hydrated SQLite checkout:

- `mmap3.test` `mmap3-1.0`, `mmap3-1.2`, `mmap3-1.3`, `mmap3-1.4`, `mmap3-1.5`, `mmap3-1.6`, `mmap3-1.7`, and `mmap3-1.8`.
- Behavior covered: `PRAGMA mmap_size` state transitions while creating or dropping schema objects, ignored/deferred changes while an active read cursor is scanning `t1`, reading `PRAGMA mmap_size` inside that cursor, zeroing mmap with function-call syntax, and re-enabling mmap after a zero setting.

Changes:

- Added `SQLiteVfsIoDynamicPlan::mmapPragmaStateProfile()`.
- Added `SQLiteRealUpstreamCorpusVfsMmapPragmaStateDynamicTest.php` with 1,010 focused TestRunner PASS cases and 19,179 behavior assertions.

Non-overlap:

- This does not repeat accepted mmap read-count growth, mmap syscall fault logging, mmap warm, sparse big mmap, corrupt-tail reads, VFS file writer, VFS lock state, rollback-journal apply, WAL checkpoint transaction, or sync-plan/application clusters. It targets the upstream `mmap3.test` PRAGMA state behavior specifically.

Dependency closure:

- No new support component is needed. The slice reuses the existing source-neutral `SQLiteVfsIoDynamicPlan` VFS/IO corpus helper and the current focused PHP TestRunner.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsMmapPragmaStateDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsMmapPragmaStateDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`
