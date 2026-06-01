# real-upstream-corpus-pager-wal-dynamic-20260601T143633Z-0

Status: ready focused PHP behavior growth on base `eef9582145ab6422998777b814b61569f08c5e06`.

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
- Ported `pager1-28.1` through `pager1-28.4`: WAL exclusive-locking write attempts are blocked while another client is open, then succeed after the peer is reopened.
- Ported `pager1-28.5` through `pager1-28.12`: changing `journal_mode=PERSIST` to `DELETE` reports `delete` but defers journal unlink while another handle owns the RESERVED lock.
- Ported `pager1-28.13` through `pager1-28.20`: an open incremental-blob reader keeps the peer commit locked until the reader closes, after which stale journal cleanup completes.

Behavior added:

- Added `SQLiteRealPagerBoundaryPlan::peerLockJournalCleanupRows()` with 1000 deterministic source-neutral dynamic rows across WAL exclusive peer-open, PERSIST-to-DELETE peer-writer, and PERSIST-to-DELETE open-blob-reader cases.
- Added `SQLiteRealUpstreamCorpusPagerWalPeerLockJournalCleanupDynamic20260601T143633ZTest.php` with 1000 dynamic TestRunner cases plus source citation, inventory/non-overlap, and invalid-count guard cases.
- Focused PASS/assertion growth: 1003 TestRunner cases and 34018 assertions from the new corpus file.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteRealPagerBoundaryPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteRealPagerBoundaryPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalPeerLockJournalCleanupDynamic20260601T143633ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalPeerLockJournalCleanupDynamic20260601T143633ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalPeerLockJournalCleanupDynamic20260601T143633ZTest.php`
  - `1 test files, 34018 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicRealPager20260531T020744ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicRealPager20260531T095120ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicRealPager20260531T103759ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalPager1InvalidPageDynamic20260601Test.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalPager1RollbackMaxPageDynamic20260601Test.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalZeroPageSizeJournalDynamic20260601T122933ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalPeerLockJournalCleanupDynamic20260601T143633ZTest.php`
  - `7 test files, 153275 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - `No SQLiteNoWordPressSpecificApiTest.php present`

Expected dashboard movement:

- `phpPass` moves `5938420 -> 5972438` by the measured new-file assertion delta.
- Mapped denominator remains `1589 / 1589`; this is PASS/assertion growth over already mapped real upstream pager inventory.

Non-overlap:

- This slice owns only `pager1.test` `pager1-28.*` peer-lock and deferred journal cleanup behavior.
- It avoids accepted pager1 zero-page-size fallback, invalid-page, rollback max-page, page-size rewrite, journal-mode-only rows, cache-spill scan rows, VFS writer/sync/lock-state, rollback-journal apply/commit, WAL byte truncation, savepoint2 WAL signatures, WAL checkpoint transaction, and master-journal batches.

Dependency closure:

- No new support component is required. The slice reuses source-neutral pager boundary modeling and the hydrated upstream SQLite checkout as source truth.

Next task:

- Continue pager/WAL corpus work only on a non-overlapping upstream section. Good remaining targets are broader pager transaction/fsync application or release/all blocker fixes, not another `pager1-28` variant.
