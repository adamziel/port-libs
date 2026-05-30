# JSON Table Numbered Method Consolidation Fifty-eighth Pass

Consolidated two remaining public JSON-table generated-path rowid production
entry methods on `SQLiteJsonTablePlan` into stable descriptive names:

- `currentSourceGeneratedPathRowidBatch()`
- `currentSourceGeneratedPathRowidCursor()`

Direct JSON-table tests and Application examples were renamed away from their
numbered file/method surfaces and updated to call the canonical methods.
Existing result-array trace keys, dependency markers, and opcode labels remain
unchanged because they are asserted planner output from the accepted
current-source contract, not public method/helper names.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidBatchTest.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCursorTest.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-batch.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cursor.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidBatchTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCursorTest.php`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-batch.php --self-test`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cursor.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this pass only renames
existing JSON-table planner entry points and direct focused callers.
