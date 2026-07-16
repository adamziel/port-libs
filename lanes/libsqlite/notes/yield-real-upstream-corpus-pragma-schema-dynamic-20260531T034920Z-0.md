# real-upstream-corpus-pragma-schema-dynamic-20260531T034920Z-0

- Base accepted HEAD: `1d87a6fc2cf9c016da25d4e727af365cff780442`.
- Added `SQLiteRealUpstreamPragmaSchemaDynamicJoinCorpusTest.php` with `1001` focused TestRunner PASS cases and `10003` behavior assertions.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test` section `6.0`: dynamic row-source join across `pragma_table_list()`, `pragma_foreign_key_list(t.name,t.schema)`, and `pragma_table_info(f."table",t.schema)`.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test` sections `7.1` through `7.3`: materialized `pragma_table_info()` rowsets and RIGHT JOIN matching of pragma row sources.
- Non-overlap: this extends the pragma/schema dynamic corpus with table-valued PRAGMA row-source joins; it does not repeat prior table-info/default, shadowing, schema-version, schema3 stale-cache, star-schema, pragmafault, table-valued attached lookup, or page-count/schema-state coverage.
- Dependency closure: no new support component is needed; this reuses existing `SQLiteAttachedSchemaCatalog`, `SQLitePragmaSchemaCatalog`, and schema-record parsing behavior.
- Focused verification:
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicJoinCorpusTest.php` -> no syntax errors.
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicJoinCorpusTest.php` -> `1 test files, 10003 assertions, 0 failures`.
