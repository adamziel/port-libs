# trigger-recursive-view-returning-current-source-next213

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger `RETURNING` rows at the current/next source boundary.

This slice adds `SQLiteTriggerRecursiveViewReturningCurrentSourceNext213Plan`, an additive current-source payload seal after accepted next212 yield receipts. The next-source view/trigger `RETURNING` stream is published only when the already-drained current-source payload seals match the actual current rows in order. Missing, unexpected, or out-of-order seals keep attempted next-source rows held while current-source rows remain visible.

Application path: copied `wp_options` imports through a recursive view trigger can now prove that the current autoload option `RETURNING` payload was sealed before a plugin migration exposes the next view/trigger source.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext213Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 78 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next213.php
application-trigger-recursive-view-returning-current-source-next213 self-test passed
```

Expected dashboard movement: `phpPass +78` from the focused test file (`103870 -> 103948`). `benchmarkDenominator.mapped` remains `623 / 1589`; this is additional current-source PHP behavior over already mapped trigger/view/RETURNING surfaces, not a new hydrated upstream inventory row.

Non-overlap: avoids accepted trigger recursive view RETURNING next172 through next212 surfaces by adding payload seals after next212 yield receipts, not another checkpoint, drain, cursor-close, rollback-reset, or yield-receipt gate. It also avoids row-value RETURNING savepoints, DML RETURNING conflicts, deferred FK triggers, schema reparse, WAL/VFS, JSON table, planner, encoding, and B-tree clusters.

Dependency closure: no new support component is needed. The slice reuses existing native PHP recursive view trigger `RETURNING`, current-source row materialization, and current/next admission metadata.

Next task: wire the payload seal into the eventual parser-level trigger bytecode path when recursive view trigger execution owns current-source publication directly.
