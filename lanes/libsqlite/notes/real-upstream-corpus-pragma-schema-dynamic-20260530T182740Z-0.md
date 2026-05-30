# real-upstream-corpus-pragma-schema-dynamic-20260530T182740Z-0

- Base accepted HEAD: `2b09fd94bbc734a3a9855d41884522c7a5a06914`.
- Upstream source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test`
    - `schema-1.*` CREATE/DROP TABLE invalidates prepared sqlite_schema scans.
    - `schema-2.*` CREATE/DROP VIEW invalidates prepared sqlite_schema scans.
    - `schema-3.*` CREATE/DROP TRIGGER invalidates prepared sqlite_schema scans.
    - `schema-4.*` CREATE/DROP INDEX invalidates prepared sqlite_schema scans.
    - `schema-5.*` ATTACH leaves existing unqualified table winners stable while DETACH invalidates the database array.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema2.test`
    - temp/main schema shadowing and schema-cookie/current-source behavior.
- Behavior ported: added focused PHP coverage for schema-cache resolution invalidation after bounded CREATE/DROP TABLE, VIEW, TRIGGER, and INDEX DDL; attached database add/remove invalidation; and temp/main shadowing where main-schema index changes do not move an unqualified PRAGMA away from the temp object.
- Focused PHP coverage: `SQLiteRealUpstreamPragmaSchemaDynamicSchemaInvalidationBatchTest.php` adds 141 distinct TestRunner PASS cases and 1659 assertions.
- Expected dashboard movement: `phpPass` moves from `298721` to `298862` for the 141 verified PASS lines. Mapped coverage remains `1189 / 1589`; this is real upstream behavior coverage over an existing schema/pragma domain rather than a new denominator row.
- Non-overlap: this does not repeat earlier dynamic PRAGMA catalog rows, schema/data-version rows, wide-batch table_info/index_xinfo/foreign_key_list coverage, or source-neutral cleanup. It focuses on schema.test prepared-statement invalidation and schema2 temp/main resolution behavior.
- Dependency closure: no new support component is needed. The slice reuses `SQLiteAttachedSchemaCatalog`, `SQLiteSchemaDdlReparsePlan`, schema-cache resolution snapshots, and existing PRAGMA schema catalog primitives.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchemaInvalidationBatchTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchemaInvalidationBatchTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchemaInvalidationBatchTest.php`
  - `1 test files, 1659 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchemaInvalidationBatchTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicWideBatchTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `4 test files, 17483 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed
