# real-upstream-corpus-pager-wal-dynamic-20260531T101742Z-0

Base accepted HEAD: `334e4120b9e72c6876e51705851ef70fc2462655`

Micro-slice: `real-upstream-corpus-pager-wal-dynamic-20260531T101742Z-0`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_walckpt.test`
- Ported scenarios: `e_walckpt-1.*`, `e_walckpt-2.*`, `e_walckpt-4.*`, and `e_walckpt-5.*`
- Evidence IDs covered: `R-00653-06026`, `R-38207-48996`, `R-14303-42483`, `R-03996-12088`, `R-41299-52117`, `R-38578-34175`, `R-38049-07913`, and `R-37257-17813`

Implementation:

- Added `SQLiteWalCheckpointInterfacePlan` as a generic lane-local model of `sqlite3_wal_checkpoint_v2()` target selection, mode validation, and attached-database continuation/abort behavior.
- Added `SQLiteRealUpstreamCorpusPagerWalCheckpointInterfaceDynamic20260531T101742ZTest` with 1,000 generated upstream-backed TestRunner cases plus source, non-overlap, and invalid-state checks.
- The batch models NULL/empty/all-database checkpoint target selection, `main`/attached/temp/non-WAL handling, unknown-database errors, accepted and misuse checkpoint modes, BUSY continuation to later attached WAL databases, and IOERR abort ordering.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalCheckpointInterfacePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalCheckpointInterfaceDynamic20260531T101742ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalCheckpointInterfaceDynamic20260531T101742ZTest.php`
- Focused result: `1 test files, 17617 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- Guard result: `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
- Diff check result: no output / clean

Expected dashboard movement:

- `phpPass`: `2864736 -> 2865739` (`+1003` distinct focused TestRunner PASS cases)
- Mapped coverage remains `1589 / 1589`; this is real PASS-line corpus growth over an already mapped upstream file, not a new denominator row.

Non-overlap:

- Avoids the accepted pager WAL boundary batch and the existing focused checkpoint protocol/autocheckpoint batches that cover `e_walckpt` busy/pnLog style outputs and `e_walauto`.
- Avoids accepted VFS writer/sync/lock, rollback-journal, super-journal, WAL byte-truncation, WAL checkpoint transaction, and pager WAL boundary clusters.

Dependency closure:

- No new external support component is needed. The slice reuses the hydrated upstream SQLite test checkout only as source truth and adds a generic native PHP planning model plus focused TestRunner coverage under `lanes/libsqlite`.
