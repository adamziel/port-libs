# Trigger Savepoint RETURNING View Current Source Next146

This slice adds bounded current-source behavior for `INSTEAD OF` view-trigger
imports with `RETURNING` across a savepoint boundary. It covers the Application
copy/import pattern where a current-source view import may be rolled back to
the savepoint, suppressing the attempted current RETURNING stream, before the
next-source rows run from the restored base table image.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerSavepointReturningViewCurrentSourceNext146Test.php`
- `php lanes/libsqlite/examples/application-trigger-savepoint-returning-view-current-source-next146.php`

Non-overlap:

- Does not repeat accepted next142 UPSERT `DO NOTHING RETURNING` conflict
  suppression.
- Does not repeat deferred-FK view barrier next127 or prior view trigger
  name-resolution/cache invalidation slices.
- Does not touch accepted WAL, B-tree, VFS, JSON table, encoding, or planner
  clusters.

Dependency closure:

- No new support component is needed. The slice reuses existing lane-local
  row-array trigger/savepoint/RETURNING primitives and adds only bounded PHP
  behavior plus a Application smoke.
