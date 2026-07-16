# real-upstream-corpus-pragma-schema-dynamic-20260530T200205Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T200205Z-0`

Base accepted HEAD: `688b5b5b02ee30d2a82f4468b5b909f17254ae0e`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema4.test`
  - `schema4-1.1` through `schema4-1.6`: triggers sharing names with a table, view, or index survive dropping the non-trigger object.
  - `schema4-2.1` through `schema4-2.11`: table renames rewrite the renamed table and dependent indexes/triggers while preserving same-name triggers and independent temp-style objects.

## Coverage Added

- Added `SQLiteRealUpstreamPragmaSchema4DynamicTest.php`.
- Focused PASS cases: `1000`.
- Focused behavior assertions: `7000`.
- The cases use generic `app_schema4_*` schema objects and exercise the existing lane-local `SQLiteSchemaDdlReparsePlan` and `SQLitePragmaSchemaCatalog` behavior.

## Non-Overlap

This ports upstream `schema4.test` same-name trigger/object and table-rename behavior. It does not repeat prior `pragma.test` table-info/index/FK coverage, `pragma3` data-version coverage, `pragma4` table-valued PRAGMA batches, `schema.test`/`schema2.test` stale prepared-statement invalidation, `schema3.test` attach/detach cache refresh, or metadata-only runner admission rows.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchema4DynamicTest.php`
  - `1 test files, 7000 assertions, 0 failures`

## Dependency Closure

No new support component is needed. This reuses the existing schema DDL reparse, schema-record catalog, and PRAGMA schema catalog primitives.
