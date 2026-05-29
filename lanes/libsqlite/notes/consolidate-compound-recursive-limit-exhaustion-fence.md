# Compound Recursive Limit Exhaustion Fence Consolidation

Consolidated the compound SELECT recursive LIMIT exhaustion fence variant inside
`SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan` by replacing the
remaining numbered helper names and cursor/result keys with stable
descriptive names.

Direct coverage migrated to
`SQLiteCompoundSelectWindowRecursiveLimitExhaustionFenceTest.php`. The adjacent
offset-yield seal test was updated because it consumes the renamed recursive
limit acknowledgement cursor keys.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitExhaustionFenceTest.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext247Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitExhaustionFenceTest.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext247Test.php`
  - `2 test files, 896 assertions, 0 failures`

Dependency closure: no new support component needed; this is a naming
consolidation over existing compound SELECT recursive/window cursor behavior.
