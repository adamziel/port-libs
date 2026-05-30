# trigger-upsert-savepoint-returning-current-source-next132

## Behavior

Adds bounded current-source evidence for UPSERT statements inside a savepoint when triggers and `RETURNING` interact:

- `RETURNING` rows are yielded from the statement row image before AFTER-trigger target mutations.
- AFTER-trigger mutations are visible in the next statement image.
- A later trigger `RAISE(ROLLBACK)` rolls back to the active savepoint, discards yielded `RETURNING` rows from the next-source stream, and restores the pre-statement rows.

This is intentionally separate from accepted trigger/deferred-FK RETURNING savepoint work and row-value UPDATE/DELETE RETURNING conflict work.

## Verification

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerUpsertSavepointReturningCurrentSourceNext132Test.php
```

Result:

```text
1 test files, 55 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-trigger-upsert-savepoint-returning-current-source-next132.php --self-test
```

## Dependency Closure

No new support component is needed. The slice composes native PHP UPSERT conflict routing, trigger row images, `RETURNING` projection, and savepoint rollback evidence.
