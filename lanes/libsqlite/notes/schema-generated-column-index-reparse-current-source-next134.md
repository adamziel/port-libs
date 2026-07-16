# schema-generated-column-index-reparse-current-source-next134

Status: focused PHP behavior growth for current-source generated-column index
reparse diagnostics after `ALTER TABLE ... ADD COLUMN`.

This slice extends `SQLiteSchemaDdlReparsePlan` so an ADD COLUMN operation also
classifies dependent index records on the changed table:

- `index_reparse_records` lists table indexes that must be reparsed against the
  next table definition.
- `generated_column_index_records` lists index terms that bind to generated
  columns after the current-source reparse.
- `expression_index_reparse_records` and `partial_index_reparse_records`
  identify expression and partial index reparses separately.
- `index_generated_column_references` records generated-column references per
  index name.

Application path:
`application-schema-generated-column-index-reparse-current-source-next134.php`
models a copied `wp_options` import that admits `option_name_lc` and must
reparse legacy/generated index records before prepared indexed statements are
reused.

Focused verification:

```sh
$ php -l lanes/libsqlite/src/SQLiteSchemaDdlReparsePlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSchemaDdlReparsePlan.php

$ php -l lanes/libsqlite/tests/SQLiteSchemaGeneratedColumnIndexReparseCurrentSourceNext134Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteSchemaGeneratedColumnIndexReparseCurrentSourceNext134Test.php

$ php -l lanes/libsqlite/examples/application-schema-generated-column-index-reparse-current-source-next134.php
No syntax errors detected in lanes/libsqlite/examples/application-schema-generated-column-index-reparse-current-source-next134.php

$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaGeneratedColumnIndexReparseCurrentSourceNext134Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 40 assertions, 0 failures
```

Dashboard delta: `phpPass` moves from `56681` to `56721` for the 40 verified
PASS lines. `benchmarkDenominator.mapped` remains unchanged at `606 / 1589`;
this is current-source PHP behavior over the existing schema DDL reparse
surface, not a newly hydrated upstream inventory row.

Non-overlap: avoids accepted schema view/trigger generated reparse next131,
generated-index create reparse next130, generated trigger/check reparse
surfaces, attach/temp schema-cache invalidation, VFS/WAL/B-tree/JSON/SELECT
clusters, and PRAGMA catalog/root/FK work. The new behavior is specifically
index-record classification during the ADD COLUMN current-source reparse.

Dependency closure: no new support component is needed. The slice reuses the
lane-local schema DDL reparse planner, PRAGMA schema catalog, generated-column
metadata, and index-term parsing primitives.
