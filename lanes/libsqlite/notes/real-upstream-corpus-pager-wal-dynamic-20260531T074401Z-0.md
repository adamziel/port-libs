# real-upstream-corpus-pager-wal-dynamic-20260531T074401Z-0

Session: `port-dev-sqlite-yield-dyn-real-pager-20260531T074401Z`

Upstream source:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walrofault.test`
- Related readonly-WAL source checks from `/home/claude/port-libs/.upstream-cache/libsqlite/test/walro.test`

Behavior ported:

- `walrofault.test` faultsim `1` `oom*`: a readonly connection opened with `file:test.db?readonly_shm=1` against a persistent WAL database still reads the committed rowset after OOM retry points.
- The dynamic matrix adds 1,000 distinct readonly-WAL fault/open cases over page sizes, sidecar existence, SHM writability, WAL sizes, checkpoint refreshes, WAL wrap refreshes, and fault phases.
- The focused tests verify stable committed rows, blocked-open cases, write denial behavior, refresh bookkeeping, source/dependency tags, and hydrated upstream provenance.

Focused verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalReadonlyFaultDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalReadonlyFaultDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalReadonlyFaultDynamicTest.php`
  - `1 test files, 19008 assertions, 0 failures`
  - Focused PASS growth: `+1002` TestRunner cases from real upstream readonly WAL fault behavior.
- `git diff --check -- lanes/libsqlite`
  - passed

Non-overlap:

- This covers `walrofault.test` OOM readonly-SHM open/read behavior.
- It does not repeat `walro2` truncate refresh, `walro.test` checkpoint xSync reader coverage, WAL byte truncation, rollback-journal apply/commit, VFS writer/sync/lock, checkpoint transaction helpers, pager master-journal cache fences, or app-WAL apply conflict work.
- Mapped denominator coverage remains complete at `1589 / 1589`; this is countable PHP PASS-line growth over already mapped real upstream WAL inventory.

Dependency closure:

- No new support component is needed. The slice reuses generic readonly WAL/SHM planning and real hydrated `walrofault.test` source evidence.

Next task:

- Continue pager/WAL work only on a distinct default-memory pressure, release/all-runner blocker, or transaction-application behavior gap. Avoid another readonly-SHM wrapper unless it covers a new upstream failure mode.
