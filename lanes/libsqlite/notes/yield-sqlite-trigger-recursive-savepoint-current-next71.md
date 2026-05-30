# SQLite trigger recursive savepoint current-next71

## Behavior

This slice adds `SQLiteRecursiveTriggerSavepointCurrentNext71Plan`, a bounded
current/next orchestration for recursive `AFTER INSERT` trigger chains inside a
named savepoint.

The current statement can recurse into a unique conflict with `OR ROLLBACK`.
The plan records the attempted recursive yields, rolls back to the current
savepoint image, suppresses committed current `RETURNING` rows, and then starts
the next statement from the restored savepoint rows. The next statement must not
inherit discarded current rows or rowid state from the rolled-back recursive
chain.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveSavepointCurrentNext71Test.php
Focused test run: 1 selected test files (root lock skipped)
49 PASS lines
1 test files, 49 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/application-trigger-recursive-savepoint-current-next71.php
```

The smoke reports a copied Application option import where `plugin_seed` recurses
into a rollback conflict against `preflight_marker`; the retry statement starts
from the restored savepoint and inserts only the `plugin_retry` recursive chain.

## Non-overlap

This does not repeat accepted trigger RETURNING savepoint current-next64/65/68
or recursive RETURNING current-next50 coverage. Those slices cover attempted
RETURNING rows and nested savepoint release/rollback. This slice covers the
current/next retry boundary after a recursive trigger rollback to the same
savepoint, including discarded current rows, restored next base rows, and next
rowid behavior.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
recursive trigger conflict and savepoint current-rollback helpers.
