# Compound SELECT Consolidation Forty-Fifth Pass

## Scope

- Consolidated the direct compound SELECT expression-limit-boundary surface from `its former numbered entry point()` to `compareExpressionLimitBoundary()`.
- Renamed the direct focused test and WordPress smoke away from the numbered `old numbered current-source filename` filenames.
- Renamed adjacent private helper clusters in `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php` from the adjacent numbered spillover, admission, commit-fence, and replay-fence method names to descriptive helper names.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitExpressionLimitBoundaryTest.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-expression-limit-boundary.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitExpressionLimitBoundaryTest.php`
  - `1 test files, 340 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-expression-limit-boundary.php --self-test`
  - `wordpress-compound-select-window-recursive-limit-current-source-expression-limit-boundary self-test passed`

## Dependency Closure

No new support component is needed. This slice preserves the existing compound SELECT expression-valued LIMIT/OFFSET behavior and only removes numbered production/test/example method and file naming from the touched surface.

## Non-Overlap

This is consolidation-only work for the compound SELECT numbered method family. It does not add or alter SQL executor behavior, JSON table behavior, WAL/VFS behavior, B-tree behavior, PRAGMA behavior, trigger behavior, row-value behavior, encoding behavior, or suite-evidence behavior.
