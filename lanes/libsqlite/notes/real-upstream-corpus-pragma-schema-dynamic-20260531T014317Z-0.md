# real-upstream-corpus-pragma-schema-dynamic-20260531T014317Z-0

Base accepted HEAD: `d0e37b664c0ef9500748faeafd4d7f1484470255`.

Added `SQLiteRealUpstreamPragmaSchemaDynamicEighthThousandTest.php` with 1001 focused TestRunner PASS cases and 13504 behavior assertions from real upstream SQLite `test/pragma.test`.

Upstream source sections:

- `pragma.test` `pragma-6.2`, `pragma-6.2.2`, `pragma-6.2.3`, `pragma-6.7`, and `pragma-6.8`: `PRAGMA table_info` declared type, default expression, null/default, notnull, and repeated primary-key ordinal behavior.
- `pragma.test` `pragma-6.5.1`, `pragma-6.5.1b`, and `pragma-6.5.1c`: `PRAGMA index_info` key column rows and `PRAGMA index_xinfo` auxiliary row behavior.
- `pragma.test` `pragma-6.6`: temp schema shadowing versus explicit `main.table_info`.
- `pragma.test` `pragma-7.1`: schema reload before `index_list` returns autoindex and user index origins.
- `pragma.test` `pragma-8.1`: `schema_version` assignment, defensive no-op writes, and attached-schema version independence.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicEighthThousandTest.php`
  - `1 test files, 13504 assertions, 0 failures`
  - `1001` PASS lines.

Non-overlap:

- Extends the accepted PRAGMA schema corpus after the accepted seventh-thousand/schema3/schema5/schema6 and pragma4 table-valued batches.
- Does not add domain-specific API or scenario text.
- Mapped denominator remains `1589 / 1589`; this is PASS-line/assertion growth only.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP PRAGMA catalog and schema-version primitives.
