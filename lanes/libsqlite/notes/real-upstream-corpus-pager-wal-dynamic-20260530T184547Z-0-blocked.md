# real-upstream-corpus-pager-wal-dynamic-20260530T184547Z-0 blocked

Base accepted HEAD: `7e63d4798cb030955a466f3272d59cba9c03648e`

## Attempted upstream section

Hydrated upstream source was inspected under
`/home/claude/port-libs/.upstream-cache/libsqlite/test` for the assigned
pager/WAL domain:

- `wal2.test`: 61 Tcl declarations.
- `walrestart.test`: 6 Tcl declarations.
- `walmode.test`: 69 Tcl declarations.
- `walpersist.test`: 20 Tcl declarations.
- `walro.test`: 2 Tcl declarations.
- `walro2.test`: 1 Tcl declaration.
- `pager1.test`: 165 Tcl declarations.
- `pager2.test`: 2 Tcl declarations.
- `pagerfault.test`: 79 Tcl declarations.
- `walckptnoop.test`: 11 Tcl declarations.
- `walnoshm.test`: 24 Tcl declarations.
- `walprotocol.test`: 14 Tcl declarations.
- `walprotocol2.test`: 7 Tcl declarations.

The current accepted worktree already contains focused real upstream pager/WAL
dynamic coverage in:

- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalLockRaceCorpusTest.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalModePersistDynamicTest.php`
- `lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
- `lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicPlan.php`

Those files cover `wal2.test` sections `wal2-1.*`, `wal2-2.*`, `wal2-4.*`,
and `wal2-5.1`; `pager1.test` multiclient RESERVED/SHARED/PENDING lock
transitions; `walrestart.test` `walrestart-1.0` through `walrestart-1.5`;
`walmode.test` mode transitions; `walpersist.test` persistent-WAL file-control
cases; and `walro.test` / `walro2.test` readonly checkpoint cases.

## Why this launch is blocked

The current hard handoff floor for `real-upstream-corpus-*` requires at least
one of:

- 1,000 distinct focused TestRunner PASS cases;
- 5,000 behavior assertions from real upstream SQLite cases;
- a named blocker fix that unlocks at least 2,000 PASS cases or 10,000
  assertions in the next batch;
- guarded mapped-denominator movement.

The existing non-overlapping pager/WAL focused run is green but only:

- `3 test files`
- `2799 assertions`
- `0 failures`

A small additive hand-authored extension from the remaining upstream pager/WAL
files would either overlap the already admitted `wal2` / `pager1` / `walmode`
/ `walpersist` surfaces or still miss the 5,000-assertion floor. I did not add
a convenience-sized ready patch.

## Next larger batch to try

The next acceptable pager/WAL worker should build a source-traced generator or
runner-map admission path for a broader remaining subset, preserving exact
upstream filenames and subtest ids. Candidate sections:

- `pagerfault.test`: fault-injected pager write, rollback, journal, and cache
  recovery declarations.
- `walnoshm.test`: WAL operation without shared-memory mappings.
- `walprotocol.test` and `walprotocol2.test`: WAL-lock protocol and snapshot
  lifecycle behavior.
- `wal2.test` sections not already covered, especially busy-lock,
  exclusive-locking, shm-lock omission, autocheckpoint, and sync-count rows.
- `pager1.test` remaining non-overlapping journal and lock scenarios outside
  the current multiclient transition rows.

The next handoff should either admit at least `5,000` behavior assertions from
those real upstream files or add runner/generator tooling that safely converts
the remaining declarations into at least `1,000` distinct focused TestRunner
PASS cases without fabricated script ids.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalLockRaceCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalModePersistDynamicTest.php`
  - `3 test files, 2799 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`

## Dependency closure

No new support component was added. The blocker is admission scale and
overlap with current accepted pager/WAL dynamic corpus coverage, not a missing
external dependency.
