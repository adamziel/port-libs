# Real Upstream Pager/WAL Dynamic Extended Corpus

Micro-slice: `real-upstream-corpus-pager-wal-dynamic-20260530T201208Z-0`

Base accepted HEAD: `c1a0d2c80ea721e0595b20a5cbe43c5043856066`

## Scope

Added `SQLiteRealUpstreamPagerWalDynamicExtendedCorpusTest.php` with 1,000 distinct focused TestRunner cases and 11,100 assertions.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walrestart.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walbak.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walmode.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager2.test`

Ported behavior clusters:

- `walrestart.test`: restart checkpoint reader pins preserve WAL tails and report reader-blocked reset decisions.
- `walbak.test`: backup/checkpoint views see committed WAL page images after passive checkpoint materialization.
- `walmode.test`: noop/passive/restart/truncate checkpoint modes preserve WAL bytes when an uncommitted tail follows the last commit.
- `pager2.test`: rollback journal recovery restores saved pages and trims expanded database images back to the initial page count.

This is non-overlapping with the existing `SQLiteRealUpstreamPagerWalDynamicFollowupCorpusTest.php` batch, which covered `wal.test`, `walcksum.test`, `wal2.test`, and `pager1.test`.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicExtendedCorpusTest.php`
  - `1 test files, 11100 assertions, 0 failures`
  - 1,000 selected PASS lines.

## Dependency Closure

No new support component is needed. The batch reuses existing native PHP WAL and rollback-journal primitives: `SQLiteWal`, `SQLiteWalHeader`, `SQLiteRollbackJournal`, and `SQLiteRollbackJournalHeader`.
