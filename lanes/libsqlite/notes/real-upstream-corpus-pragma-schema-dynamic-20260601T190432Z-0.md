# real-upstream-corpus-pragma-schema-dynamic-20260601T190432Z-0

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test`
  - `schema-8.11`
  - `schema-8.12`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema2.test`
  - `schema2-8.1`
  - `schema2-8.3`

## Behavior Ported

This slice fills the previously noted authorizer-clear gap for dynamic schema
expiry. The lane-local prepared statement expiry model now accepts
`clear_authorizer`, records `authorizer_cleared`, invalidates prepared
statements, returns the legacy `sqlite3_prepare()` `SQLITE_ERROR` then
`SQLITE_SCHEMA` finalize sequence, and lets `sqlite3_prepare_v2()` auto
reprepare to `SQLITE_ROW` then `SQLITE_OK`.

The focused test batch adds 1,002 real upstream behavior PASS cases:

- 500 legacy `schema.test` authorizer-clear variants.
- 500 prepare-v2 `schema2.test` authorizer-clear variants.
- 2 source/dependency citation cases.

## Non-Overlap

This does not repeat the accepted `set_authorizer` and `set_authorizer_deny`
coverage. The prior `20260601T042251Z` note explicitly left
`schema.test` `schema-8.11`/`schema-8.12` clear-authorizer behavior as a
separate bounded follow-up because the model did not yet expose an explicit
clear operation.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local
`SQLitePreparedStatementSchemaExpiry` model and adds the missing upstream
authorizer-clear operation.

## Verification

- `php -l lanes/libsqlite/src/SQLitePreparedStatementSchemaExpiry.php`
  - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicAuthorizerClear20260601Test.php`
  - no syntax errors
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicAuthorizerClear20260601Test.php`
  - 1 test file, 13,506 assertions, 0 failures
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicLegacyRuntime20260601Test.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicPreparedExpiryTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicActiveRuntimeTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchema2ActiveRuntimeBusyTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicAuthorizerClear20260601Test.php`
  - 5 test files, 60,430 assertions, 0 failures
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - 1 test file, 8 assertions, 0 failures
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - lane-status json ok
- `git diff --check -- lanes/libsqlite`
  - passed with no whitespace errors
