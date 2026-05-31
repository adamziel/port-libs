# real-upstream-corpus-pager-wal-dynamic-20260531T041438Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walnoshm.test`

Ported upstream behavior:

- `walnoshm.test` `1.2`: a version-1 VFS without SHM primitives refuses WAL conversion unless the connection is in exclusive locking mode.
- `walnoshm.test` `1.4`: exclusive locking mode allows WAL operation with a heap WAL-index and no xShm primitives.
- `walnoshm.test` `1.7` through `1.10`: a heap WAL-index keeps exclusive locking until rollback-journal mode is restored.
- `walnoshm.test` `2.1.3` through `2.1.5`: copied WAL databases using heap WAL-index state require exclusive locking before they can be read or converted back to rollback mode.
- `walnoshm.test` `2.2.2` through `2.2.6`: failed exclusive conversion while another SHM reader is open reports the lock failure without leaving a pending lock behind.
- `walnoshm.test` `3.1` and `3.2`: setting exclusive mode after WAL open can return to normal, while setting it before WAL open keeps later readers locked out.

Focused coverage:

- Added `SQLiteRealUpstreamPagerWalDynamicPlan::walNoShmExclusiveModeCases()` with 480 generated real-upstream cases over 10 hydrated upstream `walnoshm.test` sections.
- Added `SQLiteRealUpstreamPagerWalNoShmDynamicTest.php` with 482 TestRunner PASS cases and 7212 focused assertions.

Non-overlap:

- This slice is specifically `walnoshm.test` version-1 VFS heap WAL-index behavior.
- It does not repeat accepted `walmode.test`, `walpersist.test`, `walro.test`, `wal2.test`, `wal3.test`, `wal8.test`, `wal9.test`, `walvfs.test`, WAL byte truncation, checkpoint transaction, VFS writer/sync/lock/rollback, rollback-journal commit, super-journal, pager master-journal, or app-WAL clusters.

Dependency closure:

- No new support component is needed. The slice reuses existing generic pager/WAL dynamic plan data structures and the existing TestRunner.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalNoShmDynamicTest.php`
  - `1 test files, 7212 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalLockRaceCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalExclusiveDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalLockCheckpointDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalNoShmDynamicTest.php`
  - `4 test files, 28001 assertions, 0 failures`
- Diagnostic only: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWal*Dynamic*Test.php` reached existing pager/WAL overwrite/restart coverage and then hit PHP's 128M memory limit in `SQLiteRealUpstreamPagerWalOverwriteRestartDynamicTest.php` / `SQLiteWal.php`; this is the known broad-family memory ceiling and not a failure in the new `walnoshm` corpus file.
- Root harness: not run - isolated micro-slice.
