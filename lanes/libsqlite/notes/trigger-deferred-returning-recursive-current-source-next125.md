# Trigger Deferred RETURNING Recursive Current Source Next125

This slice adds `SQLiteTriggerDeferredReturningRecursiveCurrentSourceNextPlan`
as a bounded source-barrier planner over the accepted recursive trigger
RETURNING/deferred-FK executor. It records which RETURNING rows are yielded from
the current source, which recursive trigger rows would enter the next source,
and whether the deferred FK barrier admits or suppresses that next-source stream.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerDeferredReturningRecursiveCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteTriggerDeferredReturningRecursiveCurrentSourceNext125Test.php
php -l lanes/libsqlite/examples/application-trigger-deferred-returning-recursive-current-source-next125.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerDeferredReturningRecursiveCurrentSourceNext125Test.php
php lanes/libsqlite/examples/application-trigger-deferred-returning-recursive-current-source-next125.php --self-test
git diff --check -- lanes/libsqlite
```

Focused test delta: 59 new PASS assertions in one lane-scoped test file.

Non-overlap: this does not repeat next121 recursive deferred RETURNING rollback
semantics or next122 savepoint RETURNING recursive rollback. The new surface is
the current-source/next-source yield barrier metadata used by deferred FK checks
to admit, block, or suppress recursive trigger RETURNING rows.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP trigger/RETURNING/deferred-FK executor and adds source
barrier accounting only.
