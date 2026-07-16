# real-upstream-corpus-pragma-schema-dynamic-20260530T210438Z-0

Added `SQLiteRealUpstreamPragmaSchemaDynamicWideBatchFollowupTest.php`, a real upstream PRAGMA/schema dynamic corpus batch.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`: `pragma-6.2` through `pragma-7.1.2` for `table_info`, `table_xinfo`, `index_list`, `index_info`, `index_xinfo`, and `foreign_key_list`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`: `pragma-4.2` through `pragma-7.3` for table-valued PRAGMA calls and schema arguments.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma5.test`: `1.0` through `3.1` for `table_list` `WITHOUT ROWID` and `STRICT` flags.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma6.test`: `1.0` through `1.2` for `function_list`, `module_list`, `collation_list`, and `pragma_list` metadata.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/schema.test`: `schema-1.*`, `schema-4.*`, and `schema-10.*` for sqlite_schema SQL text as metadata source truth.

Focused delta:

- `1621` distinct TestRunner PASS cases.
- `6663` behavior assertions.
- Expected `phpPass` movement: `688934 -> 690555`.
- Mapped denominator movement: none; mapped coverage was already `1589 / 1589`.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicWideBatchFollowupTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicWideBatchFollowupTest.php`
  - `1 test files, 6663 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicWideBatchFollowupTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `2 test files, 6666 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Dependency closure:

- No new support component is needed. This reuses existing `SQLitePragmaSchemaCatalog` and `SQLiteSchemaRecord` behavior.

Non-overlap:

- Does not repeat the accepted `SQLiteRealUpstreamPragmaSchemaDynamicTest.php`, followup, batch, schema3/schema4/schema5/schema6 focused files. This batch owns a fresh wide matrix of generated application schema shapes and list-pragma metadata tied to the cited upstream PRAGMA/schema sections.
