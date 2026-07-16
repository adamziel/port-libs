# schema-view-trigger-generated-reparse-current-source-next131

Status: focused PHP behavior growth for current-source `ALTER TABLE ... ADD COLUMN` generated-column reparse of dependent views and triggers.

Implementation:

- `SQLiteSchemaDdlReparsePlan` now reports `dependent_reparse_count`, `star_expansion_records`, `generated_column_view_records`, `resolved_trigger_records`, `unresolved_trigger_records`, and `trigger_missing_references` for `ALTER TABLE ... ADD COLUMN`.
- The report is computed after the table SQL is rewritten, so triggers that reference the newly admitted generated column resolve against the next current source.
- SELECT-star views that read the changed table are explicitly reported for expansion reparse, while unrelated views/triggers remain outside the dependent set.
- Added a Application smoke for a copied `wp_options` import that adds `option_name_lc`, invalidates stale prepared statements, and reparses a SELECT-star autoload view plus an audit trigger.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaViewTriggerGeneratedReparseCurrentSourceNext131Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 46 assertions, 0 failures
```

Application smoke:

```text
$ php lanes/libsqlite/examples/application-schema-view-trigger-generated-reparse-current-source-next131.php --self-test
application-schema-view-trigger-generated-reparse-current-source-next131 self-test passed
```

Dashboard delta:

- `phpPass`: `55029` -> `55075` (+46 verified focused PASS assertions).
- `phpFail`: unchanged at `0`.
- `benchmarkDenominator.mapped`: unchanged at `606 / 1589`; this is current-source PHP behavior over the existing schema DDL reparse surface, not a newly hydrated upstream inventory row.

Non-overlap:

This avoids accepted batch130 ALTER TABLE generated-index reparse, next126 generated ADD COLUMN check validation, next125 table-rename view/trigger SQL rewrite, attach/temp schema-cache invalidation, trigger/FK/RETURNING surfaces, and VFS/WAL/B-tree/JSON/SELECT clusters. The new behavior is specifically dependent view/trigger current-source reparse diagnostics after generated-column `ADD COLUMN`.

Dependency closure:

No new support component is needed. The slice reuses the lane-local schema DDL reparse planner, PRAGMA schema catalog, and view/trigger name-resolution primitives.
