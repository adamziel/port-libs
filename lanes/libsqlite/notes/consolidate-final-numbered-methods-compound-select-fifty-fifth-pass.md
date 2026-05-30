# Compound SELECT Numbered Method Consolidation

Date: 2026-05-29

Slice: `consolidate-final-numbered-methods-compound-select-fifty-fifth-pass`

## Scope

Consolidated three remaining numbered helper families inside
`SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`:

- `compareNext169()` and private `*Next169()` helpers are now
  `compareRecursiveOrderCommaLimitNtile()` and matching descriptive helpers.
- `compareNext171()` and private `*Next171()` helpers are now
  `compareDistinctUnionWindowLimitOffset()` and matching descriptive helpers.
- `compareNext172()` and private `*Next172()` helpers are now
  `compareDistinctUnionSourceClassLimit()` and matching descriptive helpers.

Direct tests and Application examples were renamed away from generated
`CurrentSourceNextNN` filenames and updated to the descriptive scenario names.
The touched source/tests/examples no longer contain `next169`, `next171`, or
`next172` compound-select references.

## Verification

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l` for the three renamed compound-select test files
- `php -l` for the three renamed compound-select Application examples
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitRecursiveOrderCommaLimitNtileTest.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitDistinctUnionWindowLimitOffsetTest.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitDistinctUnionSourceClassLimitTest.php`
  - Result: `3 test files, 736 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-recursive-order-comma-limit-ntile.php --self-test`
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-distinct-union-window-limit-offset.php --self-test`
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-distinct-union-source-class-limit.php --self-test`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This pass only renames consolidated
scenario methods, helper suffixes, tests, and examples while preserving existing
lane-local SELECT SQL, recursive CTE, compound SELECT, window, and LIMIT/OFFSET
execution behavior.

## Follow-Up

Later numbered compound SELECT helper families remain in the canonical
production class and should be consolidated in subsequent bounded passes.
