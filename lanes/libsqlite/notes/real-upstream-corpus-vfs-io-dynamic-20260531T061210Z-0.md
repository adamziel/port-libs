# real-upstream-corpus-vfs-io-dynamic-20260531T061210Z-0

Base accepted HEAD: `2139c8ce030e83a04c23079c17d6da80f20ffd83`.

This slice ports a real upstream VFS I/O fault-recovery cluster from the hydrated SQLite corpus:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr.test`: `ioerr-1`, `ioerr-2`, `ioerr-3`, `ioerr-5`, `ioerr-7`, `ioerr-9`, `ioerr-10`, `ioerr-12`, `ioerr-13`, `ioerr-14`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr2.test`: `ioerr2-3`, `ioerr2-4`, `ioerr2-5`, `ioerr2-6`, `ioerr2-7`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr3.test`: `ioerr3-1`, `ioerr3-2`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/tempfault.test`: faultsim `1`, `2`, `3`, `4`

Implementation:

- Added `SQLiteVfsIoDynamicPlan::ioErrorFaultRecoveryProfile()` for dynamic pager/VFS fault-recovery state: persistent pager error state, rollback requirement, statement rollback, hot-journal replay, temp database accepted row states, checksum preservation, refcount reset, and integrity status.
- Added `SQLiteRealUpstreamCorpusVfsIoDynamicRecovery20260531T061210ZTest.php` with 1,260 dynamic variants plus source/count guards, for 1,262 focused TestRunner PASS cases and 13,925 assertions.

Non-overlap:

- Avoids accepted VFS file writer, rollback-journal apply/commit, WAL byte truncation, VFS sync apply, lock-state/process-lock, temp-fault, syscall, and prior `SQLiteVfsIoDynamicPlan` `io.test` traffic/default-page-size/safe-append coverage.
- This slice covers `ioerr*` and `tempfault` recovery semantics, not metadata admission rows or static ledger bookkeeping.

Verification:

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicRecovery20260531T061210ZTest.php`
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicRecovery20260531T061210ZTest.php`
  - Result: `1 test files, 13925 assertions, 0 failures`

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP VFS I/O dynamic planning helper and adds bounded fault-recovery behavior for real upstream SQLite corpus sections.
