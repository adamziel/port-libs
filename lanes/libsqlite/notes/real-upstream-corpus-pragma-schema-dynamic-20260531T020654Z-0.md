# real-upstream-corpus-pragma-schema-dynamic-20260531T020654Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T020654Z-0`

Base accepted HEAD: `140040354d7e1605b310a7ab46633d1e6e437f9b`

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema3.test`
  - `schema3-1.1..1.22`: cached schema refresh after CREATE TABLE, CREATE VIEW, CREATE INDEX, CREATE TRIGGER, DROP, and `ALTER TABLE ADD COLUMN`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema4.test`
  - `schema4-1.1..1.8`: triggers may share names with tables, views, indexes, and virtual tables; dropping same-name non-trigger objects must preserve triggers.
  - `schema4-2.1..2.11`: table renames preserve same-name triggers and rewrite trigger target SQL only when the trigger target table is renamed.

## Changes

- Added `SQLiteRealUpstreamPragmaSchemaDynamicSchema4NamesTest.php` with 1,101 distinct TestRunner PASS cases and 9,024 behavior assertions.
- The corpus uses generic `schema3_*` and `schema4_*` application table names.
- No production source changes were needed; existing schema DDL reparse and PRAGMA catalog primitives already satisfy the upstream behavior.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchema4NamesTest.php`
  - `1 test files, 9024 assertions, 0 failures`
  - 1,101 PASS lines.

## Non-overlap

This does not repeat earlier `pragma.test`, `pragma3.test`, `pragma4.test`, `pragma5.test`, `schema.test`, `schema2.test`, `schema5.test`, or `schema6.test` PRAGMA/schema corpus batches. The new surface is specifically upstream `schema3.test` multiclient schema refresh and `schema4.test` same-name trigger/table/view/index preservation during drops and renames.

## Dependency closure

No new support component is needed. The slice reuses lane-local `SQLiteSchemaDdlReparsePlan`, `SQLitePragmaSchemaCatalog`, and `SQLiteSchemaRecord`.
