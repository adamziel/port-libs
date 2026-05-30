# real-upstream-corpus-pragma-schema-dynamic-schema-tail-20260530T225155Z

Accepted base: `dc9a740fd34e07dba61e9143b3604d183ad170bf`.

Added `SQLiteRealUpstreamPragmaSchemaDynamicSchemaTailTest.php`, a 1,001-case real upstream corpus batch for SQLite `test/schema.test` tail sections:

- `schema-9.1` and `schema-9.2`: external table/view drops are visible to later PRAGMA/catalog lookups.
- `schema-10.1` through `schema-10.4`: CREATE TABLE while a cursor is open preserves readable schema rows.
- `schema-11.1` through `schema-11.8`: active function/collation replacement is modeled through function/collation PRAGMA metadata guards.
- `schema-12.1`: rollback-expired same-cookie DDL statements are invalidated before a recreated table can reuse the restored schema cookie.

Focused evidence:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchemaTailTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchemaTailTest.php` passed: `1 test files, 6254 assertions, 0 failures`, with 1,001 PASS lines.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchemaTailTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `2 test files, 6257 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSchemaTailTest.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` was not run because `SQLiteNoWordPressSpecificApiTest.php` does not exist in this accepted worktree; `SQLiteNoDomainSpecificApiTest.php` is the available guard.
- `git diff --check -- lanes/libsqlite` passed.

Non-overlap:

This does not repeat existing pragma4/schema6 table-info/index-info wide batches or prior schema invalidation batches. It specifically owns the later `schema.test` tail behavior around external drops, active cursor catalog preservation, function/collation busy metadata, and rollback-expired same-cookie DDL.

Dependency closure:

No new support component is needed. The batch reuses existing native PHP `SQLiteSchemaDdlReparsePlan`, `SQLitePragmaSchemaCatalog`, `SQLitePragmaSchemaDataVersion`, and `SQLiteSchemaRecord` behavior.
