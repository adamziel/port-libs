# Application JSON Import Insert WAL Current Next50

Status: focused PHP behavior growth for released Application JSON import rows committed into WAL current/next visibility.

- Adds `SQLiteJsonImportWalSavepointPlan::insertWalCurrentNext()`, which reuses the accepted JSON import savepoint planner and `SQLiteOptionRowsWalImportPlan` to turn only released changed rows into committed WAL append frames.
- Covers inserted and updated `wp_options` rows, malformed later-batch rollback, no-change WAL elision, JSONB/JSON subtype value serialization, current-reader versus next-reader page visibility, and custom option/autoload page placement.
- Adds `application-json-import-insert-wal-current-next50.php` as the Application smoke for copied JSON import payloads that produce WAL-visible inserts while a malformed batch rolls back.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonImportInsertWalCurrentNext50Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 59 assertions, 0 failures
```

Non-overlap:

This slice avoids accepted JSON import savepoint insert current-next48, JSON import WAL savepoint current-next35, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, WAL checkpoint transactions, JSON table cursor/source/constraint work, SELECT SQL text/subquery/grouping/expression-order clusters, B-tree page move/root-collapse/overflow freelist work, VFS lock/sync/file-writer clusters, and Unicode GLOB behavior. The new surface is the bridge from released JSON import rows to committed WAL current/next append visibility for Application option inserts and updates.

Dependency closure:

No new support component is needed. The slice reuses native PHP JSON extraction/JSONB helpers, the JSON import savepoint planner, the Application options WAL import planner, and existing WAL append/read visibility primitives.

Next:

- Wire the generated WAL append plan into broader pager transaction application if a later slice needs durable file-handle writes for these imported JSON batches.
- Add upstream Tcl evidence only if a hydrated JSON/savepoint/WAL import subset is selected for this exact current/next behavior.
