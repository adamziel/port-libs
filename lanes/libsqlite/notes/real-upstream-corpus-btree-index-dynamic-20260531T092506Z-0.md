# real-upstream-corpus-btree-index-dynamic-20260531T092506Z-0

Status: ready for integration.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/whereD.test`
- Upstream sections: `whereD-1.2` through `whereD-1.16`.

Focused coverage:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::whereDCoveringOrIndexCases(1000)`.
- Added `SQLiteRealUpstreamBtreeWhereDCoveringOrDynamicTest.php`.
- The generator models the upstream table/index shape from `whereD.test`, computes row results from OR-arm constraints, tracks chosen `ijk`/`jmn` index probes, residual `+i` handling, covering-index projections, and table-lookup fallbacks for projections not covered by every OR arm.
- Focused PASS-line movement: `+1003` TestRunner PASS cases with `20204` behavior assertions in the focused whereD file.

Non-overlap:

- This owns `whereD.test` covering OR-index behavior only.
- It does not repeat accepted `where8`, `where9`, `whereH`, `whereJ`, `whereK`, `whereL/M/N`, indexed-by enforcement, expression-index range-cost, index-interior merge, root collapse, overflow freelist/freeblock release, JSON, WAL, VFS, PRAGMA, trigger/FK, UPSERT, or source-neutral cleanup clusters.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereDCoveringOrDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereDCoveringOrDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereDCoveringOrDynamicTest.php`
  - `1 test files, 20204 assertions, 0 failures`
  - `1003` focused TestRunner PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDynamicCorpusPlanTest.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeWhereDCoveringOrDynamicTest.php`
  - `2 test files, 85372 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r '$p="lanes/libsqlite/lane-status.json"; json_decode(file_get_contents($p), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - Passed with no output.

Dependency closure:

- No new support component is needed. This reuses the lane-local B-tree/index dynamic corpus planner and existing OR-index union, covering-index projection, residual-term, and result-row helpers.
