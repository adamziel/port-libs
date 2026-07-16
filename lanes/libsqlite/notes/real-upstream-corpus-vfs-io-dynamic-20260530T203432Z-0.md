# real-upstream-corpus-vfs-io-dynamic-20260530T203432Z-0

Added `SQLiteVfsChecksumReservePlan` and `SQLiteRealUpstreamCorpusVfsIoDynamicCksumWalTest.php` as an additive real upstream VFS I/O corpus slice.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/cksumvfs.test`
  - `cksumvfs-1.0`
  - `cksumvfs-1.3` through `cksumvfs-1.9`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walvfs.test`
  - `walvfs-1.1` and `walvfs-1.3`
  - `walvfs-2.0` through `walvfs-2.3`

Behavior ported:

- VFS reserve bytes reduce usable page payload while preserving checksum trailer bytes across WAL checkpoint, close/restore/reopen, and direct reopen count checks.
- WAL VFS sequential device behavior suppresses immediate WAL-header syncs under `synchronous=normal`.
- WAL journal-size-limit behavior truncates oversized WAL sidecars after checkpoint and next insert.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoDynamicCksumWalTest.php`
- Result: `1 test files, 15713 assertions, 0 failures`
- Focused PASS cases: `1661`

Non-overlap:

- This avoids accepted `ioerr`, `ioerr2`, `ioerr4`, `ioerr5`, VFS rollback-journal apply, VFS file-writer apply, VFS lock-state/process-lock/locked-writer, WAL byte truncation, WAL checkpoint transaction, and existing `io.test` atomic/default-page-size transaction sequence clusters.
- It targets checksum-reserve/WAL-VFS sync and journal-size-limit sections not represented by the accepted status text.

Dependency closure:

- No new support component is needed. The slice reuses existing VFS file-control reserve-byte semantics and WAL checkpoint/journal-size-limit modeling in a new bounded generic plan.
