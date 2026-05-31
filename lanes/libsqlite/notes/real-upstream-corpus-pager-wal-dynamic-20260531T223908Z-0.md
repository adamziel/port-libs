# real-upstream-corpus-pager-wal-dynamic-20260531T223908Z-0

Status: focused PHP behavior growth for real upstream pager savepoint playback and fault recovery.

Upstream source truth:

- `savepoint4.test`: `savepoint4-1` and `savepoint4-2` crash-recovery loops around nested `SAVEPOINT`, `ROLLBACK TO`, `RELEASE`, and indexed page playback.
- `savepoint5.test`: empty database rollback to `sp1`, schema reset, and recreate behavior.
- `savepoint6.test`: randomized savepoint/release/rollback operations with incremental vacuum and WAL journal-mode checks.
- `savepoint7.test`: RELEASE preserving pending queries, ROLLBACK aborting pending queries, and memory-journal large rollback cases.
- `savepointfault.test`: malloc/IO fault savepoint rollback and incremental-vacuum rollback cleanup.

Changed behavior:

- Added `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::savepointFaultRecoveryRows()` with 1200 source-neutral dynamic rows over the five upstream files.
- Added `SQLiteRealUpstreamPagerWalSavepointFaultDynamicTest.php`, which verifies those rows through `SQLiteSavepointStack` rollback/release/WAL-frame planning.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalSavepointFaultDynamicTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalSavepointFaultDynamicTest.php` passed: `1 test files, 28586 assertions, 0 failures`.
- `php -d memory_limit=2048M tools/run-tests.php` over all `SQLiteRealUpstreamPagerWalDynamicCorpusPlan` test users plus `SQLiteNoDomainSpecificApiTest.php` passed: `15 test files, 545427 assertions, 0 failures`.

Dashboard delta:

- Expected focused PASS-line growth: `+1203` from the new test file.
- Expected focused assertion growth: `+28586`.
- Mapped denominator coverage remains `1589 / 1589`; this is behavior coverage over already mapped upstream corpus files.

Non-overlap:

- This does not repeat accepted WAL checkpoint byte materialization, WAL byte truncation, rollback-journal apply/commit, VFS writer/sync/lock/process-lock, WAL2 fullfsync, WAL3 readmark, WAL5 blocking checkpoint, WAL8 empty-open, readonly-SHM, JSON, B-tree, SELECT, or Unicode batches.
- The new surface is savepoint4/5/6/7 and savepointfault pager playback and fault cleanup behavior.

Dependency closure:

- No new support component is needed. The slice reuses `SQLiteSavepointStack` and existing pager/WAL dynamic corpus modeling.

Next task:

- Continue with a broader tracked libsqlite lane/release parity gate or another non-overlapping real upstream pager/WAL failure if the full runner exposes one.
