## Compound SELECT Numbered Method Consolidation, Fifty-Eighth Pass

Consolidated the direct compound SELECT `next173` public method/helper family in
`SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan` into the
descriptive `compareUnionExceptRecursiveWindowLimit()` surface and matching
private helper names.

Direct callers were migrated:

- `SQLiteCompoundSelectWindowRecursiveLimitUnionExceptRecursiveWindowLimitTest.php`
- `wordpress-compound-select-window-recursive-limit-union-except.php`

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitUnionExceptRecursiveWindowLimitTest.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-union-except.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitUnionExceptRecursiveWindowLimitTest.php`
  - `1 test files, 262 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-union-except.php`

Dependency closure: no new support component needed; this is a production name
consolidation over existing lane-local SELECT SQL, recursive CTE, compound,
window, ORDER BY, and LIMIT/OFFSET behavior.

Non-overlap: this pass only renames the direct compound UNION/EXCEPT recursive
window LIMIT method/helper family and its direct test/example. It does not touch
JSON table, pager/WAL/VFS, B-tree, trigger, PRAGMA, planner, encoding, suite
evidence, or unrelated numbered families.
