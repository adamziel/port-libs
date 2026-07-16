# real-upstream-corpus-pager-wal-dynamic-20260601T205451Z-0

## Source truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
- Scenarios: `pager1.4.5.3`, `pager1.4.5.4`, `pager1.4.5.5`, `pager1.4.5.6`
- Behavior: hot rollback journals use per-page checksums; recovery stops at the first bad checksum, keeps already-restored prefix pages, and read-only connections fail instead of rolling back a hot journal.

## Patch summary

- Added `SQLiteRollbackJournal::hotJournalChecksumRecoveryResult()` for hot-journal recovery decisions that validate record checksums and stop at the first mismatch.
- Added `SQLiteRealPagerBoundaryPlan::hotJournalChecksumStopRows()` with 1000 dynamic source-backed rows across page sizes, sector sizes, checksum nonces, valid/corrupt records, and read-only handling.
- Added focused TestRunner coverage in `SQLiteRealUpstreamCorpusPagerWalHotJournalChecksumDynamic20260601T205451ZTest.php`.

## Focused evidence

- Pre-fix probe: `php -r 'require "lanes/libsqlite/src/SQLiteRollbackJournal.php"; echo method_exists("PortLibs\\\\LibSqlite\\\\SQLiteRollbackJournal", "hotJournalChecksumRecoveryResult") ? "present\n" : "missing\n";'` => `missing`
- `php -l lanes/libsqlite/src/SQLiteRollbackJournal.php` => no syntax errors
- `php -l lanes/libsqlite/src/SQLiteRealPagerBoundaryPlan.php` => no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalHotJournalChecksumDynamic20260601T205451ZTest.php` => no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalHotJournalChecksumDynamic20260601T205451ZTest.php` => `1 test files, 25015 assertions, 0 failures`
- Related pager/WAL family gate: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerRollbackJournalCurrentTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalZeroPageSizeJournalDynamic20260601T122933ZTest.php lanes/libsqlite/tests/SQLitePagerSavepointRollbackJournalLifecycleTest.php lanes/libsqlite/tests/SQLitePagerWalDynamicRealCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalRollbackSavepointDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicRecoveryCorpusTest.php` => `6 test files, 51905 assertions, 0 failures`

## Countability

- New focused TestRunner PASS cases: 1003
- New focused assertions: 25015
- `phpPass` expected movement: `6256079 -> 6257082`
- Mapped coverage remains `1589 / 1589`.

## Non-overlap

This targets only upstream `pager1.test` hot-journal checksum-stop behavior for `pager1.4.5.3` through `pager1.4.5.6`. It avoids accepted pager1 invalid-page, peer-lock cleanup, zero-page-size journal fallback, missing DELETE-journal unlink failure, empty-database stale-journal cleanup, `savepoint2.test` WAL signatures, WAL checkpoint transactions, VFS writer/sync/lock, rollback-journal apply/commit, and broad generic pager1-4 coverage.

## Dependency closure

No new support component is needed. The slice reuses existing rollback-journal parsing, page checksum, hot-journal candidate, and pager boundary plan helpers.
