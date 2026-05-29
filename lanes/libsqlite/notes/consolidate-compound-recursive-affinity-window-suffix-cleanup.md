# Compound Recursive Affinity Window Suffix Cleanup

Slice: `consolidate-final-numbered-production-suffix-cleanup-sixty-sixth-pass`

Changed production family:

- `SQLiteCompoundRecursiveAffinityWindowCurrentSourceNextPlan`
- `SQLiteCompoundWindowRecursiveAffinityCurrentSourceNextPlan`

Cleanup performed:

- Replaced numbered public entry points the two numbered public entry points with stable descriptive entry points:
  - `compareRecursiveAffinityWindow()`
  - `compareRecursiveUnionSourceBoundary()`
- Replaced direct numbered private helpers in the consolidated production class with descriptive unsuffixed helpers.
- Renamed the two direct focused tests and WordPress examples so this family no longer exposes the worker-number suffixes in production callers.
- Confirmed the exact user-named the explicitly banned user-named CurrentSource plus Next150 plus Plan suffix suffix is absent from the touched production/test/example family.

Verification:

- `php -l` passed for all changed PHP files.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundRecursiveAffinityWindowTest.php lanes/libsqlite/tests/SQLiteCompoundRecursiveUnionSourceBoundaryTest.php`
  - `2 test files, 391 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-compound-recursive-affinity-window.php --self-test`
  - passed
- `php lanes/libsqlite/examples/wordpress-compound-recursive-union-source-boundary.php --self-test`
  - passed

Dependency closure:

- No new support component is needed. This cleanup preserves existing native `SQLiteSelectSql` recursive CTE, compound SELECT, window, and affinity behavior while removing numbered production helper names.
