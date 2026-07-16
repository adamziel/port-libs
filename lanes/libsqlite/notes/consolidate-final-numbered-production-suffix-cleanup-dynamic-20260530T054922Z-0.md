Consolidation slice: consolidate-final-numbered-production-suffix-cleanup-dynamic-20260530T054922Z-0

Scope:
- Renamed the private VFS current-source environment helper cluster in
  `SQLiteVfsCurrentSourceNextPlan` from `*162165` helper names to stable
  descriptive helper names.
- Preserved public entrypoints, direct tests/examples, output dependency labels,
  event/status text, generated handles, and observable proof keys.

Verification:
- `php -l lanes/libsqlite/src/SQLiteVfsCurrentSourceNextPlan.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext162165Test.php`: 1 test file, 22 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext158161Test.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext162165Test.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext166169Test.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext170173Test.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext174177Test.php lanes/libsqlite/tests/SQLiteVfsCurrentSourceNext178181Test.php`: 6 test files, 120 assertions, 0 failures.

Dependency closure:
- No new support component is needed; this is a production helper-name
  consolidation only.
