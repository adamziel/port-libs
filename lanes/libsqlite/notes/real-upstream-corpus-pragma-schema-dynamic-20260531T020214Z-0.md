# real-upstream-corpus-pragma-schema-dynamic-20260531T020214Z-0

Base accepted HEAD: `e1f1e0a66bff0730bf5e4118bd715c8a11c33354`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema3.test`
  - `schema3-1.1` through `schema3-1.6`: schema refresh after created tables and indexes.
  - `schema3-1.7` through `schema3-1.13`: schema refresh after `ALTER TABLE ADD COLUMN`.
  - `schema3-1.14` through `schema3-1.18`: schema refresh after index, trigger, and view creation.
  - `schema3-1.19` through `schema3-1.22`: schema refresh after drop/recreate table, index, and trigger operations.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-6.2` and `pragma-6.5`: refreshed schema SQL is visible through PRAGMA metadata.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  - `pragma-4.*` through `pragma-7.*`: table-valued PRAGMA functions expose the same refreshed metadata.

## Added Coverage

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicNinthThousandTest.php`.
- New focused PASS cases: `1001`.
- New behavior assertions: `9504`.
- Expected selected `phpPass` movement if accepted: `1603992 -> 1604993`.
- Mapped denominator movement: none; mapped coverage remains `1589 / 1589`.

## Non-Overlap

This slice extends the accepted PRAGMA/schema dynamic family after the recent eighth-thousand and shadowing batches. It focuses on upstream `schema3.test` multi-client schema refresh operations paired with PRAGMA metadata visibility, not the accepted PRAGMA shadowing, data_version, corrupt-view, schema6, rollback-cookie, or eighth-thousand clusters.

## Dependency Closure

No new support component is needed. The slice reuses existing bounded libsqlite components: `SQLiteAttachedSchemaCatalog`, `SQLiteSchemaDdlReparsePlan`, `SQLitePragmaSchemaCatalog`, `SQLiteSchemaRecord`, and `SQLitePragmaRowCursor`.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicNinthThousandTest.php`
  - Result: `1 test files, 9504 assertions, 0 failures`.
- Root harness: not run - isolated micro-slice.
