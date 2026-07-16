# PRAGMA Integrity FK Index Current Source Next82

## Behavior

`SQLitePragmaForeignKeyIntegrity::execute()` now accepts schema-qualified
`PRAGMA foreign_key_check(schema.table)` targets, resolves unqualified targets
through `SQLiteAttachedSchemaCatalog` search order, and reports the target
source (`default`, `pragma-schema`, `catalog-current`, or
`qualified-target`). This keeps paged integrity/FK current/next output on the
same temp/main/attached source that SQLite would use for the target table.

## Focused Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityFkIndexCurrentSourceNext82Test.php`
  - `1 test files, 59 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaForeignKeyCheckCorpusTest.php lanes/libsqlite/tests/SQLitePragmaForeignKeyIndexIntegrityCurrentNext71Test.php lanes/libsqlite/tests/SQLitePragmaIntegrityForeignKeyCurrentNext73Test.php lanes/libsqlite/tests/SQLitePragmaIntegrityFkIndexCurrentSourceNext82Test.php`
  - `4 test files, 291 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pragma-integrity-fk-index-current-source-next82.php --self-test`
  - `application-pragma-integrity-fk-index-current-source-next82 self-test passed`

## Non-Overlap

This does not repeat accepted PRAGMA integrity/FK pagination, autoindex
admission, pointer-map, or recovery/vacuum diagnostics. The new surface is
current-source resolution for qualified and catalog-resolved
`foreign_key_check` targets before those existing paged integrity rows are
materialized.

## Dependency Closure

No new support component is needed. The slice reuses the existing
`SQLiteAttachedSchemaCatalog`, `SQLitePragmaForeignKeyIntegrity`, and
`SQLitePragmaIntegrityCurrentNextYield` components.
