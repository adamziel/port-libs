## Real Upstream PRAGMA Schema Dynamic Quoted Schema

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T012957Z-0`

Base accepted HEAD: `a890092c734c05eb72a795bdc37321c497f93beb`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- Covered sections: `pragma-6.1` through `pragma-6.8`, focused on schema-query PRAGMAs and `pragma-6.6` temp/main shadowing.

Implementation delta:

- `SQLitePragmaSchemaCatalog::parsePragma()` now accepts quoted schema identifiers for schema-qualified PRAGMA statements, matching the target identifier forms it already accepted.
- `SQLiteAttachedSchemaCatalog::executeSchemaPragma()` quotes schema names when delegating schema-qualified `table_list` so attached schema names containing dots remain valid.

Focused behavior:

- `SQLiteRealUpstreamCorpusPragmaSchemaDynamicQuotedSchemaTest.php`
- 1,000 dynamic cases covering quoted `temp`, `main`, and attached schemas with dotted names across `table_info`, `index_info`, `index_xinfo`, `foreign_key_list`, and `table_list`.
- 1 source-citation/dependency case.

Verification:

- `php -l lanes/libsqlite/src/SQLiteAttachedSchemaCatalog.php`
- `php -l lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicQuotedSchemaTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicQuotedSchemaTest.php`
  - Result: `1 test files, 20004 assertions, 0 failures`
  - PASS lines: `1001`

Dependency closure:

- No new support component needed. This reuses existing schema catalog, attached schema catalog, and PRAGMA row behavior.

Non-overlap:

- Does not duplicate accepted PRAGMA rollback reparse, generated xinfo, schema5/schema6, runtime matrix, data_version, journal-state, table-valued list, or shadowing batches. The new behavior is specifically quoted schema identifier parsing and dotted attached schema delegation for existing schema-query PRAGMAs.
