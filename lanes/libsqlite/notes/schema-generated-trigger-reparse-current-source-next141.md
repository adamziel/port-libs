# schema-generated-trigger-reparse-current-source-next141

Status: focused PHP behavior growth for current-source trigger DDL reparse through generated-column views.

Implementation:

- `SQLiteSchemaDdlReparsePlan::triggerReparseMetadata()` now propagates generated-column dependencies from a view used by a trigger body, even when the trigger SQL does not name those generated columns directly.
- The propagation is intentionally limited to generated columns. Generated-index references remain direct trigger/indexed-by evidence, preserving accepted next135 behavior.
- Added WordPress-oriented coverage for copied `wp_options` audit triggers that read generated-column compatibility views during import schema setup.

Focused verification:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaGeneratedTriggerReparseCurrentSourceNext141Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 59 assertions, 0 failures

$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaViewTriggerIndexReparseCurrentSourceNext135Test.php lanes/libsqlite/tests/SQLiteSchemaGeneratedTriggerReparseCurrentSourceTest.php lanes/libsqlite/tests/SQLiteSchemaViewTriggerGeneratedReparseCurrentSourceNext131Test.php lanes/libsqlite/tests/SQLiteSchemaGeneratedTriggerReparseCurrentSourceNext141Test.php
Focused test run: 4 selected test files (root lock skipped)
...
4 test files, 213 assertions, 0 failures
```

WordPress smoke:

```text
$ php lanes/libsqlite/examples/wordpress-schema-generated-trigger-reparse-current-source-next141.php --self-test
wordpress-schema-generated-trigger-reparse-current-source-next141 self-test passed
```

Dashboard delta:

- `phpPass`: `61676 -> 61735` from 59 newly passing focused PASS lines.
- `phpFail`: unchanged at `0`.
- `benchmarkDenominator.mapped`: unchanged at `606 / 1589`; this is current-source PHP behavior over existing schema DDL/trigger reparse coverage, not a fresh upstream inventory row.

Non-overlap:

This avoids accepted generated-trigger direct references, generated ADD COLUMN dependent reparse, view/trigger generated index reparse, schema ALTER rename reparse, attach/temp schema-cache invalidation, trigger RETURNING/FK/savepoint surfaces, and VFS/WAL/B-tree/JSON/SELECT clusters. The new behavior is specifically transitive generated-column dependency propagation from view current source into trigger DDL reparse metadata.

Dependency closure:

No new support component is needed. The slice reuses the lane-local schema DDL reparse planner, view metadata parser, generated-column catalog metadata, trigger body source parser, and PRAGMA schema catalog.
