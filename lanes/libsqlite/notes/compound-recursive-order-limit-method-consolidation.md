# Compound Recursive Order/Limit Method Consolidation

Consolidated the numbered production method/helper names in
`SQLiteCompoundRecursiveOrderLimitCurrentSourceNextPlan` into stable
descriptive names:

- `compareRecursiveOrderLimit()`
- `traceSql()`
- `leftColumns()`
- `columnValues()`
- `queueAfterNames()`
- `lastTraceValue()`
- `rowSignatures()`
- `changedSignatures()`
- `replanReasons()`

Direct test and WordPress example callers now use the canonical public
entrypoint. Scenario labels and expected status strings remain unchanged so
the accepted behavior coverage is preserved while production method names no
longer differ by a worker number.

Verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundRecursiveOrderLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundRecursiveOrderLimitCurrentSourceNext146Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-recursive-order-limit-current-source-next146.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundRecursiveOrderLimitCurrentSourceNext146Test.php`
  - `1 test files, 208 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-compound-recursive-order-limit-current-source-next146.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed; this is a production
method-name consolidation over existing SELECT SQL, compound SELECT, recursive
CTE, and WordPress smoke coverage.
