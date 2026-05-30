# PRAGMA Database/Table List Current Next28

This isolated libsqlite slice adds bounded native PHP coverage for SQLite
table-valued PRAGMA inventory rows that were not part of accepted table/index
info rows or batch24 runtime function/module/collation metadata:

- `pragma_database_list()` over main, temp, and attached schema files.
- `pragma_table_list()` over temp/main/attached schema search order.
- Filtered `pragma_table_list(name)` and schema-pinned
  `pragma_table_list(name, schema)`.
- Direct `PRAGMA table_list`, schema-qualified `PRAGMA schema.table_list`, and
  target-filter forms.
- `SQLitePragmaRowCursor` current/next iteration over both rowsets.

The table-list rows expose bounded SQLite-shaped `schema`, `name`, `type`,
`ncol`, `wr`, and `strict` metadata for Application import preflights, including
generated-column counts, WITHOUT ROWID flags, STRICT flags, views, quoted table
names, duplicate `wp_options` tables across temp/main/attached schemas, and
empty filtered rowsets.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaDatabaseTableListCurrentNext28Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 77 assertions, 0 failures
```

New focused PASS-line delta: 61.

Additional checks:

```sh
php -l lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php
php -l lanes/libsqlite/src/SQLiteAttachedSchemaCatalog.php
php -l lanes/libsqlite/tests/SQLitePragmaDatabaseTableListCurrentNext28Test.php
php -l lanes/libsqlite/examples/application-pragma-database-table-list-current-next28.php
php lanes/libsqlite/examples/application-pragma-database-table-list-current-next28.php
git diff --check -- lanes/libsqlite
```

Non-overlap: this avoids accepted `pragma_table_info`,
`pragma_table_xinfo`, `pragma_index_list`, `pragma_index_info`,
`pragma_index_xinfo`, `pragma_foreign_key_list`, direct schema current-source
PRAGMAs, batch24 runtime `function_list`/`module_list`/`collation_list`,
JSON table cursor/source/constraint work, SELECT SQL grouped/derived/subquery
clusters, B-tree page move/root-collapse/overflow release clusters, WAL
checkpoint/savepoint clusters, VFS writer/lock/sync clusters, and Unicode GLOB
work.

Dependency closure: no new support component is needed. The slice reuses
lane-local attached-schema catalog, schema PRAGMA catalog, and row-cursor
primitives; it does not require ext/sqlite, hydrated upstream caches, or live
service credentials.
