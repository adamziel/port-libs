# Final Numbered Production Suffix Cleanup Dynamic Compound Next208

Consolidated the compound recursive LIMIT rank/dense_rank EXCEPT helper band by
renaming the private `Next208` production helper methods on
`SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan` to stable
descriptive `RankDenseRankExceptLimit` helper names.

Observable output is preserved: the public
`compareRankDenseRankExceptLimit()` entry point, status string, dependency
strings, replan reasons, non-overlap text, test file name, and WordPress example
name still expose the accepted current-source next208 evidence keys.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext208Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext*.php`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next208.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this is production helper
name consolidation over existing native SELECT SQL, compound, recursive queue,
window, EXCEPT, and LIMIT behavior.
