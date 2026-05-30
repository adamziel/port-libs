# real-upstream-corpus-pragma-schema-dynamic-20260530T172721Z-0

- Base accepted HEAD: `3c71f3e7ae505629a27d91487b87ceab9ac9eac4`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test`
    - `schema-1.*` CREATE/DROP TABLE prepared-statement invalidation
    - `schema-2.*` CREATE/DROP VIEW prepared-statement invalidation
    - `schema-3.*` CREATE/DROP TRIGGER prepared-statement invalidation
    - `schema-4.*` CREATE/DROP INDEX prepared-statement invalidation
    - `schema-5.*` ATTACH stable / DETACH invalidating schema-change behavior, represented as no-op schema operations versus schema removal
    - `schema-10.*` CREATE TABLE while a read cursor exists keeps schema catalog coherent
    - `schema-12.*` rollback of DDL must still expire stale statements when schema-cookie values are reused
- Focused PHP coverage: added `SQLiteRealUpstreamPragmaSchemaInvalidationCorpusTest.php` with 88 focused PASS cases and 656 assertions over dynamic generic application schemas.
- Non-overlap: this slice does not repeat the prior PRAGMA catalog row coverage (`table_info`, `table_xinfo`, `index_list`, `index_xinfo`, `foreign_key_list`) or the accepted schema/view/trigger generated-column reparse fixture. It focuses on upstream `schema.test` invalidation/cookie semantics and generic dynamic schema object lifecycles.
- Dependency closure: no new support component is needed; this reuses the existing `SQLiteSchemaDdlReparsePlan`, `SQLitePragmaSchemaCatalog`, and `SQLiteSchemaRecord` helpers.
- Verification:
  - `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaInvalidationCorpusTest.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaInvalidationCorpusTest.php` -> `1 test files, 656 assertions, 0 failures`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`
