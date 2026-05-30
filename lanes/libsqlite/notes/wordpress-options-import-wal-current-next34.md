# Application options import WAL current/next34

## Behavior

- Adds `SQLiteOptionRowsWalImportPlan::currentNext()` for copied `wp_options` import rows.
- The planner normalizes current/imported options, preserves existing `option_id` values for updates, assigns the next option id to inserted rows, serializes option rows plus an autoload index page into one committed WAL transaction, and reports pinned current-reader versus next-reader page visibility.
- The Application smoke is `examples/application-options-import-wal-current-next.php`.

## Focused evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteOptionRowsImportWalCurrentNext34Test.php
Focused test run: 1 selected test files (root lock skipped)
54 PASS lines
1 test files, 63 assertions, 0 failures
```

Changed PHP lint:

```text
$ php -l lanes/libsqlite/src/SQLiteOptionRowsWalImportPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteOptionRowsWalImportPlan.php

$ php -l lanes/libsqlite/tests/SQLiteOptionRowsImportWalCurrentNext34Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteOptionRowsImportWalCurrentNext34Test.php

$ php -l lanes/libsqlite/examples/application-options-import-wal-current-next.php
No syntax errors detected in lanes/libsqlite/examples/application-options-import-wal-current-next.php
```

## Non-overlap

This does not repeat accepted raw WAL append, reader/writer snapshot, WAL checkpoint, savepoint byte truncation, rollback-journal commit/apply, VFS file writer/sync/lock, or JSON/SELECT/B-tree clusters. It adds a Application import staging layer that turns option rows into a committed WAL transaction and proves current/next visibility across the copied `wp_options` pages.

## Dependency closure

No new external support component is required. The slice reuses the existing native PHP WAL append, reader snapshot, and VFS file writer primitives.
