# real-upstream-corpus-vfs-io-dynamic-20260531T013204Z-0

Base accepted HEAD: `472430c1daaad1016852e97d68cabd3ea687d289`.

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/ioerr.test`
- Ported scenario: `ioerr-11`, the UPDATE assertion-fault regression around
  `UPDATE A SET Id = 2, Name = 'Name2' WHERE Id = 1` under injected VFS I/O
  faults.

## Behavior Added

- Added `SQLiteVfsIoDynamicPlan::updateAssertionIoErrorProfile()` to model the
  upstream `ioerr-11` contract:
  - injected read/write/sync/truncate failpoints during UPDATE;
  - statement-journal rollback for write-like failures;
  - preserved b-tree cursor/assertion guard after an I/O fault;
  - final row visibility only after a successful retry;
  - preserved integrity, zero cache refcount, and closed VFS handles.
- Added `SQLiteRealUpstreamCorpusVfsIoerr11UpdateAssertionDynamicTest.php` with
  1,000 dynamic failpoint/operation PASS cases plus source citation and
  malformed-input guards.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerr11UpdateAssertionDynamicTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsIoerr11UpdateAssertionDynamicTest.php`
  - `1 test files, 24008 assertions, 0 failures`
  - 1002 focused PASS lines

## Non-Overlap

This slice owns `ioerr.test ioerr-11` update assertion-fault behavior only. It
does not repeat accepted VFS IO device-matrix behavior, auto-vacuum I/O-error
coverage, pointer-map I/O faults, ioerr2/ioerr3/ioerr4/ioerr5/ioerr6 batches,
sysfault, checksum VFS, appendvfs, WAL VFS, VFS file writer/sync/lock,
rollback-journal commit/apply, pager/WAL dynamic corpus, B-tree, JSON, PRAGMA,
trigger, rowvalue, or SELECT clusters.

## Dependency Closure

No new support component is needed. This reuses the existing lane-local VFS I/O
dynamic corpus planner surface and adds a bounded native PHP profile for the
upstream UPDATE assertion-fault recovery path.

Root harness: not run - isolated micro-slice.
