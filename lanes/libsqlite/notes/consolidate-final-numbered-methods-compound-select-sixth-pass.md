# consolidate-final-numbered-methods-compound-select-sixth-pass

## Scope

Consolidated the compound recursive collation/limit SELECT diagnostic entrypoint in `SQLiteCompoundRecursiveCollationLimitCurrentSourceNextPlan`.

## Production cleanup

- Renamed public `compareNext132()` to `compareRecursiveCollationLimit()`.
- Renamed the associated private `*Next132()` helpers to descriptive unsuffixed helper names.
- Updated the direct focused test and Application smoke to call the canonical entrypoint.

The existing status string payloads remain unchanged because the direct tests assert those diagnostic values.

## Verification

- `php -l lanes/libsqlite/src/SQLiteCompoundRecursiveCollationLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundRecursiveCollationLimitCurrentSourceNext132Test.php`
- `php -l lanes/libsqlite/examples/application-compound-recursive-collation-limit-current-source-next132.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundRecursiveCollationLimitCurrentSourceNext132Test.php`
  - `1 test files, 159 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-compound-recursive-collation-limit-current-source-next132.php --self-test`
  - `application-compound-recursive-collation-limit-current-source-next132 self-test passed`

## Dependency Closure

No new support component is needed. This is a production API/helper-name consolidation over existing compound SELECT behavior.
