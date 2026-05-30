## real-upstream-corpus-pragma-schema-dynamic-20260530T181312Z-0

Base accepted HEAD: `a9928e604a7d849ecf8aa28f83049e71a24f4b05`.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-6.*` schema-query pragma behavior: `table_info`, `table_xinfo`,
    `index_list`, `index_info`, `index_xinfo`, missing targets, quoted targets,
    and temp/main schema qualification.
  - `pragma-11.*` `collation_list` behavior.
  - table-valued pragma function behavior around `pragma_table_info`,
    `pragma_index_xinfo`, `pragma_function_list`, and `pragma_module_list`.

Patch:

- Added `SQLiteRealUpstreamPragmaSchemaDynamicBatchTest.php`.
- The test builds 125 distinct generic application `sqlite_schema` table
  families with generated columns, composite primary keys, unique indexes,
  partial indexes, expression indexes, strict tables, and WITHOUT ROWID tables.
- It verifies direct and table-valued schema pragma output through the existing
  `SQLitePragmaSchemaCatalog` model without adding production source surface.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicBatchTest.php`
  - `1 test files, 1508 assertions, 0 failures`
  - 1508 distinct focused TestRunner PASS cases.

Non-overlap:

- Does not repeat recently accepted `PRAGMA schema_version`/`data_version`,
  `PRAGMA rootpage` integrity, `index_xinfo`/foreign-key generated current-next
  shards, date/expr/window/VFS/WAL corpus batches, or source-neutral cleanup.
- Uses generic `app_settings_*` names only; no new domain-specific libsqlite
  API or source behavior.

Dependency closure:

- No new support component is needed. The batch reuses the existing native PHP
  `SQLitePragmaSchemaCatalog` and `SQLiteSchemaRecord` support.
