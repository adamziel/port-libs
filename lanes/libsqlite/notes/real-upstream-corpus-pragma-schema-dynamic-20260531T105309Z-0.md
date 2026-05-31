# Real Upstream Corpus PRAGMA Schema Dynamic

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T105309Z-0`

Base accepted HEAD: `229ee6ac6ba54ebcac89b65db02638641eecef2d`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma5.test`
- Setup guard: `SELECT count(*) FROM pragma_function_list` must compile and read the introspection virtual table.
- `pragma5-1.1`: `SELECT DISTINCT name, builtin FROM pragma_function_list WHERE name='upper' AND builtin`.
- `pragma5-1.2`: `SELECT DISTINCT name, builtin FROM pragma_function_list WHERE name LIKE 'exter%'`.
- `pragma5-2.1`: `SELECT * FROM pragma_module_list WHERE name='fts5'`.
- `pragma5-3.1`: `SELECT * FROM pragma_pragma_list WHERE name='pragma_list'`.

Patch content:

- Added `SQLitePragmaSchemaCatalog::virtualPragmaTables()` and `SQLitePragmaSchemaCatalog::executeVirtualTableSelect()` so catalog-backed introspection PRAGMA rowsets can flow through the native `SQLiteSelectSql` executor.
- Added `SQLiteRealUpstreamPragmaSchemaDynamicVirtualSelectTest.php`.
- Focused PHP coverage: 1,251 distinct TestRunner PASS cases and 5,506 behavior assertions.
- Expected countable movement: `phpPass +1251`; mapped coverage stays `1589 / 1589` because this is PASS-line growth over already mapped upstream `pragma5.test`.

Non-overlap:

- This extends the accepted `pragma5.test` direct rowset and table-valued PRAGMA coverage into the actual upstream SELECT-source form with count, DISTINCT, WHERE truthiness, LIKE filtering, and row projection.
- It does not repeat existing `SQLiteRealUpstreamPragmaSchemaDynamicIntrospectionTest.php`, `SQLiteRealUpstreamPragmaSchemaDynamicIntrospectionBatchTest.php`, or `SQLiteRealUpstreamPragmaSchemaDynamicListTableValuedTest.php`, which exercise direct PRAGMA/table-valued rowsets rather than SELECT execution over the virtual tables.
- It adds no WordPress-specific APIs, examples, classes, methods, or source names.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLitePragmaSchemaCatalog.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicVirtualSelectTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicVirtualSelectTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicVirtualSelectTest.php`
  - `1 test files, 5506 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicVirtualSelectTest.php lanes/libsqlite/tests/SQLitePragmaSchemaCatalogTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicIntrospectionTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicListTableValuedTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `5 test files, 31845 assertions, 0 failures`

Dependency closure:

- No new dependency or support-library component is needed. The slice reuses the existing native PHP PRAGMA schema catalog and SELECT executor.

Root harness:

- Not run; isolated micro-slice only.
