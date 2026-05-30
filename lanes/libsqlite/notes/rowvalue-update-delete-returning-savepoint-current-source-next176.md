# rowvalue-update-delete-returning-savepoint-current-source-next176

Status: focused PHP behavior growth for current-source row-value UPDATE/DELETE
RETURNING execution.

This slice fixes row-value equality and inequality when a tuple contains NULL
but a later non-NULL element proves inequality. SQLite returns false for
`(1,NULL,'rewrite_rules') = (1,NULL,'active_plugins')` and true for the
corresponding `<>`; the previous bounded UPDATE/DELETE RETURNING executor
stopped at the first NULL and returned UNKNOWN.

Application smoke:
`application-rowvalue-null-inequality-savepoint-current-source-next176.php`
models copied `wp_options` cleanup. It deletes rows that are deterministically
not equal to a nullable active_plugins tuple, keeps the UNKNOWN tuple, rolls
back a staged OR ROLLBACK conflict, and retries from the rollback source.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext176Test.php
php -l lanes/libsqlite/examples/application-rowvalue-null-inequality-savepoint-current-source-next176.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext176Test.php
php lanes/libsqlite/examples/application-rowvalue-null-inequality-savepoint-current-source-next176.php --self-test
git diff --check -- lanes/libsqlite
```

Focused test output:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 49 assertions, 0 failures
```

Dashboard delta: `phpPass` moves from `81770` to `81819` from 49 newly passing
focused PASS lines. Mapped upstream coverage remains `613 / 1589`; this is
fresh focused PHP executor behavior over already mapped row-value/update-delete
RETURNING primitives.

Non-overlap: avoids accepted next133 row-value `IS` / `IS NOT`, next156/164
row-value UPDATE/DELETE RETURNING savepoint retry coverage, row-value
assignment parsing, OR ROLLBACK savepoint cancellation, and DELETE row-value
`IN` handling. The new surface is specifically SQLite's nullable row-value
`=` / `<>` determinacy when later tuple elements prove inequality in both WHERE
selection and RETURNING expressions.

Dependency closure: no new support component is needed. This reuses the native
PHP UPDATE/DELETE RETURNING executor and existing savepoint current-source retry
plan.

Next task: continue with a non-overlapping row-value/planner executor gap or
pivot to the next higher-yield SQL/WAL/B-tree closure target.
