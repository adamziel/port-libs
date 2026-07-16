# yield-sqlite-trigger-returning-foreignkey-savepoint-current-next54

Added `SQLiteTriggerReturningForeignKeySavepointPlan` for bounded trigger
RETURNING plus foreign-key savepoint previews. The slice composes the existing
trigger/FK RETURNING executor with a current savepoint frame so successful
cascades commit normally, deferred FK violations can remain commit-blocked, and
`rollback_on_deferred_violation` restores parent/child rows while preserving
attempted RETURNING rows, FK violation evidence, restored page images, dirty
page numbers, and discarded WAL frame diagnostics.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerReturningForeignKeySavepointCurrentNext54Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 63 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-trigger-returning-foreignkey-savepoint-current-next54.php --self-test
application-trigger-returning-foreignkey-savepoint-current-next54 self-test passed
```

PASS delta: +63 focused PASS lines. `lane-status.json` `phpPass` moves from
19277 to 19340. `benchmarkDenominator.mapped` is unchanged because this is a
new focused PHP behavior slice, not a new upstream inventory mapping.

Non-overlap: this avoids accepted trigger/view UPSERT RETURNING savepoints,
view-trigger RETURNING savepoints, deferred-FK trigger savepoints, VFS
savepoint rollback application, WAL byte truncation, rollback-journal commit,
JSON table, B-tree, SELECT SQL, and release-runner evidence clusters. The new
surface is the composition of trigger RETURNING images, FK violation evidence,
and current savepoint rollback admission for parent/child row arrays.

Dependency closure: no new support component is needed. The slice reuses the
existing bounded trigger/FK RETURNING executor and savepoint/WAL page-image
diagnostic conventions.
