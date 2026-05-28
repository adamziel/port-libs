# schema-generated-view-index-reparse-current-source-next138

Status: focused PHP behavior growth for current-source schema DDL reparse of generated-index metadata through nested views.

This slice extends `SQLiteSchemaDdlReparsePlan` so `CREATE VIEW ... AS SELECT ... FROM existing_view` resolves the source as a view rather than a missing table. Generated-column and generated-index dependencies from the referenced view now propagate into the new view metadata, and triggers that read the nested view inherit those generated dependencies for current-source reparse decisions.

WordPress path: `wordpress-schema-generated-view-index-reparse-current-source-next138.php` models copied `wp_options` export views layered on a generated-column index view, then a plugin import trigger reading the nested view before stale prepared statements resume.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaGeneratedViewIndexReparseCurrentSourceNext138Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 56 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/wordpress-schema-generated-view-index-reparse-current-source-next138.php --self-test
wordpress-schema-generated-view-index-reparse-current-source-next138 self-test passed
```

Dashboard delta: `phpPass` increases by the verified focused PASS-line delta, `+56`. Mapped upstream coverage is unchanged because this is additional current-source PHP behavior over an already mapped schema DDL reparse surface, not a newly hydrated upstream Tcl inventory row.

Non-overlap: avoids accepted schema generated-trigger reparse, schema view/trigger/index reparse next135 direct view/index handling, generated column index reparse next134, generated index view reparse next132, attach/temp trigger and view invalidation, JSON table source/constraint work, SELECT SQL executor clusters, VFS/WAL/B-tree clusters, and encoding GLOB/UTF-16 slices. The new surface is nested view-source propagation for generated-column/generated-index current-source reparse decisions.

Dependency closure: no new support component is needed. The slice reuses lane-local `sqlite_schema` DDL reparse, PRAGMA schema catalog, generated-column metadata, trigger parsing, view dependency metadata, and index-term parsing.

Next task: carry this nested view-source metadata into any broader prepared-statement invalidation path that compares current and next view dependency graphs beyond schema-cookie equality.
