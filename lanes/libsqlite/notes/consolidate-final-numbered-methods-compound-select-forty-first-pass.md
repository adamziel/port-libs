# Compound SELECT numbered-method consolidation forty-first pass

This pass consolidates the recursive-limit/window compound SELECT current-source
family by replacing the worker-numbered production entry point and private
helper suffixes in `SQLiteCompoundRecursiveLimitWindowCurrentSourceNextPlan`.

Direct coverage was migrated to the stable names:

- `SQLiteCompoundRecursiveLimitWindowCurrentSourceNextPlan::compare()`
- `lanes/libsqlite/tests/SQLiteCompoundRecursiveLimitWindowCurrentSourceNextTest.php`
- `lanes/libsqlite/examples/wordpress-compound-recursive-limit-window-current-source-next.php`

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundRecursiveLimitWindowCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundRecursiveLimitWindowCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-recursive-limit-window-current-source-next.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundRecursiveLimitWindowCurrentSourceNextTest.php`
  passed with 1 test file, 164 assertions, 0 failures.
- `php lanes/libsqlite/examples/wordpress-compound-recursive-limit-window-current-source-next.php --self-test`
  passed.

Dependency closure: no new support component is needed. This is a production
method-name consolidation over existing compound SELECT, recursive CTE, LIMIT,
and window-frame behavior.

Non-overlap: this pass only touches the compound recursive-limit/window
current-source family and avoids JSON, WAL, pager, B-tree, trigger, PRAGMA, and
planner/STAT4 numbered families.
