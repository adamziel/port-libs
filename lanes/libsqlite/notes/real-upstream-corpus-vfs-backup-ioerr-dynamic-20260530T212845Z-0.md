# real-upstream-corpus-vfs-backup-ioerr-dynamic-20260530T212845Z-0

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T212845Z-0`

Implemented an additive real upstream VFS I/O corpus batch from:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/backup_ioerr.test`
- Scenarios: `backup_ioerr-2.*` through `backup_ioerr-13.*`, covering transient and persistent I/O errors across destination page sizes 512/1024/4096, empty and pre-populated destination database images, partial backup-step failures, source-write failures that allow backup continuation, deferred backup-update failures, final backup-step failures, successful completion, finish-time error publication, integrity checks, and restored destination image behavior.

Changed files:

- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsBackupIoerrDynamicTest.php`
- `lanes/libsqlite/notes/real-upstream-corpus-vfs-backup-ioerr-dynamic-20260530T212845Z-0.md`

Focused assertion/pass growth:

- Adds 1,502 focused TestRunner PASS cases in one new file.
- Adds 36,016 behavior assertions from real upstream backup I/O error state-machine cases.
- Does not claim new mapped denominator rows; mapped coverage is already complete on the current accepted dashboard.

Non-overlap:

- This does not repeat accepted `io.test`, `ioerr2.test`, `ioerr4.test`, `ioerr5.test`, `ioerr6.test`, `pagerfault.test`, append VFS, mmap, lock-byte, VFS file writer/sync/lock, rollback-journal apply/commit, WAL checkpoint/savepoint, or late pointer-map fault clusters.
- The owned upstream surface is `backup_ioerr.test` backup-step/finish error propagation and destination-image preservation under VFS I/O fault injection.

Dependency closure:

- No new support component is needed. The batch reuses the existing lane-local `SQLiteVfsIoTrafficPlan::backupIoErrorStateMachine()` native PHP behavior surface and adds real upstream corpus assertions over it.
