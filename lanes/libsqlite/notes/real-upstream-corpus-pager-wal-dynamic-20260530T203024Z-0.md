# Real Upstream Pager/WAL Dynamic Matrix

Slice: `real-upstream-corpus-pager-wal-dynamic-20260530T203024Z-0`
Base accepted HEAD: `d5feb4b8c9f51e52c1a4ee4e369261ca23aa819e`

Added `SQLiteRealUpstreamPagerWalDynamicMatrixTest.php`, a focused TestRunner
matrix over existing native WAL, rollback-journal, and savepoint primitives.
The test cites and ports behavior from the hydrated upstream SQLite checkout:

- `wal.test`: `wal-1.*` through `wal-4.*` committed-frame visibility,
  checkpoint reader blocking, restart, and truncate behavior.
- `wal2.test`: drained checkpoint and new-reader database visibility behavior.
- `walckptnoop.test`: `1.1` through `1.10` no-op checkpoint behavior.
- `walcksum.test`: `walcksum-1.big.*` and `walcksum-1.little.*` checksum
  byte-order, corrupt-tail, and committed-prefix recovery behavior.
- `savepoint.test`: `savepoint-1.*` through `5.*`, `10.*`, and `14.*` through
  `16.*` rollback-to WAL-prefix retention.
- `pager1.test`: hot rollback-journal page restore and non-hot journal
  preservation.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalDynamicMatrixTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 55241 assertions, 0 failures
```

Countability:

- Adds 1,641 distinct TestRunner PASS cases from real upstream pager/WAL
  behavior.
- Adds 55,241 focused behavior assertions.
- No mapped-denominator or `lane-status.json` counters were changed in this
  isolated worker; the integrator can count this as PASS-line growth only.

Dependency closure: no new support component is needed. The batch reuses
existing native PHP libsqlite primitives: `SQLiteWal`, `SQLiteRollbackJournal`,
`SQLiteSavepointStack`, and their header/frame helpers.

Non-overlap: this does not add metadata-only rows, fake upstream script ids,
domain-shaped APIs, or a duplicate runner/status patch. It extends the accepted
pager/WAL corpus with a disjoint high-volume matrix over page sizes, reader
pins, checkpoint modes, checksum byte order, corrupt tails, savepoint
truncation, and hot-journal bounded restore behavior.
