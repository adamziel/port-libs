# Compound Next213 Helper Consolidation

Consolidated the private `Next213` helper methods inside
`SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan` into stable
descriptive `minMaxIntersect*` helper names.

Observable behavior is intentionally preserved: the public
`compareMinMaxIntersectLimit()` entry point, emitted result keys, dependency
strings, replan reasons, non-overlap text, and direct test metadata remain
unchanged.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext213Test.php`
  - `1 test files, 370 assertions, 0 failures`
- `php tools/run-tests.php $(find lanes/libsqlite/tests -maxdepth 1 -type f \( -name 'SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext*Test.php' -o -name 'SQLiteCompoundWindowRecursiveLimitCurrentSourceNext*Test.php' -o -name 'SQLiteCompound*Recursive*Limit*Test.php' \) | sort)`
  - `91 test files, 32141 assertions, 0 failures`

Dependency closure: no new support component is needed; this is a production
helper-name consolidation only.

Non-overlap: this avoids functional SQL behavior work and only removes one
remaining numbered private-helper cluster in the compound recursive-limit
production family.
