# yield-sqlite-trigger-recursive-view-returning-current-next37

Status: focused PHP corpus growth for recursive `INSTEAD OF` view-trigger rows with statement `RETURNING` images.

This slice adds `SQLiteRecursiveViewReturningPlan`, a bounded Application-shaped executor for copied `wp_options` active-view imports. It feeds view rows through the existing recursive savepoint UPSERT behavior, then returns only top-level statement rows from the view image while recursive trigger rows mutate the base table and child metadata.

Focused behavior:

- update conflicts through a view preserve statement RETURNING values from the current view row;
- inserted view rows can recursively enqueue base-table rows while `recursive_triggers` is enabled;
- `current.*`, `view.*`, `excluded.*`, and `yield.*` RETURNING projections expose committed base rows, view statement rows, incoming base rows, and top-level yield metadata;
- recursive trigger rollback restores the current savepoint and clears statement RETURNING output;
- disabling recursive triggers keeps only the top-level view write;
- malformed view rows and malformed RETURNING projections fail before status admission.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRecursiveViewReturningCurrentNext37Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 58 assertions, 0 failures

php lanes/libsqlite/examples/application-trigger-recursive-view-returning-current-next37.php
```

Dashboard delta:

- `phpPass`: `12903 -> 12961` from 58 newly passing focused assertions in `SQLiteRecursiveViewReturningCurrentNext37Test.php`.
- `benchmarkDenominator.mapped`: unchanged; this is a focused native behavior slice, not a new upstream inventory unit.

Non-overlap:

This avoids accepted recursive savepoint UPSERT, standalone trigger/FK yield, UPDATE/DELETE RETURNING, UPSERT RETURNING, attach/temp view-trigger yield, JSON table/source/cursor/constraint clusters, SELECT SQL text/JOIN/GROUP/subquery/ORDER/LIMIT clusters, VFS writer/sync/lock/rollback clusters, WAL checkpoint/savepoint byte truncation, B-tree page move/root-collapse/overflow release, Unicode GLOB, and batch23 metadata/planner/VDBE work. The new surface is their uncovered intersection: recursive `INSTEAD OF` view-trigger execution with top-level statement `RETURNING` over current/next row images.

Dependency closure:

No new support component is needed. The slice reuses existing lane-local recursive trigger/savepoint semantics and adds bounded view RETURNING composition in native PHP.
