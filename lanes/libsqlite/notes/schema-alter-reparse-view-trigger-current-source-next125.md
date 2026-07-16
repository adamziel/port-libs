# schema-alter-reparse-view-trigger-current-source-next125

Status: focused PHP behavior growth for current-source `ALTER TABLE ... RENAME TO ...` sqlite_schema reparse of dependent views and triggers.

Implemented:

- Extended `SQLiteSchemaDdlReparsePlan` so table rename reparsing scans all schema SQL that references the old table, not only rows whose `tbl_name` is the renamed table.
- Dependent view SQL is rewritten while preserving the view object's own `tbl_name`, string literals, comments, and unrelated schema rows.
- Dependent trigger SQL rewrites the trigger target plus body table references, updates the trigger `tbl_name`, and preserves string literals.
- The operation now reports `rewritten_records` and `dependent_reparse_count` for integration evidence.
- Added a Application smoke for a copied `wp_options` rename that reparses an autoloaded-options view and insert trigger before invalidating prepared statements.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaAlterReparseViewTriggerCurrentSourceNext125Test.php
Focused test run: 1 selected test files (root lock skipped)
49 PASS lines
1 test files, 49 assertions, 0 failures

$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaDdlReparseCurrentNext56Test.php lanes/libsqlite/tests/SQLiteAlterTableRenameTriggerViewCorpusTest.php lanes/libsqlite/tests/SQLiteSchemaAlterReparseViewTriggerCurrentSourceNext125Test.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 247 assertions, 0 failures

$ php lanes/libsqlite/examples/application-schema-alter-reparse-view-trigger-current-source-next125.php --self-test
application-schema-alter-reparse-view-trigger-current-source-next125 self-test passed
```

Dashboard delta:

- `phpPass`: `49426 -> 49475` from 49 newly passing focused PASS lines in the new lane-scoped test file.
- `benchmarkDenominator.mapped`: unchanged; this is current-source PHP behavior coverage and does not claim a fresh upstream Tcl inventory unit.

Non-overlap:

This avoids accepted attach/temp schema-cache invalidation, generated trigger/check reparse, ALTER rename token rewriting as a standalone helper, schema catalog DDL next56, PRAGMA schema catalog/fk/root, VFS/WAL/B-tree/JSON/SELECT clusters, and batch109-123 accepted surfaces. The new behavior is specifically current-source schema DDL reparse of dependent view/trigger sqlite_schema rows during `ALTER TABLE RENAME`.

Dependency closure:

No new support component is needed. The slice reuses `SQLiteSchemaRecord`, `SQLitePragmaSchemaCatalog`, `SQLiteSchemaDdlReparsePlan`, and the existing `SQLiteAlterTableRenamePlan` tokenizer.
