# real-upstream-corpus-pragma-schema-dynamic-version-reload-20260531T041249Z

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T041249Z-0`
Base accepted HEAD: `6e668fbae83ee0543bff0a4aa8940cbc4e4fb4ca`

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-8.1.1` through `pragma-8.1.18`: schema_version assignment,
    defensive-mode ignore, attached schema isolation, and prepared-statement
    schema-cookie expiry.
  - `pragma-8.2.1` through `pragma-8.2.4.3`: user_version read/write remains
    independent while schema_version advances.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/altertab.test`
  - `22.0` and `22.1`: writable_schema edit followed by
    `PRAGMA schema_version=1234` forces a full schema reload before later
    `ALTER TABLE`.

## Behavior added

- Added `SQLitePragmaSchemaDataVersion::schemaVersionReloadPlan()` for the
  schema-cookie reload decision behind `PRAGMA schema_version=N`.
- Added a real upstream corpus test with 1,000 dynamic variants and 5,001
  distinct TestRunner PASS cases covering:
  - stale main prepared statements expire after schema_version assignment;
  - defensive mode preserves the old schema cookie and does not expire;
  - attached schema version changes expire only statements for that schema;
  - user_version writes are independent of schema_version;
  - writable-schema repair commits keep the assigned schema cookie for later
    ALTER TABLE work.

## Verification

- `php -l lanes/libsqlite/src/SQLitePragmaSchemaDataVersion.php`
  - PASS, no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicVersionReloadTest.php`
  - PASS, no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicVersionReloadTest.php`
  - PASS: `1 test files, 43004 assertions, 0 failures`.
  - PASS-line delta: `+5001`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaSchemaDataVersionCurrentNext25Test.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicVersionReloadTest.php`
  - PASS: `2 test files, 43068 assertions, 0 failures`.

## Non-overlap

This slice does not add table_info/table_xinfo/index_xinfo/table_list dynamic
coverage already present in earlier PRAGMA schema corpus files. It targets the
schema_version/user_version/prepared-statement reload edge from upstream
`pragma.test` and `altertab.test`.

## Dependency closure

No new support component is needed. The slice reuses the existing bounded
`SQLitePragmaSchemaDataVersion` state model and extends it with a schema-cookie
reload plan.
