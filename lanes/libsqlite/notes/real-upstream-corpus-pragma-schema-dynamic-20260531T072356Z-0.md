# real-upstream-corpus-pragma-schema-dynamic-20260531T072356Z-0

Implemented a real upstream PRAGMA/schema namespace corpus from SQLite upstream:

- Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/table.test`
- Upstream scenarios:
  - `table-1.10` through `table-1.13`: quoted table/column identifiers and case-insensitive object-name handling.
  - `table-2.1` through `table-2.1f`: duplicate table names, reserved `sqlite_*` object rejection, and `IF NOT EXISTS` preserving the existing table definition.
  - `table-2.2a` through `table-2.2f`: table/index names share one schema namespace.
  - `table-3.1` through `table-4.1`: wide and many-table schema catalog visibility.

Behavior fix:

- `SQLiteSchemaImportExecutor` now rejects `CREATE TABLE` when the requested table name already belongs to an index in the same schema, matching upstream `table-2.2a`.

Focused coverage:

- Added `SQLiteRealUpstreamPragmaSchemaTableNamespaceDynamicTest.php`.
- Red-first evidence: the first focused run failed 200 cases because `CREATE TABLE existing_index_name(...)` was admitted after `CREATE INDEX existing_index_name ...`.
- After the importer fix, the new focused corpus passes with 1,202 distinct TestRunner PASS cases and 6,409 behavior assertions.

Verification:

- `php -l lanes/libsqlite/src/SQLiteSchemaImportExecutor.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteSchemaImportExecutor.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaTableNamespaceDynamicTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaTableNamespaceDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaTableNamespaceDynamicTest.php`
  - `1 test files, 6409 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSchemaImportExecutorCurrentNext20Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteRealUpstreamPragmaSchemaTableNamespaceDynamicTest.php`
  - `3 test files, 6464 assertions, 0 failures`

Related guard note:

- Broader schema-import family commands that included `SQLiteRealUpstreamPragmaSchema6RowidDynamicTest.php` and `SQLiteRealUpstreamPragmaSchemaLegacyCreateDynamicTest.php` still report the pre-existing accepted-base `index_list` origin mismatch (`pk` versus older expected `u`). This patch does not alter that behavior and does not update those unrelated schema5/schema6 expectations.

Non-overlap:

- This does not repeat prior `pragma.test` table-info/index-info/table-list, `pragma4` table-valued PRAGMA join, `schema6` rowid/WITHOUT ROWID equivalence, `schema5` legacy constraints, temp-store, cache-spill, or result-column arity batches. The owned behavior is the upstream `table.test` schema object namespace and import admission path.

Dependency closure:

- No new support component is needed. This reuses the existing schema import executor and PRAGMA schema catalog.
