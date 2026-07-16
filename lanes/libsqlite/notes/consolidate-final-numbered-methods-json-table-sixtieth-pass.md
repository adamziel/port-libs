# JSON Table Numbered Method Consolidation Sixtieth Pass

Consolidated four remaining public JSON-table generated-path rowid entry
methods on `SQLiteJsonTablePlan` into stable descriptive names:

- `currentSourceGeneratedPathRowidCurrentSourceCost()`
- `currentSourceGeneratedPathRowidDeletedResume()`
- `currentSourceGeneratedPathRowidXFilterRecheck()`
- `currentSourceGeneratedPathRowidXColumnCheckpoint()`

Direct focused tests and Application examples were renamed to unsuffixed files and
updated to call the canonical methods. Planner trace keys, dependency markers,
and opcode labels remain unchanged because they are asserted current-source
behavior output rather than method/helper names. The xColumn checkpoint test now
initializes its case list independently and asserts the accepted range
checkpoint shape used by the current implementation.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCurrentSourceCostTest.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidDeletedResumeTest.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidXFilterRecheckTest.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidXColumnCheckpointTest.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-current-source-cost.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-deleted-resume.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-xfilter-recheck.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-xcolumn-checkpoint.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCurrentSourceCostTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidDeletedResumeTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidXFilterRecheckTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidXColumnCheckpointTest.php`
  - `4 test files, 221 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-current-source-cost.php --self-test`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-deleted-resume.php --self-test`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-xfilter-recheck.php --self-test`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-xcolumn-checkpoint.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this pass only renames
existing JSON-table planner entry points and their direct focused callers.
