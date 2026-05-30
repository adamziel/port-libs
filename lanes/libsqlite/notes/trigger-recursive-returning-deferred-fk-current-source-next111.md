# Trigger Recursive RETURNING Deferred FK Current Source Next111

This slice adds `SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan`
for the current-source boundary where a top-level `UPDATE ... RETURNING`
fires recursive triggers and a deferred foreign-key check is evaluated at the
savepoint/commit boundary.

The behavior intentionally keeps top-level `RETURNING` rows separate from
recursive trigger side effects, then suppresses the visible next-source
RETURNING stream when the deferred FK violation rolls back to the savepoint
image. This avoids duplicating accepted DML trigger RETURNING conflict handling
and accepted savepoint-trigger rollback surfaces.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNext111Test.php
php -l lanes/libsqlite/examples/application-trigger-recursive-returning-deferred-fk-current-source-next111.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveReturningDeferredFkCurrentSourceNext111Test.php
php lanes/libsqlite/examples/application-trigger-recursive-returning-deferred-fk-current-source-next111.php
git diff --check -- lanes/libsqlite
```

Dependency closure: no new support component is needed; the slice reuses the
existing lane-local trigger/FK/savepoint modeling style and adds a bounded
current/next planner for this recursive trigger boundary.
