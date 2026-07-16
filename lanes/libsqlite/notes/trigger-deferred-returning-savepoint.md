# Trigger Deferred RETURNING Savepoint

## Scope

This slice adds `SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan`
to model the upstream SQLite behavior needed by Application import/copy paths
that run `UPDATE ... RETURNING` under recursive triggers, deferred foreign
keys, and a current-source `ROLLBACK TO` savepoint.

The behavior is intentionally disjoint from the accepted recursive
trigger/deferred-FK/RETURNING cluster: deferred savepoint wraps that statement behavior in
savepoint semantics, preserving yielded RETURNING evidence while restoring
parent/child row images and clearing deferred FK violations after rollback.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerDeferredReturningSavepointTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 51 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/libsqlite/src/SQLiteTriggerDeferredReturningSavepointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteTriggerDeferredReturningSavepointTest.php
php -l lanes/libsqlite/examples/application-trigger-deferred-returning-savepoint.php
```

Application smoke:

```text
php lanes/libsqlite/examples/application-trigger-deferred-returning-savepoint.php --self-test
application-trigger-deferred-returning-savepoint self-test passed
```

## Dependency Closure

No new support component is needed. The slice reuses the accepted native PHP
recursive trigger/deferred-FK/RETURNING planner and adds a bounded savepoint
current-source wrapper under `lanes/libsqlite/src`.

## Next

Follow-up work should wire this savepoint result into broader statement/VFS
transaction application if a later lane needs physical file-handle persistence.
