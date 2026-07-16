# real-upstream-corpus-pager-wal-dynamic-20260531T064816Z-0

Base accepted HEAD: `598504695c988ec41a0063207004e700089f5af7`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walsetlk_snapshot.test`
- Ported sections: `walsetlk_snapshot.test` 1.0 through 1.5.

Behavior added:

- `SQLiteRealUpstreamPagerWalDynamicCorpusPlan::walSetlkSnapshotBusyRows()` generates 1,000 real upstream-derived pager/WAL rows for snapshot-open during a stalled fullshm checkpoint `xWrite`.
- `SQLiteRealUpstreamCorpusPagerWalSetlkSnapshotDynamicTest.php` asserts `SQLITE_BUSY`, sub-two-second wait behavior, setlk-timeout sleep-callback differences, final committed row visibility, and dependency provenance across passive/full/restart/truncate checkpoint modes.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalSetlkSnapshotDynamicTest.php` passed.
- `php -d memory_limit=1024M tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalSetlkSnapshotDynamicTest.php` passed: `1 test files, 17016 assertions, 0 failures`, `1003` PASS lines.

Status delta:

- Expected selected PASS-line movement: `+1003` if accepted.
- `phpPass`: `2580336 -> 2581339`.
- Mapped coverage remains `1589 / 1589`.

Non-overlap:

- Avoids accepted WAL byte truncation, rollback-journal apply/commit, VFS writer/sync/lock, WAL readonly-SHM refresh, walro cache-spill, wal8/wal9 page-size mapping, and app-WAL slices.

Dependency closure:

- No new support component is needed. The slice reuses the existing pager/WAL dynamic corpus plan and hydrated upstream SQLite test cache.
