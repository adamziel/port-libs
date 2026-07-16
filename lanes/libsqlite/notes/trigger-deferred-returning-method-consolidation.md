# Trigger Deferred RETURNING Method Consolidation

## Scope

Consolidated the deferred trigger RETURNING savepoint and commit-barrier entry
points inside `SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan`.
The production class now exposes stable methods:

- `updateParentsWithinSavepoint()`
- `commitBarrierRetry()`

Private helpers and direct tests/examples were migrated to stable names without
numbered worker suffixes.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteTriggerDeferredReturningSavepointTest.php
php -l lanes/libsqlite/tests/SQLiteTriggerDeferredReturningCommitBarrierTest.php
php -l lanes/libsqlite/examples/application-trigger-deferred-returning-savepoint.php
php -l lanes/libsqlite/examples/application-trigger-deferred-returning-commit-barrier.php
No syntax errors detected in all 5 changed PHP files.
```

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerDeferredReturningSavepointTest.php lanes/libsqlite/tests/SQLiteTriggerDeferredReturningCommitBarrierTest.php
2 test files, 179 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-trigger-deferred-returning-savepoint.php --self-test
application-trigger-deferred-returning-savepoint self-test passed

php lanes/libsqlite/examples/application-trigger-deferred-returning-commit-barrier.php --self-test
application-trigger-deferred-returning-commit-barrier self-test passed
```

```text
git diff --check -- lanes/libsqlite
passed
```

## Dependency Closure

No new support component is needed. This is a consolidation-only change over
existing lane-local trigger/deferred-FK/RETURNING and savepoint behavior.

## Status Delta

The focused production numbered-method audit for this family is clean, and the
overall remaining numbered production method-line audit is now `8772`.
