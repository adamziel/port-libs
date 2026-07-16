# Row-Value ORDER BY Expression Savepoint Current Source Next199

This slice extends the bounded native `SQLiteUpdateDeleteReturningSql` executor
so UPDATE/DELETE `ORDER BY` terms can be scalar or row-value expressions, not
only bare columns. Expression terms are evaluated into hidden order keys before
`LIMIT` target selection, while mutation and RETURNING rows still follow source
row order and hidden keys are stripped from result rows.

Focused behavior:

- `ORDER BY (blog_id, option_name) IS (...) DESC, bytes DESC` can prioritize a
  row-value match before applying `LIMIT`.
- `DELETE ... ORDER BY (status, option_name) IS (...) DESC, option_id ASC`
  reuses updated current-source rows for target selection.
- A savepoint rollback suppresses the attempted RETURNING stream and retries
  the same expression-ordered UPDATE/DELETE statements from the restored
  current source.
- User rows with `__sqlite_udl_*` internal columns are rejected so synthetic
  order keys cannot leak into wildcard RETURNING output.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpdateDeleteReturningSqlTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext199Test.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 121 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-rowvalue-order-expression-savepoint-current-source-next199.php --self-test
application-rowvalue-order-expression-savepoint-current-source-next199 self-test passed
```

Non-overlap: avoids accepted and queued rowvalue194 parenthesized predicates,
next195 unary NOT row-value predicates, next196 OR FAIL prefix preservation,
next197 explicit inner `ROLLBACK TO`, and next200 OR ABORT statement handling.
This patch is specifically DML `ORDER BY` row-value expression target selection
and hidden order-key stripping.

Dependency closure: no new support component needed; this reuses the native
UPDATE/DELETE RETURNING executor, `SQLiteUpdateDeleteLimitPlan`, row-value
expression evaluation, and savepoint current-source model.
