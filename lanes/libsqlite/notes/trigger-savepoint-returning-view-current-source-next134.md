# trigger-savepoint-returning-view-current-source-next134

Status: focused PHP behavior growth for `INSTEAD OF` view-trigger `RETURNING` rows yielded inside a savepoint when a trigger rolls the current source back before the next view source can be admitted.

This slice adds `SQLiteTriggerSavepointReturningViewCurrentSourceNext134Plan`. It models current/next view source tokens for a Application `wp_options` import view, yields `RETURNING` rows from the current view trigger before savepoint rollback, restores the pre-savepoint row image after an AFTER trigger raises rollback, suppresses the next-source `RETURNING` stream, and still records attempted next-source rows for reprepare/debug evidence. The release path admits the next view source and its generated `source` column.

Application path: `application-trigger-savepoint-returning-view-current-source-next134.php` covers a copied `wp_options` import view where a plugin `INSTEAD OF` trigger exposes `RETURNING` rows but an AFTER guard rolls the savepoint back before the next generated-column view source is visible.

Focused verification:

```sh
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerSavepointReturningViewCurrentSourceNext134Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 79 assertions, 0 failures
```

Dashboard delta: update `phpPass` by the focused PASS-line/assertion delta verified for this new test file (`+79`, from `56681` to `56760`). `benchmarkDenominator.mapped` is unchanged; this is additional current-source PHP behavior over already mapped trigger/savepoint/view/RETURNING surfaces, not a newly hydrated upstream Tcl inventory unit.

Dependency closure: no new support component is needed. The slice reuses lane-local row-array trigger, view, savepoint, and RETURNING planning primitives.

Non-overlap: this avoids accepted next131 deferred view RETURNING FK rollback, next132 trigger UPSERT savepoint RETURNING, next131 generated view/trigger DDL reparse, accepted savepoint page-image rollback, VFS savepoint rollback application, WAL byte truncation, and trigger/FK recursive RETURNING clusters. The new behavior is specifically savepoint rollback admission of current vs next view sources after a view trigger has already yielded `RETURNING` rows.

Next task: wire this current/next view-source admission boundary into the parser-level trigger executor once native view trigger bytecode owns savepoint rollback directly.
