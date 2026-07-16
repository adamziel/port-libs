# Rowvalue UPDATE/DELETE RETURNING Savepoint Current Source Next190

Status: focused PHP behavior growth for current-source row-value UPDATE/DELETE
RETURNING execution.

This slice adds `SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext190Plan`
for a copied `wp_options` batch with two savepoint boundaries:

- a released savepoint uses row-value `NOT IN` and `NOT BETWEEN` predicates in
  UPDATE/DELETE `WHERE` clauses and `RETURNING` expressions;
- a second savepoint performs speculative negated row-value UPDATE/DELETE
  work, then `ROLLBACK TO` suppresses those `RETURNING` rows;
- retry statements read from the rollback image, preserving the previously
  released current-source changes while discarding speculative rows.

Application smoke:

```sh
php lanes/libsqlite/examples/application-rowvalue-negated-savepoint-current-source-next190.php --self-test
```

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext190Plan.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext190Test.php
php -l lanes/libsqlite/examples/application-rowvalue-negated-savepoint-current-source-next190.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext190Test.php
```

Result: `1 test files, 74 assertions, 0 failures` with 74 PASS lines.

Dashboard delta: update `phpPass` by `+74`, from `90822` to `90896`.
`benchmarkDenominator.mapped` is unchanged; this is additional focused PHP
executor behavior over already mapped row-value/update-delete RETURNING and
savepoint primitives.

Dependency closure: no new support component is needed. The slice reuses the
lane-local bounded UPDATE/DELETE RETURNING executor, row-value predicate
evaluation, update/delete limit planner, and savepoint current-source modeling.

Non-overlap: avoids accepted next176 nullable equality/inequality, next180
`OR IGNORE` inner savepoint behavior, next185 `OR FAIL` partial preservation,
next187 `OR ABORT` savepoint retry, row-value `IS` / `IS NOT`, row-value
assignment parsing, DELETE row-value `IN`, trigger RETURNING, WAL/pager
savepoint byte/page-image, B-tree, JSON, encoding, PRAGMA, and suite-runner
surfaces. The new behavior is specifically negated row-value predicate
visibility across released and rolled-back UPDATE/DELETE RETURNING savepoints.

Next task: continue with a non-overlapping row-value/planner executor gap or
move to another under-owned libsqlite closure bucket with focused PASS growth.
