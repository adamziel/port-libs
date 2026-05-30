# JSON Table Numbered Method Consolidation Fifty-fourth Pass

Consolidated two remaining public JSON-table generated-path rowid production
entry methods on `SQLiteJsonTablePlan` into stable descriptive names:

- `currentSourceGeneratedPathRowidAnchorRemap()`
- `currentSourceGeneratedPathRowidRangeConstraint()`

Direct JSON-table tests and Application examples were renamed away from the
`CurrentSourceNext195` and `CurrentSourceNext209` file/method surfaces and
updated to call the canonical methods. Existing result-array trace keys and
opcode labels remain unchanged because they are asserted behavior output from
the accepted planner contract, not method/helper names.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidAnchorRemapTest.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidRangeConstraintTest.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-anchor-remap.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-range-constraint.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidAnchorRemapTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidRangeConstraintTest.php`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-anchor-remap.php --self-test`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-range-constraint.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this pass only renames
existing JSON-table planner entry points and direct focused callers.
