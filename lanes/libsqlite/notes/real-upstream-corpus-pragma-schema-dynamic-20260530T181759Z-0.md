# real-upstream-corpus-pragma-schema-dynamic-20260530T181759Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
  - `pragma-6.2` table_info/default/pk behavior
  - `pragma-8.1` and `pragma-8.2` schema_version/user_version behavior
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma3.test`
  - `pragma3-100` through `pragma3-190` data_version observer behavior
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  - `pragma4-4.3` through `pragma4-4.6` table-valued index and foreign-key pragmas
  - `pragma4-6.0` table_list joins

Implemented batch:

- Added `SQLiteRealUpstreamPragmaSchemaDynamicWideBatchTest.php`.
- The test adds 1161 distinct TestRunner cases:
  - 250 variant schemas for table_info default/pk metadata.
  - 250 variant schemas for index_list/index_xinfo metadata.
  - 250 variant schemas for foreign_key_list plus table_list metadata.
  - 250 variant schemas for generated table_xinfo plus expression index_xinfo metadata.
  - 80 data_version observer transaction variants.
  - 80 schema_version/user_version rollback variants.
  - 1 source-citation guard.

Non-overlap:

- Existing accepted files already cover smaller `pragma.test`, `pragma3.test`, and `pragma4.test` slices.
- This batch uses new `pragma_wide_*` generated schemas and wider cross-pragma assertions, rather than metadata-only admission rows or static repeated records.

Dependency closure:

- No new support component is needed. This reuses `SQLitePragmaSchemaCatalog`, `SQLiteAttachedSchemaCatalog` behavior indirectly through schema pragma parsing, and `SQLitePragmaSchemaDataVersion`.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicWideBatchTest.php`
  - `1 test files, 11054 assertions, 0 failures`
  - 1161 PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicFollowupTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicPragma4Test.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicWideBatchTest.php`
  - `5 test files, 17002 assertions, 0 failures`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicWideBatchTest.php`
  - no syntax errors
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`
  - `json ok`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - not run: guard file is absent in this worktree
- `rg -n "WordPress|wordpress|wp_|wp-|wp_options|option_name|option_value|autoload|blog_id|OptionRow|optionsTable|OptionName|OptionValue|OptionId|Autoload|BlogId" lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaDynamicWideBatchTest.php lanes/libsqlite/notes/real-upstream-corpus-pragma-schema-dynamic-20260530T181759Z-0.md`
  - no matches
- `git diff --check -- lanes/libsqlite`
  - passed
