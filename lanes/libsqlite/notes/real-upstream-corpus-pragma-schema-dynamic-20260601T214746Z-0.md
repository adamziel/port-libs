# real-upstream-corpus-pragma-schema-dynamic-20260601T214746Z-0

Base accepted HEAD: `5372f0094373074a48d1866016b698b3f7204c9f`.

Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/colmeta.test`.

Upstream scenarios ported:

- `colmeta-1` through `colmeta-7`: declared type, collation, NOT NULL, PRIMARY KEY, and AUTOINCREMENT metadata.
- `colmeta-13` through `colmeta-14` and `colmeta-100` through `colmeta-101`: implicit rowid metadata and INTEGER PRIMARY KEY AUTOINCREMENT propagation.
- `colmeta-20` through `colmeta-24`: WITHOUT ROWID composite PRIMARY KEY columns become NOT NULL and rowid lookup fails.
- `colmeta-30` through `colmeta-32`: explicit `rowid`, `oid`, and `_rowid_` columns shadow implicit rowid aliases.
- `colmeta-200` through `colmeta-203` and `colmeta-300` through `colmeta-301`: view and missing-column failures, plus NULL-column table existence probes.

Implementation:

- Added `SQLiteTableColumnMetadata`, a bounded native PHP lookup helper over lane-local `SQLiteSchemaRecord` entries.
- The helper parses `CREATE TABLE` column definitions, table-level PRIMARY KEY lists, explicit collations, WITHOUT ROWID flags, rowid aliases, and metadata-style success/error records.
- Added `SQLiteRealUpstreamCorpusPragmaSchemaDynamicColumnMetadata20260601T214746ZTest.php` with 250 dynamic variants plus a source-citation/dependency test: 1001 focused TestRunner PASS cases and 12255 assertions.

Verification:

- Red-first check before source implementation: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicColumnMetadata20260601T214746ZTest.php` failed with class-not-found failures for the new helper.
- `php -l lanes/libsqlite/src/SQLiteTableColumnMetadata.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicColumnMetadata20260601T214746ZTest.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicColumnMetadata20260601T214746ZTest.php` passed: `1 test files, 12255 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: `1 test files, 8 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passed.

Non-overlap:

This slice owns upstream `colmeta.test` table-column metadata behavior only. It avoids accepted PRAGMA `table_info` / `table_xinfo`, `index_xinfo`, schema prepared-expiry, pragma empty-result callback, CTAS/table option, WITHOUT ROWID PRAGMA catalog, schema3/schema4/schema5/schema6, and table API empty-result surfaces.

Dependency closure:

No new support component is needed. The implementation reuses existing lane-local `SQLiteSchemaRecord` values and adds the smallest native PHP metadata parser needed for the upstream `colmeta.test` cluster.

Root harness:

Not run - isolated micro-slice.
