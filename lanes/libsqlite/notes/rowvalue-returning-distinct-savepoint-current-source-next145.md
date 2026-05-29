# rowvalue-returning-distinct-savepoint-current-source-next145

Adds focused current-source coverage for row-value `IS DISTINCT FROM` and
`IS NOT DISTINCT FROM` inside UPDATE/DELETE RETURNING statements executed under
the existing savepoint current-source wrapper.

The slice covers:

- DELETE `WHERE` selection with `IS DISTINCT FROM` plus null-safe
  `IS NOT DISTINCT FROM`;
- UPDATE OR REPLACE after a prior DELETE sees the savepoint current source;
- RETURNING expressions evaluate row-value distinct predicates against the
  correct old/delete and new/update row images;
- successful release keeps current and next sources aligned;
- failed later statements roll back the current source to the savepoint image
  while preserving attempted next-source diagnostics;
- storage-class distinction for text `"24"` versus numeric `24`, and numeric
  equality for integer/real pairs.

Focused evidence:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueReturningDistinctSavepointCurrentSourceNextTest.php
```

WordPress smoke:

```sh
php lanes/libsqlite/examples/wordpress-rowvalue-returning-distinct-savepoint-current-source.php --self-test
```

Result:

```text
1 test files, 54 assertions, 0 failures
54 PASS lines
wordpress-rowvalue-returning-distinct-savepoint-current-source self-test passed
```

Dashboard delta: update `phpPass` by the verified focused PASS-line delta
`+54`, from `64226` to `64280`. Mapped upstream coverage remains `606 / 1589`;
this is fresh focused PHP behavior over already mapped row-value,
UPDATE/DELETE RETURNING, and savepoint current-source surfaces.

Non-overlap: this avoids accepted row-value `IS DISTINCT FROM` raw
UPDATE/DELETE RETURNING next142, row-value `IS` / `IS NOT` savepoint next133,
row-value DELETE/UPDATE savepoint next135, UPSERT/trigger RETURNING savepoint
clusters, and storage/VFS/WAL/B-tree/JSON/planner surfaces. The new surface is
the composition of row-value null-safe distinct predicates with the
savepoint-current-source UPDATE/DELETE RETURNING executor.

Dependency closure: no new support component is needed. The slice reuses the
lane-local UPDATE/DELETE RETURNING executor, row-value predicate parser,
savepoint current-source wrapper, and WordPress row-array smoke path.
