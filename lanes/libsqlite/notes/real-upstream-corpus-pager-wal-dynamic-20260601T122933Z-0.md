# Real Upstream Pager/WAL Dynamic: Zero Page-Size Journal Header

Slice: `real-upstream-corpus-pager-wal-dynamic-20260601T122933Z-0`
Base accepted HEAD: `bc90f87db2ed4ad7ae3d007cb6eabda51a9348d1`

## Source Truth

- Hydrated upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
- Upstream scenario: `pager1-31.1`
- Behavior: the test zeroes the rollback-journal header page-size field with `hexio_write test.db2-journal 24 00000000`, then reopens the database and still expects `PRAGMA integrity_check` to return `ok`.

## Implementation

- `SQLiteRollbackJournalHeader::parseWithDatabasePageSize()` accepts a zero page-size header field only when the caller supplies the database page size.
- `SQLiteRollbackJournal::parseWithDatabasePageSize()` threads that fallback through rollback-journal page parsing and optional checksum validation.
- `SQLiteRollbackJournal::hotJournalRecoveryResult()` now treats a zero page-size header as hot only if the fallback-parsed journal exactly matches the current parsed journal. The strict `parse()` and `hotJournalCandidate()` paths still reject the same bytes without a fallback.
- `SQLiteRealPagerBoundaryPlan::zeroPageSizeJournalHeaderRows()` produces 1000 real upstream-backed pager1 dynamic rows across page sizes, sector sizes, page counts, and journal record counts.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteRollbackJournalHeader.php`
- `php -l lanes/libsqlite/src/SQLiteRollbackJournal.php`
- `php -l lanes/libsqlite/src/SQLiteRealPagerBoundaryPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalZeroPageSizeJournalDynamic20260601T122933ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalZeroPageSizeJournalDynamic20260601T122933ZTest.php`
  - Result: `1 test files, 33019 assertions, 0 failures`
  - PASS cases: 1003
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerRollbackJournalCurrentTest.php lanes/libsqlite/tests/SQLiteRollbackJournalCurrentNext32Test.php lanes/libsqlite/tests/SQLitePagerSavepointRollbackJournalLifecycleTest.php lanes/libsqlite/tests/SQLitePagerWalDynamicRealCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalRollbackSavepointDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicRecoveryCorpusTest.php`
  - Result: `6 test files, 18944 assertions, 0 failures`

Expected focused PASS-case growth: `+1003`. `lane-status.json` `phpPass` moved from `5889968` to `5890971`.

## Non-Overlap

This slice targets pager1.test `pager1-31.1` legacy rollback-journal page-size fallback. It does not repeat accepted pager1 invalid-page, rollback max-page, DBMOVED, page-size rewrite, WAL byte truncation, VFS writer/sync/lock, rollback-journal apply/commit, savepoint2 WAL signature, or pager4 coverage.

## Dependency Closure

No new support component is needed. The slice reuses the source-neutral rollback-journal parser, hot-journal recovery planner, and pager boundary modeling against the hydrated upstream source.
