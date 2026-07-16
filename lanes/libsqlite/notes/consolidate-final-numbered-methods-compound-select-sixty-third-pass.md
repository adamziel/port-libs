# Compound SELECT Numbered Method Consolidation Sixty-Third Pass

Consolidated the compound INTERSECT/window/recursive LIMIT current-source helper surface in `SQLiteCompoundIntersectWindowRecursiveLimitCurrentSourceNextPlan`.

- Replaced the numbered compare entry point with `compareIntersectWindowRecursiveLimit()`.
- Renamed numbered private helpers to descriptive unsuffixed names for compound operators, ORDER BY columns, window terms, final LIMIT stripping, row labels, row signatures, yield boundaries, and replan reasons.
- Removed numbered suffixes from production status/dependency diagnostics.
- Renamed the direct focused test file to `SQLiteCompoundIntersectWindowRecursiveLimitCurrentSourceTest.php`.
- Updated the Application compound INTERSECT smoke to call the canonical unsuffixed method.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundIntersectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundIntersectWindowRecursiveLimitCurrentSourceTest.php`
- `php -l lanes/libsqlite/examples/application-compound-intersect-window-recursive-limit.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundIntersectWindowRecursiveLimitCurrentSourceTest.php` -> `1 test files, 257 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-compound-intersect-window-recursive-limit.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this only renames existing lane-local compound SELECT helper methods and direct callers.
