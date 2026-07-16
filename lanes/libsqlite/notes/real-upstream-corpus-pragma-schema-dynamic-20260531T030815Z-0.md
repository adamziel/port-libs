# real-upstream-corpus-pragma-schema-dynamic-20260531T030815Z-0

Implemented a focused real upstream PRAGMA schema-version dynamic coverage
slice.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test`
- `pragma-8.1.1` through `pragma-8.1.18`: `PRAGMA schema_version`
  read/write, defensive-mode write suppression, schema DDL cookie bumps,
  attached-schema isolation, and stale prepared statement expiry after schema
  cookie changes.
- `pragma-8.2.1` through `pragma-8.2.15`: `PRAGMA user_version` read/write,
  attached-schema isolation, transaction rollback restoration, VACUUM
  preserving `user_version` while changing `schema_version`, and signed
  negative `user_version`.

Lane changes:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaVersionDynamicTest.php`.
- Reused existing `SQLitePragmaSchemaDataVersion`; no new support component
  was needed.
- Updated `lanes/libsqlite/lane-status.json` by the verified focused
  TestRunner PASS-line delta only.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaVersionDynamicTest.php`
- Result: `1 test files, 17824 assertions, 0 failures`.
- Total PASS lines in focused file: `2642`.
- Newly added PASS lines in this additive block: `1441`.
- Expected `phpPass` movement: `1790051 -> 1791492` (`+1441`).

Non-overlap:

- This slice does not repeat the existing pragma schema wide batch coverage
  for `table_info`, `table_xinfo`, `index_list`, `index_info`, `index_xinfo`,
  `foreign_key_list`, `table_list`, function/module/collation lists, or
  prepared schema-expiry-only coverage.
- It owns the distinct upstream `pragma.test` section 8 schema/user version
  behavior cluster.

Dependency closure:

- No new support component is required. The existing bounded native
  `SQLitePragmaSchemaDataVersion` model is reused.
