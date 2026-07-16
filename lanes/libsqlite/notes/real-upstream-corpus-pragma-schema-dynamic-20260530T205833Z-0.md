# Real Upstream Corpus PRAGMA Schema Dynamic 20260530T205833Z-0

Base accepted HEAD: `f32e8deaca85f9598bd0eb6230903f7d3fab9f57`.

This slice adds `SQLiteRealUpstreamPragmaSchemaDynamicThirdThousandTest.php`,
with 1,001 focused TestRunner PASS cases and 8,203 behavior assertions.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  - `4.2.2` through `4.2.6`: table-valued `pragma_table_info()` over main
    and attached schemas, including dropped-object empty-row behavior.
  - `4.3.2` through `4.4.6`: `pragma_index_info()` and `pragma_index_list()`
    result shape across schema-local objects.
  - `4.5.1` through `4.5.5` and `6.0`: foreign-key/table-info join shape
    through table-valued PRAGMA functions.
  - `6.2`: `PRAGMA table_list` stays usable when a view body has an unresolved
    function.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema5.test`
  - `schema5-1.1` through `schema5-1.7`: legacy adjacent table-constraint
    syntax remains readable for old schemas.

Non-overlap:

- This is a third-thousand dynamic PRAGMA/schema batch. It does not duplicate
  accepted schema4 name-collision, schema6 rowid, data_version, schema2/temp,
  or the existing first/second-thousand PRAGMA/schema dynamic batches.
- The cases use distinct generic `third_pragma_*` schema objects and exercise
  table-valued attached-schema parsing, index metadata, FK metadata,
  legacy table-constraint catalog parsing, and table-list stability.
- No WordPress-specific APIs, examples, fixtures, or new source names were
  added.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicThirdThousandTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicThirdThousandTest.php`
  - `1 test files, 8203 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`

Dependency closure:

- No new support component is needed. This batch reuses the existing
  `SQLitePragmaSchemaCatalog` and `SQLiteSchemaRecord` native PHP components.
