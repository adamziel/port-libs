# yield-sqlite-application-import-fts-json-schema-current-next36

## Scope

Added bounded FTS5 schema import materialization for Application migration tooling:

- `SQLiteFts5SchemaImportPlan` now reports sqlite_schema-style virtual/shadow table records.
- External-content FTS5 imports now expose rebuild, delete-all, and `INSERT ... SELECT` SQL previews.
- The plan now exports a JSON metadata shape for Application import diagnostics.
- Added `contentless_delete` and `tokendata` option validation plus embedded-quote identifier parsing.

This avoids accepted JSON table cursor/source/constraint work, WAL/VFS/B-tree storage clusters, SELECT SQL text/subquery/group/order clusters, and the prior FTS5 schema-import current-next26 surface by focusing on schema-record and JSON metadata materialization.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteFts5SchemaImportPlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteFts5SchemaImportPlan.php

php -l lanes/libsqlite/tests/SQLiteFts5JsonSchemaCurrentNext36Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteFts5JsonSchemaCurrentNext36Test.php

php -l lanes/libsqlite/examples/application-fts5-json-schema-current-next36.php
No syntax errors detected in lanes/libsqlite/examples/application-fts5-json-schema-current-next36.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteFts5JsonSchemaCurrentNext36Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 77 assertions, 0 failures
71 PASS lines

php lanes/libsqlite/examples/application-fts5-json-schema-current-next36.php
PASS: emitted JSON metadata, schemaRecords, externalContentSql, and importActions for copied wp_posts FTS5 import.

git diff --check -- lanes/libsqlite
PASS
```

## Dependency Closure

No new support component is required. This reuses the existing lane-local FTS5 schema parser and bounded Application smoke path; no external SQLite extension, upstream binary, or shared support library is needed.
