# Trigger Recursive View RETURNING Current Source Next207

Status: focused PHP behavior growth for recursive `INSTEAD OF` view-trigger
`RETURNING` batches where the current-source rows must be drained before the
next view/trigger source can publish rows.

- Added `SQLiteTriggerRecursiveViewReturningCurrentSourceNext207Plan`.
- The plan layers a current RETURNING drain fence after the accepted next206
  yield watermark, using deterministic 32-hex drain keys over current
  watermark/batch-key/ordinal/name state.
- Current-source rows remain visible while missing, unexpected, reordered,
  stale-token, stale-count, or base-held drain evidence suppresses attempted
  next-source rows.
- Application smoke:
  `php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next207.php`
  returned `application-trigger-recursive-view-returning-current-source-next207 self-test passed`.

Verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext207Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 97 assertions, 0 failures
```

Syntax/example checks:

```text
$ php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext207Plan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNext207Plan.php
$ php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext207Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext207Test.php
$ php -l lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next207.php
No syntax errors detected in lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-source-next207.php
```

Expected dashboard movement: `phpPass +97` from the new focused test file.
`benchmarkDenominator.mapped` remains unchanged; this is current-source PHP
behavior over the already mapped trigger/view/RETURNING surface.

Non-overlap: this adds a current RETURNING drain-admission fence after next206
yield watermark behavior. It avoids accepted next206 watermark, next205
sequence, next203 generation handoff, next196 child drain, DML RETURNING
conflicts, row-value RETURNING savepoints, schema reparse, WAL/VFS, JSON table,
planner, B-tree, PRAGMA, compound SELECT, encoding, and suite evidence
clusters.

Dependency closure: no new support component is needed. The slice reuses the
lane-local native recursive view RETURNING current-source row/watermark
modeling and adds bounded drain-key admission metadata.
