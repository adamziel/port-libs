# real-upstream-corpus-pragma-schema-dynamic-20260531T012523Z-0

Base accepted HEAD: `af20380a278ad54b2ad38b5d180ded7ec9aac2e7`.

Implemented one real upstream PRAGMA/schema behavior cluster from the hydrated
SQLite upstream checkout:

- `test/pragma4.test` 6.0: joinable `pragma_table_list()`,
  `pragma_foreign_key_list(t.name,t.schema)`, and
  `pragma_table_info(f.table,t.schema)` rowsets that resolve child foreign-key
  metadata to parent primary-key metadata.
- `test/pragma4.test` 7.1 through 7.3: materialized and live
  `pragma_table_info()` rows preserve RIGHT JOIN-style name matching when the
  right side has fewer columns than the left side.

Added `SQLiteRealUpstreamPragmaSchemaDynamicSeventhThousandTest.php` with 500
dynamic variants for each behavior plus a source-citation case: `1001` focused
TestRunner PASS cases and `3003` behavior assertions.

Non-overlap:

- Does not repeat the existing sixth-thousand direct `pragma4.test` 4.1-4.5
  table/index/FK PRAGMA rowset checks.
- Does not repeat accepted `pragma5` function/module/pragma-list virtual row
  coverage, schema-version state, corrupt-view table-list coverage, or direct
  schema shadowing/invalidation batches.
- Uses generic application table names only.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicSeventhThousandTest.php`
  - `1 test files, 3003 assertions, 0 failures`

Dependency closure:

- No new support component is needed. The slice reuses existing
  `SQLiteAttachedSchemaCatalog`, `SQLitePragmaSchemaCatalog`, and
  `SQLiteSchemaRecord` behavior.
