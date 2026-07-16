# SQLite schema catalog DDL current/next56

## Behavior

Adds `SQLiteSchemaCatalogDdlPlan`, a bounded current/next `sqlite_schema`
catalog planner for Application migration DDL batches. The slice covers CREATE,
DROP, and `ALTER TABLE ... RENAME TO` effects over schema records, preserving
root pages and rowids for renamed objects, allocating root pages for new table
and index records, rewriting dependent object `tbl_name` fields, and advancing
schema/data cookies only when DDL is applied.

## Evidence

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaCatalogDdlCurrentNext56Test.php
```

Focused growth: 66 PASS cases from the new test file.

Application smoke:

```sh
php lanes/libsqlite/examples/application-schema-catalog-ddl-current-next56.php
```

## Non-overlap

This avoids the accepted/queued ATTACH temp WAL schema-cache, PRAGMA integrity,
JSON planner/table cursor, VFS writer/lock/sync, WAL checkpoint/savepoint,
B-tree page move/root-collapse/overflow freelist, grouped SELECT, subquery,
and expression ORDER BY clusters. It is scoped to schema-catalog DDL
current/next record transitions for migration dumps.

## Dependency closure

No new support component is needed. The slice reuses existing
`SQLiteSchemaRecord` rows and native PHP DDL tokenization.
