# real-upstream-corpus-pager-wal-dynamic-20260531T015809Z-0

Implemented a bounded generic readonly WAL SHM behavior slice from hydrated upstream SQLite:

- `walro.test` sections `1.1.*`, `1.2.*`, `1.3.*`, and `1.4.*`
- `walro2.test` page-size matrix, zero-length WAL/SHM, truncate-checkpoint cache flush, and WAL-wrap recovery sections

The slice adds `SQLiteWalReadonlyShmPlan` and `SQLiteRealUpstreamPagerWalReadonlyShmDynamicTest.php`.
It covers existing, missing, read-only, and zero-length SHM sidecars; read-only write and checkpoint denial; writer append visibility; checkpoint/cache refresh; WAL wrap recovery; and page sizes from 512 through 65536 bytes.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteWalReadonlyShmPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteWalReadonlyShmPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalReadonlyShmDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalReadonlyShmDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalReadonlyShmDynamicTest.php`
  - `1 test files, 12013 assertions, 0 failures`
  - `1003` focused PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Expected movement if accepted: selected libsqlite PASS lines `1566206 -> 1567209` (`+1003`), mapped coverage unchanged at `1589 / 1589`.

Non-overlap: avoids accepted WAL byte truncation, checkpoint transactions, rollback commit/apply, VFS writer/sync/lock state, pager real-pager sweeps, `walpersist`, `walrestart`, `walckptnoop`, and app-WAL slices.

Dependency closure: no new support component needed; this reuses generic bounded WAL/SHM planning and the hydrated upstream SQLite test files as source truth.
