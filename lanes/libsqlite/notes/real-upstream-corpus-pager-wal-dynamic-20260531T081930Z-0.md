# real-upstream-corpus-pager-wal-dynamic-20260531T081930Z-0

Status: focused real-upstream pager/WAL corpus growth on accepted base
`b9873c852a7f5b8dd171221d5d3abd96ee2031c8`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/walfault.test`
  sections `walfault-3` through `walfault-15`.

Implemented test movement:

- Added `SQLiteRealUpstreamCorpusPagerWalFaultDynamic20260531T081930ZTest.php`.
- The file contributes 1,000 distinct generated TestRunner behavior cases plus
  source-citation and handoff/non-overlap cases.
- Focused assertions: 59,088.
- Focused PASS lines: 1,002.

Behavior covered:

- Small WAL write/checkpoint fault recovery after delete.
- PSOW WAL create/checkpoint/select result coverage for `{wal 0 5 5 a b}`.
- `xShmMap` fault boundaries during large WAL build, recovery, and checkpoint.
- Transaction rollback, savepoint rollback-to, open-cursor insert fault, and
  zeroed-SHM-header checkpoint recovery paths.
- Heap-memory WAL-index exclusive/no-SHM switching and full-checkpoint WAL
  wraparound behavior.
- Valid, checksum-corrupt, salt-corrupt, and truncated WAL tail recovery using
  the existing WAL parser, committed-prefix recovery, checkpoint, reader
  visibility, persistent-WAL close, pager transition, and WAL VFS dynamic
  helpers.

Non-overlap:

- This slice covers later `walfault.test` sections 3 through 15 only.
- It avoids existing coverage for `walfault-1/2`, `walfault2.test`, WAL
  readonly restart, WAL byte truncation, rollback-journal apply/commit,
  VFS writer/sync/lock helpers, WAL checkpoint transaction helpers, JSON table
  cursor/source behavior, B-tree page relocation/root-collapse/freeblock
  clusters, and SQL text executor/grouping/order/subquery clusters.
- It adds no production APIs, generated fake upstream script IDs, or
  metadata-only admission rows.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalFaultDynamic20260531T081930ZTest.php`
  passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalFaultDynamic20260531T081930ZTest.php`
  passed: `1 test files, 59088 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses generic lane-local WAL
  parser/recovery/checkpoint, pager WAL transition, and WAL VFS dynamic
  helpers against hydrated upstream `walfault.test` source evidence.
