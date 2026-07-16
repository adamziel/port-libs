# Real Upstream Corpus: PRAGMA Schema Dynamic

Slice: `real-upstream-corpus-pragma-schema-dynamic-20260530T183207Z-0`

Accepted base: `2b09fd94bbc734a3a9855d41884522c7a5a06914`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test`
  - `schema-1.*` create/drop table schema invalidation
  - `schema-2.*` create/drop view schema invalidation
  - `schema-3.*` create/drop trigger schema invalidation
  - `schema-4.*` create/drop index schema invalidation
  - `schema-12.1` rollback-expired statement behavior when the schema cookie value is reused
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema2.test`
  - mirrored stale-schema behavior for create/drop table, view, trigger, index, function/collation/authorizer style schema invalidation

Behavior ported:

- `SQLiteSchemaDdlReparsePlan` now invalidates prepared statements explicitly marked as expired by rollback, even when their stored schema cookie equals the post-DDL cookie. This ports the `schema-12.1` edge where rollback can restore an old cookie value and a later DDL can reuse the same cookie, but the earlier prepared statement must still fail with schema expiration semantics.
- Added a focused dynamic corpus over generic `app_settings_N`, `app_audit_N`, view, trigger, and index objects for upstream schema invalidation behavior.

Focused coverage:

- `SQLiteRealUpstreamPragmaSchemaInvalidationDynamicTest.php`
  - 250 dynamic create-table invalidation cases
  - 250 dynamic drop-table dependent cleanup/invalidation cases
  - 250 dynamic view/index/trigger invalidation cases
  - 250 dynamic rollback-expired same-cookie cases
- Focused PASS cases: 1000
- Behavior assertions: 6000

Verification:

- Red-first: before the source change, the rollback-expired same-cookie cases failed because `invalidated_prepared` was empty.
- `php -l lanes/libsqlite/src/SQLiteSchemaDdlReparsePlan.php` -> no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaInvalidationDynamicTest.php` -> no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaInvalidationDynamicTest.php` -> `1 test files, 6000 assertions, 0 failures`

Expected dashboard movement:

- `phpPass`: `298721 -> 299721` (`+1000` focused PASS lines)
- `mapped coverage`: unchanged at `1189 / 1589`; this is behavior-backed PHP PASS-line growth, not a denominator admission change.

Dependency closure:

- No new support component is needed. This reuses lane-local schema DDL reparse, schema-record catalog, and schema/data-version transaction primitives.

Non-overlap:

- This does not repeat prior `pragma.test` table-info, `pragma3` data-version, `pragma4` table-valued PRAGMA, or `pragma5` function/module list coverage.
- This does not touch source-neutral cleanup, VFS/pager evidence, runner-map denominator rows, or domain-specific API surfaces.
