# libsqlite schema-generated-index-view-reparse-current-source-next132

## Behavior

Adds current-source schema reparse metadata for `CREATE VIEW` statements after a generated column and generated-column index already exist. The DDL reparse plan now validates view source tables, validates `INDEXED BY` indexes, reports generated column references, reports generated-index references, marks star-projection views as current-source reparse records, and keeps stale prepared statement invalidation tied to the final schema cookie.

## Application relevance

Copied `wp_options` export/import previews often create diagnostic views over generated columns such as `lower(option_name)` and force planner use with `INDEXED BY`. This slice records when those views depend on current generated-index schema state so stale prepared statements do not resume after a schema-cookie change.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteSchemaDdlReparsePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteSchemaGeneratedIndexViewReparseCurrentSourceNext132Test.php`
- `php -l lanes/libsqlite/examples/application-schema-generated-index-view-reparse-current-source-next132.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaGeneratedIndexViewReparseCurrentSourceNext132Test.php`
  - `1 test files, 62 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-schema-generated-index-view-reparse-current-source-next132.php --self-test`
  - `application-schema-generated-index-view-reparse-current-source-next132 self-test passed`

## Non-overlap

Avoids accepted batch130 generated-index DDL creation/reparse behavior by working only on later `CREATE VIEW` reparsing against already-present generated columns and indexes. It does not repeat generated-column add, generated-index creation, JSON table constraints, SELECT source/cursor behavior, VFS/WAL/B-tree accepted clusters, or suite ledger/provenance work.

## Dependency closure

No new support component is needed. The slice reuses native `sqlite_schema` DDL reparse, PRAGMA catalog introspection, generated-column metadata, and index-term parsing.
