# B-tree Freelist Trunk Cycle Current/Next71

This slice adds a bounded native PHP freelist traversal plan for corrupted
current/next trunk chains. The existing throwing freelist APIs still reject
cyclic trunks before allocation, while repair/preflight code can now inspect
the visited trunks, leaves, allocation order, cycle page, cycle path, and error
without walking indefinitely.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreelistTrunkCycleCurrentNext71Test.php`
  - `1 test files, 247 assertions, 0 failures`
  - 52 PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowAutoVacuumPointerMapCurrentNext53Test.php lanes/libsqlite/tests/SQLiteBTreeFreelistTrunkCycleCurrentNext71Test.php`
  - `2 test files, 757 assertions, 0 failures`
- `php -d auto_prepend_file=tools/bootstrap.php lanes/libsqlite/examples/application-freelist-trunk-cycle-current-next71.php`
  - reports `valid: false`, `cycleAtPage: 106`, `cyclePath: [106, 110, 106]`, and `SQLite freelist loops at page 106`
- `php -l lanes/libsqlite/src/SQLiteFreelistTraversalPlan.php`
- `php -l lanes/libsqlite/src/SQLiteDatabase.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeFreelistTrunkCycleCurrentNext71Test.php`
- `php -l lanes/libsqlite/examples/application-freelist-trunk-cycle-current-next71.php`
  - all changed PHP files report no syntax errors
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Non-overlap:

This avoids accepted overflow freelist release, bulk overflow freeblocks,
pointer-map vacuum append allocation, table/index page relocation, root
collapse, index-interior merge, and generic PRAGMA freelist integrity
pagination. The new behavior is specifically the current/next freelist trunk
cycle guard and bounded traversal diagnostics used before B-tree allocation.

Dependency closure:

No new support component is needed. The patch reuses existing native SQLite
header, freelist trunk, database page, overflow allocation, and pointer-map
helpers.
