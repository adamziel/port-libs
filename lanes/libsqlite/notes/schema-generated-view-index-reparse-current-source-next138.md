# schema-generated-view-index-reparse-current-source-next138

Status: root-gate blocker reduction for current-source schema DDL reparse of generated-index metadata through nested views and triggers.

This slice fixes `SQLiteSchemaDdlReparsePlan` so triggers that read a view also
inherit the source view's generated-index dependencies, matching the existing
generated-column propagation path. This clears the current-source next138
blocker where nested `wp_options` export views carried
`wp_options_generated_lookup`, but the plugin import trigger that read the
nested view dropped that generated-index dependency before current-source
reparse admission.

Application path: `application-schema-generated-view-index-reparse-current-source-next138.php` models copied `wp_options` export views layered on a generated-column index view, then a plugin import trigger reading the nested view before stale prepared statements resume.

Focused evidence:

```text
Before fix:
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaGeneratedViewIndexReparseCurrentSourceNext138Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 56 assertions, 2 failures

After fix:
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaGeneratedViewIndexReparseCurrentSourceNext138Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 56 assertions, 0 failures

Affected schema reparse family:
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaAlterGeneratedCheckReparseCurrentSourceNext126Test.php lanes/libsqlite/tests/SQLiteSchemaAlterGeneratedTriggerViewCurrentSourceTest.php lanes/libsqlite/tests/SQLiteSchemaAlterReparseViewTriggerCurrentSourceNext125Test.php lanes/libsqlite/tests/SQLiteSchemaAlterTableGeneratedIndexReparseCurrentSourceNext130Test.php lanes/libsqlite/tests/SQLiteSchemaAlterTriggerGeneratedCurrentSourceTest.php lanes/libsqlite/tests/SQLiteSchemaAlterViewTriggerGeneratedCurrentSourceNext128Test.php lanes/libsqlite/tests/SQLiteSchemaDdlReparseCurrentNext56Test.php lanes/libsqlite/tests/SQLiteSchemaDdlReparseCurrentNext70Test.php lanes/libsqlite/tests/SQLiteSchemaGeneratedCheckReparseCurrentSourceTest.php lanes/libsqlite/tests/SQLiteSchemaGeneratedColumnIndexReparseCurrentSourceNext134Test.php lanes/libsqlite/tests/SQLiteSchemaGeneratedIndexViewReparseCurrentSourceNext132Test.php lanes/libsqlite/tests/SQLiteSchemaGeneratedTriggerReparseCurrentSourceNext141Test.php lanes/libsqlite/tests/SQLiteSchemaGeneratedTriggerReparseCurrentSourceTest.php lanes/libsqlite/tests/SQLiteSchemaGeneratedViewIndexReparseCurrentSourceNext138Test.php lanes/libsqlite/tests/SQLiteSchemaRenameColumnTriggerViewCurrentSourceNext110Test.php lanes/libsqlite/tests/SQLiteSchemaTriggerViewCurrentNext73Test.php lanes/libsqlite/tests/SQLiteSchemaViewTriggerGeneratedReparseCurrentSourceNext131Test.php lanes/libsqlite/tests/SQLiteSchemaViewTriggerIndexReparseCurrentSourceNext135Test.php
18 test files, 1057 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/application-schema-generated-view-index-reparse-current-source-next138.php --self-test
application-schema-generated-view-index-reparse-current-source-next138 self-test passed
```

Dashboard delta: this is blocker reduction over existing focused tests, so
`phpPass` and mapped upstream coverage are unchanged. The direct next138 test
moves from 2 failures to 0 failures on the current base.

Non-overlap: avoids accepted schema generated-trigger reparse, schema view/trigger/index reparse next135 direct view/index handling, generated column index reparse next134, generated index view reparse next132, attach/temp trigger and view invalidation, JSON table source/constraint work, SELECT SQL executor clusters, VFS/WAL/B-tree clusters, and encoding GLOB/UTF-16 slices. The new surface is nested view-source propagation for generated-column/generated-index current-source reparse decisions.

Dependency closure: no new support component is needed. The slice reuses lane-local `sqlite_schema` DDL reparse, PRAGMA schema catalog, generated-column metadata, trigger parsing, view dependency metadata, and index-term parsing.

Next task: carry this nested view-source metadata into any broader prepared-statement invalidation path that compares current and next view dependency graphs beyond schema-cookie equality.
