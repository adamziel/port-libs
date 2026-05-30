# real-upstream-corpus-pragma-schema-dynamic-20260530T230034Z-0

Base accepted HEAD: `ee0f86482fec002ad61b846f39a1a36b0fe0ecc4`.

Added `SQLiteRealUpstreamPragmaSchemaDynamicJoinMatrixTest.php`, a real upstream PRAGMA/schema dynamic corpus batch sourced from hydrated SQLite upstream `test/pragma4.test`:

- `pragma4.test` 6.0: `pragma_table_list()` joined with `pragma_foreign_key_list(t.name, t.schema)` and `pragma_table_info(f."table", t.schema)` locates the referenced parent primary-key column.
- `pragma4.test` 7.1 through 7.3: `pragma_table_info()` rows are materializable and direct virtual-table rowsets participate in RIGHT JOIN-by-column-name semantics.

Focused coverage:

- 250 variants for table-list/foreign-key/table-info parent primary-key lookup.
- 250 variants for `pragma_table_list()` row metadata.
- 250 variants for materialized `pragma_table_info()` RIGHT JOIN behavior.
- 250 variants for direct table-valued `pragma_table_info()` RIGHT JOIN behavior.
- 1 source-citation test.

Expected movement: `+1001` focused TestRunner PASS cases, all generic application table names. No new support component is needed; the batch reuses existing `SQLitePragmaSchemaCatalog` and `SQLiteSchemaRecord` behavior.
