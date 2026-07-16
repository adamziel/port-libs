# SQLite schema rename-column trigger/view current-source next110

## Scope

- Added current-source `ALTER TABLE ... RENAME COLUMN ... TO ...` handling to `SQLiteSchemaDdlReparsePlan`.
- The DDL reparse now validates the current table column list, rewrites the table, dependent indexes, views, and triggers from the current `sqlite_schema` records, preserves unrelated schema records, bumps the schema cookie, and exposes a refreshed `PRAGMA table_xinfo` sample.
- Reused the accepted `SQLiteAlterTableRenamePlan::renameColumnSql()` tokenizer rather than adding a second schema-SQL rewriter.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaRenameColumnTriggerViewCurrentSourceNext110Test.php
Focused test run: 1 selected test files (root lock skipped)
44 PASS lines
1 test files, 69 assertions, 0 failures
```

## Application Smoke

```text
php lanes/libsqlite/examples/application-schema-rename-column-trigger-view-current-source-next110.php
```

The smoke previews a copied `wp_options` rename-column migration where dependent trigger/view/index SQL is rewritten from current schema rows, stale prepared statements are invalidated, and an unrelated `wp_postmeta` view remains unchanged.

## Non-Overlap

This does not repeat accepted batch106 generated-trigger reparse or older standalone rename-token tests. The new behavior is parser-level schema DDL application for `ALTER TABLE wp_options RENAME COLUMN option_name TO option_key` over current `sqlite_schema` records, with focused trigger/view/index dependency rewrites and stale-source invalidation.

## Dependency Closure

No new support component is needed. The slice reuses existing schema records, pragma catalog parsing, and rename SQL tokenization.
