# real-upstream-corpus-pragma-schema-dynamic-20260531T024115Z-0

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260531T024115Z-0`

Base accepted HEAD: `47e43ea345c857243140b52082e7a664319c5aa0`

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- `pragma-8.3.1`: `PRAGMA application_id` reads initial value `0`.
- `pragma-8.3.2`: `PRAGMA Application_ID(12345); PRAGMA application_id;` returns the assigned header value.
- Adjacent source context: `pragma-8.1.*` and `pragma-8.2.*` require schema/user version state to remain independent across schema-qualified PRAGMA writes and transaction rollback.

## Behavior added

- `SQLitePragmaRuntimeState` now models `application_id` alongside `schema_version` and `user_version`.
- Attached schemas start with `application_id = 0`.
- Schema-qualified `PRAGMA aux.application_id = N` is isolated from `main`.
- Runtime transaction rollback restores application ID state together with the rest of the PRAGMA runtime state.
- Constructor positional compatibility is preserved; the new seed value is available as named argument `applicationId`.

## Focused coverage

- Added `SQLiteRealUpstreamPragmaSchemaDynamicApplicationIdTest.php`.
- Focused PASS cases: `1002` total.
- Dynamic generated behavior cases: `1000` (`250` variants x `4` behavior rows).
- Focused assertions: `3504`.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaRuntimeState.php && php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicApplicationIdTest.php`
  - PASS: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicApplicationIdTest.php`
  - PASS: `1 test files, 3504 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaRuntimeDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicRuntimeMatrixTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicVersionStateTest.php`
  - PASS: `3 test files, 23523 assertions, 0 failures`.

## Non-overlap

This does not repeat accepted PRAGMA schema metadata/table-valued PRAGMA, schema3 invalidation, schema_version/user_version dynamic runtime, cache_spill, data_version, shadowing, pragma5/pragma6, corrupt view, or source-neutral cleanup batches. It owns the previously unmodeled upstream `pragma.test` `pragma-8.3` `application_id` runtime/header behavior.

## Dependency closure

No new support component is needed. This reuses the existing lane-local PRAGMA runtime state model and hydrated SQLite upstream checkout as source truth.
