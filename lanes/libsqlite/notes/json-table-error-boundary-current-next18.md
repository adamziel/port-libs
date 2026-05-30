# JSON table error boundary current-next18

- Scope: parser-level `json_each()` / `json_tree()` dynamic row sources now recognize `WHERE json_valid(<json argument>) = 1` and `WHERE json_error_position(<json argument>) = 0` as error-boundary guards before invoking the table-valued function for each host row.
- Behavior: guarded malformed text, text BLOB, and JSON subtype inputs are skipped as empty virtual-table rowsets; unguarded malformed text still raises, and false/nonzero guard predicates do not suppress errors.
- SQL expression support: `json_error_position()` is available through `SQLiteSelectExpression`, so guarded SELECTs can project and filter malformed JSON boundaries without ext/sqlite.
- WordPress path: copied `wp_options` JSON settings scans can use `json_error_position(option_value) = 0` before `json_each(option_value, '$.phones')`, allowing corrupt option rows to be skipped while valid rows remain queryable.
- Non-overlap: this does not repeat accepted JSON table cursor/source wiring, hidden/visible constraint pushdown, JSON host joins, JSONB malformed planner diagnostics, or scalar JSON error-position offset corpus; it wires guard predicates into dynamic table-source evaluation.
- Dependency closure: no new support component is required; the slice reuses existing native PHP `SQLiteJsonValidity`, `SQLiteJsonErrorPosition`, and parser-level SELECT execution components.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableErrorBoundaryCurrentNext18Test.php
Focused test run: 1 selected test files (root lock skipped)
25 PASS lines, 29 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableErrorBoundaryCurrentNext18Test.php lanes/libsqlite/tests/SQLiteJsonEachIndexedRegressionTest.php lanes/libsqlite/tests/SQLiteJsonTableIndexedConstraintCorpusTest.php
Focused test run: 3 selected test files (root lock skipped)
81 PASS lines, 119 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-json-table-error-boundary.php
[
    {
        "option_name": "plugin_contact_settings",
        "phone": "704-555-0101"
    }
]

php -l lanes/libsqlite/src/SQLiteSelectExpression.php
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/tests/SQLiteJsonTableErrorBoundaryCurrentNext18Test.php
php -l lanes/libsqlite/examples/wordpress-json-table-error-boundary.php
No syntax errors detected.

git diff --check -- lanes/libsqlite
No whitespace errors.
```
