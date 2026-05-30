# Real Upstream PRAGMA Schema Dynamic Shadowing

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T231812Z-0`

Base: `97bde16e3221376c9c3d6c7f9b2330b164322c56`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  - table-valued PRAGMA functions with schema-qualified and unqualified target resolution.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma5.test`
  - `pragma_table_list()` schema, view, column-count, `WITHOUT ROWID`, and `STRICT` metadata.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test`
  - attached-schema invalidation and existing cursor stability after schema changes.

Patch summary:

- Added `SQLiteRealUpstreamPragmaSchemaDynamicShadowingTest.php`.
- The test creates 250 generic application variants with temp, main, and attached `archive` schemas.
- Each variant verifies unqualified PRAGMA lookup resolves temp before main/archive, schema-qualified table-valued PRAGMAs stay pinned to the requested schema, `table_list` preserves flags across tables/views, and `DETACH` invalidates only archive-owned cached lookups while an existing PRAGMA cursor keeps its rowset.
- Adds 1001 distinct TestRunner PASS cases and 8504 focused assertions.

Non-overlap:

- Existing accepted PRAGMA/schema batches cover direct row shapes, data-version behavior, broad fifth-thousand attached metadata, and pragma4 join matrix behavior.
- This slice focuses on schema shadowing and detach invalidation over table-valued PRAGMA resolution. It does not add generated fake suite rows, runner metadata, or domain-specific API names.

Dependency closure:

- No new support component is needed. The slice reuses the existing bounded `SQLiteAttachedSchemaCatalog`, `SQLitePragmaSchemaCatalog`, `SQLitePragmaRowCursor`, and `SQLiteSchemaRecord` components.

Focused evidence:

- Initial red run found only a database-list ordering expectation mismatch after 750 PASS lines; corrected to SQLite connection order `main,temp`.
- Final focused run: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicShadowingTest.php`
  - `1 test files, 8504 assertions, 0 failures`
  - `1001` PASS lines

Root harness:

- Not run - isolated micro-slice.
