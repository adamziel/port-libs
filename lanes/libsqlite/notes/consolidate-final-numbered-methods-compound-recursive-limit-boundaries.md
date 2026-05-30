# Compound Recursive LIMIT Boundary Consolidation

Consolidated the remaining `Next185`, `Next186`, and `Next187` production
helper names in `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`
into descriptive entry points for the union-tail, comma-LIMIT, and negative
recursive LIMIT/OFFSET boundary variants.

The observable status strings, dependency keys, and proof labels remain stable
for downstream receipts; only the production method names and direct
test/example filenames were renamed away from worker-number suffixes.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitUnionTailBoundaryTest.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCommaBoundaryTest.php` -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitNegativeBoundaryTest.php` -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-union-tail-boundary.php` -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-comma-boundary.php` -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-negative-boundary.php` -> no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitUnionTailBoundaryTest.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCommaBoundaryTest.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitNegativeBoundaryTest.php` -> `3 test files, 965 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimit*Test.php` -> `78 test files, 29418 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-union-tail-boundary.php --self-test` -> passed.
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-comma-boundary.php --self-test` -> passed.
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-negative-boundary.php --self-test` -> passed.
- `git diff --check -- lanes/libsqlite` -> passed.

Dependency closure: no new support component needed; this is consolidation-only
and reuses the existing native PHP SELECT SQL, recursive CTE, compound, window,
and LIMIT/OFFSET helpers.
