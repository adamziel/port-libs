# real-upstream-corpus-btree-index-dynamic-20260531T023205Z-0

Slice: `real-upstream-corpus-btree-index-dynamic-20260531T023205Z-0`.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/bestindexB.test`
- Sections covered: `bestindexB-1.0` through `bestindexB-1.5`.

## Behavior Ported

- Virtual-table `xFilter` scan result row for `SELECT * FROM x1`.
- Ordinary `INSERT ... RETURNING rowid` before the trigger is installed.
- `BEFORE INSERT` trigger that selects from the virtual table without changing the outer returned rowid.
- `xBestIndex` running an `INSERT INTO y2 VALUES(NULL,NULL) RETURNING rowid` side statement while planning that trigger SELECT.
- Preservation of the side `RETURNING` rowid result after the outer insert completes.

The new focused file adds 1,003 TestRunner cases: 1,000 dynamic upstream behavior cases plus source-range, invalid-size, and dependency-closure checks.

## Non-Overlap

This targets upstream `bestindexB.test` only. It avoids accepted `bestindexA`, `bestindex6`, `bestindex7`, `bestindex8`, `bestindex9`, `bestindexF`, autoindex, `index7`, `index8`, `index9`, where8 OR optimization, B-tree page relocation/root-collapse/overflow freelist, JSON, WAL, VFS, PRAGMA, and source-neutral cleanup clusters.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local B-tree/index dynamic corpus planner and adds bounded trigger/`RETURNING` side-effect accounting for virtual-table planning.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeBestIndexBReturningDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeBestIndexBReturningDynamicTest.php`
- `git diff --check -- lanes/libsqlite`
