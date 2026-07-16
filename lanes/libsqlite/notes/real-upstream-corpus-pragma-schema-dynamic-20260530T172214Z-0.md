# real-upstream-corpus-pragma-schema-dynamic-20260530T172214Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
- Ported scenarios: pragma4 4.1.2-4.1.6, 4.2.2-4.2.6, 4.3.2-4.3.6, 4.4.1-4.4.6, 4.5.1-4.5.5, 4.6.1-4.6.5, 5.0, 6.0-6.2, and 7.1-7.3.

Behavior added:

- Added focused PHP corpus coverage for direct and table-valued schema PRAGMAs over generic main/attached catalogs.
- Covered dynamic schema replacement after dropped tables/indexes, default token preservation through comments, attached schema lookup, foreign-key-list joins with table_info, corrupt-view-tolerant table_list, and table-info join parity.
- Added cursor rewind coverage for table-valued PRAGMA row cursors using real `pragma_table_info()` rowsets.
- Focused local result: `1 test files / 534 assertions / 0 failures / 103 PASS lines`.

Non-overlap:

- This slice does not add suite metadata rows, generated fake `.test` IDs, domain-specific APIs, or new domain-specific smokes.
- It avoids accepted PRAGMA integrity/index_xinfo/foreign_key_check/rootpage clusters and focuses on upstream `pragma4.test` schema-query/table-valued PRAGMA behavior.

Dependency closure:

- No new support component is needed. The test reuses `SQLiteAttachedSchemaCatalog`, `SQLitePragmaSchemaCatalog`, `SQLiteSchemaRecord`, and `SQLitePragmaRowCursor`.
