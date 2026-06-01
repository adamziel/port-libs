# real-upstream-corpus-vfs-io-dynamic-20260601T210834Z-0

Added an additive real upstream VFS I/O dynamic corpus slice for SQLite
`pendingrace.test`.

## Upstream Source

- Hydrated source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pendingrace.test`
- Scenario range: `pendingrace.test` `1.0` through `1.3`
- Behavior: a peer connection attempts hot-journal rollback during the primary
  connection's `xAccess` probe. The peer's injected `xUnlock` failure leaves a
  `PENDING` lock, so the primary `PRAGMA integrity_check` returns
  `database is locked` instead of reading the corrupt pre-rollback image.

## Changed Lane Files

- `lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsPendingRaceDynamicTest.php`
- `lanes/libsqlite/examples/application-vfs-pendingrace-hot-journal.php`
- `lanes/libsqlite/lane-status.json`
- `lanes/libsqlite/notes/real-upstream-corpus-vfs-io-dynamic-20260601T210834Z-0.md`

## Focused Evidence

- `php -l lanes/libsqlite/src/SQLiteVfsIoDynamicPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsPendingRaceDynamicTest.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/examples/application-vfs-pendingrace-hot-journal.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusVfsPendingRaceDynamicTest.php`
  - `1 test files, 39016 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 8 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-vfs-pendingrace-hot-journal.php --self-test`
  - `application-vfs-pendingrace-hot-journal self-test passed`

## Countability

- Adds `1003` focused TestRunner PASS cases:
  - `1000` dynamic pending-lock hot-journal race variants.
  - `1` hydrated upstream source citation test.
  - `1` malformed-input guard test.
  - `1` focused pass-count ownership test.
- `phpPass` moves `6270884 -> 6271887`.
- Mapped denominator remains complete at `1589 / 1589`; this is PASS-line
  growth over already mapped real upstream VFS inventory.

## Non-Overlap

This slice owns only upstream `pendingrace.test` hot-journal pending-lock race
behavior. It does not repeat accepted `io.test` traffic/default-page-size/cache
retention, `ioerr*`, `mmap*`, `syscall`, `sysfault`, `walvfs`, append-vfs,
quota, checksum-reserve, temp lifecycle, shared-cache protocol, win32 lock,
VFS file-writer/sync/lock-state/process-lock, rollback-journal apply/commit,
super-journal, pager1 checksum/read-only hot-journal recovery, WAL checkpoint,
or savepoint byte-truncation clusters.

## Dependency Closure

No new support component is required. The slice reuses the lane-local
`SQLiteVfsIoDynamicPlan` surface and the hydrated upstream SQLite test cache.
No live service, external credential, or broad upstream runner was used.
