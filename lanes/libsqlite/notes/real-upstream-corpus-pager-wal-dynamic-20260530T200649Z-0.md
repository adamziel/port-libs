# Real upstream corpus pager/WAL dynamic 20260530T200649Z-0

Status: ready focused PHP corpus growth for `real-upstream-corpus-pager-wal-dynamic-20260530T200649Z-0`.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
  - `wal2-1.*`: corrupted WAL-index header recovery lock/read matrix.
  - `wal2-2.*`: valid but stale WAL-index header fallback followed by recovery.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walcksum.test`
  - checksum, salt, header, and truncated-tail recovery boundaries.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
  - hot rollback-journal candidate checks and page recovery application.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test`
  - passive/full/restart/truncate/noop checkpoint reader-boundary invariants.

Implementation:

- Added `SQLiteRealUpstreamPagerWalDynamicExpansionTest.php`.
- No production source changes. The test expands the already accepted WAL and rollback-journal primitives over a larger real upstream matrix:
  - 240 `wal2-1` checkpoint/read visibility cases.
  - 120 `wal2-2` stale-header/read visibility cases.
  - 96 `walcksum` corrupt recovery cases.
  - 28 `pager1` hot-journal candidate/recovery cases.
  - 24 no-commit WAL draft-boundary cases.
  - 1 upstream source-record case.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicExpansionTest.php`
  - `1 test files, 7593 assertions, 0 failures`

Expected dashboard movement:

- Countable as focused PASS-line growth: 512 distinct TestRunner PASS cases.
- Countable as behavior assertion growth: 7,593 assertions from real upstream pager/WAL cases.
- Mapped denominator unchanged; this is real upstream behavior expansion over existing pager/WAL mapped surfaces, not a new runner-map row.

Non-overlap:

- Does not repeat metadata-only suite rows, generated fake test names, source-neutral cleanup, domain-shaped API surfaces, WAL savepoint byte truncation, VFS writer/sync/lock wrappers, rollback-journal commit/super-journal application, WAL mode/persist dynamic coverage, or the existing `SQLitePagerWalDynamicRealCorpusTest.php` exact assertions.

Dependency closure:

- No new support component is needed. This reuses lane-local `SQLiteWal`, `SQLiteWalHeader`, `SQLiteRollbackJournal`, and `SQLiteRollbackJournalHeader` primitives.
