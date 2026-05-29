# sqlplanner-stat4-covering-skipscan-current-source

Status: consolidated current-source planner behavior for STAT4 covering
skip-scan plans.

This consolidation keeps `SQLitePlannerStat4CoveringSkipScanCurrentSourceNextPlan`.
It reuses the accepted expression skip-scan range/current-source planner and
adds a STAT4 covering fence for stale prepared statements whose current
`sqlite_stat4` sample payload, sample count, prefix order, or covering payload
signature has changed. The selected current plan emits a covering cursor tape
with `ReprepareIfStat4FenceStale`, `SeekScan`, `Stat4SampleGate`, and covering
`Column` reads.

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-planner-stat4-covering-skipscan-current-source.php --self-test`
  - `wordpress-planner-stat4-covering-skipscan-current-source self-test passed`

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4CoveringSkipScanCurrentSourceTest.php`
  - `PASS ...` 65 lines
  - `Test files: 1`
  - `Assertions: 65`
  - `Failures: 0`

Non-overlap: avoids accepted expression skip-scan range next143, partial
expression skip-scan next129, partial covering skip-scan next125/next127,
STAT4 expression covering/range clusters, expression-index range-cost ranking,
JSON table planner/source/cursor work, and storage/VFS/WAL surfaces. The new
surface is stale STAT4 covering skip-scan sample payload/order fencing on the
current source.

Dependency closure: no new support component is needed; the patch reuses
native PHP STAT4 sample metadata, current-source planner fences, skip-scan
loop materialization, and covering cursor evidence.
