# real-upstream-corpus-pager-wal-dynamic-20260531T011718Z-0

Base accepted HEAD: `2541019b82319811accbb79790d214be59d31028`.

Added `SQLitePagerWalDynamicRealCorpusExpandedTest.php`, a focused real-upstream pager/WAL corpus expansion built from the hydrated SQLite upstream checkout in `/home/claude/port-libs/.upstream-cache/libsqlite/test`.

Upstream source sections cited:

- `wal2.test`: `wal2-6.4.1`, `wal2-10.2`, `wal2-12.2`, `wal2-13`, `wal2-14`
- `walbig.test`: `walbig-1.1`
- `walbak.test`: `walbak-3.1`, `walbak-4`
- `walckptnoop.test`: `walckptnoop-1`
- `walrestart.test`: restart reader-prefix behavior
- `waloverwrite.test`: overwrite after restart behavior
- `walpersist.test`: persistent WAL sidecar behavior
- `walnoshm.test`: heap WAL-index reader behavior
- `walro.test`: read-only snapshot-prefix behavior
- `pageropt.test`: `pageropt-1` through `pageropt-4`

Focused behavior:

- WAL checksum-validated parsing across 18 scenario templates and 512/1024/2048/4096-byte pages.
- Committed transaction boundary versus rollback draft-tail recovery.
- Durable checkpoint database page images for every page in each scenario.
- Reader snapshot visibility for latest and midpoint reader frame bounds.
- Restart checkpoint decision behavior for complete and uncommitted-tail WALs.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerWalDynamicRealCorpusExpandedTest.php`
  - `1 test files, 4571 assertions, 0 failures`
  - `2669` focused PASS lines

Status movement:

- `lanes/libsqlite/lane-status.json` `phpPass`: `1459822 -> 1462491` (`+2669`)
- Mapped denominator remains `1589 / 1589`; this is PASS-line growth only.

Non-overlap:

- This does not repeat the accepted pager2 savepoint, journal2 safe-delete, rollback-journal commit/apply, VFS writer/sync/lock, WAL byte-truncation, WAL checkpoint-transaction, or existing `SQLitePagerWalDynamicRealCorpusTest.php` narrow wal/wal2/walcksum/pager1 matrix. It adds a separate broader WAL/pager optimization/read-mode/restart/backup/no-SHM/read-only corpus file.

Dependency closure:

- No new support component is needed. The batch reuses existing `SQLiteWal`, `SQLiteWalHeader`, checkpoint, recovery-boundary, and reader snapshot helpers.

Root harness:

- Not run; isolated micro-slice.
