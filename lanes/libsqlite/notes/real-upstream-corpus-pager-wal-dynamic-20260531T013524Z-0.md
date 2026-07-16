# real-upstream-corpus-pager-wal-dynamic-20260531T013524Z-0

Base accepted HEAD: `472430c1daaad1016852e97d68cabd3ea687d289`.

Added a focused real-upstream pager/WAL dynamic batch backed by hydrated SQLite upstream files:

- `pager1.test` `pager1-23.5.*`: in-memory databases accept only `OFF` and `MEMORY` journal modes and reject file-backed modes including `PERSIST`, `DELETE`, `TRUNCATE`, and `WAL`.
- `pager1.test` `pager1-24.1.2` through `pager1-24.1.5`: recursive SELECTs over another table remain valid while dirty cache pages spill during DELETE/UPDATE, COMMIT, and schema-change attempts.
- `walpersist.test` `walpersist-2.2` and `walckptnoop.test` `1.10` are cited as adjacent WAL persistence/checkpoint state source sections, but the new behavior assertions avoid accepted WAL byte-truncation, checkpoint transaction, rollback-journal apply/commit, VFS sync/file-writer, journal-transition, multiclient-lock, and app-WAL slices.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePagerWalDynamicPlan.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicRealPager20260531T013524ZTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicRealPager20260531T013524ZTest.php`: 1 test file, 11514 assertions, 0 failures, 1004 focused PASS lines.

Dependency closure: no new support component needed. The batch reuses `SQLitePagerWalDynamicPlan` plus hydrated upstream SQLite `.test` files as source truth.

Expected movement: PASS-line growth only if accepted. Mapped denominator remains complete at `1589 / 1589`.
