# real-upstream-corpus-btree-index-dynamic-20260531T000152Z-0

Base accepted HEAD: `dd1b1090c602dc6e35c0593d57edce4faedf25d2`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/btreefault.test`
- `btreefault-1`: `PRAGMA incremental_vacuum = 10` while an ordered statement cursor is active under injected allocation faults.
- `btreefault-2.2`: ordered `t1 CROSS JOIN t2` cursor yields `25 a 25 b` and deletes the indexed `t1` row during the callback before `25 c`.

Lane changes:

- Added `SQLiteBTreeIndexDynamicCorpusPlan::btreeFaultCursorMutationCases()` with 1000 dynamic cases split evenly across the two real upstream scenarios.
- Added `SQLiteRealUpstreamBtreeFaultDynamicTest.php` with 1003 focused TestRunner cases.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeIndexDynamicCorpusPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamBtreeFaultDynamicTest.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamBtreeFaultDynamicTest.php` -> 1 file / 22006 assertions / 0 failures / 1003 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> 1 file / 3 assertions / 0 failures.
- `git diff --check -- lanes/libsqlite` -> pass.

Non-overlap:

- This slice does not repeat accepted B-tree page relocation, root collapse, index-interior merge, overflow freelist release, autoindex3 planner, index4/index5/index6/index7/index8/index9/indexA, or indexed-by corpus coverage.
- `btreefault.test` had no focused B-tree/index TestRunner coverage in this worktree before this slice.

Dependency closure:

- No new support component is needed. The slice reuses the existing PHP B-tree/index dynamic corpus planner and the hydrated upstream SQLite Tcl test file as behavior source truth.
