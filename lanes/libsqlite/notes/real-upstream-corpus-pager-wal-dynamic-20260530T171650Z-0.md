# real-upstream-corpus-pager-wal-dynamic-20260530T171650Z-0

Base accepted HEAD: `7ae2bafb13ace2a8edf7ffe53e4f4d55f2e4902f`

## Scope

Added a focused real-upstream pager/WAL dynamic corpus batch using hydrated SQLite upstream source files:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
  - `wal2-1.2` through `wal2-1.12`: WAL-index header corruption recovery and lock sequence expectations.
  - `wal2-2.2` through `wal2-2.9`: stale-but-valid WAL-index header snapshots followed by recovery to the fresh snapshot.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pager1.test`
  - multiclient `pager1-*` lock transitions covering RESERVED, SHARED, PENDING, blocked writer, blocked observer, and final commit visibility.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walrestart.test`
  - `walrestart-1.0` through `walrestart-1.5`: restart checkpoint race where a writer changes WAL state between `mxFrame` and `nBackfill` reads, followed by integrity preservation.

## Delta

- New focused PHP test file: `lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalLockRaceCorpusTest.php`
- New source helper: `lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicPlan.php`
- Focused result: `34` selected PASS lines, `509` assertions, `0` failures.
- `lane-status.json` `phpPass`: `207759 -> 207793`.
- Mapped coverage remains `958 / 1589`; this handoff does not claim new denominator rows.

## Non-Overlap

This batch does not repeat accepted pager/VFS helpers for rollback-journal apply, WAL byte truncation, checkpoint transactions, VFS sync/file writer/lock state, or the prior generic wal2 summary coverage. The new rows are dynamic upstream scenario rows from `wal2.test`, `pager1.test`, and `walrestart.test` with distinct lock, snapshot, and checkpoint-race assertions.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalLockRaceCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalLockRaceCorpusTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `rg -n "domain|domain" lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicPlan.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalLockRaceCorpusTest.php`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The batch reuses the existing PHP TestRunner and lane-local plan/test pattern for real upstream corpus admission.
