# real-upstream-corpus-btree-index-dynamic-20260601T214855Z-1

Status: ready for clean integration.

Base accepted HEAD: `cca35c87370c082f33903fb0c550f06fe5b02fb0`.

## Source Truth

- Upstream file: `/home/claude/port-libs/.upstream-cache/libsqlite/test/wherelfault.test`
- Ported sections:
  - `wherelfault-1.1`: `DELETE FROM v1 ORDER BY a LIMIT 3`
  - `wherelfault-1.2`: `UPDATE v1 SET b = 555 ORDER BY a LIMIT 3`
  - `wherelfault-2.1`: `DELETE FROM t2 WHERE c=? ORDER BY a DESC LIMIT 10` on an empty `WITHOUT ROWID` primary-key table

## Patch

- Added `SQLiteBTreeIndexDynamicCorpusPlan::whereLimitFaultUpdateDeleteCases()`.
- Added `SQLiteRealUpstreamBtreeWhereLimitFaultDynamicTest`.
- The test adds 1000 dynamic upstream-backed retry cases plus 4 citation/range/dependency guard cases.
- `lanes/libsqlite/lane-status.json` updates `phpPass` from `6276412` to `6277416` for the +1004 focused TestRunner PASS-case delta.

## Behavior

The corpus models the upstream faultsim retry surface for update/delete with `ORDER BY ... LIMIT`:

- view-backed `INSTEAD OF DELETE` keeps the base table unchanged and logs selected keys `1,2,3`;
- view-backed `INSTEAD OF UPDATE` logs selected keys `1,2,3` and preserves the expected updated values;
- empty `WITHOUT ROWID` delete retries complete with no selected keys, no base mutation, and primary-key cursor metadata preserved.

## Non-Overlap

This slice avoids accepted B-tree page relocation, root collapse, index-interior merge, overflow freelist release, bulk overflow freeblock materialization, `wherefault` OR-planner retry, `wherelimit` and `wherelimit2` metadata, and the existing `without_rowid` dynamic corpus. It also avoids WAL/VFS/PDO/source-neutral surfaces.

## Verification

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereLimitFaultDynamicTest.php`
  `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereLimitFaultDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereLimitFaultDynamicTest.php`
  `1 test files, 39014 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereLimitFaultDynamicTest.php`
  `2 test files, 104184 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  `1 test files, 8 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  passed with no output

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing lane-local B-tree/index dynamic corpus planner, update/delete LIMIT selection metadata, trigger-log modeling, faultsim retry metadata, and WITHOUT ROWID primary-key cursor metadata.

## Follow-Up

Broad full-lane/release parity still has the known 16 failures. The next b-tree corpus work should pick another real upstream section that is not covered by the current `wherefault`, `wherelimit`, `without_rowid`, page-move, root-collapse, or overflow/freelist batches.
