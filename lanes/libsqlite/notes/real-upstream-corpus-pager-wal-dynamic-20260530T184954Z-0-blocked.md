# real-upstream-corpus-pager-wal-dynamic-20260530T184954Z-0 blocked

Base accepted HEAD: `133a53d7c328acb7a2a9f5b43747e45d705421ba`

## Attempted upstream section

Hydrated upstream source inspected under `/home/claude/port-libs/.upstream-cache/libsqlite/test`:

- `wal.test`
- `wal2.test`
- `walckptnoop.test`
- `walcksum.test`
- `walrestart.test`
- `walmode.test`
- `walpersist.test`
- `walro.test`
- `walro2.test`
- `pager1.test`
- `pager2.test`
- `pager3.test`
- `pager4.test`

Current accepted source already contains overlapping real pager/WAL dynamic corpus coverage in:

- `lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalLockRaceCorpusTest.php`
- `lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalModePersistDynamicTest.php`
- `lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicPlan.php`
- `lanes/libsqlite/src/SQLiteRealUpstreamPagerWalDynamicCorpusPlan.php`

Covered upstream sections include `wal.test` warm-body, MVCC, rollback, savepoint,
checkpoint, checksum-prefix, stale-reader, and large-WAL-index cases;
`wal2.test` header recovery, stale header, busy lock, no-SHM, checkpoint recovery,
exclusive-locking, mode transition, readonly, sync-count, and permission cases;
`walrestart.test` checkpoint-race summaries; `walmode.test` journal-mode
transitions; `walpersist.test` persistent-WAL file-control behavior; `walro.test`
and `walro2.test` readonly checkpoint behavior; `walckptnoop.test` NOOP
checkpoint semantics; `walcksum.test` checksum byte-order recovery; and
`pager1.test` multiclient lock transitions.

## Why this is blocked

The active hard handoff floor for `real-upstream-corpus-*` slices requires at
least one of:

- 1,000 distinct focused TestRunner PASS cases;
- 5,000 behavior assertions from real upstream SQLite cases;
- a named blocker fix that unlocks at least 2,000 PASS cases or 10,000
  assertions in the next batch;
- guarded mapped-denominator movement from real upstream-runner evidence.

The remaining non-overlapping pager/WAL rows I could identify are small fragments
or require a real Tcl/VFS runner mapping step before they can honestly count.
Expanding them by looped variants would be repetitive around static upstream
records rather than distinct behavior, and would violate the current real corpus
rule. I did not write a convenience-sized green patch.

## Next larger batch to try

The next acceptable pager/WAL handoff should be one of:

- a guarded runner-map admission for the remaining hydrated pager/WAL Tcl files
  that proves denominator movement with real runner artifacts;
- a generated-but-source-traced converter for `pager1.test` and `pager2.test`
  lock/journal declarations that emits at least 1,000 distinct focused PHP PASS
  cases without static metadata-only assertions;
- a behavior fix for a named WAL/Pager VFS runner blocker, with evidence that it
  unlocks at least 2,000 additional upstream PASS cases or 10,000 assertions.

## Verification

Focused verification run on the current accepted tree:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPagerWalDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalLockRaceCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPagerWalModePersistDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `git diff --check -- lanes/libsqlite`

## Dependency closure

No new support component is needed. The blocker is non-overlapping admission
scale and runner mapping for remaining pager/WAL upstream Tcl behavior, not an
external dependency.
