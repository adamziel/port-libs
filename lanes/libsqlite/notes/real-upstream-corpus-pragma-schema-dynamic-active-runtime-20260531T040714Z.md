# Real upstream PRAGMA/schema active-runtime corpus

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T040714Z-0`
Base accepted HEAD: `86b40e76030ee95766e1bca45c19abb4f5a3c27f`

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test`
- `schema-10.1` through `schema-10.5`: open btree cursor does not make `CREATE TABLE` fail or corrupt the schema table.
- `schema-11.1` through `schema-11.8`: active legacy statements make user-function/collation deletion or replacement return `SQLITE_BUSY`.
- `schema-12.1`: rollback expires prepared DDL even if the schema cookie value is reused, yielding `SQLITE_SCHEMA`.
- `schema-13.1` through `schema-13.3`: an authorizer denying schema reads returns `SQLITE_AUTH` for step/finalize.

## Implementation

- Extended `SQLitePreparedStatementSchemaExpiry` with active statement tracking, legacy-prepare runtime-object busy guards, rollback-cookie terminal `SQLITE_SCHEMA`, and deny-authorizer terminal `SQLITE_AUTH`.
- Added `SQLiteRealUpstreamPragmaSchemaDynamicActiveRuntimeTest.php` with 400 variants over five upstream behavior families, using generic tenant/settings names only.

## Evidence

- New focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicActiveRuntimeTest.php`
  - `1 test files, 18406 assertions, 0 failures`
  - `2001` focused PASS lines.
- Related guard: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicPreparedExpiryTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicActiveRuntimeTest.php`
  - `2 test files, 30163 assertions, 0 failures`
  - `3001` focused PASS lines.

## Non-overlap

This slice does not cover accepted PRAGMA/schema join-corpus, table-info defaults, schema2 prepared-v2 ordinary expiry, schema3/4/5/6 namespace behavior, page-count, data-version, cache-spill, table-valued pragma, or PRAGMA runtime-list batches. It ports the remaining active legacy runtime-object, rollback-cookie, and deny-authorizer behavior from `schema.test`.

## Dependency closure

No new support component is needed. The slice reuses the lane-local prepared statement schema-expiry model and extends it with bounded upstream `schema.test` behavior.
