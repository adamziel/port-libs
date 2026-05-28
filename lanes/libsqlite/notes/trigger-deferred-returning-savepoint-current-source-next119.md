# Trigger Deferred RETURNING Savepoint Current Source Next119

## Scope

This slice adds `SQLiteTriggerDeferredReturningSavepointCurrentSourceNext119Plan`
to model the upstream SQLite behavior needed by WordPress import/copy paths
that run `UPDATE ... RETURNING` under recursive triggers, deferred foreign
keys, and a current-source `ROLLBACK TO` savepoint.

The behavior is intentionally disjoint from the accepted next114 recursive
trigger/deferred-FK/RETURNING cluster: next119 wraps that statement behavior in
savepoint semantics, preserving yielded RETURNING evidence while restoring
parent/child row images and clearing deferred FK violations after rollback.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerDeferredReturningSavepointCurrentSourceNext119Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 51 assertions, 0 failures
```

Syntax checks:

```text
php -l lanes/libsqlite/src/SQLiteTriggerDeferredReturningSavepointCurrentSourceNext119Plan.php
php -l lanes/libsqlite/tests/SQLiteTriggerDeferredReturningSavepointCurrentSourceNext119Test.php
php -l lanes/libsqlite/examples/wordpress-trigger-deferred-returning-savepoint-current-source-next119.php
```

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-trigger-deferred-returning-savepoint-current-source-next119.php --self-test
wordpress-trigger-deferred-returning-savepoint-current-source-next119 self-test passed
```

## Dependency Closure

No new support component is needed. The slice reuses the accepted native PHP
recursive trigger/deferred-FK/RETURNING planner and adds a bounded savepoint
current-source wrapper under `lanes/libsqlite/src`.

## Next

Follow-up work should wire this savepoint result into broader statement/VFS
transaction application if a later lane needs physical file-handle persistence.
