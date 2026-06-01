# real-upstream-corpus-pragma-schema-dynamic-20260601T101144Z-0

Accepted base: `c6749612dc0422457ced2be6c92f03cc5e7fb148`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
- `pragma4-4.3.2`: `pragma_index_info('i1')` as a SELECT source.
- `pragma4-4.4.1`: `pragma_index_list('t1')` as a SELECT source.
- `pragma4-6.0`: `pragma_table_list()` as the schema rowset source.
- `pragma4-7.1` through `pragma4-7.3`: direct `pragma_table_info()` rowsets and a RIGHT JOIN over those rowsets.

Implemented behavior:

- `SQLitePragmaSchemaCatalog::executeVirtualTableSelect()` now materializes static schema PRAGMA table-valued function calls before dispatching to `SQLiteSelectSql`.
- `SQLiteAttachedSchemaCatalog::executeVirtualTableSelect()` uses the same materializer with attached-schema resolution, so static schema arguments such as `pragma_table_info('t','aux')` and `pragma_table_list('t','aux')` resolve through the attached catalog.
- Supported SELECT-source functions in this slice are `pragma_table_info`, `pragma_table_xinfo`, `pragma_index_list`, `pragma_index_info`, `pragma_index_xinfo`, `pragma_foreign_key_list`, and `pragma_table_list` when their arguments are static SQL literals or identifiers accepted by the existing table-valued PRAGMA parser.

Focused coverage added:

- `lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaTableValuedSelectSourcesTest.php`
- 250 variants x 4 real behavior cases plus one source-citation case = `+1001` focused TestRunner PASS cases.
- Focused assertions: `3505`.

Verification:

- Red-first check before source implementation:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaTableValuedSelectSourcesTest.php`
  failed with `1000 failures` because `SQLiteAttachedSchemaCatalog::executeVirtualTableSelect()` was missing.
- After implementation:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaTableValuedSelectSourcesTest.php`
  passed with `1 test files, 3505 assertions, 0 failures`.
- Neighbor checks passed:
  `SQLiteRealUpstreamPragmaSchemaDynamicVirtualSelectTest.php` passed with `5506 assertions`.
  `SQLiteRealUpstreamPragmaSchemaDynamicJoinCorpusTest.php` passed with `10003 assertions`.
  `SQLitePragmaSchemaCatalogTest.php` passed with `72 assertions`.

Non-overlap:

- This slice does not duplicate the earlier pragma5 list/introspection virtual table SELECT batch, which already covered `pragma_function_list`, `pragma_module_list`, and `pragma_pragma_list`.
- This slice does not duplicate the earlier pragma4 dynamic join corpus batch, which manually materialized table-valued PRAGMA rowsets. The new behavior is parser-level SELECT-source materialization for static table-valued PRAGMA function calls.
- Correlated dynamic table-valued arguments such as `pragma_foreign_key_list(t.name,t.schema)` remain a separate executor gap and were not claimed here.

Dependency closure:

- No new support component is needed. The patch reuses existing `SQLitePragmaSchemaCatalog`, `SQLiteAttachedSchemaCatalog`, and `SQLiteSelectSql` behavior.
