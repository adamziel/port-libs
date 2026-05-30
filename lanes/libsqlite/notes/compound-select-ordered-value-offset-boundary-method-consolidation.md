# Compound SELECT ordered value-offset boundary method consolidation

## Scope

- Consolidated the ordered value-offset compound boundary numbered public method into the descriptive canonical method `compareOrderedValueOffsetCompoundBoundary()`.
- Renamed the private numbered helper family to `*OrderedValueOffsetCompoundBoundary()`.
- Renamed the direct test and Application smoke files to remove the numbered suffix.

## Verification

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitOrderedValueOffsetBoundaryTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitOrderedValueOffsetBoundaryTest.php`
- `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-ordered-value-offset-boundary.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-compound-select-window-recursive-limit-ordered-value-offset-boundary.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitOrderedValueOffsetBoundaryTest.php`
  - `1 test files, 366 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-ordered-value-offset-boundary.php --self-test`
  - `application-compound-select-window-recursive-limit-current-source-ordered-value-offset-boundary self-test passed`
- `git diff --check -- lanes/libsqlite`
  - passed

## Dependency Closure

No new support component needed; this is a consolidation-only rename of an existing compound SELECT helper family and its direct coverage.
