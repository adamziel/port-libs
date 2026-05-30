# real-upstream-corpus-pager-wal-dynamic-20260530T181941Z-0 blocked

Base accepted HEAD: `1be884bec4b3d8944d386430e62bb83a7a09f0ef`

## Attempted upstream section

Hydrated upstream source checked under `/home/claude/port-libs/.upstream-cache/libsqlite/test`:

- `wal2.test`: 61 Tcl test declarations.
- `walrestart.test`: 6 Tcl test declarations.
- `walmode.test`: 69 Tcl test declarations.
- `walpersist.test`: 20 Tcl test declarations.
- `walro.test`: 2 Tcl test declarations.
- `walro2.test`: 1 Tcl test declaration.
- `pager1.test`: 165 Tcl test declarations.
- `pager2.test`: 2 Tcl test declarations.

The current worktree already contains overlapping real pager/WAL dynamic corpus coverage:

- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalLockRaceCorpusTest.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalModePersistDynamicTest.php`
- `lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`
- `lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicPlan.php`

Those tests cover `wal2.test` sections `wal2-1.*`, `wal2-2.*`, `wal2-4.*`, `wal2-5.1`, `pager1.test` multiclient lock transitions, `walrestart.test` `walrestart-1.0` through `walrestart-1.5`, `walmode.test` mode transitions, `walpersist.test` persistent-WAL file-control cases, and `walro.test` / `walro2.test` readonly checkpoint cases.

## Why this is blocked

The hard handoff floor for a fresh `real-upstream-corpus-*` worker requires at least 1,000 distinct focused TestRunner PASS cases, 5,000 behavior assertions, a named behavior/runner blocker that unlocks at least 2,000 PASS cases or 10,000 assertions, or guarded mapped-denominator movement.

The existing overlapping focused pager/WAL dynamic corpus run passes, but it is only:

- `3 test files`
- `2799 assertions`
- `0 failures`

Adding another small hand-authored pager/WAL dynamic extension would duplicate the already admitted `wal2` / `walmode` / `walpersist` surface and still miss the `5,000` assertion floor. I did not write a convenience-sized green patch.

## Next larger batch to try

Build a generated-but-source-traced pager/WAL admission batch from the remaining real upstream declarations in:

- `pager1.test` remaining lock and journal scenarios outside the current multiclient transition rows.
- `walmode.test` remaining attach/temp/exclusive-locking rows not already represented by `SQLitePragmaJournalState`.
- `wal2.test` sections `wal2-3.*`, `wal2-6.*` through `wal2-15.*`, especially busy-lock, exclusive-locking, shm-lock omission, autocheckpoint, and sync-count behavior.
- `walrestart.test` beyond the existing `walrestart-1.*` checkpoint-race summary if more source-traced rows can be represented without static metadata-only assertions.

The next acceptable patch should either:

- admit at least `5,000` behavior assertions from these real upstream files, or
- add a runner/generator map that can safely convert the above declarations into at least `1,000` distinct focused TestRunner PASS cases with source filename and subtest names preserved.

## Verification run

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalLockRaceCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalModePersistDynamicTest.php`
  - `3 test files, 2799 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`

## Dependency closure

No new support component was added. The blocker is admission scale and overlap with current accepted pager/WAL dynamic corpus coverage, not a missing external dependency.
