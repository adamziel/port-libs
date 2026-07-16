# Real upstream pager/WAL dynamic corpus slice

Session: `port-dev-sqlite-yield-dyn-real-pager-20260530T164703Z`
Micro-slice: `real-upstream-corpus-pager-wal-dynamic-20260530T164703Z-0`
Base accepted HEAD: `77aaee93e1232164eda546b44d6f0e2ddd146261`

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal.test`
  - `wal-1.4..1.5` committed writer rows become visible.
  - `wal-2.1..2.6` reader keeps its old snapshot until commit visibility advances.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/wal2.test`
  - `wal2-2` stale wal-index header drops to the previous snapshot before recovery.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walrestart.test`
  - `1.2..1.5` checkpoint restart observes a new salt and preserves integrity.

## Coverage added

Extended `SQLiteRealUpstreamPagerWalDynamicCorpusTest.php` with 44 additional real upstream behavior cases:

- 24 MVCC/transaction grouping cases over committed WAL frame groups, reader snapshots, page maps, and page-image provenance.
- 20 salt-restart/current-next cases over WAL restart salt changes, stale-tail recovery, current/next reader sources, and checkpointable committed prefixes.

Focused before/after for this file:

- Before: `64` PASS lines, `768` assertions.
- After: `108` PASS lines, `1660` assertions.
- Delta: `+44` PASS lines, `+892` assertions.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicCorpusTest.php`
  - Result: `1 test files, 1660 assertions, 0 failures`.

## Non-overlap

This extends the accepted real pager/WAL dynamic corpus with upstream `wal.test`, `wal2.test`, and `walrestart.test` MVCC/salt-restart behavior. It does not add metadata-only rows, fake `.test` names, domain-specific APIs, dashboard/root files, or duplicate WAL checkpoint/recovery cases already present in the file.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP `SQLiteWal` frame parsing, checksum validation, committed transaction grouping, reader snapshot, page-map, corrupt recovery, and salt-restart current/next behavior.
