# Real Upstream Corpus VFS I/O Atomic Edges

Slice: `real-upstream-corpus-vfs-io-dynamic-20260530T214926Z-0`
Base accepted HEAD: `551608c47b9b5c9b4c74afdd6349b99f03720fcd`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/io.test`
- Focused upstream scenarios:
  - `io.test io-2.7.1-2.7.6`: multi-file commit forces rollback journal handling despite atomic capability and rolls back both files when the commit-time journal open fails.
  - `io.test io-2.8.1-2.8.3`: explicit rollback before deferred journal materialization restores the previous committed rows.
  - `io.test io-2.11.1-2.11.2`: exclusive locking keeps the atomic path journal-unlinked across inserts.
  - `io.test io-6.1` and `io.test io-6.2.1.1-6.2.2.3`: atomic-write commits must not flush the warmed pager cache, so corruption written under cached pages remains hidden from `integrity_check`.

## Local Coverage Added

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoAtomicEdgesDynamicTest.php`.
- The test generates distinct page-size, sector-size, changed-page, append-page, multi-file, explicit rollback, exclusive-locking, and pager-cache retention cases against the existing native PHP VFS I/O planner.
- Focused result: `838` PASS lines and `7,830` assertions.
- Expected dashboard movement if accepted: `phpPass` `782971 -> 783809`; mapped coverage remains `1589 / 1589` because this is behavior growth over already mapped upstream inventory.

## Non-Overlap

This slice does not repeat accepted `avfs.test`, `io.test` io-1 through io-5 traffic/default-page-size matrices, `walvfs.test`, VFS lock state, VFS file writer, rollback-journal commit/apply, WAL checkpoint transaction, or prior VFS I/O error dynamic batches. It extends the real upstream VFS I/O corpus into atomic multi-file rollback, explicit rollback before deferred journal creation, exclusive atomic journal unlinking, and pager-cache retention after atomic commits.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `SQLiteVfsIoDynamicPlan` planner and the existing focused PHP test harness.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoAtomicEdgesDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoAtomicEdgesDynamicTest.php`
- Pending final gate in worker: `git diff --check -- lanes/libsqlite` and API guard.
