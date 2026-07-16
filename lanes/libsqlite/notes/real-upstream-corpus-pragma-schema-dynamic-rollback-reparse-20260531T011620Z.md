# real-upstream-corpus-pragma-schema-dynamic-rollback-reparse-20260531T011620Z

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T011620Z-0`

Accepted base: `2541019b82319811accbb79790d214be59d31028`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test`
  - `schema-5.*`: ATTACH/DETACH invalidates schema lookup and database-list state.
  - `schema-9.*`: another connection's schema change forces statement reparse.
  - `schema-12.1`: DDL prepared before rollback must expire even if a later DDL reuses the schema-cookie value.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-6.*`: schema-query PRAGMAs expose table/index/FK metadata from current sqlite_schema text.
  - `pragma-8.1.*`: `schema_version` changes for main and attached databases force schema reload.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  - `pragma-4.*` through `pragma-7.*`: table-valued PRAGMAs behave as ordinary rowsets and stable cursors while a later reparse sees new rows.

## Local Coverage

Added `SQLiteRealUpstreamPragmaSchemaDynamicRollbackReparseTest.php` with 1002 distinct TestRunner PASS cases and 9257 focused assertions:

- 250 variants for rollback-expired DDL with reused schema-cookie values.
- 250 variants for attached-schema reparse invalidation scoped to the changed schema.
- 250 variants for table-valued PRAGMA cursor stability across schema reparse.
- 250 variants for temp/main/attached schema shadowing and DETACH invalidation.
- 2 source/API guard cases citing exact upstream sections and checking generic class names.

This is non-overlapping with accepted PRAGMA journal-state, schema6 equivalence, pragma6 integrity, visible schema catalog, and table-valued virtual-row batches because it focuses on rollback/reparse/cursor-stability behavior over dynamic attached schemas.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicRollbackReparseTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicRollbackReparseTest.php`
  - `1 test files, 9257 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses existing bounded native PHP schema-catalog, table-valued PRAGMA cursor, and schema DDL reparse helpers.
