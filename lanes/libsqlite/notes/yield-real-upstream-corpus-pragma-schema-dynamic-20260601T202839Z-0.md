# real-upstream-corpus-pragma-schema-dynamic-20260601T202839Z-0

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/pragma4.test`
  - `pragma4-1.*`: `empty_result_callbacks` is a query/assignment PRAGMA with
    one-column query shape and zero-column assignment shape.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/tableapi.test`
  - `tableapi-2.7`: empty `sqlite3_get_table_printf()` result returns no
    column headers while `empty_result_callbacks` is off.
  - `tableapi-3.7`: after `PRAGMA empty_result_callbacks = ON`, an empty
    `sqlite3_get_table_printf()` result preserves the column headers.

Implemented behavior:

- Extended `SQLitePragmaConnectionBooleanState` so
  `PRAGMA empty_result_callbacks` is a connection-local boolean PRAGMA.
- Added `SQLiteTableApiResult`, a generic table API result formatter that
  models the upstream header/no-header boundary for zero-row result sets.
- Added 1001 focused TestRunner cases in
  `SQLiteRealUpstreamCorpusPragmaSchemaDynamicEmptyResultCallbacks20260601T202839ZTest.php`.

Non-overlap:

- This owns only the runtime `empty_result_callbacks` table-result behavior.
- It avoids earlier result-shape-only PRAGMA coverage, schema/user-version
  state, temp_store transaction and active-scan rejection, count_changes
  trigger results, tableopts WITHOUT ROWID validation, schemafault/pragmafault,
  schema invalidation/cache-refresh, JSON, WAL, VFS, B-tree, SELECT, and
  source-neutral cleanup clusters.

Verification:

- `php -l lanes/libsqlite/src/SQLitePragmaConnectionBooleanState.php`
  - No syntax errors detected.
- `php -l lanes/libsqlite/src/SQLiteTableApiResult.php`
  - No syntax errors detected.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicEmptyResultCallbacks20260601T202839ZTest.php`
  - No syntax errors detected.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicEmptyResultCallbacks20260601T202839ZTest.php`
  - `1 test files, 13010 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusPragmaSchemaDynamicBooleanState20260531Test.php`
  - `1 test files, 6529 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - Not run: this worktree does not contain that guard file.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 8 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`
  - Passed with no output.

Dependency closure:

- No new external support component is needed. The slice reuses the lane-local
  PRAGMA boolean state and adds a small generic table-result formatter under
  `lanes/libsqlite/src`.
