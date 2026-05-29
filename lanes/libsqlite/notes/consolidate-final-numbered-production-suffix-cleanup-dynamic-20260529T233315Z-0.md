## Final Numbered Production Suffix Cleanup Dynamic

Consolidated the remaining private `Next217` helper names in
`SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan` into stable
rank/dense-rank INTERSECT LIMIT helper names.

Observable next217 status strings, dependency keys, replan reasons,
non-overlap text, tests, and examples are preserved so generated evidence
metadata remains compatible while production helper names stop carrying the
worker number.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext217Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimit*Test.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this is a production
helper-name consolidation only.
