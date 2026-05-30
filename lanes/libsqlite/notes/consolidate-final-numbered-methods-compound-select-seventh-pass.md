Status: seventh-pass compound SELECT numbered-method consolidation.

Scope:

- Renamed the private `Next246` helper methods behind
  `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveLimitSourceHandoff()`
  to stable `RecursiveLimitSourceHandoff` helper names.
- Renamed the private `Next247` helper methods behind
  `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveOffsetYieldSeal()`
  to stable `RecursiveOffsetYieldSeal` helper names.
- Preserved the existing public descriptive entrypoints, cursor array keys, and
  direct test/example behavior so this remains a consolidation-only patch.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext246Test.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext247Test.php`
- `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next246.php`
- `php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next247.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext246Test.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext247Test.php`
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next246.php --self-test`
- `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next247.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This patch reuses the
existing compound SELECT recursive LIMIT/OFFSET implementation and only changes
private production helper names.

Non-overlap: this patch is limited to the final source-handoff and
offset-yield-seal helper methods in the canonical compound SELECT production
class. It does not touch pager, WAL/VFS, B-tree, JSON, trigger, row-value,
planner, PRAGMA, encoding, or upstream-suite families.
