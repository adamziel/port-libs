# Compound Recursive LIMIT Helper Consolidation 209-212

Consolidated the remaining private `Next209`, `Next211`, and `Next212`
helper method names in
`SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan` into stable
descriptive helper names:

- `SumCountExceptUnionLimit`
- `FilteredAggregateExceptUnionLimit`
- `GroupConcatRowNumberExceptLimit`

The public entry points, test names, status strings, dependency strings,
non-overlap text, and numbered proof keys remain unchanged so existing
observable evidence stays stable.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext209Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext209Test.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext211Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext211Test.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext212Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext212Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext209Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext211Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext212Test.php`
  - `3 test files, 1143 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompound*Recursive*Limit*Test.php`
  - `91 test files, 32141 assertions, 0 failures`

Dependency closure: no new support component needed; this is a production
helper-name consolidation over the existing compound recursive LIMIT helpers.

Root harness: not run - isolated micro-slice.
