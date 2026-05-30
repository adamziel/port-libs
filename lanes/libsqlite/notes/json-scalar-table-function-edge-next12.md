# JSON Scalar Table Function Edge Next12

- Behavior: parser-level `SQLiteSelectSql` expressions now dispatch JSON scalar functions already implemented in native PHP (`json`, `jsonb`, JSON constructors, `json_quote`, `json_valid`, `json_pretty`, patch/mutation/remove helpers) and base `json_each()` / `json_tree()` sources can evaluate scalar-function arguments without a host table row.
- Focused test delta: `+32` TestRunner PASS cases in `SQLiteJsonScalarTableFunctionEdgeNext12Test.php`; `lane-status.json` `phpPass` moves `3796 -> 3828`.
- Application smoke: `application-json-scalar-table-function-edge.php --self-test` covers copied `wp_options` JSON5 option values canonicalized and mutated before `json_each()` expansion.
- Non-overlap: avoids accepted JSON table cursor/source/hidden/visible constraint pushdown, host joins, JSON aggregate/window, JSON scalar regression helper-only coverage, SELECT expression ORDER BY, SELECT subqueries, VFS/WAL/B-tree accepted clusters, Unicode GLOB, and rollback/sync writer work. This slice wires existing scalar JSON behavior through parser-level SELECT expression execution and scalar table-function source arguments.
- Dependency closure: no new support component needed; the slice reuses existing native PHP JSON scalar, JSONB, SELECT expression, and JSON table planner/cursor components.

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonScalarTableFunctionEdgeNext12Test.php
Focused test run: 1 selected test files (root lock skipped)
PASS select dispatches json canonical scalar
PASS select dispatches jsonb canonical scalar
PASS select dispatches json_quote scalar
PASS select dispatches json_valid strict and json5 flags
PASS select dispatches json_array scalar with nested json subtype
PASS select dispatches jsonb_array scalar
PASS select dispatches json_object scalar
PASS select dispatches jsonb_object scalar
PASS select dispatches json_pretty scalar
PASS select dispatches json_patch scalar
PASS select dispatches jsonb_patch scalar
PASS select dispatches json_set scalar
PASS select dispatches json_insert scalar without replacing existing path
PASS select dispatches json_replace scalar without inserting missing path
PASS select dispatches json_remove scalar
PASS json_each accepts json scalar source expression
PASS json_tree accepts jsonb scalar source expression
PASS json_each accepts json_array scalar source expression
PASS json_tree accepts json_object scalar source expression from host row
PASS json_each accepts json_patch source expression
PASS json_each accepts json_set source expression from host row
PASS json_each accepts json_remove source expression
PASS json_each accepts nested json_extract array source expression
PASS json_tree source expression can be filtered by json_valid predicate
PASS json scalar source expression can be used in nested in subquery
PASS json scalar table expression supports left join null extension
PASS json scalar table expression preserves aliases in projection
PASS json scalar table expression preserves order limit offset
PASS json scalar table expression rejects invalid json_object labels
PASS json scalar table expression rejects invalid json_valid flags
PASS json scalar table expression rejects malformed dynamic source
PASS json scalar table expression rejects non-jsonb blob quote

1 test files, 32 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-json-scalar-table-function-edge.php --self-test
application-json-scalar-table-function-edge self-test passed
```

```text
php -l lanes/libsqlite/src/SQLiteSelectExpression.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSelectExpression.php

php -l lanes/libsqlite/src/SQLiteSelectSql.php
No syntax errors detected in lanes/libsqlite/src/SQLiteSelectSql.php

php -l lanes/libsqlite/tests/SQLiteJsonScalarTableFunctionEdgeNext12Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonScalarTableFunctionEdgeNext12Test.php

php -l lanes/libsqlite/examples/application-json-scalar-table-function-edge.php
No syntax errors detected in lanes/libsqlite/examples/application-json-scalar-table-function-edge.php

php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/libsqlite
passed with no output
```
