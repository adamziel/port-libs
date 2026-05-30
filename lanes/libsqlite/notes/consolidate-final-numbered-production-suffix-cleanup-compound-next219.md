## Compound Next219 Numbered Helper Consolidation

Consolidated the compound SELECT window recursive LIMIT `Next219` private
production helper cluster inside
`SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan` into stable
descriptive helper names for the percent_rank/cume_dist EXCEPT LIMIT path.

Observable behavior is intentionally preserved: the public entry point, status
string, dependency strings, replan reason strings, exception diagnostics, test
filenames, and example metadata still expose the accepted `next219` evidence
keys.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext219Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimit*.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a production
helper-name consolidation over the existing native SELECT SQL compound,
recursive CTE, window, EXCEPT, current-source token, and final LIMIT helpers.
