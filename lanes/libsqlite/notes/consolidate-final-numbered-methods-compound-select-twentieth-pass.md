# Compound SELECT Numbered Method Consolidation Twentieth Pass

Session: `port-dev-sqlite-yield-consol-meth-compound-y`

Scope:

- Renamed numbered production entrypoints and private helpers in:
  - `SQLiteCompoundCollationWindowCurrentSourceNextPlan`
  - `SQLiteCompoundSelectExceptWindowLimitCurrentSourceNextPlan`
  - `SQLiteCompoundWindowRecursiveYieldCurrentSourceNextPlan`
- Migrated direct focused tests and WordPress examples to the descriptive unsuffixed entrypoints.
- Added the missing direct `SQLiteAffinityComparison` include to the recursive-yield WordPress example so it runs outside the test harness.

Verification:

- `php -l` passed for the three changed production PHP files, three focused test files, and three changed examples.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundCollationWindowCurrentSourceNext136Test.php lanes/libsqlite/tests/SQLiteCompoundSelectExceptWindowLimitCurrentSourceNext148Test.php lanes/libsqlite/tests/SQLiteCompoundWindowRecursiveYieldCurrentSourceNext159Test.php`
  - Result: `3 test files, 741 assertions, 0 failures`.
- `php lanes/libsqlite/examples/wordpress-compound-collation-window-current-source-next136.php --self-test`
  - Result: self-test passed.
- `php lanes/libsqlite/examples/wordpress-compound-select-except-window-limit-current-source-next148.php --self-test`
  - Result: self-test passed.
- `php lanes/libsqlite/examples/wordpress-compound-window-recursive-yield-current-source-next159.php --self-test`
  - Result: self-test passed.
- `git diff --check -- lanes/libsqlite`
  - Result: passed.

Dependency closure:

- No new support component is needed. This consolidation reuses the existing native PHP SELECT SQL, compound SELECT, window, collation, recursive CTE, and affinity comparison helpers.

Non-overlap:

- This is consolidation-only. It does not add a new compound SELECT behavior slice and does not reintroduce production numbered classes/files.
