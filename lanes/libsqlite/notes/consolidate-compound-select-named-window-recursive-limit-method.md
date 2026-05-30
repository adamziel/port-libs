# Compound Select Named Window Recursive Limit Method Consolidation

- Scope: `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan` named-window recursive LIMIT/OFFSET comparison cluster.
- Cleanup: renamed the generated numbered comparison method and its private helper suffixes to stable descriptive `compareNamedWindowRecursiveLimitOffset` names.
- Direct callers: renamed and updated the direct focused test and Application smoke away from generated numbered filenames.
- Evidence:
  - `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
  - `php -l lanes/libsqlite/tests/SQLiteCompoundSelectNamedWindowRecursiveLimitOffsetTest.php`
  - `php -l lanes/libsqlite/examples/application-compound-select-named-window-recursive-limit-offset.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectNamedWindowRecursiveLimitOffsetTest.php` => `1 test files, 269 assertions, 0 failures`
  - `php lanes/libsqlite/examples/application-compound-select-named-window-recursive-limit-offset.php`
- Dependency closure: no new support component needed; this reuses existing lane-local SELECT SQL, recursive CTE, compound SELECT, named window, and LIMIT/OFFSET helpers.
