# Compound SELECT numbered-method consolidation fifty-first pass

Consolidated the compound SELECT recursive/window comma-boundary variant in
`SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan` away from the
generated worker-numbered public entry point, helper suffixes,
status/dependency markers, direct test name, and WordPress smoke name.

Stable names now cover the same behavior:

- `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveCommaBoundary()`
- `lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCommaBoundaryTest.php`
- `lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-comma-boundary.php`
- `lanes/libsqlite/notes/compound-select-window-recursive-limit-comma-boundary.md`

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCommaBoundaryTest.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-comma-boundary.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCommaBoundaryTest.php` -> `1 test files, 265 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-comma-boundary.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This is a production
method-name consolidation over existing SELECT SQL, recursive CTE, window,
compound SELECT, and comma-form LIMIT/OFFSET behavior.

Root harness: not run - isolated micro-slice.
