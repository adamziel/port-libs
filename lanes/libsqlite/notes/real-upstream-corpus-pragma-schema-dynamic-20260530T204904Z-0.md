# real-upstream-corpus-pragma-schema-dynamic-20260530T204904Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T204904Z-0`

Base accepted HEAD: `1f31dce1639d568089f4d00f0a45319dbd949c4c`

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema3.test`
  - `schema3-1.*.1` through `schema3-1.*.22`
  - Real upstream multiclient schema-cache invalidation cases for CREATE TABLE, ALTER TABLE ADD COLUMN, CREATE INDEX, CREATE TRIGGER, CREATE VIEW, DROP/CREATE replacement, and dependent view/trigger refresh behavior.

## Changes

- Added `SQLiteRealUpstreamSchema3MulticlientDynamicCorpusTest.php`.
- Ports the 22 upstream `schema3.test` multiclient schema-cache modification scenarios over 46 generic dynamic table-name variants.
- Coverage uses existing libsqlite schema DDL/current-source primitives:
  - `SQLiteAttachedSchemaCatalog::applySchemaDdlCurrentSource()`
  - `SQLiteSchemaDdlReparsePlan`
  - `PRAGMA table_info`
  - `PRAGMA index_list`
  - schema-cache resolution invalidation snapshots
- Adds 1,012 focused TestRunner PASS cases and 5,382 behavior assertions.

## Verification

```sh
php -l lanes/libsqlite/tests/SQLiteRealUpstreamSchema3MulticlientDynamicCorpusTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSchema3MulticlientDynamicCorpusTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php
git diff --check -- lanes/libsqlite
```

Focused result:

```text
1 test files, 5382 assertions, 0 failures
```

## Non-Overlap

This follow-up avoids the earlier `real-upstream-corpus-pragma-schema-dynamic-20260530T183207Z-0` coverage, which already covered `schema.test`, `schema2.test`, PRAGMA catalog/table-valued behavior, schema rollback-expired prepared statements, schema4 object DDL, and PRAGMA right-join/table-info behavior.

This slice specifically owns upstream `schema3.test` multiclient cache-refresh scenarios `schema3-1.*.1` through `schema3-1.*.22`, using generic `schema3_dyn_*` object names only.

## Dependency Closure

No new support component is needed. The slice reuses the existing lane-local schema catalog, schema DDL reparse, attached schema cache, and PRAGMA catalog primitives.
