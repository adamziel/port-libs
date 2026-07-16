# real-upstream-corpus-pager-wal-dynamic-20260531T103759Z-0

Base accepted HEAD: `f9d9e6312c63dfc0751eedbcf238e9e6c2d6e7da`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager4.test`
- Upstream scenarios: `pager4-1.2` through `pager4-1.11`

Behavior ported:

- `pager4-1.2`: a database whose pathname has moved stays readable.
- `pager4-1.3`: writes after the pathname move fail with `attempt to write a readonly database`.
- `pager4-1.4`: creating a replacement file at the original pathname does not clear the readonly state.
- `pager4-1.5` and `pager4-1.6`: restoring the database name restores read/write behavior.
- `pager4-1.7` and `pager4-1.8`: `journal_mode=OFF` and `journal_mode=MEMORY` writes remain allowed after the pathname move.
- `pager4-1.9` through `pager4-1.11`: `DELETE`, `TRUNCATE`, and `PERSIST` rollback-journal modes keep moved-database writes readonly.

Patch summary:

- Added `SQLiteRealPagerBoundaryPlan::databaseMovedWriteBoundary()` with source-neutral pager state for moved-database read/write and journal-mode boundaries.
- Added `SQLiteRealUpstreamCorpusPagerWalDynamicRealPager20260531T103759ZTest` with 1003 focused TestRunner PASS cases and 21820 assertions.
- Updated `lane-status.json` from `2878175` to `2879178` selected PASS lines. Mapped coverage remains `1589 / 1589`.

Non-overlap:

- This does not repeat accepted pager4-1.1 temp pager visibility, pager4-2.2 cache reload, pager1 real-boundary, pager2 savepoint, WAL byte truncation, checkpoint transactions, VFS writer/sync/lock, rollback-journal apply/commit, or wal2/walfault coverage.

Dependency closure:

- No new support component is required. The slice reuses existing lane-local real-pager boundary plan infrastructure and the hydrated upstream checkout as source truth.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRealPagerBoundaryPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicRealPager20260531T103759ZTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicRealPager20260531T103759ZTest.php` -> `1 test files, 21820 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicRealPager20260531T020744ZTest.php` -> `1 test files, 9013 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicRealPager20260531T095120ZTest.php` -> `1 test files, 9864 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`.
- `jq . lanes/libsqlite/lane-status.json >/dev/null` -> valid JSON.
- `git diff --check -- lanes/libsqlite` -> clean.

Root harness:

- Not run - isolated micro-slice.
