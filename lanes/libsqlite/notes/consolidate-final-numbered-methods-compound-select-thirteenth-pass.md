# Compound SELECT Numbered Method Consolidation Thirteenth Pass

Consolidated the remaining public production entry points and private helpers for
the compound SELECT window/recursive LIMIT next224-next226 variants inside
`SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan`.

Stable production entry points now replace the numbered method names:

- `compareMixedCompoundRankFence()`
- `compareLagLastValueFence()`
- `compareAggregateWindowFence()`

Direct tests and WordPress examples were migrated to those stable entry points.
No compatibility shims or numbered production helper names were added.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext224Test.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext225Test.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext226Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next224.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next225.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next226.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext224Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext225Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext226Test.php`
  - `3 test files, 1218 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next224.php --self-test`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next225.php --self-test`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next226.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this is a production-name
consolidation over existing compound SELECT behavior and focused evidence.
