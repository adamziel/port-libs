# Compound SELECT Numbered Method Consolidation Sixty-Fifth Pass

Consolidated two adjacent numbered helper families inside
`SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`:

- The union/intersect window LIMIT/OFFSET numbered entry point and private helpers are now
  `compareUnionIntersectWindowLimitOffset()` and matching descriptive helpers.
- The UNION ALL final LIMIT boundary numbered entry point and private helpers are now
  `compareUnionAllWindowFinalLimitBoundary()` and matching descriptive helpers.

Direct tests and WordPress examples were renamed away from generated
generated numbered filenames and updated to the descriptive scenario names.
The touched source/tests/examples no longer expose generated numbered compound-select references.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitUnionIntersectWindowLimitOffsetTest.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitUnionAllWindowFinalLimitBoundaryTest.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-union-intersect-window-limit-offset.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-union-all-window-final-limit-boundary.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitUnionIntersectWindowLimitOffsetTest.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitUnionAllWindowFinalLimitBoundaryTest.php`
  - Result: `2 test files, 517 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-union-intersect-window-limit-offset.php --self-test`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-union-all-window-final-limit-boundary.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This pass only renames
existing lane-local compound SELECT helper methods and direct callers while
preserving existing SELECT SQL, recursive CTE, compound SELECT, window, ORDER BY,
and LIMIT/OFFSET behavior.
