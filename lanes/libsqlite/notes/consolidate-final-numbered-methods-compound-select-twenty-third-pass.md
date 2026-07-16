# Compound SELECT Numbered Method Consolidation Twenty-Third Pass

Session: `port-dev-sqlite-yield-consol-meth-compound-ab`

Scope:

- Renamed the public numbered production entrypoints in three standalone compound SELECT families:
  - `SQLiteCompoundHavingWindowCurrentSourceNextPlan::compareHavingWindow()`
  - `SQLiteCompoundLimitWindowAffinityCurrentSourceNextPlan::compareLimitWindowAffinity()`
  - `SQLiteCompoundWindowExceptLimitCurrentSourceNextPlan::compareWindowExceptLimit()`
- Renamed each family's private `*Next128`, `*Next137`, and `*Next141` helpers to descriptive unsuffixed helper names.
- Migrated the direct focused tests and Application examples to the stable entrypoints.
- Preserved existing diagnostic statuses, dependency strings, result keys, assertions, and example output text.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundHavingWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/src/SQLiteCompoundLimitWindowAffinityCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/src/SQLiteCompoundWindowExceptLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundHavingWindowCurrentSourceNext128Test.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundLimitWindowAffinityCurrentSourceNext137Test.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundWindowExceptLimitCurrentSourceNext141Test.php`
- `php -l lanes/libsqlite/examples/application-compound-having-window-current-source-next128.php`
- `php -l lanes/libsqlite/examples/application-compound-limit-window-affinity-current-source-next137.php`
- `php -l lanes/libsqlite/examples/application-compound-window-except-limit-current-source-next141.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundHavingWindowCurrentSourceNext128Test.php lanes/libsqlite/tests/SQLiteCompoundLimitWindowAffinityCurrentSourceNext137Test.php lanes/libsqlite/tests/SQLiteCompoundWindowExceptLimitCurrentSourceNext141Test.php`
- `php lanes/libsqlite/examples/application-compound-having-window-current-source-next128.php --self-test`
- `php lanes/libsqlite/examples/application-compound-limit-window-affinity-current-source-next137.php --self-test`
- `php lanes/libsqlite/examples/application-compound-window-except-limit-current-source-next141.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This is a production API/helper-name consolidation over existing compound SELECT behavior.

Non-overlap: this pass only removes numbered production method/helper names from the compound HAVING/window, LIMIT/window/affinity, and window/EXCEPT/LIMIT families. It does not change SQL execution behavior, WAL/VFS, B-tree, JSON, trigger, row-value, planner, PRAGMA, encoding, or suite evidence.
