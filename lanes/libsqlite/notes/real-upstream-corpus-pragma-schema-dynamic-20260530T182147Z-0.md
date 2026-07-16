# real-upstream-corpus-pragma-schema-dynamic-20260530T182147Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T182147Z-0`
Base accepted HEAD: `f9e4e2d5498742752e9304fb10cad66aa60851fc`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test`
- Covered upstream scenario families:
  - `schema-1.*`: CREATE/DROP TABLE invalidates prepared schema statements.
  - `schema-2.*`: CREATE/DROP VIEW invalidates prepared schema statements.
  - `schema-3.*`: CREATE/DROP TRIGGER invalidates prepared schema statements.
  - `schema-4.*`: CREATE/DROP INDEX invalidates prepared schema statements.
  - `schema-5.*`: ATTACH leaves the active statement runnable, DETACH expires attached schema lookups.
  - `schema-12.1`: rollback/DDL schema-cookie reuse still leaves the prepared statement stale.

## Change

- Added `SQLiteRealUpstreamPragmaSchemaDynamicSchemaInvalidationTest.php`.
- The file contributes 1,500 distinct focused TestRunner PASS cases over 250 dynamic schema variants.
- Each variant checks six behavior paths: create table, drop table, view/trigger/index DDL, attach, detach, and rollback-cookie reuse.
- The tests exercise `SQLiteAttachedSchemaCatalog` schema-cache resolution snapshots, invalidation reports, direct schema PRAGMAs, table-valued PRAGMAs, attached schema resolution, and temp schema shadowing.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchemaInvalidationTest.php`
  - `1 test files, 12000 assertions, 0 failures`
  - 1,500 selected PASS lines.

## Non-Overlap

This is not another `pragma.test` table-info/index-info/default-value batch and does not repeat `SQLiteRealUpstreamPragmaSchemaDynamicTest.php`, `SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php`, `SQLiteRealUpstreamPragmaSchemaDynamicFollowupTest.php`, or `SQLiteRealUpstreamPragmaSchemaDynamicPragma4Test.php`.
Those existing files cover PRAGMA row shape, schema shadowing, generated columns, defaults, table-valued PRAGMA forms, and data/user/schema-version behavior. This slice adds upstream `schema.test` prepared-statement invalidation behavior for schema DDL, ATTACH/DETACH, and rollback-cookie reuse.

## Dependency Closure

No new support component is needed. The batch reuses existing lane-local schema catalog, schema record, PRAGMA catalog, table-valued PRAGMA, and schema-cache invalidation helpers.
