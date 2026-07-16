# real-upstream-corpus-vfs-io-dynamic-20260531T041950Z-0

Base accepted HEAD: `5823f556f77d50bd49ce909acb22097fc44da229`.

Added `SQLiteRealUpstreamCorpusVfsMmap3PragmaStateDynamicTest.php` as an additive real upstream VFS I/O corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/mmap3.test`

Covered upstream sections:

- `mmap3-1.0`: initial `PRAGMA mmap_size` state after row population and virtual-table setup.
- `mmap3-1.2`: direct `mmap_size` shrink after schema read and table creation.
- `mmap3-1.3`: direct `mmap_size` growth after dropping a table.
- `mmap3-1.4`: active read cursor defers a shrink request.
- `mmap3-1.5`: active read cursor defers a disable request.
- `mmap3-1.6`: reading `PRAGMA mmap_size` inside an active cursor reports the retained mapping size.
- `mmap3-1.7`: function syntax disables mmap after the active cursor finishes.
- `mmap3-1.8`: active cursor accepts growth from zero.

Focused movement:

- Adds `1003` focused TestRunner PASS lines.
- Adds `21006` behavior assertions.
- Expected lane-status `phpPass` movement: `2025275 -> 2026278`.
- Mapped denominator remains unchanged because upstream coverage is already `1589 / 1589`.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsMmap3PragmaStateDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsMmap3PragmaStateDynamicTest.php` passed: `1 test files, 21006 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Non-overlap:

This owns `mmap3.test` PRAGMA `mmap_size` state transitions over schema changes and active read cursors. It does not repeat accepted mmap active-resize tests, mmap warm/fault/corrupt tests, syscall registry/retry/temp/chunk tests, appendvfs, short 8.3 names, `io.test` quick-balance/atomic/cache-retention matrices, `ioerr*`, `walvfs`, VFS writer, sync, lock-state/process-lock, rollback-journal apply/commit, or WAL checkpoint/savepoint clusters.

Dependency closure:

No new support component is required. The slice reuses the existing generic `SQLiteVfsIoDynamicPlan::mmapPragmaStateProfile()` native PHP planning surface and adds focused upstream-backed assertions only.
