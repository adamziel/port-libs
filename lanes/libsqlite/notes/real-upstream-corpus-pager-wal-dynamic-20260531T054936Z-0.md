# real-upstream-corpus-pager-wal-dynamic-20260531T054936Z-0

Base accepted HEAD: `db171f640e25dd929585c8e1b7a1c804219fdfee`.

This slice adds a real upstream pager/WAL dynamic corpus file for hydrated
SQLite `pager1.test` sections:

- `pager1-23.5.*`: in-memory databases reject file-backed journal modes and
  accept only `MEMORY`/`OFF`.
- `pager1-23.6.*`: locking-mode changes do not relax in-memory journal-mode
  constraints.
- `pager1-24.1.2` through `pager1-24.1.5`: cache-spill delete/update, commit
  during scan, and recursive SELECT schema-change integrity behavior.

Focused growth: `1003` TestRunner PASS cases and `16014` behavior assertions
in `SQLiteRealUpstreamCorpusPagerWalDynamic20260531T054936ZTest.php`.

Non-overlap: this avoids accepted WAL byte truncation, WAL checkpoint
transactions, rollback-journal apply/commit, VFS sync/file writer/lock, WAL
header validation `031451`, page-size mapping, readonly-SHM, persistent WAL
close, and pager/WAL recovery dynamic batches.

Dependency closure: no new support component is needed. The test reuses
`SQLitePagerWalDynamicPlan` and the hydrated upstream SQLite `pager1.test`
source truth.
